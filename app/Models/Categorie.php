<?php
declare(strict_types=1);

namespace App\Models;

use App\Core\Database;

final class Categorie
{
    public static function trouver(int $id): ?array
    {
        $requete = Database::getConnection()->prepare(
            'SELECT c.id, c.libelle, c.domaine_id, d.libelle AS domaine
             FROM categories c
             JOIN domaines d ON d.id = c.domaine_id
             WHERE c.id = :id'
        );
        $requete->execute(['id' => $id]);
        return $requete->fetch() ?: null;
    }

    /** Catégories regroupées par domaine, pour le menu déroulant du formulaire. */
    public static function groupeesParDomaine(): array
    {
        $lignes = Database::getConnection()->query(
            'SELECT c.id, c.libelle, d.libelle AS domaine
             FROM categories c
             JOIN domaines d ON d.id = c.domaine_id
             ORDER BY d.id, c.libelle'
        )->fetchAll();

        $groupes = [];
        foreach ($lignes as $ligne) {
            $groupes[$ligne['domaine']][] = $ligne;
        }
        return $groupes;
    }
}