<div class="kpis">
    <div class="kpi">
        <p class="kpi-label">Mes tickets ouverts</p>
        <p class="kpi-value" data-kpi="ouverts"><?= (int) $kpi['ouverts'] ?></p>
        <p class="kpi-note">en cours de traitement</p>
    </div>
    <div class="kpi <?= $kpi['a_confirmer'] > 0 ? 'kpi-attention' : '' ?>">
        <p class="kpi-label">À confirmer</p>
        <p class="kpi-value" data-kpi="a_confirmer"><?= (int) $kpi['a_confirmer'] ?></p>
        <p class="kpi-note"><?= $kpi['a_confirmer'] > 0 ? 'Votre confirmation est attendue' : 'rien à confirmer' ?></p>
    </div>
    <div class="kpi">
        <p class="kpi-label">Tickets clôturés</p>
        <p class="kpi-value" data-kpi="clotures"></p>
        <p class="kpi-note">sur <?= (int) $kpi['total'] ?> déclarés</p>
    </div>
    <div class="kpi">
        <p class="kpi-label">Délai moyen de résolution</p>
        <p class="kpi-value" data-kpi="delai_moyen"></p>
        <p class="kpi-note">de mes tickets résolus</p>
    </div>
</div>

<div class="section-head">
    <h2>Mes derniers tickets</h2>
    <a class="btn btn-primary btn-auto" href="<?= e(url('/tickets/nouveau')) ?>">Nouveau ticket</a>
</div>

<?php if (!$recents): ?>
    <div class="empty"><p>Vous n'avez encore déclaré aucun incident.</p></div>
<?php else:
    $lignes = $recents; $avecTechnicien = true;
    require BASE_PATH . '/app/Views/dashboard/_liste.php';
    ?>
    <p class="more"><a href="<?= e(url('/tickets')) ?>">Voir tous mes tickets &rarr;</a></p>
<?php endif; ?>