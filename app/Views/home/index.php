<section class="hero">
    <div class="wrap hero-grid">
        <div>
            <h1>Un incident déclaré, le bon technicien prévenu.</h1>
            <p class="lead">Déclarez une panne en une minute. Le ticket est routé automatiquement vers l'équipe Réseau, Système ou Applicatif, selon sa catégorie et la charge de chacun.</p>
            <div class="hero-actions">
                <?php if (($utilisateurCourant !== null)): ?>
                    <a class="btn btn-primary btn-lg" href="<?= e(url('/dashboard')) ?>">Accéder à mon espace</a>
                <?php else: ?>
                    <a class="btn btn-primary btn-lg" href="<?= e(url('/login')) ?>">Se connecter</a>
                    <a class="btn btn-outline btn-lg" href="<?= e(url('/inscription')) ?>">Créer un compte</a>
                <?php endif; ?>
            </div>
        </div>

        <div class="routage" aria-label="Exemple de routage d'un ticket">
            <div class="routage-entree">
                <span class="ref">Exemple · urgence haute</span>
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
        <ol class="steps">
            <li><h3>Déclarer</h3><p>Décrivez le problème, choisissez la catégorie et l'urgence, joignez une capture si besoin.</p></li>
            <li><h3>Router</h3><p>Le ticket va au technicien du domaine qui a le moins de tickets en cours.</p></li>
            <li><h3>Traiter</h3><p>Le technicien prend en charge et fait avancer le statut. Vous êtes prévenu par e-mail.</p></li>
            <li><h3>Mesurer</h3><p>La DSI suit les délais, les retards et la charge de chaque technicien.</p></li>
        </ol>
    </div>
</section>

<?php if (!($utilisateurCourant !== null)): ?>
<section class="section" id="acces">
    <div class="wrap">
        <h2>Accéder à l'application</h2>
        <div class="acces">
            <article class="acces-carte">
                <h3>J'ai déjà un compte</h3>
                <p>Connectez-vous avec votre adresse e-mail : vous arrivez directement sur votre espace.</p>
                <a class="btn btn-primary" href="<?= e(url('/login')) ?>">Se connecter</a>
            </article>
            <article class="acces-carte">
                <h3>Je n'ai pas encore de compte</h3>
                <p>L'inscription est réservée au personnel : code d'accréditation, code reçu par e-mail, puis validation par la DSI.</p>
                <a class="btn btn-secondary" href="<?= e(url('/inscription')) ?>">Créer un compte</a>
            </article>
        </div>
    </div>
</section>
<?php endif; ?>