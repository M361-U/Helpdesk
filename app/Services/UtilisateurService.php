<?php
declare(strict_types=1);

namespace App\Services;

use App\Models\Audit;
use App\Models\Utilisateur;
use DomainException;

/** Règles métier de la gestion des comptes (création, modification, mot de passe). */
final class UtilisateurService
{
    /** Les erreurs de validation sont renvoyées dans UNE exception, une par ligne. */
    public static function creer(array $saisie, string $motDePasse, array $admin): int
    {
        [$erreurs, $d] = self::valider($saisie, null);
        if (($erreur = self::erreurMotDePasse($motDePasse)) !== null) {
            $erreurs[] = $erreur;
        }
        if ($erreurs) {
            throw new DomainException(implode("\n", $erreurs));
        }

        $id = Utilisateur::creer($d, password_hash($motDePasse, PASSWORD_BCRYPT));
        Audit::enregistrer('CREATION_UTILISATEUR', $d['email'] . ' (' . self::codeRole($d['role_id']) . ')', $admin['id']);
        return $id;
    }

    public static function modifier(int $id, array $saisie, bool $actif, array $admin): void
    {
        $actuel = Utilisateur::trouverParId($id) ?? throw new DomainException('Utilisateur introuvable.');
        [$erreurs, $d] = self::valider($saisie, $id);

        $nouveauRole = self::codeRole($d['role_id']);

        // Un administrateur ne peut pas se retirer ses propres droits ni se désactiver
        if ($id === $admin['id'] && ($nouveauRole !== 'admin' || !$actif)) {
            $erreurs[] = 'Vous ne pouvez pas retirer vos propres droits d\'administrateur ni désactiver votre compte.';
        }
        // Il doit toujours rester au moins un administrateur actif
        if ($actuel['role'] === 'admin' && (int) $actuel['actif'] === 1 && ($nouveauRole !== 'admin' || !$actif)
            && Utilisateur::compterAdminsActifs() <= 1) {
            $erreurs[] = 'Il doit rester au moins un administrateur actif.';
        }
        // Un technicien qui a des tickets en cours ne peut pas quitter son poste sans les avoir réassignés
        if ($actuel['role'] === 'technicien') {
            $quitte = !$actif || $nouveauRole !== 'technicien' || (int) $d['domaine_id'] !== (int) $actuel['domaine_id'];
            $enCours = Utilisateur::ticketsEnCours($id);
            if ($quitte && $enCours > 0) {
                $erreurs[] = 'Ce technicien traite encore ' . $enCours . ' ticket(s) : réassignez-les avant de le désactiver ou de changer son rôle ou son domaine.';
            }
        }

        if ($erreurs) {
            throw new DomainException(implode("\n", $erreurs));
        }

        Utilisateur::modifier($id, $d, $actif);
        Audit::enregistrer('MODIFICATION_UTILISATEUR', $actuel['email'] . ($actif ? '' : ' (désactivé)'), $admin['id']);
        if ((int) $actuel['actif'] === 1 && !$actif) {
            Audit::enregistrer('DESACTIVATION_UTILISATEUR', $actuel['email'], $admin['id']);
        }
    }

    public static function reinitialiserMotDePasse(int $id, string $motDePasse, array $admin): void
    {
        $actuel = Utilisateur::trouverParId($id) ?? throw new DomainException('Utilisateur introuvable.');
        if (($erreur = self::erreurMotDePasse($motDePasse)) !== null) {
            throw new DomainException($erreur);
        }
        Utilisateur::changerMotDePasse($id, password_hash($motDePasse, PASSWORD_BCRYPT));
        // Jamais le mot de passe lui-même dans le journal
        Audit::enregistrer('REINITIALISATION_MOT_DE_PASSE', $actuel['email'], $admin['id']);
    }

    

    /** Politique de mot de passe : 8 caractères minimum, au moins une lettre et un chiffre. */
    public static function erreurMotDePasse(string $mdp): ?string
    {
        if (strlen($mdp) < 8) {
            return 'Le mot de passe doit contenir au moins 8 caractères.';
        }
        if (strlen($mdp) > 72) {                  // bcrypt ignore tout ce qui dépasse 72 octets
            return 'Le mot de passe ne doit pas dépasser 72 caractères.';
        }
        if (!preg_match('/[A-Za-z]/', $mdp) || !preg_match('/\d/', $mdp)) {
            return 'Le mot de passe doit contenir au moins une lettre et un chiffre.';
        }
        return null;
    }

    /** @return array{0: string[], 1: array} [erreurs, données nettoyées] */
    private static function valider(array $saisie, ?int $idExistant): array
    {
        $erreurs = [];
        $nom     = trim((string) ($saisie['nom'] ?? ''));
        $prenom  = trim((string) ($saisie['prenom'] ?? ''));
        $email   = mb_strtolower(trim((string) ($saisie['email'] ?? '')));
        $service = trim((string) ($saisie['service'] ?? ''));
        $roleId  = (int) ($saisie['role_id'] ?? 0);
        $domaine = (int) ($saisie['domaine_id'] ?? 0);

        if ($nom === '' || mb_strlen($nom) > 80 || $prenom === '' || mb_strlen($prenom) > 80) {
            $erreurs[] = 'Le nom et le prénom sont obligatoires (80 caractères maximum).';
        }
        if (!filter_var($email, FILTER_VALIDATE_EMAIL) || mb_strlen($email) > 150) {
            $erreurs[] = 'L\'adresse e-mail n\'est pas valide.';
        } elseif (Utilisateur::emailExiste($email, $idExistant)) {
            $erreurs[] = 'Cette adresse e-mail est déjà utilisée par un autre compte.';
        }
        if (mb_strlen($service) > 100) {
            $erreurs[] = 'Le service ne doit pas dépasser 100 caractères.';
        }

        $codeRole = self::codeRole($roleId);
        if ($codeRole === null) {
            $erreurs[] = 'Veuillez choisir un rôle valide.';
        }

        // Le domaine n'a de sens que pour un technicien (c'est lui qui sert au routage)
        $domaineId = null;
        if ($codeRole === 'technicien') {
            if (!in_array($domaine, array_map('intval', array_column(Utilisateur::domaines(), 'id')), true)) {
                $erreurs[] = 'Un technicien doit être rattaché à un domaine (Réseau, Système ou Applicatif).';
            } else {
                $domaineId = $domaine;
            }
        }

        return [$erreurs, [
            'nom' => $nom, 'prenom' => $prenom, 'email' => $email,
            'service' => $service !== '' ? $service : null,
            'role_id' => $roleId, 'domaine_id' => $domaineId,
        ]];
    }

    private static function codeRole(int $roleId): ?string
    {
        foreach (Utilisateur::roles() as $role) {
            if ((int) $role['id'] === $roleId) {
                return $role['code'];
            }
        }
        return null;
    }
}