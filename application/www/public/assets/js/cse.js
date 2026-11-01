/* Éditeur du simulateur d'élections CSE. Les calculs sont faits par le serveur (une seule règle, testée). */
window.cseEditor = function (init) {
    'use strict';
    var blankType = function (n) { return { sieges: 0, votants: 0, blancs: 0, votes: new Array(n).fill(0) }; };
    var blankBureauType = function (n) { return { votants: 0, blancs: 0, votes: new Array(n).fill(0) }; };
    return {
        state: init,
        json: '',
        excl: function (s, ci) { return !!s.categoriel && s.exclus.indexOf(ci) >= 0; },
        toggleExclu: function (s, ci, present) {
            var i = s.exclus.indexOf(ci);
            if (present && i >= 0) s.exclus.splice(i, 1);
            if (!present && i < 0) s.exclus.push(ci);
        },
        addSyndicat: function () {
            this.state.syndicats.push({ nom: 'Nouveau', categoriel: false, exclus: [] });
            this.state.colleges.forEach(function (c) {
                ['tit', 'sup'].forEach(function (t) { c[t].votes.push(0); c.bureaux.forEach(function (b) { b[t].votes.push(0); }); });
            });
        },
        removeSyndicat: function (si) {
            this.state.syndicats.splice(si, 1);
            var self = this;
            this.state.colleges.forEach(function (c) {
                ['tit', 'sup'].forEach(function (t) { c[t].votes.splice(si, 1); c.bureaux.forEach(function (b) { b[t].votes.splice(si, 1); }); });
                self.sync(c);
            });
        },
        addCollege: function () {
            var n = this.state.syndicats.length, k = this.state.colleges.length + 1;
            this.state.colleges.push({ nom: k + 'e collège', subtitle: '', inscrits: 0, tit: blankType(n), sup: blankType(n), bureaux: [] });
        },
        removeCollege: function (ci) {
            this.state.colleges.splice(ci, 1);
            this.state.syndicats.forEach(function (s) {
                s.exclus = s.exclus.filter(function (x) { return x !== ci; }).map(function (x) { return x > ci ? x - 1 : x; });
            });
        },
        addBureau: function (c) {
            var n = this.state.syndicats.length;
            c.bureaux.push({ name: 'Bureau ' + (c.bureaux.length + 1), tit: blankBureauType(n), sup: blankBureauType(n) });
            this.sync(c);
        },
        /* Totaux du collège = somme des bureaux, comme le calcul du serveur. */
        sync: function (c) {
            if (!c.bureaux.length) return;
            ['tit', 'sup'].forEach(function (t) {
                c[t].votants = c.bureaux.reduce(function (a, b) { return a + (Number(b[t].votants) || 0); }, 0);
                c[t].blancs = c.bureaux.reduce(function (a, b) { return a + (Number(b[t].blancs) || 0); }, 0);
                c[t].votes = c[t].votes.map(function (_, si) { return c.bureaux.reduce(function (a, b) { return a + (Number(b[t].votes[si]) || 0); }, 0); });
            });
        }
    };
};
