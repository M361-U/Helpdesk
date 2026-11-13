<?php
declare(strict_types=1);

namespace App\Models;

use App\Core\Auth;
use App\Core\Database;
use Throwable;

final class Audit
{
    public static function enregistrer(string $action, ?string $details = null, ?int $utilisateurId = null): void
    {
        $utilisateurId ??= Auth::utilisateur()['id'] ?? null;

        try {
            $requete = Database::getConnection()->prepare(
                'INSERT INTO journal_audit (utilisateur_id, action, details, adresse_ip)
                 VALUES (:uid, :action, :details, :ip)'
            );
            $requete->execute([
                'uid'     => $utilisateurId,
                'action'  => $action,
                'details' => $details !== null ? mb_substr($details, 0, 255) : null,
                'ip'      => $_SERVER['REMOTE_ADDR'] ?? null,
            ]);
        } catch (Throwable $e) {
            // Un échec de journalisation ne doit jamais bloquer l'utilisateur
            error_log('Audit : ' . $e->getMessage());
        }
    }    // ---------- Consultation du journal (administration) ----------

    /** @return array<int, string> les types d'actions déjà enregistrés */
    public static function actions(): array
    {
        return Database::getConnection()
            ->query('SELECT DISTINCT action FROM journal_audit ORDER BY action')
            ->fetchAll(\PDO::FETCH_COLUMN);
    }

    public static function compter(string $action, string $recherche): int
    {
        [$where, $parametres] = self::filtre($action, $recherche);
        $requete = Database::getConnection()->prepare(
            'SELECT COUNT(*) FROM journal_audit a LEFT JOIN utilisateurs u ON u.id = a.utilisateur_id ' . $where
        );
        $requete->execute($parametres);
        return (int) $requete->fetchColumn();
    }

    public static function lister(string $action, string $recherche, int $limite, int $decalage): array
    {
        [$where, $parametres] = self::filtre($action, $recherche);
        $limite   = max(1, $limite);
        $decalage = max(0, $decalage);

        $requete = Database::getConnection()->prepare(
            "SELECT a.id, a.action, a.details, a.adresse_ip, a.cree_le, u.prenom, u.nom
             FROM journal_audit a
             LEFT JOIN utilisateurs u ON u.id = a.utilisateur_id
             $where
             ORDER BY a.id DESC
             LIMIT $limite OFFSET $decalage"
        );
        $requete->execute($parametres);
        return $requete->fetchAll();
    }

    public static function supprimer(int $id): bool
    {
        $requete = Database::getConnection()->prepare('DELETE FROM journal_audit WHERE id = :id');
        $requete->execute(['id' => $id]);
        return $requete->rowCount() > 0;
    }

    public static function supprimerPlusieurs(array $ids): int
    {
        $supprimes = 0;
        foreach (array_unique($ids) as $id) {
            if (self::supprimer((int) $id)) {
                $supprimes++;
            }
        }
        return $supprimes;
    }

    public static function supprimerTout(): int
    {
        return Database::getConnection()->exec('DELETE FROM journal_audit');
    }

    /** @return array{0: string, 1: array} clause WHERE et paramètres */
    private static function filtre(string $action, string $recherche): array
    {
        $conditions = [];
        $parametres = [];
        if ($action !== '') {
            $conditions[] = 'a.action = :action';
            $parametres['action'] = $action;
        }
        if ($recherche !== '') {
            // On neutralise % et _ pour qu'ils soient cherchés comme de simples caractères
            $motif = '%' . str_replace(['\\', '%', '_'], ['\\\\', '\\%', '\\_'], $recherche) . '%';
            $conditions[] = "(a.details LIKE :q1 OR CONCAT(u.prenom, ' ', u.nom) LIKE :q2)";
            $parametres['q1'] = $motif;
            $parametres['q2'] = $motif;
        }
        return [$conditions ? 'WHERE ' . implode(' AND ', $conditions) : '', $parametres];
    }


}