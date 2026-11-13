<div class="page-head">
    <h1>Journal d'audit</h1>
</div>

<form method="get" action="<?= e(url('/admin/audit')) ?>" class="toolbar">
    <select name="action" aria-label="Filtrer par action">
        <option value="">Toutes les actions</option>
        <?php foreach ($actions as $a): ?>
            <option value="<?= e($a) ?>" <?= $a === $action ? 'selected' : '' ?>><?= e(libelle_audit($a)) ?></option>
        <?php endforeach; ?>
    </select>
    <input type="search" name="q" value="<?= e($recherche) ?>" placeholder="Rechercher (personne, référence, e-mail…)" aria-label="Rechercher dans le journal">
    <button type="submit" class="btn btn-primary btn-small">Filtrer</button>
    <?php if ($action !== '' || $recherche !== ''): ?>
        <a class="btn btn-secondary btn-small" href="<?= e(url('/admin/audit')) ?>">Réinitialiser</a>
    <?php endif; ?>
    <span class="toolbar-count"><?= (int) $total ?> entrée<?= $total > 1 ? 's' : '' ?></span>
</form>

<?php if (!$entrees): ?>
    <div class="empty"><p>Aucune entrée ne correspond.</p></div>
<?php else: ?>
<div class="admin-bulk-bar">
    <label class="admin-select-all">
        <input type="checkbox" data-selection-tout="audit">
        Tout sélectionner (<?= count($entrees) ?> affichée<?= count($entrees) > 1 ? 's' : '' ?>)
    </label>
    <form id="bulk-audit" method="post" action="<?= e(url('/admin/audit/suppression-selection')) ?>"
          data-confirmer="Supprimer les entrées sélectionnées ? Cette action est définitive.">
        <?= csrf_field() ?>
        <button type="submit" class="btn btn-danger btn-small">Supprimer la sélection</button>
    </form>
    <form method="post" action="<?= e(url('/admin/audit/suppression-tout')) ?>"
          data-confirmer="Supprimer définitivement toutes les entrées du journal ?">
        <?= csrf_field() ?>
        <button type="submit" class="btn btn-danger btn-small">Supprimer tout le journal</button>
    </form>
</div>
<div class="table-wrap">
    <table class="table">
        <thead>
            <tr><th><span class="sr-only">Sélection</span></th><th></th><th>Date</th><th>Utilisateur</th><th>Action</th><th>Détails</th><th>Adresse IP</th></tr>
        </thead>
        <tbody>
        <?php foreach ($entrees as $l): ?>
            <tr>
                <td>
                    <input type="checkbox" name="ids[]" value="<?= (int) $l['id'] ?>" form="bulk-audit"
                           data-selection-item="audit" aria-label="Sélectionner cette entrée du journal">
                </td>
                <td>
                    <form method="post" action="<?= e(url('/admin/audit/' . $l['id'] . '/suppression')) ?>"
                          class="admin-delete-form"
                          data-confirmer="Supprimer définitivement cette entrée du journal ?">
                        <?= csrf_field() ?>
                        <button type="submit" class="btn btn-poubelle" aria-label="Supprimer cette entrée du journal" title="Supprimer">
                            <?= icone_poubelle() ?>
                        </button>
                    </form>
                </td>
                <td><?= e(date('d/m/Y H:i:s', strtotime($l['cree_le']))) ?></td>
                <td><?= $l['nom'] !== null ? e($l['prenom'] . ' ' . $l['nom']) : '<span class="muted">—</span>' ?></td>
                <td><span class="badge badge-audit"><?= e(libelle_audit($l['action'])) ?></span></td>
                <td><?= e((string) $l['details']) ?></td>
                <td class="muted"><?= e((string) $l['adresse_ip']) ?></td>
            </tr>
        <?php endforeach; ?>
        </tbody>
    </table>
</div>

<?php
$lien = fn(int $p): string => url('/admin/audit') . '?' . http_build_query(['action' => $action, 'q' => $recherche, 'page' => $p]);
?>
<nav class="pager" aria-label="Pagination">
    <?php if ($page > 1): ?><a class="btn btn-secondary btn-small" href="<?= e($lien($page - 1)) ?>">&larr; Précédent</a><?php endif; ?>
    <span class="muted">Page <?= (int) $page ?> sur <?= (int) $pages ?></span>
    <?php if ($page < $pages): ?><a class="btn btn-secondary btn-small" href="<?= e($lien($page + 1)) ?>">Suivant &rarr;</a><?php endif; ?>
</nav>
<?php endif; ?>