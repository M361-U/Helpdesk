<div class="page-head">
    <h1>Déclarer un incident</h1>
</div>

<form method="post" action="<?= e(url('/tickets')) ?>" class="form-card"  enctype="multipart/form-data" novalidate>
    <?= csrf_field() ?>

    <div class="field">
        <label for="titre">Titre</label>
        <input type="text" id="titre" name="titre" maxlength="150" required
               placeholder="Ex. : Plus d'accès au Wi-Fi dans le bureau 12"
               value="<?= e($ancien['titre'] ?? '') ?>">
    </div>

    <div class="field-row">
        <div class="field">
            <label for="categorie_id">Catégorie</label>
            <select id="categorie_id" name="categorie_id" required data-equipe="equipe-info">
                <option value="">Choisir…</option>
                <?php foreach ($categories as $domaine => $liste): ?>
                    <optgroup label="<?= e($domaine) ?>">
                        <?php foreach ($liste as $c): ?>
                            <option value="<?= (int) $c['id'] ?>"
                                <?= (int) ($ancien['categorie_id'] ?? 0) === (int) $c['id'] ? 'selected' : '' ?>>
                                <?= e($c['libelle']) ?>
                            </option>
                        <?php endforeach; ?>
                    </optgroup>
                <?php endforeach; ?>
            </select>
            <p class="hint">La catégorie détermine l'équipe qui prendra votre demande en charge.</p>
            <p class="equipe-info" id="equipe-info" hidden></p>
        </div>

        <div class="field">
            <label for="urgence">Urgence</label>
            <select id="urgence" name="urgence" required>
                <?php foreach ($urgences as $u): ?>
                    <option value="<?= e($u) ?>"
                        <?= ($ancien['urgence'] ?? 'normale') === $u ? 'selected' : '' ?>>
                        <?= e(libelle_urgence($u)) ?>
                    </option>
                <?php endforeach; ?>
            </select>
            <p class="hint">Critique : service bloqué pour plusieurs personnes.</p>
        </div>
    </div>

    <div class="field">
        <label for="description">Description</label>
        <textarea id="description" name="description" rows="6" required maxlength="5000" data-compteur data-min="10"
                  placeholder="Décrivez le problème : que s'est-il passé, depuis quand, message d'erreur éventuel…"><?= e($ancien['description'] ?? '') ?></textarea>
    </div>

    <div class="field">
        <label for="fichiers">Pièces jointes <span class="muted">(facultatif)</span></label>
        <input type="file" id="fichiers" name="fichiers[]" multiple
               data-fichiers data-max-mo="2" data-max-nb="3" data-ext="jpg,jpeg,png,pdf,txt,log"
               accept=".jpg,.jpeg,.png,.pdf,.txt,.log">
        <p class="hint">Capture d'écran, journal d'erreur… 3 fichiers maximum, 2 Mo chacun (JPG, PNG, PDF, TXT, LOG).</p>
    </div>
    
    <div class="form-actions">
        <button type="submit" class="btn btn-primary btn-auto">Envoyer le ticket</button>
        <a class="btn btn-secondary" href="<?= e(url('/tickets')) ?>">Annuler</a>
    </div>
</form>