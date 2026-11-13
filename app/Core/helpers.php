<?php
declare(strict_types=1);

use App\Core\Auth;

/** Lit un réglage : config('db.host'), config('app.base_url')... */
function config(string $cle, mixed $defaut = null): mixed
{
    static $cfg = null;
    $cfg ??= require BASE_PATH . '/config/config.php';

    $valeur = $cfg;
    foreach (explode('.', $cle) as $segment) {
        if (!is_array($valeur) || !array_key_exists($segment, $valeur)) {
            return $defaut;
        }
        $valeur = $valeur[$segment];
    }
    return $valeur;
}

/** Échappe le texte avant affichage : protège contre les failles XSS. */
function e(?string $texte): string
{
    return htmlspecialchars((string) $texte, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
}

/** Construit une URL interne : url('/login') */
function url(string $chemin = ''): string
{
    return rtrim((string) config('app.base_url'), '/') . '/' . ltrim($chemin, '/');
}

/** URL d'une ressource statique : asset('css/style.css') */
function asset(string $fichier): string
{
    return url('assets/' . $fichier);
}

/** Champ caché contenant le jeton CSRF, à placer dans chaque formulaire POST. */
function csrf_field(): string
{
    return '<input type="hidden" name="_csrf" value="' . e(Auth::csrfToken()) . '">';
}

function icone_poubelle(): string
{
    return '<span aria-hidden="true">&#128465;&#65039;</span>';
}

/** Enregistre un message à afficher à la prochaine page. */
function flash(string $type, string $message): void
{
    $_SESSION['flash'][] = ['type' => $type, 'message' => $message];
}

/** Récupère puis efface les messages en attente. */
function recuperer_flashs(): array
{
    $messages = $_SESSION['flash'] ?? [];
    unset($_SESSION['flash']);
    return $messages;
}

/** Chemin de la page courante sans le préfixe de l'application : "/dashboard" */
function chemin_courant(): string
{
    $chemin = parse_url($_SERVER['REQUEST_URI'] ?? '/', PHP_URL_PATH) ?: '/';
    $base   = rtrim((string) config('app.base_url'), '/');
    if ($base !== '' && str_starts_with($chemin, $base)) {
        $chemin = substr($chemin, strlen($base));
    }
    return '/' . trim($chemin, '/');
}

/**
 * Entrées du menu principal selon le rôle : [chemin, libellé].
 * On y ajoutera une ligne à chaque nouvelle fonctionnalité.
 */
function menu_principal(string $role): array
{
    $menu = [['/dashboard', 'Tableau de bord']];

       $parRole = [
        'employe'    => [['/tickets/nouveau', 'Nouveau ticket'], ['/tickets', 'Mes tickets']],
        'technicien' => [['/tickets', 'Mes tickets assignés']],
        'admin'      => [
            ['/tickets', 'Tous les tickets'],
            ['/admin/utilisateurs', 'Utilisateurs'],
            ['/admin/audit', 'Journal'],
            ['/admin/notifications', 'E-mails'],
        ],
    ];

    return array_merge($menu, $parRole[$role] ?? []);
}

function libelle_statut(string $statut): string
{
    return [
        'nouveau' => 'Nouveau', 'assigne' => 'Assigné', 'en_cours' => 'En cours',
        'en_attente' => 'En attente', 'resolu' => 'Résolu', 'cloture' => 'Clôturé',
    ][$statut] ?? $statut;
}

function libelle_urgence(string $urgence): string
{
    return ['basse' => 'Basse', 'normale' => 'Normale', 'haute' => 'Haute', 'critique' => 'Critique'][$urgence] ?? $urgence;
}

/** Date SQL => 14/10/2026 11:30 */
function date_fr(?string $date): string
{
    return $date ? date('d/m/Y H:i', strtotime($date)) : '—';
}

function libelle_role(string $role): string
{
    return ['employe' => 'Employé', 'technicien' => 'Technicien', 'admin' => 'Administrateur'][$role] ?? $role;
}

/** Texte du bouton qui fait passer un ticket du statut $de au statut $vers. */
function libelle_action(string $de, string $vers, string $role): string
{
    return match (true) {
        $vers === 'en_cours' && $de === 'assigne'    => 'Prendre en charge',
        $vers === 'en_cours' && $de === 'en_attente' => 'Reprendre le traitement',
        $vers === 'en_cours' && $de === 'resolu'     => 'Rouvrir : le problème persiste',
        $vers === 'en_attente'                       => 'Mettre en attente',
        $vers === 'resolu'                           => 'Marquer comme résolu',
        $vers === 'cloture'                          => $role === 'employe' ? 'Confirmer et clôturer' : 'Clôturer le ticket',
        default                                      => libelle_statut($vers),
    };
}

/** Durée en secondes => « 2 h 15 min », « 1 j 3 h »... */
function duree_fr(int $secondes): string
{
    $secondes = max(0, $secondes);
    $minutes  = intdiv($secondes, 60);

    if ($minutes < 60) {
        return $minutes . ' min';
    }
    if ($minutes < 1440) {
        return intdiv($minutes, 60) . ' h ' . str_pad((string) ($minutes % 60), 2, '0', STR_PAD_LEFT) . ' min';
    }
    return intdiv($minutes, 1440) . ' j ' . intdiv($minutes % 1440, 60) . ' h';
}

/** Part en pourcentage (0 à 100), arrondie. */
function pourcentage(int|float $partie, int|float $total): int
{
    return $total > 0 ? (int) round($partie * 100 / $total) : 0;
}

/** Délai moyen (en secondes, ou null) => « 3 h 20 min » ou « — ». */
function delai_moyen_fr(?float $secondes): string
{
    return $secondes === null ? '—' : duree_fr((int) round($secondes));
}

/** Taille en octets => « 850 Ko », « 1,4 Mo ». */
function taille_fr(int $octets): string
{
    if ($octets >= 1048576) {
        return number_format($octets / 1048576, 1, ',', ' ') . ' Mo';
    }
    return max(1, (int) round($octets / 1024)) . ' Ko';
}


/** Libellé lisible d'un code d'action du journal d'audit. */
function libelle_audit(string $code): string
{
    return [
        'CONNEXION' => 'Connexion', 'DECONNEXION' => 'Déconnexion', 'ECHEC_CONNEXION' => 'Échec de connexion',
        'CREATION_TICKET' => 'Création de ticket', 'ASSIGNATION_AUTO' => 'Assignation automatique',
        'TICKET_NON_ASSIGNE' => 'Ticket non assigné', 'ASSIGNATION_MANUELLE' => 'Assignation manuelle',
        'CHANGEMENT_STATUT' => 'Changement de statut', 'COMMENTAIRE' => 'Commentaire',
        'AJOUT_PIECE_JOINTE' => 'Pièce jointe ajoutée', 'TELECHARGEMENT_PIECE_JOINTE' => 'Pièce jointe téléchargée',
        'EXPORT_CSV' => 'Export CSV', 'EXPORT_PDF' => 'Export PDF', 'EXPORT_FICHE_PDF' => 'Export fiche PDF',
        'CREATION_UTILISATEUR' => 'Compte créé', 'MODIFICATION_UTILISATEUR' => 'Compte modifié',
        'DESACTIVATION_UTILISATEUR' => 'Compte désactivé', 'REINITIALISATION_MOT_DE_PASSE' => 'Mot de passe réinitialisé',
        'INSCRIPTION' => 'Demande d\'inscription', 'EMAIL_CONFIRME' => 'E-mail confirmé',
         'INSCRIPTION_APPROUVEE' => 'Inscription approuvée', 'INSCRIPTION_REFUSEE' => 'Inscription refusée',
        'SUPPRESSION_TICKET' => 'Ticket supprimé',
    ][$code] ?? $code;
}

/** Badge HTML du statut d'un compte (actif, désactivé ou étape d'inscription). */
function badge_compte(array $u): string
{
    if ((int) $u['actif'] === 1) {
        return '<span class="badge badge-st-resolu">Actif</span>';
    }
    return match ($u['statut_compte'] ?? 'actif') {
        'email_a_verifier' => '<span class="badge badge-compte-email">E-mail non confirmé</span>',
        'en_attente'       => '<span class="badge badge-compte-attente">En attente</span>',
        'refuse'           => '<span class="badge badge-compte-refuse">Refusé</span>',
        default            => '<span class="badge badge-st-cloture">Désactivé</span>',
    };
}

/** Classe CSS d'un domaine (couleur propre à Réseau, Système, Applicatif). */
function classe_domaine(?string $libelle): string
{
    $sansAccent = strtr(mb_strtolower((string) $libelle), ['é' => 'e', 'è' => 'e', 'ê' => 'e']);
    return 'dom-' . preg_replace('/[^a-z]/', '', $sansAccent);
}