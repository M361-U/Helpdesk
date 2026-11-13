<?php
declare(strict_types=1);

namespace App\Services;

use App\Core\Database;
use App\Models\Audit;
use App\Models\Categorie;
use App\Models\Commentaire;
use App\Models\Ticket;
use App\Models\Utilisateur;
use DomainException;
use Throwable;
use App\Models\PieceJointe;

final class TicketService
{
    /** Cycle de vie : statut actuel => statuts suivants possibles. */
    private const TRANSITIONS = [
        'assigne'    => ['en_cours'],
        'en_cours'   => ['en_attente', 'resolu'],
        'en_attente' => ['en_cours', 'resolu'],
        'resolu'     => ['cloture', 'en_cours'],
    ];

    /**
     * Enregistre un ticket puis tente de l'assigner automatiquement.
     * Retourne ['id', 'reference', 'technicien' (ou null)].
     */
    public static function declarer(int $demandeurId, array $categorie, string $titre,
                                    string $description, string $urgence): array
    {
        $pdo = Database::getConnection();
        $pdo->beginTransaction();   // tout réussit ou rien n'est enregistré

        try {
            $id = Ticket::creer($demandeurId, (int) $categorie['id'], $titre, $description, $urgence);
            Ticket::ajouterHistorique($id, null, 'nouveau', $demandeurId);

            $technicien = RoutageService::choisirTechnicien((int) $categorie['domaine_id']);
            if ($technicien !== null) {
                Ticket::assigner($id, (int) $technicien['id']);
                Ticket::ajouterHistorique($id, 'nouveau', 'assigne', null);   // null = le système
            }

            $pdo->commit();
        } catch (Throwable $e) {
            $pdo->rollBack();
            throw $e;
        }

        $ticket = Ticket::trouver($id);

        Audit::enregistrer('CREATION_TICKET', $ticket['reference'], $demandeurId);
                NotificationService::ticketCree($ticket, $technicien);
        Audit::enregistrer(
            $technicien !== null ? 'ASSIGNATION_AUTO' : 'TICKET_NON_ASSIGNE',
            $ticket['reference'] . ($technicien !== null ? ' -> ' . $technicien['email'] : ' (domaine ' . $categorie['domaine'] . ')'),
            $demandeurId
        );

        return ['id' => $id, 'reference' => $ticket['reference'], 'technicien' => $technicien];
    }

    /** Statuts que cet utilisateur a le droit de donner à ce ticket. */
    public static function transitionsPermises(array $ticket, array $utilisateur): array
    {
        $suivants = self::TRANSITIONS[$ticket['statut']] ?? [];

        if ($utilisateur['role'] === 'admin') {
            return $suivants;
        }
        // Le technicien assigné traite le ticket, mais ne le clôture pas
        if ($utilisateur['role'] === 'technicien' && (int) $ticket['technicien_id'] === $utilisateur['id']) {
            return $ticket['statut'] === 'resolu' ? [] : $suivants;
        }
        // Le demandeur confirme la résolution (clôture) ou rouvre le ticket
        if ((int) $ticket['demandeur_id'] === $utilisateur['id'] && $ticket['statut'] === 'resolu') {
            return $suivants;
        }
        return [];
    }

    public static function changerStatut(int $ticketId, string $nouveau, array $utilisateur): void
    {
        $ticket = Ticket::trouver($ticketId) ?? throw new DomainException('Ticket introuvable.');

        if (!in_array($nouveau, self::transitionsPermises($ticket, $utilisateur), true)) {
            throw new DomainException('Ce changement de statut n\'est pas autorisé.');
        }

        self::transaction(function () use ($ticket, $nouveau, $utilisateur): void {
            Ticket::modifierStatut((int) $ticket['id'], $ticket['statut'], $nouveau);
            Ticket::ajouterHistorique((int) $ticket['id'], $ticket['statut'], $nouveau, $utilisateur['id']);
        });

        Audit::enregistrer('CHANGEMENT_STATUT', $ticket['reference'] . ' : ' . $ticket['statut'] . ' -> ' . $nouveau, $utilisateur['id']);
                NotificationService::statutChange($ticket, $nouveau, $utilisateur);
    }

    public static function commenter(int $ticketId, array $utilisateur, string $contenu): void
    {
        $contenu = trim($contenu);
        if ($contenu === '' || mb_strlen($contenu) > 2000) {
            throw new DomainException('Le commentaire doit contenir entre 1 et 2000 caractères.');
        }

        $ticket = Ticket::trouver($ticketId) ?? throw new DomainException('Ticket introuvable.');
        if ($ticket['statut'] === 'cloture') {
            throw new DomainException('Ce ticket est clôturé : les commentaires sont fermés.');
        }

        Commentaire::ajouter($ticketId, $utilisateur['id'], $contenu);
        Audit::enregistrer('COMMENTAIRE', $ticket['reference'], $utilisateur['id']);
                NotificationService::commentaire($ticket, $utilisateur, $contenu);
    }

