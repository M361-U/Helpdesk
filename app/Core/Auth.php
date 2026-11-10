<?php
declare(strict_types=1);

namespace App\Core;

final class Auth
{
    private const MAX_TENTATIVES = 5;
    private const DUREE_BLOCAGE  = 300; // secondes
    private const VERIFICATION_TOUTES_LES = 60; // secondes : le compte est revérifié en base
    public static function start(): void
    {
        if (session_status() === PHP_SESSION_NONE) {
            session_set_cookie_params([
                'lifetime' => 0,
                'path'     => '/',
                'httponly' => true,       // inaccessible au JavaScript
                'samesite' => 'Lax',      // limite les attaques CSRF
            ]);
            session_start();
        }
    }

    public static function connecter(array $utilisateur): void
    {
        session_regenerate_id(true); // empêche la fixation de session
        $_SESSION['utilisateur'] = [    
            'id'         => (int) $utilisateur['id'],
            'nom'        => $utilisateur['nom'],
            'prenom'     => $utilisateur['prenom'],
            'email'      => $utilisateur['email'],
            'role'       => $utilisateur['role'],
            'domaine_id' => $utilisateur['domaine_id'] !== null ? (int) $utilisateur['domaine_id'] : null,
        ];
        $_SESSION['verifie_le'] = time();
    }

    public static function deconnecter(): void
    {
        $_SESSION = [];                // on efface toutes les données de session
        session_regenerate_id(true);   // nouvel identifiant : l'ancien devient inutilisable
    }

    public static function estConnecte(): bool
    {
        return isset($_SESSION['utilisateur']);
    }

    public static function utilisateur(): ?array
    {
        return $_SESSION['utilisateur'] ?? null;
    }

    public static function exigerConnexion(): void
    {
        if (!self::estConnecte()) {
            flash('error', 'Veuillez vous connecter pour accéder à cette page.');
            header('Location: ' . url('/login'));
            exit;
        }
        if (!self::compteValide()) {
            self::deconnecter();
            flash('error', 'Votre session a été désactivé  ou n\'existe plus. Contactez l\'administrateur.');
            header('Location: ' . url('/login'));
            exit;
        }

    }


     public static function compteValide(): bool
    {
        $u = self::utilisateur();
        if ($u === null) {
            return false;
        }
        if (time() - (int) ($_SESSION['verifie_le'] ?? 0) < self::VERIFICATION_TOUTES_LES) {
            return true;
        }

        $compte = \App\Models\Utilisateur::trouverParId($u['id']);
        if ($compte === null || (int) $compte['actif'] !== 1) {
            self::deconnecter();
            return false;
        }
        $_SESSION['utilisateur']['role']       = $compte['role'];
        $_SESSION['utilisateur']['prenom']     = $compte['prenom'];
        $_SESSION['utilisateur']['nom']        = $compte['nom'];
        $_SESSION['utilisateur']['domaine_id'] = $compte['domaine_id'] !== null ? (int) $compte['domaine_id'] : null;
        $_SESSION['verifie_le'] = time();
        return true;
    }

    /** Autorise uniquement certains rôles : Auth::exigerRole('admin', 'technicien') */
    public static function exigerRole(string ...$roles): void
    {
        self::exigerConnexion();
        if (!in_array(self::utilisateur()['role'], $roles, true)) {
            http_response_code(403);
            exit('403 : accès refusé');
        }
    }

    // ----- Protection CSRF -----
    public static function csrfToken(): string
    {
        if (empty($_SESSION['csrf'])) {
            $_SESSION['csrf'] = bin2hex(random_bytes(32));
        }
        return $_SESSION['csrf'];
    }

    public static function verifierCsrf(): void
    {
        $envoye = $_POST['_csrf'] ?? '';
        if (!is_string($envoye) || !hash_equals(self::csrfToken(), $envoye)) {
            http_response_code(419);
            exit('Session expirée ou requête invalide. Rechargez la page.');
        }
    }

    // ----- Limitation des tentatives de connexion -----
    public static function tropDeTentatives(): bool
    {
        $t = $_SESSION['tentatives'] ?? null;
        if ($t === null) {
            return false;
        }
        if (time() - $t['debut'] > self::DUREE_BLOCAGE) {
            unset($_SESSION['tentatives']);
            return false;
        }
        return $t['n'] >= self::MAX_TENTATIVES;
    }

    public static function enregistrerEchec(): void
    {
        $t = $_SESSION['tentatives'] ?? ['n' => 0, 'debut' => time()];
        $t['n']++;
        $_SESSION['tentatives'] = $t;
    }

    public static function reinitialiserTentatives(): void
    {
        unset($_SESSION['tentatives']);
    }
}