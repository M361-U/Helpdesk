<div class="login-card login-card-large">
    <h1>Vérifiez votre boîte e-mail</h1>
    <?php if (!empty($email)): ?>
        <p>Un lien de confirmation vient d'être envoyé à <strong><?= e($email) ?></strong>. Il est valable 24 heures.</p>
    <?php else: ?>
        <p>Si votre demande a été enregistrée, un lien de confirmation vous a été envoyé.</p>
    <?php endif; ?>

    <ol class="etapes-inscription">
        <li class="fait"><div><strong>Demande envoyée</strong><br><span class="muted">Votre code d'accréditation est valide.</span></div></li>
        <li><div><strong>Confirmez votre adresse e-mail</strong><br><span class="muted">Ouvrez le lien reçu par e-mail.</span></div></li>
        <li><div><strong>Validation par la DSI</strong><br><span class="muted">Un administrateur active votre compte, vous êtes prévenu par e-mail.</span></div></li>
    </ol>

    <?php if (!empty($lienDemo)): ?>
        <div class="lien-demo">
            <strong>Mode démonstration</strong> : l'envoi d'e-mails n'est pas activé. Utilisez ce lien à la place :<br>
            <a href="<?= e($lienDemo) ?>"><?= e($lienDemo) ?></a>
        </div>
    <?php endif; ?>

    <p class="auth-lien"><a href="<?= e(url('/login')) ?>">Retour à la connexion</a></p>
</div>