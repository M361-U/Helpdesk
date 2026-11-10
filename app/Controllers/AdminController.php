<?php
declare(strict_types=1);

namespace App\Controllers;

use App\Core\Auth;
use App\Core\Controller;
use App\Models\Audit;
use App\Models\Notification;
use App\Models\Utilisateur;
use App\Services\UtilisateurService;
use DomainException;

/** Pages réservées à l'administrateur. */
final class AdminController extends Controller
{
    private const PAR_PAGE = 50;

    /** Boîte d'envoi : tous les e-mails générés par l'application. */
    public function notifications(): void
    {
        Auth::exigerRole('admin');

        $this->vue('admin/notifications', [
            'titre'    => 'E-mails envoyés',
            'messages' => Notification::lister(100),
            'actif'    => (bool) config('mail.actif', false),
        ]);
    }

    /** Journal d'audit : qui a fait quoi, avec filtre et pagination. */
    public function audit(): void
    {
        Auth::exigerRole('admin');

        $action    = (string) ($_GET['action'] ?? '');
        $recherche = trim((string) ($_GET['q'] ?? ''));
        $page      = max(1, (int) ($_GET['page'] ?? 1));

        $total = Audit::compter($action, $recherche);
        $pages = max(1, (int) ceil($total / self::PAR_PAGE));
        $page  = min($page, $pages);

        $this->vue('admin/audit', [
            'titre'     => 'Journal d\'audit',
            'entrees'   => Audit::lister($action, $recherche, self::PAR_PAGE, ($page - 1) * self::PAR_PAGE),
            'actions'   => Audit::actions(),
            'action'    => $action,
            'recherche' => $recherche,
            'page'      => $page,
            'pages'     => $pages,
            'total'     => $total,
        ]);
    }

    // ---------- Gestion des utilisateurs ----------

    public function utilisateurs(): void
    {
        Auth::exigerRole('admin');

        $ancien = $_SESSION['ancien_utilisateur'] ?? [];
        unset($_SESSION['ancien_utilisateur']);

        $this->vue('admin/utilisateurs', [
            'titre'        => 'Utilisateurs',
            'utilisateurs' => Utilisateur::tous(),
            'roles'        => Utilisateur::roles(),
            'domaines'     => Utilisateur::domaines(),
            'ancien'       => $ancien,
        ]);
    }

    public function creerUtilisateur(): void
    {
        Auth::exigerRole('admin');
        Auth::verifierCsrf();

        try {
            $id = UtilisateurService::creer($_POST, (string) ($_POST['mot_de_passe'] ?? ''), Auth::utilisateur());
            flash('success', 'Compte créé. Communiquez le mot de passe à l\'utilisateur de vive voix ou par un canal sûr.');
            $this->rediriger('/admin/utilisateurs/' . $id);
        } catch (DomainException $e) {
            $this->signaler($e);
            // On garde la saisie (sauf le mot de passe) pour ne pas tout retaper
            $_SESSION['ancien_utilisateur'] = array_diff_key($_POST, ['mot_de_passe' => 1, '_csrf' => 1]);
            $this->rediriger('/admin/utilisateurs');
        }
    }

    public function utilisateur(string $id): void
    {
        Auth::exigerRole('admin');
        $compte = ctype_digit($id) ? Utilisateur::trouverParId((int) $id) : null;
        if ($compte === null) {
            $this->introuvable();
        }

        $this->vue('admin/utilisateur', [
            'titre'    => $compte['prenom'] . ' ' . $compte['nom'],
            'compte'   => $compte,
            'roles'    => Utilisateur::roles(),
            'domaines' => Utilisateur::domaines(),
            'enCours'  => Utilisateur::ticketsEnCours((int) $compte['id']),
            'moi'      => Auth::utilisateur()['id'] === (int) $compte['id'],
            'utilisateurs' => Utilisateur::tous(),
            'demandes' => Utilisateur::demandesEnAttente(),
        ]);
    }

    public function modifierUtilisateur(string $id): void
    {
        Auth::exigerRole('admin');
        Auth::verifierCsrf();
        $compte = ctype_digit($id) ? Utilisateur::trouverParId((int) $id) : null;
        if ($compte === null) {
            $this->introuvable();
        }

        try {
            UtilisateurService::modifier((int) $compte['id'], $_POST, isset($_POST['actif']), Auth::utilisateur());
            flash('success', 'Compte mis à jour.');
        } catch (DomainException $e) {
            $this->signaler($e);
        }
        $this->rediriger('/admin/utilisateurs/' . $compte['id']);
    }

    public function motDePasse(string $id): void
    {
        Auth::exigerRole('admin');
        Auth::verifierCsrf();
        $compte = ctype_digit($id) ? Utilisateur::trouverParId((int) $id) : null;
        if ($compte === null) {
            $this->introuvable();
        }

        try {
            UtilisateurService::reinitialiserMotDePasse((int) $compte['id'], (string) ($_POST['mot_de_passe'] ?? ''), Auth::utilisateur());
            flash('success', 'Mot de passe modifié. Communiquez-le à l\'utilisateur.');
        } catch (DomainException $e) {
            $this->signaler($e);
        }
        $this->rediriger('/admin/utilisateurs/' . $compte['id']);
    }
    

    /** Une exception peut contenir plusieurs erreurs (une par ligne) : on les affiche séparément. */
    private function signaler(DomainException $e): void
    {
        foreach (explode("\n", $e->getMessage()) as $erreur) {
            flash('error', $erreur);
        }
    }
}