<?php
declare(strict_types=1);

namespace App\Services;

use App\Core\Mailer;
use App\Models\Utilisateur;
use Throwable;

/**
 * Rédige et envoie les e-mails liés aux tickets.
 * Chaque méthode est protégée : une erreur ici ne doit jamais faire échouer l'action principale.
 */
final class NotificationService
{
    /** Création : accusé de réception au demandeur, alerte au technicien (ou aux admins si non assigné). */
    public static function ticketCree(array $ticket, ?array $technicien): void
    {
        self::proteger(function () use ($ticket, $technicien): void {
            $ref = $ticket['reference'];

            $prise = $technicien !== null
                ? 'Il a été assigné à ' . $technicien['prenom'] . ' ' . $technicien['nom'] . '.'
                : 'Il sera bientôt attribué à un technicien par l\'administrateur.';
            self::envoyer($ticket['dem_email'], "[$ref] Votre demande a bien été enregistrée",
                self::bonjour($ticket['dem_prenom'])
                . "Votre ticket « {$ticket['titre']} » a bien été enregistré.\n{$prise}\n"
                . 'Échéance de traitement prévue : ' . date_fr($ticket['echeance_sla']) . ".\n"
                . self::lien($ticket));

            if ($technicien !== null) {
                self::envoyer($technicien['email'], "[$ref] Nouveau ticket à traiter ({$ticket['urgence']})",
                    self::bonjour($technicien['prenom'])
                    . "Un ticket vous a été assigné automatiquement :\n"
                    . "- Titre : {$ticket['titre']}\n- Domaine : {$ticket['domaine']}\n"
                    . '- Urgence : ' . libelle_urgence($ticket['urgence']) . "\n"
                    . '- À traiter avant : ' . date_fr($ticket['echeance_sla']) . "\n"
                    . self::lien($ticket));
            } else {
                foreach (Utilisateur::administrateurs() as $admin) {
                    self::envoyer($admin['email'], "[$ref] Ticket non assigné : action requise",
                        self::bonjour($admin['prenom'])
                        . "Aucun technicien disponible pour le ticket « {$ticket['titre']} » (domaine {$ticket['domaine']}).\n"
                        . "Merci de l'assigner manuellement.\n" . self::lien($ticket));
                }
            }
        });
    }

    /** Assignation manuelle : le nouveau technicien est prévenu, et le demandeur aussi. */
    public static function ticketAssigne(array $ticket, array $technicien): void
    {
        self::proteger(function () use ($ticket, $technicien): void {
            $ref = $ticket['reference'];
            self::envoyer($technicien['email'], "[$ref] Ticket assigné par l'administrateur",
                self::bonjour($technicien['prenom'])
                . "Le ticket « {$ticket['titre']} » vous a été assigné.\n"
                . '- Urgence : ' . libelle_urgence($ticket['urgence']) . "\n"
                . '- À traiter avant : ' . date_fr($ticket['echeance_sla']) . "\n" . self::lien($ticket));

            self::envoyer($ticket['dem_email'], "[$ref] Votre ticket est pris en charge",
                self::bonjour($ticket['dem_prenom'])
                . "Votre ticket « {$ticket['titre']} » est pris en charge par {$technicien['prenom']} {$technicien['nom']}.\n"
                . self::lien($ticket));
        });
    }

        /** Ticket supprimé : on prévient le technicien assigné et le demandeur, sauf celui qui a supprimé. */
    public static function ticketSupprime(array $ticket, array $acteur): void
    {
        self::proteger(function () use ($ticket, $acteur): void {
            $ref = $ticket['reference'];
            $qui = $acteur['prenom'] . ' ' . $acteur['nom'];
            if ($ticket['technicien_id'] !== null && (int) $ticket['technicien_id'] !== (int) $acteur['id']) {
                self::envoyer($ticket['tech_email'], "[$ref] Ticket supprimé",
                    self::bonjour($ticket['tech_prenom'])
                    . "Le ticket « {$ticket['titre']} » a été supprimé par {$qui}. Il n'y a plus rien à traiter.\n");
            }
            if ((int) $ticket['demandeur_id'] !== (int) $acteur['id']) {
                self::envoyer($ticket['dem_email'], "[$ref] Votre ticket a été supprimé",
                    self::bonjour($ticket['dem_prenom'])
                    . "Votre ticket « {$ticket['titre']} » a été supprimé par {$qui}.\n"
                    . "Si c'est une erreur, déclarez-le à nouveau ou contactez la DSI.\n");
            }
        });
    }

    
    public static function statutChange(array $ticket, string $nouveau, array $acteur): void
    {
        self::proteger(function () use ($ticket, $nouveau, $acteur): void {
            $ref = $ticket['reference'];
            $phrase = match ($nouveau) {
                'en_cours'   => 'Le traitement de votre ticket a commencé.',
                'en_attente' => 'Votre ticket est en attente (information ou intervention extérieure nécessaire).',
                'resolu'     => 'Votre ticket a été résolu. Merci de confirmer la résolution (clôture) ou de le rouvrir si le problème persiste.',
                'cloture'    => 'Le ticket a été clôturé.',
                default      => 'Le statut du ticket a changé.',
            };
            $sujet = "[$ref] Statut : " . libelle_statut($nouveau);

            foreach (self::interesses($ticket, $acteur) as $destinataire) {
                self::envoyer($destinataire['email'], $sujet,
                    self::bonjour($destinataire['prenom'])
                    . "Ticket « {$ticket['titre']} »\n{$phrase}\n"
                    . 'Nouveau statut : ' . libelle_statut($nouveau) . ' (modifié par ' . $acteur['prenom'] . ' ' . $acteur['nom'] . ").\n"
                    . self::lien($ticket));
            }
        });
    }

