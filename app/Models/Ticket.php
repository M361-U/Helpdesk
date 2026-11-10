<?php
declare(strict_types=1);

namespace App\Models;

use App\Core\Database;

final class Ticket
{
    public const URGENCES = ['basse', 'normale', 'haute', 'critique'];

    /** Délai cible de résolution (SLA), en heures, selon l'urgence. */
    public const SLA_HEURES = ['critique' => 4, 'haute' => 8, 'normale' => 24, 'basse' => 72];

    /** Insère le ticket (statut « nouveau ») et retourne son identifiant. */
    public static function creer(int $demandeurId, int $categorieId, string $titre,
                                 string $description, string $urgence): int
    {
        $pdo = Database::getConnection();
        $maintenant = time();

        $requete = $pdo->prepare(
            'INSERT INTO tickets (reference, titre, description, categorie_id, demandeur_id,
                                  urgence, statut, echeance_sla, cree_le)
             VALUES (:ref, :titre, :description, :cat, :dem, :urgence, \'nouveau\', :sla, :cree)'
        );
        $requete->execute([
            'ref'         => 'TMP-' . bin2hex(random_bytes(6)),   // remplacée juste après
            'titre'       => $titre,
            'description' => $description,
            'cat'         => $categorieId,
            'dem'         => $demandeurId,
            'urgence'     => $urgence,
            'sla'         => date('Y-m-d H:i:s', $maintenant + self::SLA_HEURES[$urgence] * 3600),
            'cree'        => date('Y-m-d H:i:s', $maintenant),
        ]);
        $id = (int) $pdo->lastInsertId();

        // Référence lisible : TK-2026-0001
        $reference = sprintf('TK-%s-%04d', date('Y'), $id);
        $pdo->prepare('UPDATE tickets SET reference = :ref WHERE id = :id')
            ->execute(['ref' => $reference, 'id' => $id]);

        return $id;
    }

    /** Affecte le ticket à un technicien et passe son statut à « assigné ». */
    public static function assigner(int $ticketId, int $technicienId): void
    {
        Database::getConnection()->prepare(
            'UPDATE tickets
             SET technicien_id = :tech, statut = \'assigne\', assigne_le = :quand
             WHERE id = :id'
        )->execute([
            'tech'  => $technicienId,
            'quand' => date('Y-m-d H:i:s'),
            'id'    => $ticketId,
        ]);
    }

    public static function ajouterHistorique(int $ticketId, ?string $ancien, string $nouveau, ?int $parUtilisateur): void
    {
        Database::getConnection()->prepare(
            'INSERT INTO historique_statuts (ticket_id, ancien_statut, nouveau_statut, modifie_par, modifie_le)
             VALUES (:t, :a, :n, :u, :quand)'
        )->execute([
            't' => $ticketId, 'a' => $ancien, 'n' => $nouveau,
            'u' => $parUtilisateur, 'quand' => date('Y-m-d H:i:s'),
        ]);
    }

    public static function trouver(int $id): ?array
    {
        $requete = Database::getConnection()->prepare(
            'SELECT t.*, c.libelle AS categorie, d.libelle AS domaine, d.code AS domaine_code,
                    dem.prenom AS dem_prenom, dem.nom AS dem_nom, dem.email AS dem_email,
                                        tech.prenom AS tech_prenom, tech.nom AS tech_nom, tech.email AS tech_email
             FROM tickets t
             JOIN categories c   ON c.id = t.categorie_id
             JOIN domaines d     ON d.id = c.domaine_id
             JOIN utilisateurs dem ON dem.id = t.demandeur_id
             LEFT JOIN utilisateurs tech ON tech.id = t.technicien_id
             WHERE t.id = :id'
        );
        $requete->execute(['id' => $id]);
        return $requete->fetch() ?: null;
    }

    public static function historique(int $ticketId): array
    {
        $requete = Database::getConnection()->prepare(
            'SELECT h.*, u.prenom, u.nom
             FROM historique_statuts h
             LEFT JOIN utilisateurs u ON u.id = h.modifie_par
             WHERE h.ticket_id = :id
             ORDER BY h.id'
        );
        $requete->execute(['id' => $ticketId]);
        return $requete->fetchAll();
    }

    /** Liste des tickets visibles par un utilisateur selon son rôle. */
    public static function lister(string $role, int $utilisateurId): array
    {
        $sql = 'SELECT t.id, t.reference, t.titre, t.urgence, t.statut, t.cree_le, t.echeance_sla,
                       c.libelle AS categorie, d.libelle AS domaine,
                       dem.prenom AS dem_prenom, dem.nom AS dem_nom,
                       tech.prenom AS tech_prenom, tech.nom AS tech_nom
                FROM tickets t
                JOIN categories c ON c.id = t.categorie_id
                JOIN domaines d   ON d.id = c.domaine_id
                JOIN utilisateurs dem ON dem.id = t.demandeur_id
                LEFT JOIN utilisateurs tech ON tech.id = t.technicien_id';
        $parametres = [];

        if ($role === 'employe') {
            $sql .= ' WHERE t.demandeur_id = :uid ORDER BY t.cree_le DESC, t.id DESC';
            $parametres['uid'] = $utilisateurId;
        } elseif ($role === 'technicien') {
            $sql .= ' WHERE t.technicien_id = :uid
                      ORDER BY (t.statut IN (\'resolu\',\'cloture\')),
                               FIELD(t.urgence, \'critique\',\'haute\',\'normale\',\'basse\'), t.cree_le';
            $parametres['uid'] = $utilisateurId;
        } else {
            $sql .= ' ORDER BY t.cree_le DESC, t.id DESC LIMIT 200';
        }

        $requete = Database::getConnection()->prepare($sql);
        $requete->execute($parametres);
        return $requete->fetchAll();
    }
    
    /**
     * Change le statut. La date de résolution est renseignée quand le ticket
     * passe à « résolu », et effacée seulement s'il est rouvert.
     */
    public static function modifierStatut(int $ticketId, string $ancien, string $nouveau): void
    {
        Database::getConnection()->prepare(
            'UPDATE tickets
             SET statut = :nouveau,
                 resolu_le = CASE WHEN :cible = \'resolu\' THEN :quand
                                  WHEN :precedent = \'resolu\' AND :cible2 = \'en_cours\' THEN NULL
                                  ELSE resolu_le END
             WHERE id = :id'
        )->execute([
            'nouveau'   => $nouveau,
            'cible'     => $nouveau,
            'cible2'    => $nouveau,
            'quand'     => date('Y-m-d H:i:s'),
            'precedent' => $ancien,
            'id'        => $ticketId,
        ]);
    }

    /** Change le technicien sans toucher au statut. */
    public static function affecterTechnicien(int $ticketId, int $technicienId): void
    {
        Database::getConnection()->prepare(
            'UPDATE tickets SET technicien_id = :tech, assigne_le = :quand WHERE id = :id'
        )->execute([
            'tech' => $technicienId, 'quand' => date('Y-m-d H:i:s'), 'id' => $ticketId,
        ]);
    }
    
        /** Supprime le ticket ; commentaires, historique et pièces jointes partent avec lui (ON DELETE CASCADE). */
    public static function supprimer(int $ticketId): void
    {
        Database::getConnection()->prepare('DELETE FROM tickets WHERE id = :id')->execute(['id' => $ticketId]);
    }
}