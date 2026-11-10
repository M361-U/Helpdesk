<?php
declare(strict_types=1);

namespace App\Models;

use App\Core\Database;

final class PieceJointe
{
    public static function ajouter(int $ticketId, int $auteurId, string $nomOriginal, string $nomStocke, int $taille): void
    {
        Database::getConnection()->prepare(
            'INSERT INTO pieces_jointes (ticket_id, auteur_id, nom_original, nom_stocke, taille, cree_le)
             VALUES (:t, :a, :nom, :stocke, :taille, :quand)'
        )->execute([
            't' => $ticketId, 'a' => $auteurId, 'nom' => $nomOriginal,
            'stocke' => $nomStocke, 'taille' => $taille, 'quand' => date('Y-m-d H:i:s'),
        ]);
    }

    public static function lister(int $ticketId): array
    {
        $requete = Database::getConnection()->prepare(
            'SELECT p.id, p.nom_original, p.taille, p.cree_le, u.prenom, u.nom
             FROM pieces_jointes p
             LEFT JOIN utilisateurs u ON u.id = p.auteur_id
             WHERE p.ticket_id = :id
             ORDER BY p.id'
        );
        $requete->execute(['id' => $ticketId]);
        return $requete->fetchAll();
    }

    public static function compter(int $ticketId): int
    {
        $requete = Database::getConnection()->prepare('SELECT COUNT(*) FROM pieces_jointes WHERE ticket_id = :id');
        $requete->execute(['id' => $ticketId]);
        return (int) $requete->fetchColumn();
    }

    /** Retourne la pièce SEULEMENT si elle appartient bien à ce ticket. */
    public static function trouver(int $id, int $ticketId): ?array
    {
        $requete = Database::getConnection()->prepare(
            'SELECT * FROM pieces_jointes WHERE id = :id AND ticket_id = :t'
        );
        $requete->execute(['id' => $id, 't' => $ticketId]);
        return $requete->fetch() ?: null;
    }

    
    public static function nomsStockes(int $ticketId): array
    {
        $requete = Database::getConnection()->prepare('SELECT nom_stocke FROM pieces_jointes WHERE ticket_id = :id');
        $requete->execute(['id' => $ticketId]);
        return array_column($requete->fetchAll(), 'nom_stocke');
    }
}