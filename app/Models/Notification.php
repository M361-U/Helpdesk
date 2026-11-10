<?php
declare(strict_types=1);

namespace App\Models;

use App\Core\Database;

/** File des e-mails : chaque message est d'abord enregistré, puis envoyé. */
final class Notification
{
    public static function ajouter(string $destinataire, string $sujet, string $corps): int
    {
        $pdo = Database::getConnection();
        $pdo->prepare(
            'INSERT INTO notifications_email (destinataire, sujet, corps, envoye, cree_le)
             VALUES (:d, :s, :c, 0, :quand)'
        )->execute(['d' => $destinataire, 's' => $sujet, 'c' => $corps, 'quand' => date('Y-m-d H:i:s')]);
        return (int) $pdo->lastInsertId();
    }

    public static function marquerEnvoye(int $id): void
    {
        Database::getConnection()
            ->prepare('UPDATE notifications_email SET envoye = 1, erreur = NULL WHERE id = :id')
            ->execute(['id' => $id]);
    }

    public static function marquerEchec(int $id, string $erreur): void
    {
        Database::getConnection()
            ->prepare('UPDATE notifications_email SET erreur = :e WHERE id = :id')
            ->execute(['e' => mb_substr($erreur, 0, 255), 'id' => $id]);
    }

    public static function lister(int $limite = 100): array
    {
        $limite = max(1, $limite);
        return Database::getConnection()
            ->query("SELECT id, destinataire, sujet, corps, envoye, erreur, cree_le
                     FROM notifications_email ORDER BY id DESC LIMIT $limite")
            ->fetchAll();
    }
}