    /** Assignation (ou réassignation) par l'administrateur. */
    public static function assignerManuellement(int $ticketId, int $technicienId, array $admin): void
    {
        $ticket = Ticket::trouver($ticketId) ?? throw new DomainException('Ticket introuvable.');

        if (in_array($ticket['statut'], ['resolu', 'cloture'], true)) {
            throw new DomainException('Un ticket résolu ou clôturé ne peut plus être réassigné.');
        }

        $categorie  = Categorie::trouver((int) $ticket['categorie_id']);
        $technicien = Utilisateur::trouverTechnicienDuDomaine($technicienId, (int) $categorie['domaine_id']);
        if ($technicien === null) {
            throw new DomainException('Ce technicien n\'est pas valide pour le domaine ' . $categorie['domaine'] . '.');
        }
        if ((int) $ticket['technicien_id'] === $technicienId) {
            throw new DomainException('Ce technicien est déjà assigné à ce ticket.');
        }

        self::transaction(function () use ($ticket, $technicien, $admin): void {
            $id = (int) $ticket['id'];
            Ticket::affecterTechnicien($id, (int) $technicien['id']);

            if ($ticket['statut'] === 'nouveau') {
                Ticket::modifierStatut($id, 'nouveau', 'assigne');
                Ticket::ajouterHistorique($id, 'nouveau', 'assigne', $admin['id']);
            } else {
                Commentaire::ajouter($id, $admin['id'],
                    'Ticket réassigné à ' . $technicien['prenom'] . ' ' . $technicien['nom'] . ' par l\'administrateur.');
            }
        });

        Audit::enregistrer('ASSIGNATION_MANUELLE', $ticket['reference'] . ' -> ' . $technicien['prenom'] . ' ' . $technicien['nom'], $admin['id']);
         NotificationService::ticketAssigne($ticket, $technicien);
    }


        /**
     * Règles de saisie d'un ticket. Retourne la liste des erreurs (vide si tout est valide).
     */
    public static function verifierSaisie(string $titre, string $description, ?array $categorie, string $urgence): array
    {
        $erreurs = [];
        if ($titre === '' || mb_strlen($titre) > 150) {
            $erreurs[] = 'Le titre est obligatoire (150 caractères maximum).';
        }
        if (mb_strlen($description) < 10 || mb_strlen($description) > 5000) {
            $erreurs[] = 'La description doit contenir entre 10 et 5000 caractères.';
        }
        if ($categorie === null) {
            $erreurs[] = 'Veuillez choisir une catégorie valide.';
        }
        if (!in_array($urgence, Ticket::URGENCES, true)) {
            $erreurs[] = 'Veuillez choisir un niveau d\'urgence valide.';
        }
        return $erreurs;
    }

    /**
     * L'administrateur peut supprimer tous les tickets ; l'employé peut supprimer
     * les siens sauf lorsqu'ils sont en cours ; le technicien ne peut pas les supprimer.
     */
    public static function peutSupprimer(array $ticket, array $utilisateur): bool
    {
        if ($utilisateur['role'] === 'admin') {
            return true;
        }
        return $utilisateur['role'] === 'employe'
            && (int) $ticket['demandeur_id'] === $utilisateur['id']
            && $ticket['statut'] !== 'en_cours';
    }

    public static function supprimer(int $ticketId, array $utilisateur): void
    {
        $ticket = Ticket::trouver($ticketId) ?? throw new DomainException('Ticket introuvable.');
        if (!self::peutSupprimer($ticket, $utilisateur)) {
            throw new DomainException($utilisateur['role'] === 'employe'
                ? ((int) $ticket['demandeur_id'] === $utilisateur['id']
                    ? 'Un ticket au statut « En cours » ne peut pas être supprimé.'
                    : 'Vous ne pouvez supprimer que vos propres tickets.')
                : 'Vous n\'avez pas le droit de supprimer ce ticket.');
        }

        $fichiers = PieceJointe::nomsStockes($ticketId);
        Ticket::supprimer($ticketId);                        // cascade : commentaires, historique, pièces jointes

        // Une fois la base nettoyée, on efface aussi les fichiers du disque
        foreach ($fichiers as $nom) {
            $chemin = PieceJointeService::dossier() . '/' . basename((string) $nom);
            if (is_file($chemin)) {
                @unlink($chemin);
            }
        }

        // Le journal garde la trace : qui, quel ticket, quel titre
        Audit::enregistrer('SUPPRESSION_TICKET', $ticket['reference'] . ' « ' . $ticket['titre'] . ' » (statut : ' . $ticket['statut'] . ')', $utilisateur['id']);
        NotificationService::ticketSupprime($ticket, $utilisateur);
    }

    /** Exécute plusieurs écritures en une seule transaction. */
    private static function transaction(callable $travail): void
    {
        $pdo = Database::getConnection();
        $pdo->beginTransaction();
        try {
            $travail();
            $pdo->commit();
        } catch (Throwable $e) {
            $pdo->rollBack();
            throw $e;
        }
    }
    
}

