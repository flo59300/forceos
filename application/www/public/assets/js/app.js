/* FO Gestion : comportements communs du hub (sans dépendance hors htmx et Alpine). */
(function () {
    'use strict';
    var meta = document.getElementById('csrf-meta');

    // Jeton CSRF envoyé avec chaque requête htmx
    document.addEventListener('htmx:configRequest', function (e) {
        if (meta) e.detail.headers[meta.getAttribute('name')] = meta.getAttribute('content');
    });

    // Garder le focus clavier sur l'élément remplacé (même identifiant)
    var lastFocusId = null;
    document.addEventListener('htmx:beforeRequest', function () {
        lastFocusId = document.activeElement && document.activeElement.id ? document.activeElement.id : null;
    });
    document.addEventListener('htmx:afterSettle', function () {
        if (lastFocusId) {
            var el = document.getElementById(lastFocusId);
            if (el) el.focus();
        }
    });

    // Erreur réseau ou serveur : message lisible, annoncé aux lecteurs d'écran
    document.addEventListener('htmx:responseError', function () {
        var live = document.getElementById('annonce');
        if (live) live.textContent = "L'action n'a pas pu être enregistrée. Rechargez la page et réessayez.";
    });

    // Application installable : service worker à la racine du site (même portée que la 2.x)
    if ('serviceWorker' in navigator && meta) {
        window.addEventListener('load', function () {
            var base = document.querySelector('link[rel=manifest]');
            var root = base ? base.href.replace(/manifest\.webmanifest$/, '') : '/';
            navigator.serviceWorker.register(root + 'sw.js', { scope: root }).catch(function () {});
        });
    }

    // Hors ligne : la page affichée est la dernière version consultée
    function offline() {
        var id = 'bandeau-hors-ligne', el = document.getElementById(id);
        if (navigator.onLine) { if (el) el.remove(); return; }
        if (el) return;
        el = document.createElement('p');
        el.id = id; el.setAttribute('role', 'status');
        el.className = 'alert alert-info m-0 rounded-none';
        el.textContent = 'Hors ligne : vous consultez la dernière version enregistrée de cette page. Les saisies attendront le retour de la connexion.';
        document.body.insertBefore(el, document.body.firstChild);
    }
    window.addEventListener('online', offline);
    window.addEventListener('offline', offline);
    document.addEventListener('DOMContentLoaded', offline);

    // Après une erreur de formulaire, placer le focus sur le récapitulatif
    document.addEventListener('DOMContentLoaded', function () {
        var box = document.getElementById('erreurs');
        if (box) box.focus();
    });
})();
