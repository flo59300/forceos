/* Page Bourse : cours chargés après la page, rafraîchis chaque minute (désactivable). Tout est inséré en texte. */
(function () {
    'use strict';
    var api = document.getElementById('bourse-script').getAttribute('data-api');
    var state = document.getElementById('bourse-etat');
    var auto = document.getElementById('bourse-auto');
    var live = document.getElementById('annonce');
    var timer = null;

    function price(v, cur) {
        if (v === null || v === undefined) return '–';
        var d = cur === 'JPY' ? 0 : 2;
        var s = Number(v).toLocaleString('fr-FR', { minimumFractionDigits: d, maximumFractionDigits: d });
        return s + ' ' + ({ EUR: '€', USD: '$', GBP: '£', JPY: '¥', CNY: '¥' }[cur] || cur || '');
    }
    function pct(p) { return (p > 0 ? '+' : '') + Number(p).toLocaleString('fr-FR', { minimumFractionDigits: 2, maximumFractionDigits: 2 }) + ' %'; }
    function trend(p) { return p > 0.01 ? 'en hausse' : (p < -0.01 ? 'en baisse' : 'stable'); }
    function tone(p) { return p > 0.01 ? 'text-success' : (p < -0.01 ? 'text-danger' : 'text-muted'); }
    function time(ts, tz) {
        try { return new Date(ts * 1000).toLocaleString('fr-FR', { timeZone: tz || 'Europe/Paris', day: '2-digit', month: '2-digit', hour: '2-digit', minute: '2-digit' }); }
        catch (e) { return new Date(ts * 1000).toLocaleString('fr-FR'); }
    }
    function market(s) {
        s = (s || '').toUpperCase();
        if (s === 'REGULAR' || s === 'OPEN') return 'Marché ouvert.';
        if (s.indexOf('PRE') >= 0) return 'Pré-ouverture.';
        if (s.indexOf('POST') >= 0) return 'Après la clôture.';
        return 'Marché fermé.';
    }
    function el(tag, cls, text) { var e = document.createElement(tag); if (cls) e.className = cls; if (text !== undefined) e.textContent = text; return e; }

    function grid(id, items) {
        var ul = document.getElementById(id);
        ul.textContent = '';
        if (!items.length) { var li = el('li', 'card text-muted', 'Cours indisponibles pour le moment.'); ul.appendChild(li); return; }
        items.forEach(function (it) {
            var li = el('li', 'card');
            var h = el('h3', 'm-0 text-base', it.name + ' ');
            h.appendChild(el('span', 'font-normal text-muted', it.symbol));
            li.appendChild(h);
            li.appendChild(el('p', 'm-0 mt-1 text-2xl font-bold', price(it.price, it.currency)));
            var v = el('p', 'm-0 font-bold ' + tone(it.changePct), pct(it.changePct));
            v.appendChild(el('span', 'sr-only', ' (' + trend(it.changePct) + ')'));
            li.appendChild(v);
            li.appendChild(el('p', 'm-0 text-sm text-muted', time(it.timestamp, it.timezone)));
            ul.appendChild(li);
        });
    }
    function get(g) { return fetch(api + '?groupe=' + g, { headers: { Accept: 'application/json' }, credentials: 'same-origin' }).then(function (r) { return r.json(); }); }

    function load(manual) {
        Promise.all([get('stellantis'), get('companies'), get('indices')]).then(function (r) {
            var s = r[0].ok && r[0].data[0];
            if (s) {
                document.getElementById('stla-prix').textContent = price(s.price, s.currency);
                var v = document.getElementById('stla-var');
                v.textContent = (s.change > 0 ? '+' : '') + Number(s.change).toLocaleString('fr-FR', { minimumFractionDigits: 2, maximumFractionDigits: 2 }) + ' (' + pct(s.changePct) + '), ' + trend(s.changePct);
                v.className = 'm-0 mt-1 text-lg font-bold ' + (s.changePct > 0.01 ? 'text-[#86e3a8]' : (s.changePct < -0.01 ? 'text-[#ff9aa5]' : 'text-[#c3cbe0]'));
                document.getElementById('stla-marche').textContent = market(s.marketState);
                document.getElementById('stla-heure').textContent = 'Cours du ' + time(s.timestamp, s.timezone) + '.';
            }
            grid('bourse-companies', r[1].ok ? r[1].data : []);
            grid('bourse-indices', r[2].ok ? r[2].data : []);
            var msg = (s ? 'Cours mis à jour à ' : 'Cours de Stellantis indisponibles. Dernier essai à ') + new Date().toLocaleTimeString('fr-FR', { hour: '2-digit', minute: '2-digit' }) + '.';
            state.textContent = msg;
            if (manual === true) live.textContent = msg;
        }).catch(function () { state.textContent = 'Les cours sont momentanément indisponibles.'; if (manual === true) live.textContent = state.textContent; });
    }
    function schedule(on) {
        if (timer) { clearInterval(timer); timer = null; }
        if (on) timer = setInterval(load, 60000);
        auto.setAttribute('aria-pressed', on ? 'true' : 'false');
        auto.textContent = 'Actualisation automatique : ' + (on ? 'oui' : 'non');
    }
    document.getElementById('bourse-maj').addEventListener('click', function () { load(true); });
    auto.addEventListener('click', function () { schedule(auto.getAttribute('aria-pressed') !== 'true'); });
    load();
    schedule(true);
})();
