<?php
declare(strict_types=1);

namespace App\Controllers;

use App\Core\Auth;
use App\Core\Controller;
use App\Models\Categorie;
use App\Models\Audit;
use App\Models\Commentaire;
use App\Models\PieceJointe;
use App\Models\Ticket;
use App\Models\Utilisateur;
use App\Services\PieceJointeService;
use App\Services\TicketService;
use DomainException;

final class TicketController extends Controller
{
    /** Liste : chaque rôle voit ses propres tickets (admin : tous). */
    public function index(): void
    {
        Auth::exigerConnexion();
        $u = Auth::utilisateur();

        $titres = [
            'employe'    => 'Mes tickets',
            'technicien' => 'Mes tickets assignés',
            'admin'      => 'Tous les tickets',
        ];

        $this->vue('tickets/index', [
            'titre'   => $titres[$u['role']] ?? 'Tickets',
            'tickets' => Ticket::lister($u['role'], $u['id']),
            'role'    => $u['role'],
        ]);
    }

    public function nouveau(): void
    {
        Auth::exigerRole('employe');

        $ancien = $_SESSION['ancien'] ?? [];   // valeurs saisies avant une erreur
        unset($_SESSION['ancien']);

        $this->vue('tickets/nouveau', [
            'titre'      => 'Nouveau ticket',
            'categories' => Categorie::groupeesParDomaine(),
            'urgences'   => Ticket::URGENCES,
            'ancien'     => $ancien,
        ]);
    }

    public function enregistrer(): void
    {
        Auth::exigerRole('employe');
        $this->refuserEnvoiTropGros('/tickets/nouveau');
        Auth::verifierCsrf();

        $titre       = trim((string) ($_POST['titre'] ?? ''));
        $description = trim((string) ($_POST['description'] ?? ''));
        $categorieId = (int) ($_POST['categorie_id'] ?? 0);
        $urgence     = (string) ($_POST['urgence'] ?? '');

        $categorie = Categorie::trouver($categorieId);
        $erreurs   = TicketService::verifierSaisie($titre, $description, $categorie, $urgence);

        if ($erreurs) {
            foreach ($erreurs as $erreur) {
                flash('error', $erreur);
            }
            $_SESSION['ancien'] = [
                'titre' => $titre, 'description' => $description,
                'categorie_id' => $categorieId, 'urgence' => $urgence,
            ];
            $this->rediriger('/tickets/nouveau');
        }

        $resultat = TicketService::declarer(Auth::utilisateur()['id'], $categorie, $titre, $description, $urgence);

        // Pièces jointes éventuelles : le ticket existe déjà, une erreur de fichier ne l'annule pas
        $pj = PieceJointeService::enregistrer((int) $resultat['id'], $resultat['reference'], Auth::utilisateur()['id'], $_FILES['fichiers'] ?? null);
        foreach ($pj['erreurs'] as $erreur) {
            flash('warning', $erreur);
        }

        if ($resultat['technicien'] !== null) {
            flash('success', sprintf(
                'Ticket %s créé et assigné à %s %s.',
                $resultat['reference'],
                $resultat['technicien']['prenom'],
                $resultat['technicien']['nom']
            ));
        } else {
            flash('warning', sprintf(
                'Ticket %s créé. Aucun technicien disponible dans ce domaine : il sera attribué par l\'administrateur.',
                $resultat['reference']
            ));
        }
        $this->rediriger('/tickets/' . $resultat['id']);
    }

    public function afficher(string $id): void
    {
        [$ticket, $u] = $this->ticketAutorise($id);

        // Boutons de changement de statut auxquels cet utilisateur a droit
        $actions = [];
        foreach (TicketService::transitionsPermises($ticket, $u) as $vers) {
            $actions[] = [$vers, libelle_action($ticket['statut'], $vers, $u['role'])];
        }

        // L'administrateur peut (ré)assigner tant que le ticket est ouvert
        $techniciens = [];
        if ($u['role'] === 'admin' && in_array($ticket['statut'], ['nouveau', 'assigne', 'en_cours', 'en_attente'], true)) {
            $categorie   = Categorie::trouver((int) $ticket['categorie_id']);
            $techniciens = Utilisateur::techniciensDuDomaine((int) $categorie['domaine_id']);
        }

        $this->vue('tickets/detail', [
            'titre'         => $ticket['reference'],
            'ticket'        => $ticket,
            'historique'    => Ticket::historique((int) $ticket['id']),
            'commentaires'  => Commentaire::lister((int) $ticket['id']),
            'pieces'        => PieceJointe::lister((int) $ticket['id']),
            'actions'       => $actions,
            'techniciens'   => $techniciens,
            'peutSupprimer' => TicketService::peutSupprimer($ticket, $u),
        ]);
    }

    public function changerStatut(string $id): void
    {
        [$ticket, $u] = $this->ticketAutorise($id);
        Auth::verifierCsrf();

        $nouveau = (string) ($_POST['statut'] ?? '');
        try {
            TicketService::changerStatut((int) $ticket['id'], $nouveau, $u);
            flash('success', 'Statut mis à jour : ' . libelle_statut($nouveau) . '.');
        } catch (DomainException $e) {
            flash('error', $e->getMessage());
        }
        $this->rediriger('/tickets/' . $ticket['id']);
    }

