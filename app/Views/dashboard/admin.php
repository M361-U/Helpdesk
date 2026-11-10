<?php if (!empty($demandes)): ?>
<div class="alert alert-warning"><?= (int) $demandes ?> demande(s) d'inscription en attente de validation.
    <a href="<?= e(url('/admin/utilisateurs')) ?>#demandes">Les traiter</a></div>
<?php endif; ?>
<?php
$tauxSla    = $kpi['avec_resolution'] > 0 ? pourcentage($kpi['sla_respectes'], $kpi['avec_resolution']) : null;
$maxStatut  = max(1, ...array_values($statuts));
$maxDomaine = max(1, ...array_column($domaines, 'total'));
$maxJour    = max(1, ...array_values($jours));
$maxCharge  = max(1, ...array_column($techniciens, 'en_cours') ?: [1]);
// Classe de couleur d'un domaine : « Réseau » => dom-reseau, « Système » => dom-systeme, « Applicatif » => dom-applicatif
$cd = fn(?string $libelle): string => 'dom-' . preg_replace('/[^a-z]/', '', strtr(mb_strtolower((string) $libelle), ['é' => 'e', 'è' => 'e', 'ê' => 'e']));
?>

<div class="kpis kpis-3">
    <div class="kpi">
        <p class="kpi-label">Tickets ouverts</p>
        <p class="kpi-value" data-kpi="ouverts"><?= (int) $kpi['ouverts'] ?></p>
        <p class="kpi-note">sur <?= (int) $kpi['total'] ?> au total</p>
    </div>
    <div class="kpi <?= $kpi['non_assignes'] > 0 ? 'kpi-attention' : '' ?>">
        <p class="kpi-label">Non assignés</p>
        <p class="kpi-value" data-kpi="non_assignes"><?= (int) $kpi['non_assignes'] ?></p>
        <p class="kpi-note"><?= $kpi['non_assignes'] > 0 ? 'À assigner manuellement' : 'tout est assigné' ?></p>
    </div>
    <div class="kpi <?= $kpi['en_retard'] > 0 ? 'kpi-alerte' : '' ?>">
        <p class="kpi-label">En retard</p>
        <p class="kpi-value" data-kpi="en_retard"><?= (int) $kpi['en_retard'] ?></p>
        <p class="kpi-note"><?= $kpi['en_retard'] > 0 ? 'Échéance SLA dépassée' : 'aucun retard' ?></p>
    </div>
    <div class="kpi">
        <p class="kpi-label">Tickets résolus</p>
        <p class="kpi-value" data-kpi="resolus"><?= (int) $kpi['resolus'] ?></p>
        <p class="kpi-note"><?= $kpi['total'] > 0 ? pourcentage($kpi['resolus'], $kpi['total']) . ' % du total' : '—' ?></p>
    </div>
    <div class="kpi">
        <p class="kpi-label">Délai moyen de résolution</p>
        <p class="kpi-value" data-kpi="delai_moyen"><?= e(delai_moyen_fr($kpi['delai_moyen'])) ?></p>
        <p class="kpi-note">de la création à la résolution</p>
    </div>
    <div class="kpi">
        <p class="kpi-label">SLA respecté</p>
        <p class="kpi-value" data-kpi="taux_sla"><?= $tauxSla !== null ? $tauxSla . ' %' : '—' ?></p>
        <p class="kpi-note"><?= (int) $kpi['sla_respectes'] ?> sur <?= (int) $kpi['avec_resolution'] ?> tickets résolus</p>
    </div>
</div>

<div class="dash-grid">
    <section class="panel">
        <h2>Tickets par statut</h2>
        <ul class="bars">
            <?php foreach ($statuts as $statut => $n): ?>
                <li class="st-<?= e($statut) ?>" title="<?= e(libelle_statut($statut)) ?> : <?= (int) $n ?>">
                    <span class="bar-label"><?= e(libelle_statut($statut)) ?></span>
                    <span class="bar-track"><span class="bar-fill" style="width: <?= pourcentage($n, $maxStatut) ?>%"></span></span>
                    <span class="bar-val"><?= (int) $n ?></span>
                </li>
            <?php endforeach; ?>
        </ul>
    </section>

    <section class="panel">
        <h2>Volume par domaine</h2>
        <ul class="bars">
            <?php foreach ($domaines as $d): ?>
                <li class="<?= e($cd($d['domaine'])) ?>" title="<?= e($d['domaine']) ?> : <?= (int) $d['total'] ?> tickets, dont <?= (int) $d['ouverts'] ?> ouverts">
                    <span class="bar-label"><?= e($d['domaine']) ?></span>
                    <span class="bar-track"><span class="bar-fill" style="width: <?= pourcentage((int) $d['total'], $maxDomaine) ?>%"></span></span>
                    <span class="bar-val"><?= (int) $d['total'] ?> <small>(<?= (int) $d['ouverts'] ?> ouverts)</small></span>
                </li>
            <?php endforeach; ?>
        </ul>
    </section>
