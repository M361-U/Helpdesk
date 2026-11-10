<?php
$ferme    = in_array($ticket['statut'], ['resolu', 'cloture'], true);
$enRetard = !$ferme
            && $ticket['echeance_sla'] !== null
            && strtotime($ticket['echeance_sla']) < time();
$dureeResolution = $ticket['resolu_le'] !== null
    ? strtotime($ticket['resolu_le']) - strtotime($ticket['cree_le'])
    : null;
$slaRespecte = $ticket['resolu_le'] !== null && $ticket['echeance_sla'] !== null
    ? strtotime($ticket['resolu_le']) <= strtotime($ticket['echeance_sla'])
    : null;
$base = '/tickets/' . $ticket['id'];
?>
<p class="back"><a href="<?= e(url('/tickets')) ?>">&larr; Retour à la liste</a></p>

<div class="page-head">
    <h1><span class="ref"><?= e($ticket['reference']) ?></span> <?= e($ticket['titre']) ?></h1>
    <div class="page-head-actions">
        <span class="badge badge-st-<?= e($ticket['statut']) ?> badge-lg"><?= e(libelle_statut($ticket['statut'])) ?></span>
        <a class="btn btn-secondary btn-small" href="<?= e(url($base . '/pdf')) ?>">Fiche PDF</a>
    </div>
</div>

<div class="detail-layout">
<div class="detail-main">

<section class="panel">
    <h2>Description</h2>
    <p class="description"><?= nl2br(e($ticket['description'])) ?></p>
</section>

<?php if ($actions): ?>
<section class="panel">
    <h2>Actions</h2>
    <div class="actions-row">
        <?php foreach ($actions as [$statut, $libelle]): ?>
            <form method="post" action="<?= e(url($base . '/statut')) ?>"
                  <?php if ($statut === 'cloture'): ?>data-confirmer="Clôturer ce ticket ? Cette action est définitive."<?php elseif ($statut === 'resolu'): ?>data-confirmer="Marquer ce ticket comme résolu ?"<?php endif; ?>>
                <?= csrf_field() ?>
                <input type="hidden" name="statut" value="<?= e($statut) ?>">
                <button type="submit" class="btn btn-auto <?= $statut === 'cloture' ? 'btn-success' : ($statut === 'resolu' ? 'btn-primary' : 'btn-secondary') ?>">
                    <?= e($libelle) ?>
                </button>
            </form>
        <?php endforeach; ?>
    </div>
</section>
<?php endif; ?>

<?php if ($techniciens): ?>
<section class="panel">
    <h2><?= $ticket['technicien_id'] === null ? 'Assigner le ticket' : 'Réassigner le ticket' ?></h2>
    <form method="post" action="<?= e(url($base . '/assignation')) ?>" class="inline-form"
          data-confirmer="Confirmer l'assignation à ce technicien ?">
        <?= csrf_field() ?>
        <select name="technicien_id" required>
            <option value="">Choisir un technicien du domaine <?= e($ticket['domaine']) ?>…</option>
            <?php foreach ($techniciens as $t): ?>
                <option value="<?= (int) $t['id'] ?>">
                    <?= e($t['prenom'] . ' ' . $t['nom']) ?> (<?= (int) $t['charge'] ?> en cours)
                </option>
            <?php endforeach; ?>
        </select>
        <button type="submit" class="btn btn-primary btn-auto">Valider</button>
    </form>
</section>
<?php endif; ?>

<section class="panel">
    <h2>Pièces jointes</h2>

    <?php if (!$pieces): ?>
        <p class="muted">Aucune pièce jointe.</p>
    <?php else: ?>
        <ul class="files">
            <?php foreach ($pieces as $p): ?>
                <li>
                    <a href="<?= e(url($base . '/pieces/' . $p['id'])) ?>"><?= e($p['nom_original']) ?></a>
                    <span class="muted">
                        <?= e(taille_fr((int) $p['taille'])) ?>
                        · <?= e($p['prenom'] !== null ? $p['prenom'] . ' ' . $p['nom'] : 'utilisateur supprimé') ?>
                        · <?= e(date_fr($p['cree_le'])) ?>
                    </span>
                </li>
            <?php endforeach; ?>
        </ul>
    <?php endif; ?>

    <?php if ($ticket['statut'] !== 'cloture'): ?>
    <form method="post" action="<?= e(url($base . '/pieces')) ?>" enctype="multipart/form-data" class="comment-form">
        <?= csrf_field() ?>
        <label for="fichiers">Ajouter des fichiers</label>
        <input type="file" id="fichiers" name="fichiers[]" multiple data-fichiers data-max-mo="2" data-max-nb="3" data-ext="jpg,jpeg,png,pdf,txt,log" accept=".jpg,.jpeg,.png,.pdf,.txt,.log" required>
        <p class="hint">3 fichiers maximum par envoi, 2 Mo chacun (JPG, PNG, PDF, TXT, LOG).</p>
        <button type="submit" class="btn btn-primary btn-auto">Envoyer</button>
    </form>
    <?php endif; ?>
