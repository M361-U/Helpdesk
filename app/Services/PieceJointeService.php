<?php
declare(strict_types=1);

namespace App\Services;

use App\Models\Audit;
use App\Models\PieceJointe;

final class PieceJointeService
{
    public const TAILLE_MAX    = 2 * 1024 * 1024;   // 2 Mo par fichier
    public const MAX_PAR_ENVOI = 3;                 // fichiers par envoi
    public const MAX_PAR_TICKET = 10;               // fichiers par ticket

    /** Extension autorisée => types MIME réellement acceptés pour cette extension. */
    public const TYPES = [
        'jpg'  => ['image/jpeg'],
        'jpeg' => ['image/jpeg'],
        'png'  => ['image/png'],
        'pdf'  => ['application/pdf'],
        'txt'  => ['text/plain'],
        'log'  => ['text/plain'],
    ];

    /** Dossier de stockage : HORS de public/, donc jamais accessible directement par une URL. */
    public static function dossier(): string
    {
        return BASE_PATH . '/storage/uploads';
    }

    /**
     * Traite le champ <input type="file" name="fichiers[]">.
     * @return array{ajoutes: int, erreurs: string[]}
     */
    public static function enregistrer(int $ticketId, string $reference, int $auteurId, ?array $envoi): array
    {
        $resultat = ['ajoutes' => 0, 'erreurs' => []];
        if ($envoi === null || !is_array($envoi['name'] ?? null)) {
            return $resultat;
        }

        $deja = PieceJointe::compter($ticketId);
        $traites = 0;

        foreach ($envoi['name'] as $i => $nomOriginal) {
            $code = (int) $envoi['error'][$i];
            if ($code === UPLOAD_ERR_NO_FILE) {
                continue;                       // champ laissé vide
            }
            $nomAffiche = mb_substr(preg_replace('/[\x00-\x1F\x7F]/u', '', basename((string) $nomOriginal)) ?? '', 0, 200);

            if (++$traites > self::MAX_PAR_ENVOI) {
                $resultat['erreurs'][] = 'Maximum ' . self::MAX_PAR_ENVOI . ' fichiers par envoi : « ' . $nomAffiche . ' » ignoré.';
                continue;
            }
            if ($deja + $resultat['ajoutes'] >= self::MAX_PAR_TICKET) {
                $resultat['erreurs'][] = 'Ce ticket a atteint la limite de ' . self::MAX_PAR_TICKET . ' pièces jointes.';
                break;
            }
            if ($code === UPLOAD_ERR_INI_SIZE || $code === UPLOAD_ERR_FORM_SIZE) {
                $resultat['erreurs'][] = '« ' . $nomAffiche . ' » dépasse la taille maximale autorisée.';
                continue;
            }
            if ($code !== UPLOAD_ERR_OK) {
                $resultat['erreurs'][] = '« ' . $nomAffiche . ' » n\'a pas pu être envoyé (erreur ' . $code . ').';
                continue;
            }

            $temporaire = (string) $envoi['tmp_name'][$i];
            $taille     = (int) $envoi['size'][$i];

            if (!is_uploaded_file($temporaire)) {
                $resultat['erreurs'][] = '« ' . $nomAffiche . ' » : envoi invalide.';
                continue;
            }
            if ($taille <= 0 || $taille > self::TAILLE_MAX) {
                $resultat['erreurs'][] = '« ' . $nomAffiche . ' » dépasse 2 Mo ou est vide.';
                continue;
            }

            // 1) l'extension doit être autorisée
            $extension = strtolower(pathinfo($nomAffiche, PATHINFO_EXTENSION));
            if (!isset(self::TYPES[$extension])) {
                $resultat['erreurs'][] = '« ' . $nomAffiche . ' » : type non autorisé (JPG, PNG, PDF, TXT, LOG uniquement).';
                continue;
            }
            // 2) le contenu réel doit correspondre à l'extension (on ne croit pas le navigateur)
            $mime = (new \finfo(FILEINFO_MIME_TYPE))->file($temporaire);
            if (!in_array($mime, self::TYPES[$extension], true)) {
                $resultat['erreurs'][] = '« ' . $nomAffiche . ' » : le contenu ne correspond pas à son extension.';
                continue;
            }

            // 3) nom aléatoire : le nom d'origine n'est jamais utilisé sur le disque
            $nomStocke = bin2hex(random_bytes(16)) . '.' . $extension;
            if (!is_dir(self::dossier())) {
                mkdir(self::dossier(), 0775, true);
            }
            if (!move_uploaded_file($temporaire, self::dossier() . '/' . $nomStocke)) {
                $resultat['erreurs'][] = '« ' . $nomAffiche . ' » : enregistrement impossible.';
                continue;
            }

            PieceJointe::ajouter($ticketId, $auteurId, $nomAffiche, $nomStocke, $taille);
            Audit::enregistrer('AJOUT_PIECE_JOINTE', $reference . ' : ' . $nomAffiche, $auteurId);
            $resultat['ajoutes']++;
        }
        return $resultat;
    }

      
}