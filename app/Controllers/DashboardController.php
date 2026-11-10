<?php
declare(strict_types=1);

namespace App\Controllers;

use App\Core\Auth;
use App\Core\Controller;
use App\Models\Statistiques;
use App\Models\Ticket;

final class DashboardController extends Controller
{
    public function index(): void
    {
        Auth::exigerConnexion();
        $u = Auth::utilisateur();

        $donnees = ['titre' => 'Tableau de bord', 'utilisateur' => $u];

        if ($u['role'] === 'admin') {
            $donnees += [
                'kpi'         => Statistiques::indicateurs(),
                'statuts'     => Statistiques::parStatut(),
                'domaines'    => Statistiques::parDomaine(),
                'techniciens' => Statistiques::chargeTechniciens(),
                'jours'       => Statistiques::parJour(14),
                'aTraiter'    => Statistiques::aTraiter(8),
                'demandes'    => \App\Models\Utilisateur::compterEnAttente(),
            ];
        } elseif ($u['role'] === 'technicien') {
            $ouverts = array_filter(
                Ticket::lister('technicien', $u['id']),
                fn(array $t): bool => !in_array($t['statut'], ['resolu', 'cloture'], true)
            );
            $donnees += [
                'kpi'          => Statistiques::indicateurs($u['id'], null),
                'prioritaires' => array_slice(array_values($ouverts), 0, 6),
            ];
        } else {
            $donnees += [
                'kpi'     => Statistiques::indicateurs(null, $u['id']),
                'recents' => array_slice(Ticket::lister('employe', $u['id']), 0, 5),
            ];
        }

        $this->vue('dashboard/index', $donnees);
    }
}