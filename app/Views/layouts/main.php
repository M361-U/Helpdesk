<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title><?= e($titre ?? 'HelpDesk DSI') ?> | <?= e(config('app.nom')) ?></title>
    <link rel="preload" href="<?= e(asset('fonts/figtree-latin-wght-normal.woff2')) ?>" as="font" type="font/woff2" crossorigin>
    <link rel="stylesheet" href="<?= e(asset('css/style.css')) ?>">
    <link rel="stylesheet" href="<?= e(asset('css/tableau.css')) ?>">
</head>
<body>
<?php $u = \App\Core\Auth::utilisateur(); ?>
<div class="app">
    <aside class="sidebar">
               <div class="brand"><span class="brand-logo" aria-hidden="true"><i></i><i></i><i></i></span>HelpDesk <span>DSI</span></div>
        <?php if ($u): ?>
        <nav class="main-nav" aria-label="Menu principal">
            <?php foreach (menu_principal($u['role']) as [$chemin, $libelle]): ?>
                <a href="<?= e(url($chemin)) ?>"<?= chemin_courant() === $chemin ? ' class="actif" aria-current="page"' : '' ?>><?= e($libelle) ?></a>
            <?php endforeach; ?>
        </nav>
        <div class="topbar-user">
            <strong><?= e($u['prenom'] . ' ' . $u['nom']) ?></strong>
            <em><?= e(libelle_role($u['role'])) ?></em>
            <form method="post" action="<?= e(url('/logout')) ?>">
                <?= csrf_field() ?>
                <button type="submit" class="btn btn-ghost">Déconnexion</button>
            </form>
        </div>
        <?php endif; ?>
    </aside>

    <div class="contenu">
        <main class="container">
            <?php foreach (recuperer_flashs() as $f): ?>
                <div class="alert alert-<?= e($f['type']) ?>"><?= e($f['message']) ?></div>
            <?php endforeach; ?>
            <?= $contenu ?>
        </main>
    </div>
</div>
<script src="<?= e(asset('js/app.js')) ?>" defer></script>
</body>
</html>