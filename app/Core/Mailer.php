<?php
declare(strict_types=1);

namespace App\Core;

use App\Models\Notification;
use RuntimeException;
use Throwable;

/**
 * Envoi d'e-mails.
 * 1) le message est TOUJOURS enregistré dans la table notifications_email ;
 * 2) si config « mail.actif » est vrai, on tente l'envoi par SMTP.
 * Une panne d'envoi ne doit jamais bloquer l'action de l'utilisateur : aucune exception ne sort d'ici.
 */
final class Mailer
{
    public static function envoyer(string $destinataire, string $sujet, string $corps): void
    {
        try {
            $id = Notification::ajouter($destinataire, $sujet, $corps);
        } catch (Throwable $e) {
            error_log('Mailer (enregistrement) : ' . $e->getMessage());
            return;
        }

        if (!config('mail.actif', false)) {
            return;                         // mode démonstration : le message reste dans la file
        }

        try {
            self::smtp($destinataire, $sujet, $corps);
            Notification::marquerEnvoye($id);
        } catch (Throwable $e) {
            error_log('Mailer (SMTP) : ' . $e->getMessage());
            Notification::marquerEchec($id, $e->getMessage());
        }
    }

    /** Dialogue SMTP minimal : EHLO, [STARTTLS], [AUTH LOGIN], MAIL FROM, RCPT TO, DATA, QUIT. */
    private static function smtp(string $destinataire, string $sujet, string $corps): void
    {
        $hote = (string) config('mail.hote');
        $port = (int) config('mail.port');
        $chiffrement = (string) config('mail.chiffrement', '');
        $expediteur = (string) config('mail.expediteur');

        $flux = @stream_socket_client(
            ($chiffrement === 'ssl' ? 'ssl://' : 'tcp://') . $hote . ':' . $port,
            $numero, $texteErreur, 10
        );
        if ($flux === false) {
            throw new RuntimeException('Connexion SMTP impossible (' . $texteErreur . ')');
        }
        stream_set_timeout($flux, 10);

        // Lit une réponse (éventuellement sur plusieurs lignes « 250-… » puis « 250 … »)
        $lire = function () use ($flux): array {
            $texte = '';
            while (($ligne = fgets($flux, 515)) !== false) {
                $texte .= $ligne;
                if (strlen($ligne) < 4 || $ligne[3] === ' ') {
                    break;
                }
            }
            return [(int) substr($texte, 0, 3), trim($texte)];
        };
        $commande = function (string $ligne, array $codesAttendus) use ($flux, $lire): void {
            fwrite($flux, $ligne . "\r\n");
            [$code, $texte] = $lire();
            if (!in_array($code, $codesAttendus, true)) {
                throw new RuntimeException('Réponse SMTP inattendue : ' . $texte);
            }
        };

        try {
            [$code, $texte] = $lire();
            if ($code !== 220) {
                throw new RuntimeException('Serveur SMTP indisponible : ' . $texte);
            }
            $commande('EHLO localhost', [250]);

            if ($chiffrement === 'tls') {
                $commande('STARTTLS', [220]);
                if (!stream_socket_enable_crypto($flux, true, STREAM_CRYPTO_METHOD_TLS_CLIENT)) {
                    throw new RuntimeException('Échec de la connexion chiffrée (STARTTLS).');
                }
                $commande('EHLO localhost', [250]);
            }

            $utilisateur = (string) config('mail.utilisateur', '');
            if ($utilisateur !== '') {
                $commande('AUTH LOGIN', [334]);
                $commande(base64_encode($utilisateur), [334]);
                $commande(base64_encode((string) config('mail.mot_de_passe', '')), [235]);
            }

            $commande('MAIL FROM:<' . self::adresse($expediteur) . '>', [250]);
            $commande('RCPT TO:<' . self::adresse($destinataire) . '>', [250, 251]);
            $commande('DATA', [354]);
            $commande(self::message($destinataire, $sujet, $corps) . "\r\n.", [250]);
            $commande('QUIT', [221]);
        } finally {
            fclose($flux);
        }
    }

    /** Une adresse ne doit contenir ni retour à la ligne ni chevron (injection d'en-têtes SMTP). */
    private static function adresse(string $adresse): string
    {
        $adresse = str_replace(["\r", "\n", '<', '>'], '', trim($adresse));
        if (!filter_var($adresse, FILTER_VALIDATE_EMAIL)) {
            throw new RuntimeException('Adresse e-mail invalide : ' . $adresse);
        }
        return $adresse;
    }

    /** Construit l'e-mail complet (en-têtes + corps en UTF-8 encodé en base64). */
    private static function message(string $destinataire, string $sujet, string $corps): string
    {
        $encoder = fn(string $t): string => '=?UTF-8?B?' . base64_encode(str_replace(["\r", "\n"], ' ', $t)) . '?=';

        $entetes = [
            'Date: ' . date('r'),
            'From: ' . $encoder((string) config('mail.nom', 'HelpDesk')) . ' <' . self::adresse((string) config('mail.expediteur')) . '>',
            'To: <' . self::adresse($destinataire) . '>',
            'Subject: ' . $encoder($sujet),
            'Message-ID: <' . bin2hex(random_bytes(8)) . '@helpdesk.local>',
            'MIME-Version: 1.0',
            'Content-Type: text/plain; charset=UTF-8',
            'Content-Transfer-Encoding: base64',
        ];
        return implode("\r\n", $entetes) . "\r\n\r\n" . rtrim(chunk_split(base64_encode($corps), 76, "\r\n"));
    }
}