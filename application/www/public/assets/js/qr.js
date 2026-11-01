/* QR codes des permanences : chaque élément [data-qr] reçoit le dessin SVG de son adresse. */
(function () {
    'use strict';
    if (typeof qrcode !== 'function') return;
    document.querySelectorAll('[data-qr]').forEach(function (el) {
        var q = qrcode(0, 'M');
        q.addData(el.getAttribute('data-qr'));
        q.make();
        el.innerHTML = q.createSvgTag({ cellSize: 6, margin: 2, scalable: true });
        var svg = el.querySelector('svg');
        if (svg) { svg.setAttribute('aria-hidden', 'true'); svg.setAttribute('focusable', 'false'); svg.style.width = '100%'; svg.style.height = 'auto'; }
    });
})();
