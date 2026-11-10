<div class="page-head">
    <h1>Tableau de bord</h1>
    <p class="muted">Bienvenue <?= e($utilisateur['prenom']) ?> (<?= e(libelle_role($utilisateur['role'])) ?>)</p>
</div>
<p class="maj muted" data-actualiser="<?= e(url('/api/statistiques')) ?>">
    Chiffres actualisés automatiquement toutes les 30 secondes · dernière mise à jour : <span data-maj>à l'ouverture de la page</span>
</p>
<?php
// Chaque rôle a son propre tableau de bord
$vueRole = match ($utilisateur['role']) {
    'admin'      => 'admin',
    'technicien' => 'technicien',
    default      => 'employe',
};
require BASE_PATH . '/app/Views/dashboard/' . $vueRole . '.php';