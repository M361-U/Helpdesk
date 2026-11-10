<?php $tauxSla = $kpi['avec_resolution'] > 0 ? pourcentage($kpi['sla_respectes'], $kpi['avec_resolution']) : null; ?>
<div class="kpis">
    <div class="kpi">
        <p class="kpi-label">Tickets à traiter</p>
        <p class="kpi-value" data-kpi="ouverts"><?= (int) $kpi['ouverts'] ?></p>
        <p class="kpi-note">assignés, en cours ou en attente</p>
    </div>
    <div class="kpi <?= $kpi['urgents'] > 0 ? 'kpi-attention' : '' ?>">
        <p class="kpi-label">Urgents</p>
        <p class="kpi-value" data-kpi="urgents"><?= (int) $kpi['urgents'] ?></p>
        <p class="kpi-note">urgence haute ou critique</p>
    </div>
    <div class="kpi <?= $kpi['en_retard'] > 0 ? 'kpi-alerte' : '' ?>">
        <p class="kpi-label">En retard</p>
        <p class="kpi-value" data-kpi="en_retard"><?= (int) $kpi['en_retard'] ?></p>
        <p class="kpi-note"><?= $kpi['en_retard'] > 0 ? 'Échéance SLA dépassée' : 'aucun retard' ?></p>
    </div>
    <div class="kpi">
        <p class="kpi-label">Tickets résolus</p>
        <p class="kpi-value" data-kpi="avec_resolution"><?= (int) $kpi['avec_resolution'] ?></p>
        <p class="kpi-note">depuis le début</p>
    </div>
    <div class="kpi">
        <p class="kpi-label">Délai moyen</p>
        <p class="kpi-value" data-kpi="delai_moyen"><?= e(delai_moyen_fr($kpi['delai_moyen'])) ?></p>
        <p class="kpi-note"><?= $tauxSla !== null ? 'SLA respecté : ' . $tauxSla . ' %' : 'pas encore de résolution' ?></p>
    </div>
</div>

<div class="section-head">
    <h2>À traiter en priorité</h2>
    <a href="<?= e(url('/tickets')) ?>">Tous mes tickets &rarr;</a>
</div>

<?php if (!$prioritaires): ?>
    <div class="empty"><p>Aucun ticket en attente de traitement.</p></div>
<?php else:
    $lignes = $prioritaires; $avecTechnicien = false;
    require BASE_PATH . '/app/Views/dashboard/_liste.php';
endif; ?>