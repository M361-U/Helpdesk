<?php /* Liste de tickets : $lignes (tableau), $avecTechnicien (bool) */ ?>
<div class="table-wrap">
    <table class="table">
        <thead>
            <tr>
                <th>Référence</th>
                <th>Titre</th>
                <th>Domaine</th>
                <th>Urgence</th>
                <th>Statut</th>
                <?php if ($avecTechnicien): ?><th>Technicien</th><?php endif; ?>
            </tr>
        </thead>
        <tbody>
        <?php foreach ($lignes as $t): ?>
            <tr>
                <td><a class="ref" href="<?= e(url('/tickets/' . $t['id'])) ?>"><?= e($t['reference']) ?></a></td>
                <td><?= e($t['titre']) ?></td>
                <td><?= e($t['domaine']) ?></td>
                <td><span class="badge badge-urg-<?= e($t['urgence']) ?>"><?= e(libelle_urgence($t['urgence'])) ?></span></td>
                <td><span class="badge badge-st-<?= e($t['statut']) ?>"><?= e(libelle_statut($t['statut'])) ?></span></td>
                <?php if ($avecTechnicien): ?>
                    <td><?= $t['tech_nom'] !== null ? e($t['tech_prenom'] . ' ' . $t['tech_nom']) : '<span class="muted">Non assigné</span>' ?></td>
                <?php endif; ?>
            </tr>
        <?php endforeach; ?>
        </tbody>
    </table>
</div>