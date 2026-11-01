'use strict';
/* ForceOS : page « Sauvegarde chiffrée » de FO Gestion (Florian Bigotte pour Force Ouvrière)
   1. ticket à usage unique demandé à FO Gestion (session, rôle, jeton CSRF) ;
   2. ticket et phrase remis au processus principal d'Electron, qui chiffre. */
(() => {
  const $ = (s) => document.getElementById(s);
  const meta = $('csrf-meta');
  const nettoyer = (e) => String(e?.message ?? e).replace(/^Error invoking remote method '[^']+': (Error: )?/, '');

  function message(type, texte) {
    $('fo-erreur').hidden = type !== 'erreur';
    $('fo-succes').hidden = type !== 'succes';
    const cible = type === 'erreur' ? $('fo-erreur') : $('fo-succes');
    cible.textContent = texte;
    cible.scrollIntoView({ block: 'nearest' });
  }

  async function ticket(usage) {
    const corps = new URLSearchParams({ usage });
    const r = await fetch(new URL('/forceos/ticket', window.location.origin), {
      method: 'POST',
      credentials: 'same-origin',
      headers: { [meta.getAttribute('name')]: meta.getAttribute('content'), 'Content-Type': 'application/x-www-form-urlencoded' },
      body: corps
    });
    const d = await r.json().catch(() => ({}));
    if (!r.ok || !d.ticket) throw new Error(d.erreur || 'Opération refusée.');
    if (d.csrf) meta.setAttribute('content', d.csrf);
    return d.ticket;
  }

  if (!window.forceos?.sauvegarde) {
    message('erreur', 'La sauvegarde chiffrée n’est disponible que dans l’application FO Gestion de ForceOS.');
    return;
  }

  $('fo-export').addEventListener('submit', async (e) => {
    e.preventDefault();
    const p1 = $('phrase-export').value;
    const p2 = $('phrase-confirmation').value;
    if ([...p1].length < 12) return message('erreur', 'La phrase de passe doit comporter au moins 12 caractères.');
    if (p1 !== p2) return message('erreur', 'Les deux phrases de passe ne correspondent pas.');
    const bouton = e.submitter;
    bouton.disabled = true;
    message('succes', 'Chiffrement en cours… Choisissez l’emplacement du fichier.');
    try {
      const r = await window.forceos.sauvegarde.exporter({ ticket: await ticket('export'), phrase: p1 });
      if (r.annule) { message('succes', 'Sauvegarde annulée.'); return; }
      const mo = (r.taille / 1024 / 1024).toLocaleString('fr-FR', { maximumFractionDigits: 1 });
      message('succes', `Sauvegarde enregistrée et vérifiée : ${r.fichier} (${mo} Mo).`);
      $('fo-export').reset();
    } catch (err) {
      message('erreur', nettoyer(err));
    } finally {
      bouton.disabled = false;
    }
  });

  $('fo-restauration')?.addEventListener('submit', async (e) => {
    e.preventDefault();
    const phrase = $('phrase-restauration').value;
    if ([...phrase].length < 12) return message('erreur', 'La phrase de passe doit comporter au moins 12 caractères.');
    const bouton = e.submitter;
    bouton.disabled = true;
    try {
      const r = await window.forceos.sauvegarde.restaurer({ ticket: await ticket('restauration'), phrase });
      if (r.annule) { message('succes', 'Restauration annulée : aucune donnée n’a été modifiée.'); return; }
      message('succes', 'Restauration terminée. FO Gestion redémarre…');
    } catch (err) {
      message('erreur', `${nettoyer(err)} Les données d’origine ont été rétablies.`);
    } finally {
      bouton.disabled = false;
    }
  });
})();