</div>

<section class="panel">
    <h2>Tickets créés ces 14 derniers jours</h2>
    <div class="columns" role="img" aria-label="Nombre de tickets créés par jour sur 14 jours">
        <?php foreach ($jours as $jour => $n): ?>
            <div class="column" title="<?= e(date('d/m/Y', strtotime($jour))) ?> : <?= (int) $n ?> ticket(s)">
                <span class="column-val"><?= $n === $maxJour ? (int) $n : '' ?></span>
                <span class="column-fill" style="height: <?= pourcentage($n, $maxJour) ?>%"></span>
                <span class="column-label"><?= e(date('d/m', strtotime($jour))) ?></span>
            </div>
        <?php endforeach; ?>
    </div>
</section>

<section class="panel">
    <h2>Charge des techniciens</h2>
    <div class="table-wrap table-flat">
        <table class="table">
            <thead>
                <tr>
                    <th>Technicien</th>
                    <th>Domaine</th>
                    <th>Tickets en cours</th>
                    <th>Résolus</th>
                    <th>Délai moyen</th>
                </tr>
            </thead>
            <tbody>
            <?php foreach ($techniciens as $t): ?>
                <tr>
                    <td><?= e($t['prenom'] . ' ' . $t['nom']) ?></td>
                    <td><span class="badge badge-<?= e($cd($t['domaine'])) ?>"><?= e($t['domaine']) ?></span></td>
                    <td>
                        <span class="bar-inline <?= e($cd($t['domaine'])) ?>">
                            <span class="bar-track"><span class="bar-fill" style="width: <?= pourcentage((int) $t['en_cours'], $maxCharge) ?>%"></span></span>
                            <span class="bar-val"><?= (int) $t['en_cours'] ?></span>
                        </span>
                    </td>
                    <td><?= (int) $t['resolus'] ?></td>
                    <td><?= e(delai_moyen_fr($t['delai_moyen'] !== null ? (float) $t['delai_moyen'] : null)) ?></td>
                </tr>
            <?php endforeach; ?>
            </tbody>
        </table>
    </div>
</section>

<section class="panel">
    <h2>À traiter en priorité</h2>
    <?php if (!$aTraiter): ?>
        <p class="muted">Rien à signaler : aucun ticket non assigné ni en retard.</p>
    <?php else: ?>
    <div class="table-wrap table-flat">
        <table class="table">
            <thead>
                <tr><th>Référence</th><th>Titre</th><th>Domaine</th><th>Urgence</th><th>Motif</th><th>Technicien</th></tr>
            </thead>
            <tbody>
            <?php foreach ($aTraiter as $t): ?>
                <tr>
                    <td><a class="ref" href="<?= e(url('/tickets/' . $t['id'])) ?>"><?= e($t['reference']) ?></a></td>
                    <td><?= e($t['titre']) ?></td>
                    <td><span class="badge badge-<?= e($cd($t['domaine'])) ?>"><?= e($t['domaine']) ?></span></td>
                    <td><span class="badge badge-urg-<?= e($t['urgence']) ?>"><?= e(libelle_urgence($t['urgence'])) ?></span></td>
                    <td>
                        <?php if ($t['statut'] === 'nouveau'): ?>
                            <span class="badge badge-st-nouveau">Non assigné</span>
                        <?php else: ?>
                            <span class="badge badge-retard">En retard</span>
                        <?php endif; ?>
                    </td>
                    <td><?= $t['tech_nom'] !== null ? e($t['tech_prenom'] . ' ' . $t['tech_nom']) : '<span class="muted">—</span>' ?></td>
                </tr>
            <?php endforeach; ?>
            </tbody>
        </table>
    </div>
    <?php endif; ?>
</section>