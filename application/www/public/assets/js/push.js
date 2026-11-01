/* Abonnement de l'appareil aux notifications push (page Mon profil). */
(function () {
    'use strict';
    var box = document.getElementById('push');
    if (!box) return;
    var base = document.getElementById('push-script').getAttribute('data-base');
    var key = box.getAttribute('data-key');
    var etat = document.getElementById('push-etat');
    var on = document.getElementById('push-on'), off = document.getElementById('push-off'), test = document.getElementById('push-test');
    var meta = document.getElementById('csrf-meta');
    function headers() { var h = { 'Content-Type': 'application/json', 'Accept': 'application/json' }; if (meta) h[meta.getAttribute('name')] = meta.getAttribute('content'); return h; }
    function post(path, body) { return fetch(base + path, { method: 'POST', credentials: 'same-origin', headers: headers(), body: JSON.stringify(body || {}) }).then(function (r) { return r.json(); }); }
    function keyBytes(k) { var p = '='.repeat((4 - k.length % 4) % 4), s = atob((k + p).replace(/-/g, '+').replace(/_/g, '/')), a = new Uint8Array(s.length); for (var i = 0; i < s.length; i++) a[i] = s.charCodeAt(i); return a; }
    function show(msg, subscribed) {
        etat.textContent = msg;
        on.hidden = subscribed !== false; off.hidden = subscribed !== true; test.hidden = subscribed !== true;
    }
    if (!('serviceWorker' in navigator) || !('PushManager' in window) || !('Notification' in window)) {
        show('Ce navigateur ne permet pas les notifications push. Sur iPhone, installez d’abord l’application sur l’écran d’accueil.', null);
        return;
    }
    function refresh() {
        if (Notification.permission === 'denied') { show('Les notifications sont bloquées dans les réglages du navigateur pour ce site.', null); return; }
        navigator.serviceWorker.ready.then(function (reg) { return reg.pushManager.getSubscription(); }).then(function (sub) {
            if (sub) { post('/profil/push/abonnement', sub.toJSON()); show('Activées sur cet appareil.', true); }
            else show('Désactivées sur cet appareil.', false);
        });
    }
    on.addEventListener('click', function () {
        Notification.requestPermission().then(function (p) {
            if (p !== 'granted') { refresh(); return; }
            return navigator.serviceWorker.ready.then(function (reg) { return reg.pushManager.subscribe({ userVisibleOnly: true, applicationServerKey: keyBytes(key) }); })
                .then(function (sub) { return post('/profil/push/abonnement', sub.toJSON()); })
                .then(function () { show('Activées sur cet appareil.', true); off.focus(); });
        }).catch(function () { show('L’activation a échoué. Réessayez.', false); });
    });
    off.addEventListener('click', function () {
        navigator.serviceWorker.ready.then(function (reg) { return reg.pushManager.getSubscription(); }).then(function (sub) {
            if (!sub) return;
            return post('/profil/push/desabonnement', { endpoint: sub.endpoint }).then(function () { return sub.unsubscribe(); });
        }).then(function () { show('Désactivées sur cet appareil.', false); on.focus(); });
    });
    test.addEventListener('click', function () {
        post('/profil/push/essai').then(function (d) { etat.textContent = d.ok ? 'Essai envoyé : la notification va apparaître.' : 'L’essai n’a pas pu être envoyé.'; });
    });
    refresh();
})();
