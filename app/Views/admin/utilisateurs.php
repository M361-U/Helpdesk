<div class="page-head">
    <h1>Utilisateurs</h1>
</div>

<?php if (!empty($demandes)): ?>
<section class="panel panel-attente" id="demandes">
    <h2>Demandes d'inscription à valider (<?= count($demandes) ?>)</h2>
    <p class="muted small">Adresse e-mail confirmée et code d'accréditation correct. Approuvez uniquement les personnes que vous reconnaissez.</p>
    <?php foreach ($demandes as $d): ?>
        <div class="demande">
            <div class="demande-infos">
                <strong><?= e($d['prenom'] . ' ' . $d['nom']) ?> <span class="badge badge-role"><?= e(libelle_role($d['role'])) ?></span></strong>
                <span class="muted"><?= e($d['email']) ?>
                    · <?= $d['domaine'] !== null ? 'Domaine ' . e($d['domaine']) : e((string) $d['service']) ?></span>
            </div>
            <div class="demande-actions">
                <form method="post" action="<?= e(url('/admin/utilisateurs/' . $d['id'] . '/validation')) ?>">
                    <?= csrf_field() ?><input type="hidden" name="decision" value="approuver">
                    <button type="submit" class="btn btn-success btn-small">Approuver</button>
                </form>
                <form method="post" action="<?= e(url('/admin/utilisateurs/' . $d['id'] . '/validation')) ?>" data-confirmer="Refuser la demande de <?= e($d['prenom'] . ' ' . $d['nom']) ?> ?">
                    <?= csrf_field() ?><input type="hidden" name="decision" value="refuser">
                    <button type="submit" class="btn btn-danger btn-small">Refuser</button>
                </form>
            </div>
        </div>
    <?php endforeach; ?>
</section>
<?php endif; ?>

<div class="table-wrap">
    <table class="table">
        <thead>
            <tr><th>Nom</th><th>E-mail</th><th>Rôle</th><th>Domaine</th><th>Service</th><th>Tickets en cours</th><th>Statut</th><th></th></tr>
        </thead>
        <tbody>
        <?php foreach ($utilisateurs as $u): ?>
            <tr class="<?= (int) $u['actif'] === 1 ? '' : 'ligne-inactive' ?>">
                <td><?= e($u['prenom'] . ' ' . $u['nom']) ?></td>
                <td><?= e($u['email']) ?></td>
                <td><?= e($u['role_libelle']) ?></td>
                <td><?= $u['domaine'] !== null ? e($u['domaine']) : '<span class="muted">—</span>' ?></td>
                <td><?= e((string) $u['service']) ?></td>
                <td><?= $u['role'] === 'technicien' ? (int) $u['tickets_en_cours'] : '<span class="muted">—</span>' ?></td>
                <td><?= badge_compte($u) ?></td>
                <td><a class="btn btn-secondary btn-small" href="<?= e(url('/admin/utilisateurs/' . $u['id'])) ?>">Modifier</a></td>
            </tr>
        <?php endforeach; ?>
        </tbody>
    </table>
</div>

<section class="panel panel-form">
    <h2>Créer un compte</h2>
    <form method="post" action="<?= e(url('/admin/utilisateurs')) ?>" novalidate>
        <?= csrf_field() ?>
        <?php require BASE_PATH . '/app/Views/admin/_champs_utilisateur.php'; ?>

        <div class="field">
            <label for="mot_de_passe">Mot de passe provisoire</label>
            <input type="password" id="mot_de_passe" name="mot_de_passe" autocomplete="new-password" required>
            <p class="hint">8 caractères minimum, avec au moins une lettre et un chiffre.</p>
        </div>

        <div class="form-actions">
            <button type="submit" class="btn btn-primary btn-auto">Créer le compte</button>
        </div>
    </form>
</section>