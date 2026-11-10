<?php
declare(strict_types=1);

// À lancer en ligne de commande : php database/seed.php
if (PHP_SAPI !== 'cli') {
    exit("Ce script s'exécute uniquement en ligne de commande.\n");
}

define('BASE_PATH', dirname(__DIR__));
require BASE_PATH . '/app/Core/Database.php';

use App\Core\Database;

$pdo = Database::getConnection();

if ((int) $pdo->query('SELECT COUNT(*) FROM utilisateurs')->fetchColumn() > 0) {
    exit("Des utilisateurs existent déjà : aucune action effectuée.\n");
}

const MOT_DE_PASSE_DEMO = 'Passw0rd!';
$hash = password_hash(MOT_DE_PASSE_DEMO, PASSWORD_BCRYPT);

// role_id : 1 employé, 2 technicien, 3 admin
// domaine_id : 1 réseau, 2 système, 3 applicatif
$comptes = [
    ['DSI',       'Admin',     'admin@dsi.local',          3, null, 'DSI'],
    ['Obame',     'Jean',      'tech.reseau1@dsi.local',   2, 1,    'DSI'],
    ['Nze',       'Patrice',   'tech.reseau2@dsi.local',   2, 1,    'DSI'],
    ['Mba',       'Sylvie',    'tech.systeme1@dsi.local',  2, 2,    'DSI'],
    ['Ndong',     'Arnaud',    'tech.systeme2@dsi.local',  2, 2,    'DSI'],
    ['Moussavou', 'Carine',    'tech.appli1@dsi.local',    2, 3,    'DSI'],
    ['Ondo',      'Brice',     'tech.appli2@dsi.local',    2, 3,    'DSI'],
    ['Ella',      'Marie',     'marie.ella@dsi.local',     1, null, 'Comptabilité'],
    ['Mintsa',    'Paul',      'paul.mintsa@dsi.local',    1, null, 'Ressources humaines'],
    ['Bouanga',   'Lydie',     'lydie.bouanga@dsi.local',  1, null, 'Direction'],
];

$requete = $pdo->prepare(
    'INSERT INTO utilisateurs (nom, prenom, email, mot_de_passe, role_id, domaine_id, service)
     VALUES (:nom, :prenom, :email, :mdp, :role, :domaine, :service)'
);

foreach ($comptes as [$nom, $prenom, $email, $role, $domaine, $service]) {
    $requete->execute([
        'nom' => $nom, 'prenom' => $prenom, 'email' => $email, 'mdp' => $hash,
        'role' => $role, 'domaine' => $domaine, 'service' => $service,
    ]);
}

echo count($comptes) . " comptes créés. Mot de passe de démonstration : " . MOT_DE_PASSE_DEMO . "\n";