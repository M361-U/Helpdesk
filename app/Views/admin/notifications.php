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
<div class="admin-bulk-bar">
    <label class="admin-select-all">
        <input type="checkbox" data-selection-tout="notifications">
        Tout sélectionner (<?= count($messages) ?> affiché<?= count($messages) > 1 ? 's' : '' ?>)
    </label>
    <form id="bulk-notifications" method="post" action="<?= e(url('/admin/notifications/suppression-selection')) ?>"
          data-confirmer="Supprimer les e-mails sélectionnés ? Cette action est définitive.">
        <?= csrf_field() ?>
        <button type="submit" class="btn btn-danger btn-small">Supprimer la sélection</button>
    </form>
    <form method="post" action="<?= e(url('/admin/notifications/suppression-tout')) ?>"
          data-confirmer="Supprimer définitivement tous les e-mails enregistrés ?">
        <?= csrf_field() ?>
        <button type="submit" class="btn btn-danger btn-small">Supprimer tous les e-mails</button>
    </form>
</div>
<div class="table-wrap">
    <table class="table">
        <thead>
            <tr><th><span class="sr-only">Sélection</span></th><th></th><th>Date</th><th>Destinataire</th><th>Sujet</th><th>État</th></tr>
        </thead>
        <tbody>
        <?php foreach ($messages as $m): ?>
            <tr>
                <td>
                    <input type="checkbox" name="ids[]" value="<?= (int) $m['id'] ?>" form="bulk-notifications"
                           data-selection-item="notifications" aria-label="Sélectionner l'e-mail destiné à <?= e($m['destinataire']) ?>">
                </td>
                <td>
                    <form method="post" action="<?= e(url('/admin/notifications/' . $m['id'] . '/suppression')) ?>"
                          class="admin-delete-form"
                          data-confirmer="Supprimer définitivement l'e-mail destiné à <?= e($m['destinataire']) ?> ?">
                        <?= csrf_field() ?>
                        <button type="submit" class="btn btn-poubelle" aria-label="Supprimer l'e-mail destiné à <?= e($m['destinataire']) ?>" title="Supprimer">
                            <?= icone_poubelle() ?>
                        </button>
                    </form>
                </td>
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