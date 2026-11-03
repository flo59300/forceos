'use strict';
/* ForceOS : page « Mode serveur pour les téléphones » (Florian Bigotte pour Force Ouvrière)
   1. ticket à usage unique demandé à FO Gestion (session, rôle de secrétaire, CSRF) ;
   2. ticket, réseau et durée remis au processus principal d'Electron, qui ouvre
      le réseau (mot de passe administrateur) et sert FO Gestion aux téléphones. */
(() => {
  const $ = (s) => document.getElementById(s);
  const meta = $('csrf-meta');
  const nettoyer = (e) => String(e?.message ?? e).replace(/^Error invoking remote method '[^']+': (Error: )?/, '');
  const erreur = (texte) => { const b = $('fo-erreur'); b.textContent = texte; b.hidden = !texte; if (texte) b.scrollIntoView({ block: 'nearest' }); };
  if (!$('fo-ouvrir')) return;
  if (!window.forceos?.serveur) {
    erreur('Le mode serveur n’est disponible que dans l’application FO Gestion du PC ForceOS.');
    $('fo-ouvrir').querySelector('button').disabled = true;
    return;
  }

  function afficher(e) {
    $('fo-preparation').hidden = Boolean(e.actif);
    $('fo-actif').hidden = !e.actif;
    if (!e.actif) {
      if (e.raison && e.raison !== 'arrêt demandé') erreur(`Mode serveur fermé : ${e.raison}.`);
      return;
    }
    erreur('');
    $('fo-fin').textContent = new Date(e.fin).toLocaleTimeString('fr-FR', { hour: '2-digit', minute: '2-digit' });
    $('fo-bloc-wifi').hidden = !e.qrWifi;
    $('fo-etape-adresse').textContent = e.qrWifi ? '2.' : '';
    if (e.qrWifi) {
      $('fo-qr-wifi').src = e.qrWifi;
      $('fo-ssid').textContent = e.reseau;
      $('fo-mdp').textContent = e.motDePasseWifi;
    }
    $('fo-qr-adresse').src = e.qrAdresse;
    $('fo-adresse').textContent = e.adresse;
    $('fo-empreinte').textContent = String(e.empreinte || '').split(':').slice(-4).join(':');
  }

  async function ticket() {
    const r = await fetch(new URL('/forceos/ticket', window.location.origin), {
      method: 'POST',
      credentials: 'same-origin',
      headers: { [meta.getAttribute('name')]: meta.getAttribute('content'), 'Content-Type': 'application/x-www-form-urlencoded' },
      body: new URLSearchParams({ usage: 'serveur' })
    });
    const d = await r.json().catch(() => ({}));
    if (!r.ok || !d.ticket) throw new Error(d.erreur || 'Opération refusée.');
    if (d.csrf) meta.setAttribute('content', d.csrf);
    return d.ticket;
  }

  $('fo-ouvrir').addEventListener('submit', async (ev) => {
    ev.preventDefault();
    const bouton = ev.submitter ?? $('fo-ouvrir').querySelector('button');
    bouton.disabled = true;
    const texte = bouton.textContent;
    bouton.textContent = 'Ouverture en cours…';
    erreur('');
    try {
      const mode = $('fo-ouvrir').querySelector('input[name=mode]:checked').value;
      const minutes = Number($('fo-duree').value);
      afficher(await window.forceos.serveur.demarrer({ ticket: await ticket(), mode, minutes }));
    } catch (e) {
      erreur(nettoyer(e));
    } finally {
      bouton.disabled = false;
      bouton.textContent = texte;
    }
  });
  $('fo-fermer').addEventListener('click', async () => afficher(await window.forceos.serveur.arreter()));
  window.forceos.serveur.surEtat(afficher);
  window.forceos.serveur.etat().then(afficher).catch(() => {});
})();
