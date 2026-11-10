<div class="page-head">
    <h1>E-mails de l'application</h1>
</div>

<?php if (!$actif): ?>
    <div class="alert alert-warning">
        L'envoi réel est désactivé (mode démonstration) : les messages sont enregistrés ici mais ne partent pas.
        Pour les envoyer, renseignez la section « mail » de <code>config/config.php</code>.
    </div>
<?php endif; ?>

<?php if (!$messages): ?>
    <div class="empty"><p>Aucun e-mail pour le moment.</p></div>
<?php else: ?>
<div class="table-wrap">
    <table class="table">
        <thead>
            <tr><th>Date</th><th>Destinataire</th><th>Sujet</th><th>État</th></tr>
        </thead>
        <tbody>
        <?php foreach ($messages as $m): ?>
            <tr>
                <td><?= e(date_fr($m['cree_le'])) ?></td>
                <td><?= e($m['destinataire']) ?></td>
                <td>
                    <details>
                        <summary><?= e($m['sujet']) ?></summary>
                        <pre class="mail-corps"><?= e($m['corps']) ?></pre>
                    </details>
                </td>
                <td>
                    <?php if ((int) $m['envoye'] === 1): ?>
                        <span class="badge badge-st-resolu">Envoyé</span>
                    <?php elseif ($m['erreur'] !== null): ?>
                        <span class="badge badge-retard" title="<?= e($m['erreur']) ?>">Échec</span>
                    <?php else: ?>
                        <span class="badge badge-st-cloture">Enregistré</span>
                    <?php endif; ?>
                </td>
            </tr>
        <?php endforeach; ?>
        </tbody>
    </table>
</div>
<?php endif; ?>