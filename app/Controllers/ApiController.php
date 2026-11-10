<?php
declare(strict_types=1);

namespace App\Controllers;

use App\Core\Auth;
use App\Core\Controller;
use App\Models\Statistiques;

/** Points d'accès JSON appelés par le JavaScript (fetch). */
final class ApiController extends Controller
{
    /** Chiffres clés du tableau de bord, selon le rôle de l'utilisateur connecté. */
    public function statistiques(): void
    {
        // Pas de redirection vers /login ici : le JavaScript attend une réponse JSON
        $u = Auth::utilisateur();
        if ($u === null || !Auth::compteValide()) {
            $this->json(['erreur' => 'Non connecté'], 401);
        }
        $u = Auth::utilisateur();

        $kpi = match ($u['role']) {
            'admin'      => Statistiques::indicateurs(),
            'technicien' => Statistiques::indicateurs($u['id'], null),
            default      => Statistiques::indicateurs(null, $u['id']),
        };

        $this->json([
            'genere_a' => date('H:i:s'),
            'kpi' => [
                'ouverts'         => $kpi['ouverts'],
                'non_assignes'    => $kpi['non_assignes'],
                'a_confirmer'     => $kpi['a_confirmer'],
                'clotures'        => $kpi['resolus'] - $kpi['a_confirmer'],
                'resolus'         => $kpi['resolus'],
                'avec_resolution' => $kpi['avec_resolution'],
                'en_retard'       => $kpi['en_retard'],
                'urgents'         => $kpi['urgents'],
                'delai_moyen'     => delai_moyen_fr($kpi['delai_moyen']),
                'taux_sla'        => $kpi['avec_resolution'] > 0
                    ? pourcentage($kpi['sla_respectes'], $kpi['avec_resolution']) . ' %'
                    : '—',
            ],
        ]);
    }

    private function json(array $donnees, int $statut = 200): never
    {
        http_response_code($statut);
        header('Content-Type: application/json; charset=utf-8');
        header('Cache-Control: no-store');
        echo json_encode($donnees, JSON_UNESCAPED_UNICODE);
        exit;
    }
}