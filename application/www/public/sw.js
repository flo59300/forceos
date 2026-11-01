/* FO Gestion 3 : service worker (application installable, consultation hors ligne, notifications push).
 * Même adresse et même portée que celui de la 2.x (/sw.js) : il le remplace à la première visite,
 * et les abonnements push existants restent valables (mêmes clés VAPID). */
const VERSION = 'fo3-v1';
const STATIC = VERSION + '-static';
const PAGES = 'fo3-pages';
const OFFLINE = new URL('hors-ligne.html', self.registration.scope).href;
const MAX_PAGES = 40;

self.addEventListener('install', (event) => {
    event.waitUntil(caches.open(STATIC).then((c) => c.addAll([OFFLINE])).then(() => self.skipWaiting()));
});

self.addEventListener('activate', (event) => {
    event.waitUntil(
        caches.keys().then((keys) => Promise.all(keys.filter((k) => k !== STATIC && k !== PAGES).map((k) => caches.delete(k))))
            .then(() => self.clients.claim())
    );
});

async function trim(cache) {
    const keys = await cache.keys();
    for (let i = 0; i < keys.length - MAX_PAGES; i++) await cache.delete(keys[i]);
}

self.addEventListener('fetch', (event) => {
    const req = event.request;
    if (req.method !== 'GET') return;
    const url = new URL(req.url);
    if (url.origin !== self.location.origin) return;

    // Pages : réseau d'abord ; la dernière version consultée sert hors ligne.
    if (req.mode === 'navigate') {
        event.respondWith((async () => {
            try {
                const res = await fetch(req);
                const type = res.headers.get('Content-Type') || '';
                if (res.ok && type.includes('text/html') && !res.headers.get('Content-Disposition')) {
                    const cache = await caches.open(PAGES);
                    cache.put(req, res.clone()).then(() => trim(cache));
                }
                return res;
            } catch (e) {
                return (await caches.match(req, { cacheName: PAGES })) || (await caches.match(OFFLINE)) || new Response('Hors ligne', { status: 503 });
            }
        })());
        return;
    }

    // Fichiers versionnés (?v=…) : en cache définitivement.
    if (url.search.includes('v=') && /\.(css|js|svg|png|webp|woff2?)$/.test(url.pathname)) {
        event.respondWith(caches.open(STATIC).then((c) => c.match(req).then((hit) => hit || fetch(req).then((res) => { if (res.ok) c.put(req, res.clone()); return res; }))));
        return;
    }

    // Autres fichiers statiques : cache, puis mise à jour en arrière-plan.
    if (/\.(css|js|svg|png|webp|woff2?|ico)$/.test(url.pathname)) {
        event.respondWith(caches.open(STATIC).then((c) => c.match(req).then((hit) => {
            const net = fetch(req).then((res) => { if (res.ok) c.put(req, res.clone()); return res; }).catch(() => hit);
            return hit || net;
        })));
    }
});

// Déconnexion : la page demande l'effacement des pages gardées pour la consultation hors ligne.
self.addEventListener('message', (event) => {
    if (event.data === 'logout') event.waitUntil(caches.delete(PAGES));
});

self.addEventListener('push', (event) => {
    let d = { title: 'FO Gestion', body: 'Nouvelle notification', url: self.registration.scope, tag: 'general' };
    try { if (event.data) d = Object.assign(d, event.data.json()); } catch (e) { /* message non JSON */ }
    event.waitUntil(self.registration.showNotification(d.title, {
        body: d.body,
        icon: d.icon || new URL('assets/icons/fostellantis.png', self.registration.scope).href,
        badge: new URL('assets/icons/favicon.svg', self.registration.scope).href,
        tag: d.tag || 'general',
        renotify: true,
        data: { url: d.url || self.registration.scope },
    }));
});

self.addEventListener('notificationclick', (event) => {
    event.notification.close();
    let target = event.notification.data && event.notification.data.url ? event.notification.data.url : self.registration.scope;
    // Notifications envoyées par la 2.x (adresses /pages/…) : ouvertes via le centre de notifications.
    if (/\/pages\/|\/index\.php/.test(target)) target = new URL('notifications', self.registration.scope).href;
    event.waitUntil(self.clients.matchAll({ type: 'window', includeUncontrolled: true }).then((list) => {
        for (const c of list) {
            if (c.url === target && 'focus' in c) return c.focus();
        }
        return self.clients.openWindow ? self.clients.openWindow(target) : undefined;
    }));
});
