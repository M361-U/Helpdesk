<?php
declare(strict_types=1);

namespace App\Models;

use App\Core\Database;

final class Statistiques
{
    private const OUVERTS = '(\'nouveau\', \'assigne\', \'en_cours\', \'en_attente\')';

    public const STATUTS = ['nouveau', 'assigne', 'en_cours', 'en_attente', 'resolu', 'cloture'];

    /** Chiffres clés : tous les tickets, ceux d'un technicien ou d'un demandeur. */
    public static function indicateurs(?int $technicienId = null, ?int $demandeurId = null): array
    {
        [$where, $parametres] = self::perimetre($technicienId, $demandeurId);
        $parametres['maintenant'] = date('Y-m-d H:i:s');
        $ouverts = self::OUVERTS;

        $requete = Database::getConnection()->prepare(
            "SELECT COUNT(*) AS total,
                    COALESCE(SUM(statut IN $ouverts), 0)                                   AS ouverts,
                    COALESCE(SUM(statut = 'nouveau'), 0)                                   AS non_assignes,
                    COALESCE(SUM(statut = 'resolu'), 0)                                    AS a_confirmer,
                    COALESCE(SUM(statut IN ('resolu', 'cloture')), 0)                      AS resolus,
                    COALESCE(SUM(statut IN $ouverts AND echeance_sla < :maintenant), 0)    AS en_retard,
                    COALESCE(SUM(statut IN $ouverts AND urgence IN ('haute', 'critique')), 0) AS urgents,
                    COUNT(resolu_le)                                                       AS avec_resolution,
                    COALESCE(SUM(resolu_le IS NOT NULL AND resolu_le <= echeance_sla), 0)  AS sla_respectes,
                    AVG(TIMESTAMPDIFF(SECOND, cree_le, resolu_le))                         AS delai_moyen
             FROM tickets $where"
        );
        $requete->execute($parametres);
        $ligne = $requete->fetch();

        foreach ($ligne as $cle => $valeur) {
            $ligne[$cle] = $cle === 'delai_moyen' ? ($valeur !== null ? (float) $valeur : null) : (int) $valeur;
        }
        return $ligne;
    }

    /** Nombre de tickets par statut (tous les statuts sont présents, même à 0). */
    public static function parStatut(): array
    {
        $comptes = array_fill_keys(self::STATUTS, 0);
        $lignes = Database::getConnection()
            ->query('SELECT statut, COUNT(*) AS n FROM tickets GROUP BY statut')->fetchAll();
        foreach ($lignes as $ligne) {
            $comptes[$ligne['statut']] = (int) $ligne['n'];
        }
        return $comptes;
    }

    /** Tickets par domaine : total et encore ouverts. */
    public static function parDomaine(): array
    {
        $ouverts = self::OUVERTS;
        return Database::getConnection()->query(
            "SELECT d.libelle AS domaine,
                    COUNT(t.id) AS total,
                    COALESCE(SUM(t.statut IN $ouverts), 0) AS ouverts
             FROM domaines d
             LEFT JOIN categories c ON c.domaine_id = d.id
             LEFT JOIN tickets t    ON t.categorie_id = c.id
             GROUP BY d.id, d.libelle
             ORDER BY d.id"
        )->fetchAll();
    }

    /** Charge et performance de chaque technicien actif. */
    public static function chargeTechniciens(): array
    {
        return Database::getConnection()->query(
            "SELECT u.id, u.prenom, u.nom, d.libelle AS domaine,
                    COALESCE(SUM(t.statut IN ('assigne', 'en_cours', 'en_attente')), 0) AS en_cours,
                    COUNT(t.resolu_le) AS resolus,
                    AVG(TIMESTAMPDIFF(SECOND, t.cree_le, t.resolu_le)) AS delai_moyen
             FROM utilisateurs u
             JOIN roles r ON r.id = u.role_id
             LEFT JOIN domaines d ON d.id = u.domaine_id
             LEFT JOIN tickets t  ON t.technicien_id = u.id
             WHERE r.code = 'technicien' AND u.actif = 1
             GROUP BY u.id, u.prenom, u.nom, d.id, d.libelle
             ORDER BY d.id, u.nom"
        )->fetchAll();
    }

    /** Tickets créés par jour sur les N derniers jours (les jours vides valent 0). */
    public static function parJour(int $jours = 14): array
    {
        $debut = date('Y-m-d', strtotime('-' . ($jours - 1) . ' days'));

        $requete = Database::getConnection()->prepare(
            'SELECT DATE(cree_le) AS jour, COUNT(*) AS n
             FROM tickets WHERE cree_le >= :debut
             GROUP BY DATE(cree_le)'
        );
        $requete->execute(['debut' => $debut . ' 00:00:00']);
        $trouves = array_column($requete->fetchAll(), 'n', 'jour');

        $serie = [];
        for ($i = $jours - 1; $i >= 0; $i--) {
            $jour = date('Y-m-d', strtotime("-$i days"));
            $serie[$jour] = (int) ($trouves[$jour] ?? 0);
        }
        return $serie;
    }

    /** Tickets qui demandent une action : non assignés ou en retard. */
    public static function aTraiter(int $limite = 8): array
    {
        $ouverts = self::OUVERTS;
        $limite  = max(1, $limite);

        $requete = Database::getConnection()->prepare(
            "SELECT t.id, t.reference, t.titre, t.urgence, t.statut, t.echeance_sla,
                    d.libelle AS domaine, tech.prenom AS tech_prenom, tech.nom AS tech_nom
             FROM tickets t
             JOIN categories c ON c.id = t.categorie_id
             JOIN domaines d   ON d.id = c.domaine_id
             LEFT JOIN utilisateurs tech ON tech.id = t.technicien_id
             WHERE t.statut = 'nouveau'
                OR (t.statut IN $ouverts AND t.echeance_sla < :maintenant)
             ORDER BY (t.statut = 'nouveau') DESC,
                      FIELD(t.urgence, 'critique', 'haute', 'normale', 'basse'),
                      t.echeance_sla
             LIMIT $limite"
        );
        $requete->execute(['maintenant' => date('Y-m-d H:i:s')]);
        return $requete->fetchAll();
    }

    /** @return array{0: string, 1: array} clause WHERE et paramètres */
    private static function perimetre(?int $technicienId, ?int $demandeurId): array
    {
        $conditions = [];
        $parametres = [];
        if ($technicienId !== null) {
            $conditions[] = 'technicien_id = :tech';
            $parametres['tech'] = $technicienId;
        }
        if ($demandeurId !== null) {
            $conditions[] = 'demandeur_id = :dem';
            $parametres['dem'] = $demandeurId;
        }
        return [$conditions ? 'WHERE ' . implode(' AND ', $conditions) : '', $parametres];
    }
}