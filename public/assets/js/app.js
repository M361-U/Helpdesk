/**
 * HelpDesk DSI : améliorations côté navigateur.
 * Principe : tout fonctionne sans JavaScript (le serveur contrôle tout) ;
 * ce fichier ne fait qu'améliorer le confort d'utilisation.
 */
(function () {
    'use strict';

    // ---------- 1. Compteur de caractères (textarea[data-compteur]) ----------
    document.querySelectorAll('textarea[data-compteur]').forEach(function (zone) {
        var max = parseInt(zone.getAttribute('maxlength'), 10) || 0;
        var min = parseInt(zone.dataset.min, 10) || 0;
        var info = document.createElement('p');
        info.className = 'hint compteur';
        zone.insertAdjacentElement('afterend', info);

        function maj() {
            var n = zone.value.length;
            var texte = n + (max ? ' / ' + max : '') + ' caractères';
            if (min && n < min) { texte += ' (minimum ' + min + ')'; }
            info.textContent = texte;
            info.classList.toggle('compteur-alerte', min > 0 && n < min && n > 0);
        }
        zone.addEventListener('input', maj);
        maj();
    });

    // ---------- 2. Contrôle des fichiers avant envoi (input[data-fichiers]) ----------
    // Confort uniquement : le serveur refait TOUS ces contrôles.
    document.querySelectorAll('input[type=file][data-fichiers]').forEach(function (champ) {
        var maxOctets = (parseFloat(champ.dataset.maxMo) || 2) * 1024 * 1024;
        var maxNb = parseInt(champ.dataset.maxNb, 10) || 3;
        var autorisees = (champ.dataset.ext || '').toLowerCase().split(',');
        var message = document.createElement('p');
        message.className = 'erreur-js';
        message.setAttribute('role', 'alert');
        message.hidden = true;
        champ.insertAdjacentElement('afterend', message);

        champ.addEventListener('change', function () {
            var erreurs = [];
            var fichiers = Array.prototype.slice.call(champ.files);

            if (fichiers.length > maxNb) {
                erreurs.push('Maximum ' + maxNb + ' fichiers par envoi.');
            }
            fichiers.forEach(function (f) {
                var ext = f.name.indexOf('.') >= 0 ? f.name.split('.').pop().toLowerCase() : '';
                if (autorisees.indexOf(ext) === -1) {
                    erreurs.push('« ' + f.name + ' » : type non autorisé.');
                } else if (f.size > maxOctets) {
                    erreurs.push('« ' + f.name + ' » dépasse ' + (maxOctets / 1048576) + ' Mo.');
                }
            });

            if (erreurs.length) {
                champ.value = '';                       // on vide la sélection invalide
                message.textContent = erreurs.join(' ');
                message.hidden = false;
            } else {
                message.hidden = true;
            }
        });
    });

    // ---------- 3. Équipe responsable selon la catégorie (select[data-equipe]) ----------
    document.querySelectorAll('select[data-equipe]').forEach(function (liste) {
        var cible = document.getElementById(liste.dataset.equipe);
        if (!cible) { return; }

        function maj() {
            var option = liste.options[liste.selectedIndex];
            var groupe = option && option.parentElement.tagName === 'OPTGROUP' ? option.parentElement.label : '';
            cible.hidden = groupe === '';
            cible.textContent = groupe ? 'Ce ticket sera pris en charge par l\'équipe « ' + groupe + ' ».' : '';
        }
        liste.addEventListener('change', maj);
        maj();
    });

    // ---------- 4. Recherche et filtres de la liste (div[data-filtre]) ----------
    document.querySelectorAll('[data-filtre]').forEach(function (barre) {
        var table = document.querySelector(barre.dataset.filtre);
        if (!table) { return; }
        var lignes = Array.prototype.slice.call(table.querySelectorAll('tbody tr'));
        var champTexte = barre.querySelector('[data-f=texte]');
        var champStatut = barre.querySelector('[data-f=statut]');
        var champUrgence = barre.querySelector('[data-f=urgence]');
        var compte = barre.querySelector('[data-compte]');
        var vide = table.parentElement.querySelector('[data-vide]');

        function normaliser(t) {
            return t.toLowerCase().normalize('NFD').replace(/[\u0300-\u036f]/g, '');   // sans accents
        }
        var textes = lignes.map(function (l) { return normaliser(l.textContent); });

        function filtrer() {
            var recherche = normaliser(champTexte.value.trim());
            var visibles = 0;
            lignes.forEach(function (ligne, i) {
                var ok = (recherche === '' || textes[i].indexOf(recherche) !== -1)
                    && (champStatut.value === '' || ligne.dataset.statut === champStatut.value)
                    && (champUrgence.value === '' || ligne.dataset.urgence === champUrgence.value);
                ligne.hidden = !ok;
                if (ok) { visibles++; }
            });
            compte.textContent = visibles + ' ticket' + (visibles > 1 ? 's' : '') + ' affiché' + (visibles > 1 ? 's' : '') + ' sur ' + lignes.length;
            if (vide) { vide.hidden = visibles !== 0; }
        }
                // Les liens d'export reprennent les filtres statut et urgence choisis
        var liensExport = barre.querySelectorAll('a[data-export]');
        liensExport.forEach(function (lien) { lien.dataset.base = lien.getAttribute('href'); });
        function majExport() {
            var parametres = [];
            if (champStatut.value) { parametres.push('statut=' + encodeURIComponent(champStatut.value)); }
            if (champUrgence.value) { parametres.push('urgence=' + encodeURIComponent(champUrgence.value)); }
            liensExport.forEach(function (lien) {
                lien.setAttribute('href', lien.dataset.base + (parametres.length ? '?' + parametres.join('&') : ''));
            });
        }
        champStatut.addEventListener('change', majExport);
        champUrgence.addEventListener('change', majExport);
        [champTexte, champStatut, champUrgence].forEach(function (c) {
            c.addEventListener('input', filtrer);
            c.addEventListener('change', filtrer);
        });
        filtrer();
    });

    // ---------- 5. Confirmation avant une action importante (form[data-confirmer]) ----------
    document.querySelectorAll('form[data-confirmer]').forEach(function (form) {
        form.addEventListener('submit', function (evenement) {
            if (!window.confirm(form.dataset.confirmer)) {
                evenement.preventDefault();
            }
        });
    });

    // ---------- 6. Anti double envoi : on désactive le bouton après le premier clic ----------
    document.querySelectorAll('form[method=post]').forEach(function (form) {
        form.addEventListener('submit', function (evenement) {
            if (evenement.defaultPrevented) { return; }
            var bouton = form.querySelector('button[type=submit]');
            if (!bouton) { return; }
            // setTimeout : le navigateur doit d'abord lancer l'envoi, puis on désactive
            setTimeout(function () {
                bouton.disabled = true;
                bouton.dataset.texte = bouton.textContent;
                bouton.textContent = 'Envoi en cours…';
            }, 0);
        });
    });
    // Retour arrière du navigateur : on réactive les boutons
    window.addEventListener('pageshow', function () {
        document.querySelectorAll('button[type=submit][data-texte]').forEach(function (b) {
            b.disabled = false;
            b.textContent = b.dataset.texte;
        });
    });

        // ---------- 6b. Formulaire utilisateur : le domaine ne concerne que les techniciens ----------
    document.querySelectorAll('select[data-role-domaine]').forEach(function (liste) {
        var bloc = document.getElementById(liste.dataset.roleDomaine);
        if (!bloc) { return; }
        function maj() {
            var option = liste.options[liste.selectedIndex];
            var technicien = option && option.dataset.code === 'technicien';
            bloc.hidden = !technicien;
            if (!technicien) {
                var champ = bloc.querySelector('select');
                if (champ) { champ.value = ''; }
            }
        }
        liste.addEventListener('change', maj);
        maj();
    });

    // ---------- 7. Tableau de bord : actualisation des chiffres (AJAX / fetch) ----------
    var zoneMaj = document.querySelector('[data-actualiser]');
    if (zoneMaj && window.fetch) {
        var adresse = zoneMaj.dataset.actualiser;
        var horodatage = zoneMaj.querySelector('[data-maj]');

        function actualiser() {
            if (document.hidden) { return; }           // onglet en arrière-plan : on économise
            fetch(adresse, { headers: { 'Accept': 'application/json' }, credentials: 'same-origin' })
                .then(function (reponse) {
                    if (reponse.status === 401) { window.location.reload(); return null; }   // session expirée
                    return reponse.ok ? reponse.json() : null;
                })
                .then(function (donnees) {
                    if (!donnees) { return; }
                    document.querySelectorAll('[data-kpi]').forEach(function (el) {
                        var nouvelle = String(donnees.kpi[el.dataset.kpi]);
                        if (el.textContent !== nouvelle) {
                            el.textContent = nouvelle;
                            el.classList.remove('kpi-change');
                            void el.offsetWidth;                // relance l'animation
                            el.classList.add('kpi-change');
                        }
                    });
                    if (horodatage) { horodatage.textContent = donnees.genere_a; }
                })
                .catch(function () { /* réseau coupé : on réessaiera au prochain tour */ });
        }
        setInterval(actualiser, 30000);
    }
    
        // ---------- 8. Formulaire d'inscription : champs selon le profil ----------
    var formInscription = document.getElementById('form-inscription');
    if (formInscription) {
        var blocs = formInscription.querySelectorAll('[data-profil]');
        var basculer = function () {
            var choisi = formInscription.querySelector('input[name="profil"]:checked');
            var profil = choisi ? choisi.value : 'employe';
            blocs.forEach(function (bloc) { bloc.hidden = bloc.dataset.profil !== profil; });
        };
        formInscription.querySelectorAll('input[name="profil"]').forEach(function (radio) {
            radio.addEventListener('change', basculer);
        });
        basculer();
    }



})();