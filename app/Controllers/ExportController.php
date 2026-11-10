<?php
declare(strict_types=1);

namespace App\Controllers;

use App\Core\Auth;
use App\Core\Controller;
use App\Core\Pdf;
use App\Models\Audit;
use App\Models\Commentaire;
use App\Models\PieceJointe;
use App\Models\Ticket;

/** Exports : liste des tickets en CSV ou PDF, fiche d'un ticket en PDF. */
final class ExportController extends Controller
{
    private const COLONNES_CSV = [
        'Référence', 'Titre', 'Domaine', 'Catégorie', 'Urgence', 'Statut',
        'Demandeur', 'Technicien', 'Créé le', 'Échéance SLA',
    ];

    /** Export CSV de la liste (ouvrable dans Excel). */
    public function csv(): void
    {
        Auth::exigerConnexion();
        $u = Auth::utilisateur();
        $tickets = $this->ticketsFiltres($u);

        Audit::enregistrer('EXPORT_CSV', count($tickets) . ' ticket(s)', $u['id']);

        header('Content-Type: text/csv; charset=utf-8');
        header('Content-Disposition: attachment; filename="tickets_' . date('Y-m-d') . '.csv"');
        header('X-Content-Type-Options: nosniff');
        header('Cache-Control: no-store');

        $sortie = fopen('php://output', 'w');
        fwrite($sortie, "\xEF\xBB\xBF");                       // BOM : Excel reconnaît l'UTF-8 (accents)
        fputcsv($sortie, self::COLONNES_CSV, ';');             // « ; » : séparateur d'Excel en version française

        foreach ($tickets as $t) {
            fputcsv($sortie, array_map([$this, 'proteger'], [
                $t['reference'], $t['titre'], $t['domaine'], $t['categorie'],
                libelle_urgence($t['urgence']), libelle_statut($t['statut']),
                $t['dem_prenom'] . ' ' . $t['dem_nom'],
                $t['tech_nom'] !== null ? $t['tech_prenom'] . ' ' . $t['tech_nom'] : 'Non assigné',
                date_fr($t['cree_le']), date_fr($t['echeance_sla']),
            ]), ';');
        }
        fclose($sortie);
        exit;
    }

    /** Export PDF de la liste (paysage). */
    public function pdfListe(): void
    {
        Auth::exigerConnexion();
        $u = Auth::utilisateur();
        $tickets = $this->ticketsFiltres($u);

        Audit::enregistrer('EXPORT_PDF', count($tickets) . ' ticket(s)', $u['id']);

        $pdf = new Pdf(paysage: true);
        $pdf->piedDePage(config('app.nom') . ' : liste des tickets');
        $pdf->titre('Liste des tickets');
        $pdf->paragraphe('Édité le ' . date('d/m/Y à H:i') . ' par ' . $u['prenom'] . ' ' . $u['nom'] . ' (' . libelle_role($u['role']) . ').');

        $criteres = [];
        if (!empty($_GET['statut'])) {
            $criteres[] = 'statut : ' . libelle_statut((string) $_GET['statut']);
        }
        if (!empty($_GET['urgence'])) {
            $criteres[] = 'urgence : ' . libelle_urgence((string) $_GET['urgence']);
        }
        $pdf->paragraphe(count($tickets) . ' ticket(s)' . ($criteres ? ' (filtre : ' . implode(', ', $criteres) . ')' : '') . '.');
        $pdf->espace(8);

        $lignes = array_map(fn(array $t): array => [
            $t['reference'], $t['titre'], $t['domaine'],
            libelle_urgence($t['urgence']), libelle_statut($t['statut']),
            $t['dem_prenom'] . ' ' . $t['dem_nom'],
            $t['tech_nom'] !== null ? $t['tech_prenom'] . ' ' . $t['tech_nom'] : 'Non assigné',
            date_fr($t['cree_le']),
        ], $tickets);

        $pdf->tableau([
            ['Référence', 85], ['Titre', 200], ['Domaine', 65], ['Urgence', 55],
            ['Statut', 65], ['Demandeur', 105], ['Technicien', 105], ['Créé le', 85],
        ], $lignes);

        $pdf->telecharger('tickets_' . date('Y-m-d') . '.pdf');
    }

