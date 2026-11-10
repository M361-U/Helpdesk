<div class="login-card login-card-large">
    <h1>Saisissez votre code</h1>
    <p>Un code à 6 chiffres vient d'être envoyé<?= $email !== '' ? ' à <strong>' . e($email) . '</strong>' : '' ?>. Il est valable <?= (int) $validite ?> minutes.</p>

    <?php foreach (recuperer_flashs() as $f): ?>
        <div class="alert alert-<?= e($f['type']) ?>"><?= e($f['message']) ?></div>
    <?php endforeach; ?>

    <?php if (!empty($codeDemo)): ?>
        <div class="lien-demo">
            <strong>Mode démonstration</strong> : l'envoi d'e-mails n'est pas activé. Votre code est :
            <strong class="code-demo"><?= e($codeDemo) ?></strong>
        </div>
    <?php endif; ?>

    <form method="post" action="<?= e(url('/inscription/verification')) ?>" class="auth-form" id="form-code" novalidate>
        <?= csrf_field() ?>
        <?php if ($email === ''): ?>
            <div class="field">
                <label for="email">Adresse e-mail utilisée pour l'inscription</label>
                <input type="email" id="email" name="email" required autocomplete="email">
            </div>
        <?php else: ?>
            <input type="hidden" name="email" value="<?= e($email) ?>">
        <?php endif; ?>
        <div class="field">
            <label for="code">Code de vérification</label>
            <input id="code" name="code" class="champ-code" required inputmode="numeric" pattern="[0-9]{6}" maxlength="6" autocomplete="one-time-code" autofocus placeholder="000000">
        </div>
        <button type="submit" class="btn btn-primary">Confirmer mon adresse</button>
    </form>

    <form method="post" action="<?= e(url('/inscription/renvoyer')) ?>" class="renvoyer">
        <?= csrf_field() ?>
        <input type="hidden" name="email" value="<?= e($email) ?>">
        <span class="muted">Vous n'avez rien reçu ?</span>
        <button type="submit" class="btn-lien">Envoyer un nouveau code</button>
    </form>

    <p class="auth-lien"><a href="<?= e(url('/inscription')) ?>">Modifier mes informations</a> · <a href="<?= e(url('/login')) ?>">Retour à la connexion</a></p>
</div>