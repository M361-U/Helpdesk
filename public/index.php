<?php
declare(strict_types=1);

use App\Controllers\AuthController;
use App\Controllers\DashboardController;
use App\Controllers\HomeController;
use App\Core\Auth;
use App\Core\Router;
use App\Controllers\TicketController;
use App\Controllers\ApiController;
use App\Controllers\AdminController;
use App\Controllers\ExportController;
use App\Controllers\InscriptionController;

define('BASE_PATH', dirname(__DIR__));

// Chargement automatique des classes : App\Core\Router => app/Core/Router.php
spl_autoload_register(function (string $classe): void {
    $prefixe = 'App\\';
    if (strncmp($classe, $prefixe, strlen($prefixe)) !== 0) {
        return;
    }
    $relatif = substr($classe, strlen($prefixe));
    $fichier = BASE_PATH . '/app/' . str_replace('\\', '/', $relatif) . '.php';
    if (is_file($fichier)) {
        require $fichier;
    }
});

require BASE_PATH . '/app/Core/helpers.php';

if (config('app.debug')) {
    ini_set('display_errors', '1');
    error_reporting(E_ALL);
} else {
    ini_set('display_errors', '0');
    ini_set('log_errors', '1');
    ini_set('error_log', BASE_PATH . '/storage/logs/php-errors.log');
}
date_default_timezone_set((string) config('app.fuseau', 'Africa/Libreville'));
Auth::start();

$routeur = new Router((string) config('app.base_url'));

// ----- Table des routes -----
$routeur->get('/',          [HomeController::class, 'index']);
$routeur->get('/login',     [AuthController::class, 'afficherConnexion']);
$routeur->post('/login',    [AuthController::class, 'connecter']);
$routeur->post('/logout',   [AuthController::class, 'deconnecter']);
$routeur->get('/dashboard', [DashboardController::class, 'index']);
$routeur->get('/admin/notifications', [AdminController::class, 'notifications']);
$routeur->post('/admin/notifications/{id}/suppression', [AdminController::class, 'supprimerNotification']);
$routeur->post('/admin/notifications/suppression-selection', [AdminController::class, 'supprimerNotificationsSelectionnees']);
$routeur->post('/admin/notifications/suppression-tout', [AdminController::class, 'supprimerToutesNotifications']);
$routeur->get('/admin/audit',  [AdminController::class, 'audit']);
$routeur->post('/admin/audit/{id}/suppression', [AdminController::class, 'supprimerEntreeAudit']);
$routeur->post('/admin/audit/suppression-selection', [AdminController::class, 'supprimerEntreesAuditSelectionnees']);
$routeur->post('/admin/audit/suppression-tout', [AdminController::class, 'supprimerToutAudit']);
$routeur->get('/admin/utilisateurs',  [AdminController::class, 'utilisateurs']);
$routeur->post('/admin/utilisateurs', [AdminController::class, 'creerUtilisateur']);
$routeur->post('/admin/utilisateurs/{id}/suppression', [AdminController::class, 'supprimerUtilisateur']);
$routeur->post('/admin/utilisateurs/suppression-selection', [AdminController::class, 'supprimerUtilisateursSelectionnes']);
$routeur->post('/admin/utilisateurs/suppression-tout', [AdminController::class, 'supprimerTousUtilisateurs']);
$routeur->get('/admin/utilisateurs/{id}', [AdminController::class, 'utilisateur']);
$routeur->post('/admin/utilisateurs/{id}',               [AdminController::class, 'modifierUtilisateur']);
$routeur->post('/admin/utilisateurs/{id}/validation',    [AdminController::class, 'decision']);
$routeur->post('/admin/utilisateurs/{id}/mot-de-passe',  [AdminController::class, 'motDePasse']);
$routeur->get('/inscription',                      [InscriptionController::class, 'formulaire']);
$routeur->post('/inscription',                     [InscriptionController::class, 'enregistrer']);
$routeur->get('/inscription/verification',         [InscriptionController::class, 'formulaireCode']);
$routeur->post('/inscription/verification',        [InscriptionController::class, 'verifierCode']);
$routeur->post('/inscription/renvoyer',            [InscriptionController::class, 'renvoyerCode']);
$routeur->get('/inscription/terminee',             [InscriptionController::class, 'terminee']);



 
$routeur->get('/tickets/export/csv', [ExportController::class, 'csv']);
$routeur->get('/tickets/export/pdf', [ExportController::class, 'pdfListe']);
$routeur->get('/tickets/{id}/pdf',   [ExportController::class, 'pdfFiche']);
$routeur->get('/tickets',          [TicketController::class, 'index']);
$routeur->get('/tickets/nouveau',  [TicketController::class, 'nouveau']);
$routeur->post('/tickets',         [TicketController::class, 'enregistrer']);
$routeur->get('/tickets/{id}',     [TicketController::class, 'afficher']);
$routeur->post('/tickets/{id}/statut',       [TicketController::class, 'changerStatut']);
$routeur->post('/tickets/{id}/commentaires', [TicketController::class, 'commenter']);
$routeur->post('/tickets/{id}/pieces',         [TicketController::class, 'televerser']);
$routeur->get('/tickets/{id}/pieces/{pj}',     [TicketController::class, 'telecharger']);
$routeur->post('/tickets/{id}/suppression',  [TicketController::class, 'supprimer']);
$routeur->post('/tickets/{id}/assignation',  [TicketController::class, 'assigner']);
$routeur->get('/api/statistiques', [ApiController::class, 'statistiques']);
$routeur->dispatch($_SERVER['REQUEST_METHOD'], $_SERVER['REQUEST_URI']);
