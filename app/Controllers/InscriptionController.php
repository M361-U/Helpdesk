<?php
declare(strict_types=1);

namespace App\Controllers;

use App\Core\Auth;
use App\Core\Controller;
use App\Models\Utilisateur;
use App\Services\InscriptionService;
use DomainException;

/** Inscription en libre-service : le compte reste inactif tant qu'il n'est pas contrôlé. */
final class InscriptionController extends Controller
{
    private const MAX_ENVOIS = 5;          // formulaires envoyés ...
    private const FENETRE    = 1800;       // ... en 30 minutes (anti-abus et anti-devinette du code)

    public function formulaire(): void
    {
        if (Auth::estConnecte()) {
            $this->rediriger('/dashboard');
        }
        $ancien = $_SESSION['ancien_inscription'] ?? [];
        unset($_SESSION['ancien_inscription']);

        $this->vue('auth/inscription', [
            'titre'    => 'Créer un compte',
            'domaines' => Utilisateur::domaines(),
            'ancien'   => $ancien,
        ], 'auth');
    }

    public function enregistrer(): void
    {
        Auth::verifierCsrf();

        // Champ piège : invisible pour une personne, rempli par les robots
        if (trim((string) ($_POST['site_web'] ?? '')) !== '') {
            $this->rediriger('/inscription/verification');
        }

        if ($this->tropDEnvois()) {
            flash('error', 'Trop de tentatives. Réessayez dans 30 minutes.');
            $this->rediriger('/inscription');
        }

        try {
            $resultat = InscriptionService::inscrire($_POST);
        } catch (DomainException $e) {
            foreach (explode("\n", $e->getMessage()) as $erreur) {
                flash('error', $erreur);
            }
            // On garde la saisie, sauf les mots de passe et le code
            $_SESSION['ancien_inscription'] = array_diff_key($_POST, [
                'mot_de_passe' => 1, 'mot_de_passe_confirmation' => 1, 'code' => 1, '_csrf' => 1,
            ]);
            $this->rediriger('/inscription');
        }

        $_SESSION['inscription_email'] = $resultat['utilisateur']['email'];
        $this->memoriserCodeDemo($resultat['code']);
        $this->rediriger('/inscription/verification');
    }

    /** Page où l'on saisit le code reçu par e-mail. */
    public function formulaireCode(): void
    {
        $email = (string) ($_SESSION['inscription_email'] ?? '');
        $demo  = $_SESSION['inscription_code_demo'] ?? null;

        $this->vue('auth/inscription_code', [
            'titre'    => 'Saisissez votre code',
            'email'    => $email,
            'codeDemo' => $demo,
            'validite' => (int) (InscriptionService::VALIDITE_CODE / 60),
        ], 'auth');
    }

    public function verifierCode(): void
    {
        Auth::verifierCsrf();
        $email = (string) ($_POST['email'] ?? $_SESSION['inscription_email'] ?? '');

        try {
            InscriptionService::verifierCode($email, (string) ($_POST['code'] ?? ''));
        } catch (DomainException $e) {
            $_SESSION['inscription_email'] = mb_strtolower(trim($email));
            flash('error', $e->getMessage());
            $this->rediriger('/inscription/verification');
        }

        unset($_SESSION['inscription_code_demo'], $_SESSION['inscription_email']);
        $this->rediriger('/inscription/terminee');
    }

    public function renvoyerCode(): void
    {
        Auth::verifierCsrf();
        $email = (string) ($_POST['email'] ?? $_SESSION['inscription_email'] ?? '');
        $_SESSION['inscription_email'] = mb_strtolower(trim($email));

        try {
            $code = InscriptionService::renvoyerCode($email);
            $this->memoriserCodeDemo((string) $code);
            flash('success', 'Un nouveau code vient d\'être envoyé.');
        } catch (DomainException $e) {
            flash('error', $e->getMessage());
        }
        $this->rediriger('/inscription/verification');
    }

    /** Dernière étape côté utilisateur : il attend la décision de l'administrateur. */
    public function terminee(): void
    {
        $this->vue('auth/inscription_terminee', ['titre' => 'Adresse confirmée'], 'auth');
    }

    /** Mode démonstration : sans envoi réel d'e-mail, on affiche le code à l'écran. */
    private function memoriserCodeDemo(string $code): void
    {
        if (!config('mail.actif', false) && config('inscription.afficher_code_demo', false)) {
            $_SESSION['inscription_code_demo'] = $code;
        }
    }

    /** Compte les envois du formulaire dans la session (5 en 30 minutes). */
    private function tropDEnvois(): bool
    {
        $maintenant = time();
        $envois = array_filter($_SESSION['inscription_envois'] ?? [], fn(int $t): bool => $maintenant - $t < self::FENETRE);
        if (count($envois) >= self::MAX_ENVOIS) {
            $_SESSION['inscription_envois'] = $envois;
            return true;
        }
        $envois[] = $maintenant;
        $_SESSION['inscription_envois'] = $envois;
        return false;
    }
}