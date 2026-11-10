<?php
declare(strict_types=1);

namespace App\Services;

use App\Core\Database;

/**
 * Routage automatique : choisit le technicien d'un domaine
 * qui a le moins de tickets en cours.
 */
final class RoutageService
{
    public static function choisirTechnicien(int $domaineId): ?array
    {
        $sql = 'SELECT u.id, u.prenom, u.nom, u.email,
                       (SELECT COUNT(*) FROM tickets t
                         WHERE t.technicien_id = u.id
                           AND t.statut IN (\'assigne\', \'en_cours\', \'en_attente\')) AS charge,
                       (SELECT MAX(t.assigne_le) FROM tickets t
                         WHERE t.technicien_id = u.id) AS dernier_assigne
                FROM utilisateurs u
                JOIN roles r ON r.id = u.role_id
                WHERE r.code = \'technicien\' AND u.actif = 1 AND u.domaine_id = :domaine
                ORDER BY charge ASC,
                         (dernier_assigne IS NOT NULL) ASC,
                         dernier_assigne ASC,
                         u.id ASC
                LIMIT 1';

        $requete = Database::getConnection()->prepare($sql);
        $requete->execute(['domaine' => $domaineId]);

        return $requete->fetch() ?: null;
    }
}