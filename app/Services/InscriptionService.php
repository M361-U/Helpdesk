<?php
declare(strict_types=1);

namespace App\Services;

use App\Models\Audit;
use App\Models\Utilisateur;
use DomainException;


final class InscriptionService
{
    public const PROFILS = ['employe', 'technicien'];          
    public const VALIDITE_CODE = 15 * 60;                      
    public const ESSAIS_MAX = 5;                               
    public const DELAI_RENVOI = 60;                                   
    private const VALIDITE_LIEN = 24 * 3600;                   

    /**
     * @return array{id: int, lien: string, utilisateur: array}
     * @throws DomainException avec une erreur par ligne
     */
    public static function inscrire(array $saisie): array
    {
        $erreurs = [];
        $prenom  = trim((string) ($saisie['prenom'] ?? ''));
        $nom     = trim((string) ($saisie['nom'] ?? ''));
        $email   = mb_strtolower(trim((string) ($saisie['email'] ?? '')));
        $profil  = (string) ($saisie['profil'] ?? '');
        $service = trim((string) ($saisie['service'] ?? ''));
        $domaine = (int) ($saisie['domaine_id'] ?? 0);
        $code    = trim((string) ($saisie['code'] ?? ''));
        $mdp     = (string) ($saisie['mot_de_passe'] ?? '');

        if ($prenom === '' || $nom === '' || mb_strlen($prenom) > 80 || mb_strlen($nom) > 80) {
            $erreurs[] = 'Le nom et le prénom sont obligatoires (80 caractères maximum).';
        }

       
        $existant = null;
        if (!filter_var($email, FILTER_VALIDATE_EMAIL) || mb_strlen($email) > 150) {
            $erreurs[] = 'L\'adresse e-mail n\'est pas valide.';
        } else {
            $autorises = (array) config('inscription.domaines_email', []);
            $domaineEmail = substr(strrchr($email, '@'), 1);
            if ($autorises && !in_array($domaineEmail, array_map('strtolower', $autorises), true)) {
                $erreurs[] = 'Seules les adresses professionnelles (' . implode(', ', array_map(fn($d) => '@' . $d, $autorises)) . ') sont acceptées.';
            }
            $existant = Utilisateur::trouverParEmailTous($email);
            
            if ($existant !== null && $existant['statut_compte'] !== 'email_a_verifier') {
                $erreurs[] = 'Cette adresse e-mail est déjà utilisée.';
            }
        }

        
        if (!in_array($profil, self::PROFILS, true)) {
            $erreurs[] = 'Veuillez choisir votre profil : employé ou technicien.';
        } else {
            $attendu = (string) config('inscription.codes.' . $profil, '');
            if ($attendu !== '' && !hash_equals(mb_strtoupper($attendu), mb_strtoupper($code))) {
                $erreurs[] = 'Le code d\'accréditation ' . ($profil === 'technicien' ? 'technicien' : 'employé')
                    . ' est incorrect. Demandez-le à la DSI.';
            }
        }

        
        $domaineId = null;
        if ($profil === 'technicien') {
            if (!in_array($domaine, array_map('intval', array_column(Utilisateur::domaines(), 'id')), true)) {
                $erreurs[] = 'Un technicien doit indiquer son domaine (Réseau, Système ou Applicatif).';
            } else {
                $domaineId = $domaine;
            }
            $service = $service !== '' ? $service : 'DSI';
        } elseif ($profil === 'employe' && $service === '') {
            $erreurs[] = 'Indiquez votre service (par exemple : Comptabilité).';
        }
        if (mb_strlen($service) > 100) {
            $erreurs[] = 'Le service ne doit pas dépasser 100 caractères.';
        }

        
        if (($e = UtilisateurService::erreurMotDePasse($mdp)) !== null) {
            $erreurs[] = $e;
        } elseif (!hash_equals($mdp, (string) ($saisie['mot_de_passe_confirmation'] ?? ''))) {
            $erreurs[] = 'Les deux mots de passe ne sont pas identiques.';
        }

        if ($erreurs) {
            throw new DomainException(implode("\n", $erreurs));
        }

        
        $roleId = null;
        foreach (Utilisateur::roles() as $r) {
            if ($r['code'] === $profil) {
                $roleId = (int) $r['id'];
            }
        }
        $jeton = bin2hex(random_bytes(32));                    
               $code6 = self::nouveauCode();
        $id = Utilisateur::enregistrerInscription(
            $existant !== null ? (int) $existant['id'] : null,
            ['nom' => $nom, 'prenom' => $prenom, 'email' => $email, 'role_id' => $roleId,
             'domaine_id' => $domaineId, 'service' => $service !== '' ? $service : null],
            password_hash($mdp, PASSWORD_BCRYPT),
            password_hash($code6, PASSWORD_BCRYPT),            
            date('Y-m-d H:i:s', time() + self::VALIDITE_CODE)
        );

        Audit::enregistrer('INSCRIPTION', $email . ' (' . $profil . ')', $id);

        $utilisateur = ['id' => $id, 'prenom' => $prenom, 'nom' => $nom, 'email' => $email, 'profil' => $profil];
        NotificationService::inscriptionVerification($utilisateur, $code6);

        return ['id' => $id, 'code' => $code6, 'utilisateur' => $utilisateur];
    }

