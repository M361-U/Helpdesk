<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title><?= e($titre ?? 'Accueil') ?> | <?= e(config('app.nom')) ?></title>
    <link rel="preload" href="<?= e(asset('fonts/figtree-latin-wght-normal.woff2')) ?>" as="font" type="font/woff2" crossorigin>
    <link rel="stylesheet" href="<?= e(asset('css/style.css')) ?>">
</head>
<body class="site">
<header class="site-header">
    <div class="wrap site-header-inner">
                <div class="brand"><span class="brand-logo" aria-hidden="true"><i></i><i></i><i></i></span>HelpDesk <span>DSI</span></div>
                <nav class="site-nav" aria-label="Navigation principale">
            <a href="<?= e(url('/')) ?>#fonctionnement">Fonctionnement</a>
            <?php if (\App\Core\Auth::estConnecte()): ?>
                <a class="btn btn-clair" href="<?= e(url('/dashboard')) ?>">Mon espace</a>
            <?php else: ?>
                <a class="btn btn-light" href="<?= e(url('/login')) ?>">Se connecter</a>
                <a class="btn btn-clair" href="<?= e(url('/inscription')) ?>">Créer un compte</a>
            <?php endif; ?>
        </nav>
    </div>
</header>

<?= $contenu ?>

<footer class="site-footer">
    <div class="wrap">
        <p><strong>HelpDesk DSI</strong> : gestion des incidents informatiques avec routage automatique.</p>
                <p class="muted">&copy; <?= date('Y') ?> INPTIC.</p>
            <?php if (!\App\Core\Auth::estConnecte()): ?><?php endif; ?></p>
    </div>
</footer>
</body>
</html>