</section>

<section class="panel">
    <h2>Commentaires</h2>

    <?php if (!$commentaires): ?>
        <p class="muted">Aucun commentaire pour le moment.</p>
    <?php endif; ?>
    <?php foreach ($commentaires as $c): ?>
        <div class="comment">
            <div class="comment-head">
                <strong><?= e($c['prenom'] . ' ' . $c['nom']) ?></strong>
                <span class="badge badge-role"><?= e(libelle_role($c['role'])) ?></span>
                <span class="muted"><?= e(date_fr($c['cree_le'])) ?></span>
            </div>
            <p><?= nl2br(e($c['contenu'])) ?></p>
        </div>
    <?php endforeach; ?>

    <?php if ($ticket['statut'] !== 'cloture'): ?>
    <form method="post" action="<?= e(url($base . '/commentaires')) ?>" class="comment-form">
        <?= csrf_field() ?>
        <label for="contenu">Ajouter un commentaire</label>
        <textarea id="contenu" name="contenu" rows="3" maxlength="2000" required></textarea>
        <button type="submit" class="btn btn-primary btn-auto">Publier</button>
    </form>
    <?php else: ?>
        <p class="muted">Ce ticket est clôturé : les commentaires sont fermés.</p>
    <?php endif; ?>
</section>

<section class="panel">
    <h2>Historique</h2>
    <ol class="timeline">
        <?php foreach ($historique as $h): ?>
            <li>
                <strong><?= e(libelle_statut($h['nouveau_statut'])) ?></strong>
                <span class="muted">
                    le <?= e(date_fr($h['modifie_le'])) ?>
                    par <?= $h['nom'] !== null ? e($h['prenom'] . ' ' . $h['nom']) : 'le système (routage automatique)' ?>
                </span>
            </li>
        <?php endforeach; ?>
    </ol>
</section>

<?php if (!empty($peutSupprimer)): ?>
<section class="panel panel-danger">
    <h2>Supprimer ce ticket</h2>
    <p class="muted">
        <?php if (\App\Core\Auth::utilisateur()['role'] === 'admin'): ?>
            Supprime définitivement le ticket, ses commentaires, son historique et ses pièces jointes. L'action est enregistrée dans le journal.
        <?php else: ?>
            Ce ticket n'a pas encore été traité : vous pouvez le supprimer s'il a été envoyé par erreur. Cette action est définitive.
        <?php endif; ?>
    </p>
    <form method="post" action="<?= e(url($base . '/suppression')) ?>"
          data-confirmer="Supprimer définitivement le ticket <?= e($ticket['reference']) ?> ? Cette action est irréversible.">
        <?= csrf_field() ?>
        <button type="submit" class="btn btn-danger btn-auto">Supprimer le ticket</button>
    </form>
</section>
<?php endif; ?>

</div>
<aside class="panel detail-side">
    <h2>Informations</h2>
    <dl class="infos">
        <dt>Catégorie</dt><dd><?= e($ticket['categorie']) ?></dd>
        <dt>Domaine</dt><dd><?= e($ticket['domaine']) ?></dd>
        <dt>Urgence</dt><dd><span class="badge badge-urg-<?= e($ticket['urgence']) ?>"><?= e(libelle_urgence($ticket['urgence'])) ?></span></dd>
        <dt>Demandeur</dt><dd><?= e($ticket['dem_prenom'] . ' ' . $ticket['dem_nom']) ?></dd>
        <dt>Technicien</dt>
        <dd><?= $ticket['tech_nom'] !== null ? e($ticket['tech_prenom'] . ' ' . $ticket['tech_nom']) : '<span class="muted">Non assigné</span>' ?></dd>
        <dt>Créé le</dt><dd><?= e(date_fr($ticket['cree_le'])) ?></dd>
        <dt>Échéance (SLA)</dt>
        <dd><?= e(date_fr($ticket['echeance_sla'])) ?>
            <?php if ($enRetard): ?><span class="badge badge-retard">En retard</span><?php endif; ?></dd>
        <?php if ($dureeResolution !== null): ?>
            <dt>Résolu le</dt><dd><?= e(date_fr($ticket['resolu_le'])) ?></dd>
            <dt>Délai de résolution</dt>
            <dd><?= e(duree_fr($dureeResolution)) ?>
                <span class="badge <?= $slaRespecte ? 'badge-st-resolu' : 'badge-retard' ?>"><?= $slaRespecte ? 'SLA respecté' : 'SLA dépassé' ?></span></dd>
        <?php endif; ?>
    </dl>
</aside>
</div>