    /**
     * Contrôle le code saisi. En cas de succès, le compte passe « en attente » de l'administrateur.
     * @throws DomainException message à afficher à la personne
     */
    public static function verifierCode(string $email, string $code): array
    {
        $email = mb_strtolower(trim($email));
        $code  = preg_replace('/\s+/', '', $code) ?? '';
        $generique = 'Code incorrect ou expiré. Vérifiez le code reçu par e-mail ou demandez-en un nouveau.';

        $compte = Utilisateur::trouverEnVerification($email);
        if ($compte === null) {
            throw new DomainException($generique);
        }
        if ((int) $compte['code_essais'] >= self::ESSAIS_MAX) {
            throw new DomainException('Trop d\'essais incorrects. Demandez un nouveau code.');
        }
        if ($compte['jeton_expire_le'] === null || strtotime((string) $compte['jeton_expire_le']) < time()) {
            throw new DomainException('Ce code a expiré. Demandez un nouveau code.');
        }
        if (!preg_match('/^\d{6}$/', $code) || !password_verify($code, (string) $compte['jeton_hash'])) {
            Utilisateur::ajouterEssai((int) $compte['id']);
            $reste = self::ESSAIS_MAX - (int) $compte['code_essais'] - 1;
            throw new DomainException($reste > 0
                ? "Code incorrect. Il vous reste {$reste} essai(s)."
                : 'Code incorrect. Demandez un nouveau code.');
        }

        Utilisateur::confirmerEmail((int) $compte['id']);
        Audit::enregistrer('EMAIL_CONFIRME', $compte['email'], (int) $compte['id']);
        NotificationService::inscriptionEnAttente($compte);
        return $compte;
    }

    /**
     * Envoie un nouveau code (60 s minimum entre deux envois).
     * @return string|null le code (pour l'affichage en mode démonstration)
     * @throws DomainException
     */
    public static function renvoyerCode(string $email): ?string
    {
        $email  = mb_strtolower(trim($email));
        $compte = Utilisateur::trouverEnVerification($email);
        if ($compte === null) {
            throw new DomainException('Aucune inscription en attente de confirmation pour cette adresse.');
        }
        
        $envoyeA = strtotime((string) $compte['jeton_expire_le']) - self::VALIDITE_CODE;
        $attente = self::DELAI_RENVOI - (time() - $envoyeA);
        if ($attente > 0) {
            throw new DomainException("Patientez encore {$attente} seconde(s) avant de demander un nouveau code.");
        }
        $code6 = self::nouveauCode();
        Utilisateur::remplacerCode((int) $compte['id'], password_hash($code6, PASSWORD_BCRYPT),
            date('Y-m-d H:i:s', time() + self::VALIDITE_CODE));
        NotificationService::inscriptionVerification(
            ['id' => (int) $compte['id'], 'prenom' => $compte['prenom'], 'email' => $compte['email'], 'profil' => $compte['role']], $code6);
        return $code6;
    }

    
    private static function nouveauCode(): string
    {
        return str_pad((string) random_int(0, 999999), 6, '0', STR_PAD_LEFT);
    }
}