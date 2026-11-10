<div class="page-head">
    <h1><?= e($titre) ?></h1>
    <?php if ($role === 'employe'): ?>
        <a class="btn btn-primary btn-auto" href="<?= e(url('/tickets/nouveau')) ?>">Nouveau ticket</a>
    <?php endif; ?>
</div>

<?php if (!$tickets): ?>
    <div class="empty">
        <p>Aucun ticket pour le moment.</p>
        <?php if ($role === 'employe'): ?>
            <a class="btn btn-primary btn-auto" href="<?= e(url('/tickets/nouveau')) ?>">Déclarer un incident</a>
        <?php endif; ?>
    </div>
<?php else: ?>
<div class="toolbar" data-filtre="#table-tickets">
    <input type="search" data-f="texte" placeholder="Rechercher (référence, titre, personne…)" aria-label="Rechercher un ticket">
    <select data-f="statut" aria-label="Filtrer par statut">
        <option value="">Tous les statuts</option>
        <?php foreach (['nouveau', 'assigne', 'en_cours', 'en_attente', 'resolu', 'cloture'] as $st): ?>
            <option value="<?= e($st) ?>"><?= e(libelle_statut($st)) ?></option>
        <?php endforeach; ?>
    </select>
    <select data-f="urgence" aria-label="Filtrer par urgence">
        <option value="">Toutes les urgences</option>
        <?php foreach (['critique', 'haute', 'normale', 'basse'] as $ur): ?>
            <option value="<?= e($ur) ?>"><?= e(libelle_urgence($ur)) ?></option>
        <?php endforeach; ?>
    </select>
    <span class="toolbar-count" data-compte aria-live="polite"></span>
        <span class="toolbar-export">
        Exporter :
        <a class="btn btn-secondary btn-small" data-export href="<?= e(url('/tickets/export/csv')) ?>">CSV</a>
        <a class="btn btn-secondary btn-small" data-export href="<?= e(url('/tickets/export/pdf')) ?>">PDF</a>
    </span>
</div>

<div class="table-wrap">
    <table class="table" id="table-tickets">
        <thead>
            <tr>
                <th>Référence</th>
                <th>Titre</th>
                <th>Domaine</th>
                <th>Urgence</th>
                <th>Statut</th>
                <?php if ($role !== 'employe'): ?><th>Demandeur</th><?php endif; ?>
                <?php if ($role !== 'technicien'): ?><th>Technicien</th><?php endif; ?>
                <th>Créé le</th>
            </tr>
        </thead>
        <tbody>
        <?php foreach ($tickets as $t): ?>
            <tr data-statut="<?= e($t['statut']) ?>" data-urgence="<?= e($t['urgence']) ?>">
                <td><a class="ref" href="<?= e(url('/tickets/' . $t['id'])) ?>"><?= e($t['reference']) ?></a></td>
                <td><?= e($t['titre']) ?></td>
                <td><?= e($t['domaine']) ?></td>
                <td><span class="badge badge-urg-<?= e($t['urgence']) ?>"><?= e(libelle_urgence($t['urgence'])) ?></span></td>
                <td><span class="badge badge-st-<?= e($t['statut']) ?>"><?= e(libelle_statut($t['statut'])) ?></span></td>
                <?php if ($role !== 'employe'): ?>
                    <td><?= e($t['dem_prenom'] . ' ' . $t['dem_nom']) ?></td>
                <?php endif; ?>
                <?php if ($role !== 'technicien'): ?>
                    <td><?= $t['tech_nom'] !== null ? e($t['tech_prenom'] . ' ' . $t['tech_nom']) : '<span class="muted">Non assigné</span>' ?></td>
                <?php endif; ?>
                <td><?= e(date_fr($t['cree_le'])) ?></td>
            </tr>
        <?php endforeach; ?>
        </tbody>
    </table>
    <p class="empty-filter" data-vide hidden>Aucun ticket ne correspond à ces critères.</p>
</div>
<?php endif; ?>