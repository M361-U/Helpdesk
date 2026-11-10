<?php
declare(strict_types=1);

namespace App\Models;

use App\Core\Database;

final class Commentaire
{
    public static function ajouter(int $ticketId, int $auteurId, string $contenu): void
    {
        Database::getConnection()->prepare(
            'INSERT INTO commentaires (ticket_id, auteur_id, contenu, cree_le)
             VALUES (:t, :a, :c, :quand)'
        )->execute([
            't' => $ticketId, 'a' => $auteurId, 'c' => $contenu,
            'quand' => date('Y-m-d H:i:s'),
        ]);
    }

    public static function lister(int $ticketId): array
    {
        $requete = Database::getConnection()->prepare(
            'SELECT c.id, c.contenu, c.cree_le, u.prenom, u.nom, r.code AS role
             FROM commentaires c
             JOIN utilisateurs u ON u.id = c.auteur_id
             JOIN roles r        ON r.id = u.role_id
             WHERE c.ticket_id = :id
             ORDER BY c.id'
        );
        $requete->execute(['id' => $ticketId]);
        return $requete->fetchAll();
    }
}