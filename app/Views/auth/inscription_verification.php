<div class="login-card login-card-large">
<?php if ($compte !== null): ?>
    <h1>Adresse confirmée</h1>
    <div class="alert alert-success">Merci <?= e($compte['prenom']) ?>, votre adresse e-mail est confirmée.</div>
    <p>Dernière étape : un administrateur de la DSI doit valider votre compte
       (<?= e(libelle_role($compte['role'])) ?>). Vous recevrez un e-mail dès la décision.</p>
    <p class="auth-lien"><a href="<?= e(url('/')) ?>">Retour à l'accueil</a></p>
<?php else: ?>
    <h1>Lien invalide</h1>
    <div class="alert alert-error">Ce lien de confirmation est incorrect, déjà utilisé ou expiré.</div>
    <p>Refaites votre demande d'inscription avec la même adresse : un nouveau lien vous sera envoyé.</p>
    <p class="auth-lien"><a href="<?= e(url('/inscription')) ?>">Créer un compte</a></p>
<?php endif; ?>
</div>