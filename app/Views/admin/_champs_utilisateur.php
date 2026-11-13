<?php
$v = $compte ?? $ancien ?? [];
?>
<div class="field-row">
    <div class="field">
        <label for="prenom">Prénom</label>
        <input type="text" id="prenom" name="prenom" maxlength="80" required value="<?= e((string) ($v['prenom'] ?? '')) ?>">
    </div>
    <div class="field">
        <label for="nom">Nom</label>
        <input type="text" id="nom" name="nom" maxlength="80" required value="<?= e((string) ($v['nom'] ?? '')) ?>">
    </div>
</div>

<div class="field-row">
    <div class="field">
        <label for="email">Adresse e-mail</label>
        <input type="email" id="email" name="email" maxlength="150" required value="<?= e((string) ($v['email'] ?? '')) ?>">
    </div>
    <div class="field">
        <label for="service">Service <span class="muted">(facultatif)</span></label>
        <input type="text" id="service" name="service" maxlength="100" value="<?= e((string) ($v['service'] ?? '')) ?>">
    </div>
</div>

<div class="field-row">
    <div class="field">
        <label for="role_id">Rôle</label>
        <select id="role_id" name="role_id" required data-role-domaine="champ-domaine">
            <option value="">Choisir…</option>
            <?php foreach ($roles as $r): ?>
                <option value="<?= (int) $r['id'] ?>" data-code="<?= e($r['code']) ?>"
                    <?= (int) ($v['role_id'] ?? 0) === (int) $r['id'] ? 'selected' : '' ?>><?= e($r['libelle']) ?></option>
            <?php endforeach; ?>
        </select>
    </div>
    <div class="field" id="champ-domaine">
        <label for="domaine_id">Domaine du technicien</label>
        <select id="domaine_id" name="domaine_id">
            <option value="">Choisir…</option>
            <?php foreach ($domaines as $d): ?>
                <option value="<?= (int) $d['id'] ?>"
                    <?= (int) ($v['domaine_id'] ?? 0) === (int) $d['id'] ? 'selected' : '' ?>><?= e($d['libelle']) ?></option>
            <?php endforeach; ?>
        </select>
        <p class="hint">Obligatoire pour un technicien : c'est ce domaine qui sert au routage automatique.</p>
    </div>
</div>