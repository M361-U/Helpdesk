<?php
declare(strict_types=1);

namespace App\Controllers;

use App\Core\Auth;
use App\Core\Controller;
use App\Models\Audit;
use App\Models\Utilisateur;

final class AuthController extends Controller
{
    public function afficherConnexion(): void
    {
        if (Auth::estConnecte()) {
            $this->rediriger('/dashboard');
        }
        $this->vue('auth/login', [ 
            'titre'  => 'Connexion'], 'auth');
    }

    public function connecter(): void
    {
        Auth::verifierCsrf();

        if (Auth::tropDeTentatives()) {
            flash('error', 'Trop de tentatives. Réessayez dans 5 minutes.');
            $this->rediriger('/login');
        }

        $email = mb_strtolower(trim((string) ($_POST['email'] ?? '')));
        $mdp   = (string) ($_POST['mot_de_passe'] ?? '');

        $utilisateur = Utilisateur::trouverParEmail($email);

        if ($utilisateur && password_verify($mdp, $utilisateur['mot_de_passe']) && (int) $utilisateur['actif'] !== 1) {
            // Mot de passe correct, mais le compte ne peut pas encore (ou plus) servir : on explique pourquoi
            flash('error', match ($utilisateur['statut_compte']) {
                'email_a_verifier' => 'Confirmez d\'abord votre adresse e-mail grâce au lien qui vous a été envoyé.',
                'en_attente'       => 'Votre adresse est confirmée. Un administrateur doit encore valider votre compte.',
                'refuse'           => 'Votre demande de compte a été refusée. Rapprochez-vous de la DSI.',
                default            => 'Ce compte est désactivé. Rapprochez-vous de la DSI.',
            });
            $this->rediriger('/login');
        }

        if ($utilisateur && password_verify($mdp, $utilisateur['mot_de_passe'])) {
            Auth::reinitialiserTentatives();
            Auth::connecter($utilisateur);
            Audit::enregistrer('CONNEXION', null, (int) $utilisateur['id']);
            $this->rediriger('/dashboard');
        }

        Auth::enregistrerEchec();
        Audit::enregistrer('ECHEC_CONNEXION', $email);
        // Message volontairement vague : on ne révèle pas si l'e-mail existe
        flash('error', 'Identifiants incorrects.');
        $this->rediriger('/login');
    }

    public function deconnecter(): void
    {
        Auth::verifierCsrf();
        Audit::enregistrer('DECONNEXION');
        Auth::deconnecter();
        flash('success', 'Vous êtes déconnecté.');
        $this->rediriger('/login');
    }
}