<section class="hero">
    <div class="wrap hero-grid">
        <div class="hero-copy">
            <p class="hero-kicker"><span aria-hidden="true"></span> Assistance informatique · INPTIC</p>
            <h1>Un incident déclaré, le bon technicien prévenu.</h1>
            <p class="lead">Signalez votre problème en quelques étapes. Votre demande est dirigée vers la bonne équipe et vous pouvez suivre son traitement.</p>
            <div class="hero-actions">
                <?php if (($utilisateurCourant !== null)): ?>
                    <a class="btn btn-primary btn-lg" href="<?= e(url('/dashboard')) ?>">Accéder à mon espace</a>
                <?php else: ?>
                    <a class="btn btn-primary btn-lg" href="<?= e(url('/login')) ?>">Se connecter</a>
                    <a class="btn btn-outline btn-lg" href="<?= e(url('/inscription')) ?>">Créer un compte</a>
                <?php endif; ?>
            </div>
            <ul class="hero-points" aria-label="Les avantages du service">
                <li><span aria-hidden="true">✓</span> Demande suivie</li>
                <li><span aria-hidden="true">✓</span> Équipe adaptée</li>
                <li><span aria-hidden="true">✓</span> Notifications par e-mail</li>
            </ul>
        </div>

        <div class="routage" aria-label="Exemple de routage d'un ticket">
            <div class="routage-entete">
                <span class="routage-indicateur" aria-hidden="true"></span>
                <span>Affectation automatique</span>
                <span class="routage-etat">Activée</span>
            </div>
            <div class="routage-entree">
                <span class="ref">Exemple de demande <span class="routage-urgence">Urgence haute</span></span>
                <strong>Le Wi-Fi du 2e étage ne fonctionne plus</strong>
            </div>
            <div class="routage-voies">
                <div class="voie voie-reseau voie-choisie"><div><strong>Réseau</strong><span>2 tickets en cours</span></div><b>Assigné</b></div>
                <div class="voie voie-systeme"><div><strong>Système</strong><span>5 tickets en cours</span></div></div>
                <div class="voie voie-applicatif"><div><strong>Applicatif</strong><span>4 tickets en cours</span></div></div>
            </div>
        </div>
    </div>
</section>

<section class="section section-alt" id="fonctionnement">
    <div class="wrap">
        <h2>Du signalement à la résolution</h2>
        <p class="section-intro">Votre demande suit un parcours clair, de sa création jusqu’à sa clôture.</p>
        <ol class="steps">
            <li><h3>Décrivez</h3><p>Indiquez le problème et son urgence. Ajoutez une capture si elle peut aider.</p></li>
            <li><h3>Votre demande est dirigée</h3><p>Le ticket est transmis à l’équipe compétente selon sa catégorie.</p></li>
            <li><h3>Suivez le traitement</h3><p>Consultez l’avancement de votre ticket depuis votre espace personnel.</p></li>
            <li><h3>Recevez les nouvelles</h3><p>Les notifications par e-mail vous tiennent informé des changements.</p></li>
        </ol>
    </div>
</section>

<?php if (!($utilisateurCourant !== null)): ?>
<section class="section" id="acces">
    <div class="wrap">
        <p class="section-kicker">Prêt à commencer ?</p>
        <h2>Accéder à l'application</h2>
        <p class="section-intro">Connectez-vous ou créez votre compte pour déclarer et suivre vos demandes.</p>
        <div class="acces">
            <article class="acces-carte">
                <span class="acces-etiquette">Déjà inscrit</span>
                <h3> Déjà un compte ? </h3>
                <p>Connectez-vous avec votre adresse e-mail : vous arrivez directement sur votre espace.</p>
                <a class="btn btn-primary" href="<?= e(url('/login')) ?>">Se connecter</a>
            </article>
            <article class="acces-carte">
                <span class="acces-etiquette">Première visite</span>
                <h3>Je n'ai pas encore de compte</h3>
                <p>L'inscription est réservée au personnel : code d'accréditation, code reçu par e-mail, puis validation par la DSI.</p>
                <a class="btn btn-secondary" href="<?= e(url('/inscription')) ?>">Créer un compte</a>
            </article>
        </div>
    </div>
</section>
<?php endif; ?>