    public function commenter(string $id): void
    {
        [$ticket, $u] = $this->ticketAutorise($id);
        Auth::verifierCsrf();

        try {
            TicketService::commenter((int) $ticket['id'], $u, (string) ($_POST['contenu'] ?? ''));
            flash('success', 'Commentaire ajouté.');
        } catch (DomainException $e) {
            flash('error', $e->getMessage());
        }
        $this->rediriger('/tickets/' . $ticket['id']);
    }

    /** Ajoute des fichiers à un ticket existant. */
    public function televerser(string $id): void
    {
        [$ticket, $u] = $this->ticketAutorise($id);
        $this->refuserEnvoiTropGros('/tickets/' . $ticket['id']);
        Auth::verifierCsrf();

        if ($ticket['statut'] === 'cloture') {
            flash('error', 'Ce ticket est clôturé : on ne peut plus y ajouter de fichier.');
            $this->rediriger('/tickets/' . $ticket['id']);
        }

        $pj = PieceJointeService::enregistrer((int) $ticket['id'], $ticket['reference'], $u['id'], $_FILES['fichiers'] ?? null);
        if ($pj['ajoutes'] > 0) {
            flash('success', $pj['ajoutes'] . ' fichier(s) ajouté(s).');
        } elseif (!$pj['erreurs']) {
            flash('error', 'Aucun fichier sélectionné.');
        }
        foreach ($pj['erreurs'] as $erreur) {
            flash('error', $erreur);
        }
        $this->rediriger('/tickets/' . $ticket['id']);
    }

    /** Télécharge une pièce jointe, après contrôle d'accès au ticket. */
    public function telecharger(string $id, string $pj): void
    {
        [$ticket, $u] = $this->ticketAutorise($id);

        $piece = ctype_digit($pj) ? PieceJointe::trouver((int) $pj, (int) $ticket['id']) : null;
        $chemin = $piece !== null ? PieceJointeService::dossier() . '/' . basename($piece['nom_stocke']) : '';
        if ($piece === null || !is_file($chemin)) {
            $this->introuvable();
        }

        Audit::enregistrer('TELECHARGEMENT_PIECE_JOINTE', $ticket['reference'] . ' : ' . $piece['nom_original'], $u['id']);

        header('Content-Type: application/octet-stream');
        header('Content-Length: ' . filesize($chemin));
        header("Content-Disposition: attachment; filename*=UTF-8''" . rawurlencode($piece['nom_original']));
        header('X-Content-Type-Options: nosniff');
        readfile($chemin);
        exit;
    }

    public function assigner(string $id): void
    {
        Auth::exigerRole('admin');
        [$ticket, $u] = $this->ticketAutorise($id);
        Auth::verifierCsrf();

        try {
            TicketService::assignerManuellement((int) $ticket['id'], (int) ($_POST['technicien_id'] ?? 0), $u);
            flash('success', 'Ticket assigné.');
        } catch (DomainException $e) {
            flash('error', $e->getMessage());
        }
        $this->rediriger('/tickets/' . $ticket['id']);
    }

    /** Suppression d'un ticket : administrateur (tous) ou employé (le sien, tant qu'il n'est pas traité). */
    public function supprimer(string $id): void
    {
        [$ticket, $u] = $this->ticketAutorise($id);
        Auth::verifierCsrf();

        try {
            TicketService::supprimer((int) $ticket['id'], $u);
            flash('success', 'Le ticket ' . $ticket['reference'] . ' a été supprimé.');
            $this->rediriger('/tickets');
        } catch (DomainException $e) {
            flash('error', $e->getMessage());
            $this->rediriger('/tickets/' . $ticket['id']);
        }
    }

    /**
     * Si le fichier dépasse post_max_size, PHP vide $_POST et $_FILES :
     * on le détecte pour afficher un message clair au lieu d'une erreur CSRF.
     */
    private function refuserEnvoiTropGros(string $retour): void
    {
        if (empty($_POST) && empty($_FILES) && (int) ($_SERVER['CONTENT_LENGTH'] ?? 0) > 0) {
            flash('error', 'Envoi trop volumineux : réduisez la taille des fichiers (2 Mo maximum chacun).');
            $this->rediriger($retour);
        }
    }

    /**
     * Charge le ticket et vérifie que l'utilisateur y a droit :
     * demandeur, technicien assigné ou administrateur.
     * @return array{0: array, 1: array} [ticket, utilisateur connecté]
     */
    private function ticketAutorise(string $id): array
    {
        Auth::exigerConnexion();
        $u = Auth::utilisateur();

        $ticket = ctype_digit($id) ? Ticket::trouver((int) $id) : null;
        if ($ticket === null) {
            $this->introuvable();
        }

        $autorise = $u['role'] === 'admin'
            || (int) $ticket['demandeur_id'] === $u['id']
            || ($ticket['technicien_id'] !== null && (int) $ticket['technicien_id'] === $u['id']);
        if (!$autorise) {
            $this->interdit();
        }

        return [$ticket, $u];
    }
}