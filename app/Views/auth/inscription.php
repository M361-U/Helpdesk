<?php
$a = $ancien ?? [];
$profil = $a['profil'] ?? 'employe';
?>
<div class="login-card login-card-large">
    <h1>Créer un compte</h1>
    <p class="muted">Votre profil est vérifié avant l'activation : code d'accréditation, confirmation de l'e-mail, puis validation par la DSI.</p>

    <?php foreach (recuperer_flashs() as $f): ?>
        <div class="alert alert-<?= e($f['type']) ?>"><?= e($f['message']) ?></div>
    <?php endforeach; ?>

    <form method="post" action="<?= e(url('/inscription')) ?>" class="auth-form" id="form-inscription" novalidate>
        <?= csrf_field() ?>
        <div class="honeypot" aria-hidden="true">
            <label for="site_web">Ne pas remplir</label>
            <input type="text" id="site_web" name="site_web" tabindex="-1" autocomplete="off">
        </div>

        <fieldset style="border:0;padding:0;margin:0 0 1rem">
            <legend style="font-weight:650;font-size:.95rem;margin-bottom:.4rem;padding:0">Vous êtes</legend>
            <div class="choix-profil">
                <label><input type="radio" name="profil" value="employe" <?= $profil === 'employe' ? 'checked' : '' ?>><span>Employé<small>je déclare des incidents</small></span></label>
                <label><input type="radio" name="profil" value="technicien" <?= $profil === 'technicien' ? 'checked' : '' ?>><span>Technicien<small>je traite des incidents</small></span></label>
            </div>
        </fieldset>

        <div class="field-row">
            <div class="field"><label for="prenom">Prénom</label><input id="prenom" name="prenom" value="<?= e($a['prenom'] ?? '') ?>" required autocomplete="given-name"></div>
            <div class="field"><label for="nom">Nom</label><input id="nom" name="nom" value="<?= e($a['nom'] ?? '') ?>" required autocomplete="family-name"></div>
        </div>
        <div class="field">
            <label for="email">Adresse e-mail</label>
            <input type="email" id="email" name="email" value="<?= e($a['email'] ?? '') ?>" required autocomplete="email">
        </div>

        <div class="field" id="bloc-service" data-profil="employe">
            <label for="service">Service</label>
            <input id="service" name="service" value="<?= e($a['service'] ?? '') ?>" placeholder="Comptabilité, RH, Direction…">
        </div>
        <div class="field" id="bloc-domaine" data-profil="technicien" hidden>
            <label for="domaine_id">Domaine de compétence</label>
            <select id="domaine_id" name="domaine_id">
                <option value="">Choisir…</option>
                <?php foreach ($domaines as $d): ?>
                    <option value="<?= (int) $d['id'] ?>" <?= (string) ($a['domaine_id'] ?? '') === (string) $d['id'] ? 'selected' : '' ?>><?= e($d['libelle']) ?></option>
                <?php endforeach; ?>
            </select>
            <p class="hint">Les tickets de ce domaine vous seront assignés automatiquement.</p>
        </div>

        <div class="field">
            <label for="code">Code d'accréditation</label>
            <input id="code" name="code" required autocomplete="off" spellcheck="false">
            <p class="hint" id="aide-code">Code remis par la DSI à votre profil. Sans lui, la demande est refusée.</p>
        </div>

        <div class="field-row">
            <div class="field"><label for="mot_de_passe">Mot de passe</label><input type="password" id="mot_de_passe" name="mot_de_passe" required autocomplete="new-password"></div>
            <div class="field"><label for="mot_de_passe_confirmation">Confirmation</label><input type="password" id="mot_de_passe_confirmation" name="mot_de_passe_confirmation" required autocomplete="new-password"></div>
        </div>
        <p class="hint" style="margin-top:-.4rem">8 caractères minimum, avec au moins une lettre et un chiffre.</p>

        <button type="submit" class="btn btn-primary">Envoyer ma demande</button>
    </form>

    <p class="auth-lien">Déjà un compte ? <a href="<?= e(url('/login')) ?>">Se connecter</a></p>
</div>