    /** Fiche PDF d'un seul ticket (mêmes droits d'accès que la page du ticket). */
    public function pdfFiche(string $id): void
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

        Audit::enregistrer('EXPORT_FICHE_PDF', $ticket['reference'], $u['id']);

        $pdf = new Pdf();
        $pdf->piedDePage(config('app.nom') . ' : fiche ' . $ticket['reference']);
        $pdf->titre('Fiche de ticket ' . $ticket['reference']);
        $pdf->paragraphe('Éditée le ' . date('d/m/Y à H:i') . ' par ' . $u['prenom'] . ' ' . $u['nom'] . '.');

        $pdf->sousTitre('Informations générales');
        $pdf->cleValeur('Titre', $ticket['titre']);
        $pdf->cleValeur('Statut', libelle_statut($ticket['statut']));
        $pdf->cleValeur('Urgence', libelle_urgence($ticket['urgence']));
        $pdf->cleValeur('Domaine', $ticket['domaine']);
        $pdf->cleValeur('Catégorie', $ticket['categorie']);
        $pdf->cleValeur('Demandeur', $ticket['dem_prenom'] . ' ' . $ticket['dem_nom']);
        $pdf->cleValeur('Technicien', $ticket['tech_nom'] !== null ? $ticket['tech_prenom'] . ' ' . $ticket['tech_nom'] : 'Non assigné');
        $pdf->cleValeur('Créé le', date_fr($ticket['cree_le']));
        $pdf->cleValeur('Échéance SLA', date_fr($ticket['echeance_sla']));
        if ($ticket['resolu_le'] !== null) {
            $pdf->cleValeur('Résolu le', date_fr($ticket['resolu_le']));
            $delai = strtotime($ticket['resolu_le']) - strtotime($ticket['cree_le']);
            $pdf->cleValeur('Délai de résolution', duree_fr($delai)
                . ($ticket['resolu_le'] <= $ticket['echeance_sla'] ? ' (SLA respecté)' : ' (SLA dépassé)'));
        }

        $pdf->sousTitre('Description');
        $pdf->paragraphe($ticket['description']);

        $pieces = PieceJointe::lister((int) $ticket['id']);
        if ($pieces) {
            $pdf->sousTitre('Pièces jointes');
            foreach ($pieces as $p) {
                $pdf->paragraphe('- ' . $p['nom_original'] . ' (' . taille_fr((int) $p['taille']) . ')');
            }
        }

        $pdf->sousTitre('Historique des statuts');
        foreach (Ticket::historique((int) $ticket['id']) as $h) {
            $par = $h['nom'] !== null ? $h['prenom'] . ' ' . $h['nom'] : 'le système';
            $pdf->paragraphe(date_fr($h['modifie_le']) . ' : ' . libelle_statut($h['nouveau_statut']) . ' (par ' . $par . ')');
        }

        $commentaires = Commentaire::lister((int) $ticket['id']);
        if ($commentaires) {
            $pdf->sousTitre('Commentaires');
            foreach ($commentaires as $c) {
                $pdf->paragraphe($c['prenom'] . ' ' . $c['nom'] . ' le ' . date_fr($c['cree_le']), 9, true);
                $pdf->paragraphe($c['contenu']);
                $pdf->espace(4);
            }
        }

        $pdf->telecharger($ticket['reference'] . '.pdf');
    }

    /** Tickets visibles par l'utilisateur, avec les filtres facultatifs ?statut= et ?urgence=. */
    private function ticketsFiltres(array $u): array
    {
        $tickets = Ticket::lister($u['role'], $u['id']);
        $statut  = (string) ($_GET['statut'] ?? '');
        $urgence = (string) ($_GET['urgence'] ?? '');

        return array_values(array_filter($tickets, fn(array $t): bool =>
            ($statut === '' || $t['statut'] === $statut) && ($urgence === '' || $t['urgence'] === $urgence)
        ));
    }

    /**
     * Protection contre l'« injection de formule » : un texte qui commence par = + - @
     * serait exécuté comme une formule par Excel. On le fait précéder d'une apostrophe.
     */
    private function proteger(string $valeur): string
    {
        return $valeur !== '' && str_contains("=+-@\t\r", $valeur[0]) ? "'" . $valeur : $valeur;
    }
}