    /** Nouveau commentaire : l'autre partie est prévenue. */
    public static function commentaire(array $ticket, array $auteur, string $contenu): void
    {
        self::proteger(function () use ($ticket, $auteur, $contenu): void {
            $ref = $ticket['reference'];
            foreach (self::interesses($ticket, $auteur) as $destinataire) {
                self::envoyer($destinataire['email'], "[$ref] Nouveau commentaire",
                    self::bonjour($destinataire['prenom'])
                    . $auteur['prenom'] . ' ' . $auteur['nom'] . " a commenté le ticket « {$ticket['titre']} » :\n\n"
                    . '« ' . mb_substr($contenu, 0, 500) . (mb_strlen($contenu) > 500 ? '…' : '') . " »\n\n"
                    . self::lien($ticket));
            }
        });
    }

        /** Inscription : lien de confirmation envoyé au futur utilisateur. */
        /** Inscription : code de vérification à 6 chiffres envoyé au futur utilisateur. */
    public static function inscriptionVerification(array $utilisateur, string $code): void
    {
        self::proteger(function () use ($utilisateur, $code): void {
            self::envoyer($utilisateur['email'], 'Votre code de vérification : ' . $code,
                self::bonjour($utilisateur['prenom'])
                . "Vous avez demandé un compte HelpDesk DSI (profil : {$utilisateur['profil']}).\n"
                . "Votre code de vérification est : {$code}\n"
                . "Il est valable 15 minutes. Saisissez-le sur la page d'inscription pour confirmer votre adresse.\n\n"
                . "Après confirmation, un administrateur validera votre demande. Si vous n'êtes pas à l'origine de cette demande, ignorez ce message.\n");
        });
    }
    /** Inscription : l'e-mail est confirmé, les administrateurs doivent décider. */
    public static function inscriptionEnAttente(array $compte): void
    {
        self::proteger(function () use ($compte): void {
            $lien = rtrim((string) config('app.url_publique', ''), '/') . '/admin/utilisateurs';
            foreach (Utilisateur::administrateurs() as $admin) {
                self::envoyer($admin['email'], 'Nouvelle demande d\'inscription à valider',
                    self::bonjour($admin['prenom'])
                    . "{$compte['prenom']} {$compte['nom']} ({$compte['email']}) demande un compte {$compte['role']}"
                    . ($compte['domaine'] !== null ? ", domaine {$compte['domaine']}" : '') . ".\n"
                    . "Son adresse e-mail est confirmée. Approuvez ou refusez la demande ici :\n{$lien}\n");
            }
        });
    }

    /** Inscription : l'utilisateur est prévenu de la décision. */
    public static function inscriptionDecision(array $compte, bool $approuve): void
    {
        self::proteger(function () use ($compte, $approuve): void {
            $connexion = rtrim((string) config('app.url_publique', ''), '/') . '/login';
            self::envoyer($compte['email'],
                $approuve ? 'Votre compte HelpDesk DSI est activé' : 'Votre demande de compte HelpDesk DSI a été refusée',
                self::bonjour($compte['prenom'])
                . ($approuve
                    ? "Votre compte est activé. Vous pouvez maintenant vous connecter :\n{$connexion}\n"
                    : "Votre demande de compte n'a pas été retenue. Rapprochez-vous de la DSI pour en savoir plus.\n"));
        });
    }

    // ---------- outils internes ----------

    /** Demandeur et technicien du ticket, sans la personne qui vient d'agir. */
    private static function interesses(array $ticket, array $acteur): array
    {
        $liste = [];
        if ((int) $ticket['demandeur_id'] !== (int) $acteur['id']) {
            $liste[] = ['prenom' => $ticket['dem_prenom'], 'email' => $ticket['dem_email']];
        }
        if ($ticket['technicien_id'] !== null && (int) $ticket['technicien_id'] !== (int) $acteur['id']) {
            $liste[] = ['prenom' => $ticket['tech_prenom'], 'email' => $ticket['tech_email']];
        }
        return $liste;
    }

    private static function envoyer(string $destinataire, string $sujet, string $corps): void
    {
        Mailer::envoyer($destinataire, $sujet, $corps . "\n--\nHelpDesk DSI : message automatique, merci de ne pas y répondre.");
    }

    private static function bonjour(string $prenom): string
    {
        return "Bonjour {$prenom},\n\n";
    }

    private static function lien(array $ticket): string
    {
        return 'Consulter le ticket : ' . rtrim((string) config('app.url_publique', ''), '/') . '/tickets/' . $ticket['id'] . "\n";
    }

    private static function proteger(callable $travail): void
    {
        try {
            $travail();
        } catch (Throwable $e) {
            error_log('NotificationService : ' . $e->getMessage());
        }
    }
}