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
use PDOException;

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

    public function supprimerNotification(string $id): void
    {
        Auth::exigerRole('admin');
        Auth::verifierCsrf();

        if (!ctype_digit($id) || (int) $id < 1) {
            $this->introuvable();
        }

        $supprime = Notification::supprimer((int) $id);
        flash($supprime ? 'success' : 'error', $supprime ? 'E-mail supprimé.' : 'E-mail introuvable.');
        $this->rediriger('/admin/notifications');
    }

    public function supprimerNotificationsSelectionnees(): void
    {
        Auth::exigerRole('admin');
        Auth::verifierCsrf();

        $ids = $this->idsSelectionnes();
        if (!$ids) {
            flash('error', 'Sélectionnez au moins un e-mail à supprimer.');
        } else {
            $nombre = Notification::supprimerPlusieurs($ids);
            flash('success', $nombre . ' e-mail(s) supprimé(s).');
        }
        $this->rediriger('/admin/notifications');
    }

    public function supprimerToutesNotifications(): void
    {
        Auth::exigerRole('admin');
        Auth::verifierCsrf();

        $nombre = Notification::supprimerTout();
        flash('success', $nombre . ' e-mail(s) supprimé(s).');
        $this->rediriger('/admin/notifications');
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

    public function supprimerEntreeAudit(string $id): void
    {
        Auth::exigerRole('admin');
        Auth::verifierCsrf();

        if (!ctype_digit($id) || (int) $id < 1) {
            $this->introuvable();
        }

        $supprime = Audit::supprimer((int) $id);
        flash($supprime ? 'success' : 'error', $supprime ? 'Entrée du journal supprimée.' : 'Entrée du journal introuvable.');
        $this->rediriger('/admin/audit');
    }

    public function supprimerEntreesAuditSelectionnees(): void
    {
        Auth::exigerRole('admin');
        Auth::verifierCsrf();

        $ids = $this->idsSelectionnes();
        if (!$ids) {
            flash('error', 'Sélectionnez au moins une entrée à supprimer.');
        } else {
            $nombre = Audit::supprimerPlusieurs($ids);
            flash('success', $nombre . ' entrée(s) du journal supprimée(s).');
        }
        $this->rediriger('/admin/audit');
    }

    public function supprimerToutAudit(): void
    {
        Auth::exigerRole('admin');
        Auth::verifierCsrf();

        $nombre = Audit::supprimerTout();
        flash('success', $nombre . ' entrée(s) du journal supprimée(s).');
        $this->rediriger('/admin/audit');
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

    public function supprimerUtilisateur(string $id): void
    {
        Auth::exigerRole('admin');
        Auth::verifierCsrf();

        if (!ctype_digit($id) || (int) $id < 1) {
            $this->introuvable();
        }

        $erreur = $this->supprimerCompte((int) $id, Auth::utilisateur(), true);
        if ($erreur === 'Compte introuvable.') {
            $this->introuvable();
        }
        flash($erreur === null ? 'success' : 'error', $erreur ?? 'Utilisateur supprimé.');
        $this->rediriger('/admin/utilisateurs');
    }

    public function supprimerUtilisateursSelectionnes(): void
    {
        Auth::exigerRole('admin');
        Auth::verifierCsrf();

        $ids = $this->idsSelectionnes();
        if (!$ids) {
            flash('error', 'Sélectionnez au moins un utilisateur à supprimer.');
            $this->rediriger('/admin/utilisateurs');
        }

        $this->supprimerComptesEnLot($ids);
        $this->rediriger('/admin/utilisateurs');
    }

    public function supprimerTousUtilisateurs(): void
    {
        Auth::exigerRole('admin');
        Auth::verifierCsrf();

        $ids = array_map(static fn(array $u): int => (int) $u['id'], Utilisateur::tous());
        $this->supprimerComptesEnLot($ids);
        $this->rediriger('/admin/utilisateurs');
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

    private function idsSelectionnes(): array
    {
        $soumis = $_POST['ids'] ?? [];
        if (!\is_array($soumis)) {
            return [];
        }

        $ids = [];
        foreach ($soumis as $id) {
            if (\is_string($id) && ctype_digit($id) && (int) $id > 0) {
                $ids[] = (int) $id;
            }
        }
        return array_values(array_unique($ids));
    }

    private function supprimerComptesEnLot(array $ids): void
    {
        $admin = Auth::utilisateur();
        $supprimes = 0;
        $raisons = [];

        foreach (array_unique($ids) as $id) {
            $raison = $this->supprimerCompte((int) $id, $admin, false);
            if ($raison === null) {
                $supprimes++;
            } else {
                $raisons[$raison] = ($raisons[$raison] ?? 0) + 1;
            }
        }

        if ($supprimes > 0) {
            Audit::enregistrer('SUPPRESSION_UTILISATEURS', $supprimes . ' compte(s) supprimé(s) en lot.', (int) $admin['id']);
            flash('success', $supprimes . ' utilisateur(s) supprimé(s).');
        }
        if ($raisons) {
            $details = [];
            foreach ($raisons as $raison => $nombre) {
                $details[] = $nombre . ' compte(s) conservé(s) : ' . $raison;
            }
            flash('error', implode(' ', $details));
        }
        if ($supprimes === 0 && !$raisons) {
            flash('error', 'Aucun utilisateur à supprimer.');
        }
    }

    private function supprimerCompte(int $id, array $admin, bool $journaliser): ?string
    {
        $compte = Utilisateur::trouverParId($id);
        if ($compte === null) {
            return 'Compte introuvable.';
        }
        if ((int) $admin['id'] === (int) $compte['id']) {
            return 'Vous ne pouvez pas supprimer votre propre compte.';
        }
        if ($compte['role'] === 'admin' && (int) $compte['actif'] === 1
            && Utilisateur::compterAdminsActifs() <= 1) {
            return 'Il doit rester au moins un administrateur actif.';
        }
        if (Utilisateur::aDesTickets((int) $compte['id'])) {
            return 'Ce compte est lié à des tickets : réassignez ou supprimez les tickets avant de supprimer le compte.';
        }

        try {
            if (!Utilisateur::supprimer((int) $compte['id'])) {
                return 'Compte introuvable.';
            }
        } catch (PDOException $e) {
            error_log('Suppression utilisateur : ' . $e->getMessage());
            return 'Impossible de supprimer ce compte car il est encore lié à des données existantes.';
        }

        if ($journaliser) {
            Audit::enregistrer('SUPPRESSION_UTILISATEUR', $compte['email'], (int) $admin['id']);
        }
        return null;
    }
}