<div class="page-head">
    <h1><?= e($compte['prenom'] . ' ' . $compte['nom']) ?></h1>
    <a class="btn btn-secondary btn-small" href="<?= e(url('/admin/utilisateurs')) ?>">&larr; Tous les utilisateurs</a>
</div>

<section class="panel panel-form">
    <h2>Informations du compte</h2>
    <p class="muted">Créé le <?= e(date_fr($compte['cree_le'])) ?>
        <?php if ($compte['role'] === 'technicien'): ?> · <?= (int) $enCours ?> ticket(s) en cours<?php endif; ?></p>

    <form method="post" action="<?= e(url('/admin/utilisateurs/' . $compte['id'])) ?>" novalidate>
        <?= csrf_field() ?>
        <?php require BASE_PATH . '/app/Views/admin/_champs_utilisateur.php'; ?>

        <div class="field field-check">
            <label>
                <input type="checkbox" name="actif" value="1" <?= (int) $compte['actif'] === 1 ? 'checked' : '' ?> <?= $moi ? 'disabled' : '' ?>>
                Compte actif
            </label>
            <?php if ($moi): ?>
                <input type="hidden" name="actif" value="1">
                <p class="hint">Vous ne pouvez pas désactiver votre propre compte.</p>
            <?php else: ?>
                <p class="hint">Un compte désactivé ne peut plus se connecter (même s'il est déjà connecté, au bout d'une minute au plus).</p>
            <?php endif; ?>
        </div>

        <div class="form-actions">
            <button type="submit" class="btn btn-primary btn-auto">Enregistrer</button>
        </div>
    </form>
</section>

<section class="panel panel-form">
    <h2>Réinitialiser le mot de passe</h2>
    <form method="post" action="<?= e(url('/admin/utilisateurs/' . $compte['id'] . '/mot-de-passe')) ?>" novalidate
          data-confirmer="Remplacer le mot de passe de ce compte ?">
        <?= csrf_field() ?>
        <div class="field">
            <label for="nouveau_mdp">Nouveau mot de passe</label>
            <input type="password" id="nouveau_mdp" name="mot_de_passe" autocomplete="new-password" required>
            <p class="hint">8 caractères minimum, avec au moins une lettre et un chiffre. Le mot de passe n'est jamais affiché ni enregistré en clair.</p>
        </div>
        <div class="form-actions">
            <button type="submit" class="btn btn-secondary btn-auto">Changer le mot de passe</button>
        </div>
    </form>
</section>