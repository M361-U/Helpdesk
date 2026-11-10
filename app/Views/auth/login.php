<div class="login-card">
    <h1>Connexion</h1>
    <p class="muted">Saisissez votre e-mail et votre mot de passe : vous arrivez directement sur votre espace (employé, technicien ou administrateur).</p>

    <?php foreach (recuperer_flashs() as $f): ?>
        <div class="alert alert-<?= e($f['type']) ?>"><?= e($f['message']) ?></div>
    <?php endforeach; ?>

    <form method="post" action="<?= e(url('/login')) ?>" class="auth-form" novalidate>
        <?= csrf_field() ?>
        <div class="field">
            <label for="email">Adresse e-mail</label>
            <input type="email" id="email" name="email" required autofocus autocomplete="username">
        </div>
        <div class="field">
            <label for="mot_de_passe">Mot de passe</label>
            <input type="password" id="mot_de_passe" name="mot_de_passe" required autocomplete="current-password">
        </div>
        <button type="submit" class="btn btn-primary">Se connecter</button>
    </form>

    <p class="auth-lien">Pas encore de compte ? <a href="<?= e(url('/inscription')) ?>">Créer un compte</a></p>
</div>