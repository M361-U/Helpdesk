<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title><?= e($titre ?? 'HelpDesk DSI') ?> | <?= e(config('app.nom')) ?></title>
    <link rel="preload" href="<?= e(asset('fonts/figtree-latin-wght-normal.woff2')) ?>" as="font" type="font/woff2" crossorigin>
    <link rel="stylesheet" href="<?= e(asset('css/style.css')) ?>">
    <link rel="stylesheet" href="<?= e(asset('css/auth.css')) ?>">
</head>
<body class="entree">
<div class="entree-page">
    <header class="entree-tete">
        <div class="brand"><span class="brand-logo" aria-hidden="true"><i></i><i></i><i></i></span>HelpDesk <span>DSI</span></div>
        <a class="entree-retour" href="<?= e(url('/')) ?>"><span aria-hidden="true">&larr;</span> Retour à l'accueil</a>
    </header>

    <?= $contenu ?>

    <section class="entree-pied" aria-label="Domaines pris en charge">
        <p>Chaque incident va au bon technicien : le ticket est routé automatiquement vers l'équipe du domaine concerné.</p>
        <ul>
            <li class="d-reseau">Réseau</li>
            <li class="d-systeme">Système</li>
            <li class="d-applicatif">Applicatif</li>
        </ul>
    </section>
</div>
<script src="<?= e(asset('js/app.js')) ?>" defer></script>
</body>
</html>