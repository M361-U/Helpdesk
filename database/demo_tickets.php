<?php
declare(strict_types=1);

/**
 * DONNÉES DE DÉMONSTRATION — à lancer une seule fois : php database/demo_tickets.php
 * Génère ~40 tickets fictifs répartis sur 14 jours pour alimenter les tableaux de bord.
 * Ces données sont artificielles : à signaler comme telles dans le rapport.
 */
if (PHP_SAPI !== 'cli') { exit("Script réservé à la ligne de commande.\n"); }

define('BASE_PATH', dirname(__DIR__));
$config = require BASE_PATH . '/config/config.php';
$c = $config['db'];
$pdo = new PDO("mysql:host={$c['host']};dbname={$c['name']};charset={$c['charset']}", $c['user'], $c['pass'],
    [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION, PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC]);
date_default_timezone_set('Africa/Libreville');
mt_srand(2026); // résultats reproductibles

if ((int) $pdo->query('SELECT COUNT(*) FROM tickets')->fetchColumn() > 0) {
    exit("Des tickets existent déjà : script annulé.\n");
}

$sla = ['critique' => 4, 'haute' => 8, 'normale' => 24, 'basse' => 72];
$urgences = ['basse', 'normale', 'normale', 'normale', 'haute', 'critique'];
$demandeurs = $pdo->query("SELECT u.id FROM utilisateurs u JOIN roles r ON r.id=u.role_id WHERE r.code='employe'")->fetchAll(PDO::FETCH_COLUMN);
$categories = $pdo->query('SELECT c.id, c.libelle, c.domaine_id FROM categories c')->fetchAll();
$techs = [];
foreach ($pdo->query("SELECT u.id, u.domaine_id FROM utilisateurs u JOIN roles r ON r.id=u.role_id WHERE r.code='technicien' AND u.actif=1")->fetchAll() as $t) {
    $techs[$t['domaine_id']][] = $t['id'];
}

$ins = $pdo->prepare('INSERT INTO tickets (reference,titre,description,categorie_id,demandeur_id,technicien_id,urgence,statut,echeance_sla,cree_le,assigne_le,resolu_le)
                      VALUES (:ref,:titre,:desc,:cat,:dem,:tech,:urg,:st,:sla,:cree,:ass,:res)');
$hist = $pdo->prepare('INSERT INTO historique_statuts (ticket_id,ancien_statut,nouveau_statut,modifie_par,modifie_le) VALUES (?,?,?,?,?)');
$fmt = fn(int $ts): string => date('Y-m-d H:i:s', $ts);
$rr = [];

for ($i = 1; $i <= 40; $i++) {
    $cat = $categories[array_rand($categories)];
    $urg = $urgences[array_rand($urgences)];
    $cree = time() - mt_rand(2 * 3600, 14 * 86400);
    $tech = $techs[$cat['domaine_id']][$rr[$cat['domaine_id']] = (($rr[$cat['domaine_id']] ?? -1) + 1) % count($techs[$cat['domaine_id']])];
    $assigne = $cree + 60;
    $echeance = $cree + $sla[$urg] * 3600;
    $age = time() - $cree;

    // Les tickets anciens sont plus souvent terminés
    $tirage = mt_rand(1, 100);
    $resolu = null;
    if ($age > 3 * 86400 && $tirage <= 70)      { $st = $tirage <= 45 ? 'cloture' : 'resolu'; }
    elseif ($age > 86400 && $tirage <= 40)      { $st = 'resolu'; }
    elseif ($tirage <= 10)                       { $st = 'nouveau'; $tech = null; $assigne = null; }
    elseif ($tirage <= 40)                       { $st = 'assigne'; }
    elseif ($tirage <= 75)                       { $st = 'en_cours'; }
    else                                         { $st = 'en_attente'; }

    if (in_array($st, ['resolu', 'cloture'], true)) {
        // 80 % des résolutions respectent le SLA
        $duree = mt_rand(1, 100) <= 80 ? (int) ($sla[$urg] * 3600 * mt_rand(20, 95) / 100) : (int) ($sla[$urg] * 3600 * mt_rand(110, 220) / 100);
        $resolu = min($cree + $duree, time() - 600);
    }

    $ins->execute([
        'ref' => 'TMP-' . $i, 'titre' => $cat['libelle'] . ' (démo ' . $i . ')',
        'desc' => 'Ticket de démonstration généré automatiquement.',
        'cat' => $cat['id'], 'dem' => $demandeurs[array_rand($demandeurs)], 'tech' => $tech,
        'urg' => $urg, 'st' => $st, 'sla' => $fmt($echeance), 'cree' => $fmt($cree),
        'ass' => $assigne ? $fmt($assigne) : null, 'res' => $resolu ? $fmt($resolu) : null,
    ]);
    $id = (int) $pdo->lastInsertId();
    $pdo->prepare('UPDATE tickets SET reference=? WHERE id=?')->execute([sprintf('TK-%s-%04d', date('Y'), $id), $id]);

    $hist->execute([$id, null, 'nouveau', null, $fmt($cree)]);
    if ($assigne) { $hist->execute([$id, 'nouveau', 'assigne', null, $fmt($assigne)]); }
    if ($resolu)  { $hist->execute([$id, 'en_cours', 'resolu', $tech, $fmt($resolu)]); }
}
echo "40 tickets de démonstration créés.\n";