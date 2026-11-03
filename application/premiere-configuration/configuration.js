'use strict';
/* ForceOS : formulaire de première configuration (Florian Bigotte pour Force Ouvrière)
   Les contrôles ci-dessous aident le secrétaire ; les mêmes règles sont
   appliquées une seconde fois côté FO Gestion, qui fait foi. */
(() => {
  const f = document.getElementById('formulaire');
  const erreur = document.getElementById('erreur');
  const bouton = document.getElementById('valider');
  const champ = (n) => f.elements.namedItem(n);
  const montant = (v) => Number(String(v).trim().replace(/\s/g, '').replace(',', '.'));

  function evaluer(mdp) {
    let p = 0;
    if (mdp.length >= 12) p++;
    if (mdp.length >= 16) p++;
    if (/[a-zà-ÿ]/.test(mdp) && /[A-ZÀ-Ý]/.test(mdp)) p++;
    if (/\d/.test(mdp)) p++;
    if (/[^\p{L}\d]/u.test(mdp) || /\s/.test(mdp)) p++;
    return mdp.length < 12 ? 0 : p;
  }

  champ('mot_de_passe').addEventListener('input', (e) => {
    const p = evaluer(e.target.value);
    const barre = document.getElementById('force');
    barre.style.width = `${Math.min(100, p * 20)}%`;
    barre.style.background = p >= 4 ? '#1a7f37' : p >= 3 ? '#d98c00' : '#E2001A';
    document.getElementById('force-texte').textContent = e.target.value.length < 12
      ? `Encore ${12 - e.target.value.length} caractère(s) au minimum.`
      : p >= 4 ? 'Mot de passe solide.' : 'Correct. Une phrase plus longue le rendrait plus sûr.';
  });

  function verifier() {
    const fautes = [];
    const marquer = (n, faux, message) => {
      champ(n).setAttribute('aria-invalid', faux ? 'true' : 'false');
      if (faux) fautes.push(message);
    };
    const section = champ('section').value.trim();
    marquer('section', section.length < 2, 'Indiquez le nom du syndicat.');
    for (const n of ['tarif_timbre', 'tarif_carte']) {
      const v = montant(champ(n).value);
      marquer(n, !Number.isFinite(v) || v < 0 || v > 9999, 'Les tarifs doivent être des montants valides.');
    }
    marquer('prenom', !champ('prenom').value.trim(), 'Indiquez votre prénom.');
    marquer('nom', !champ('nom').value.trim(), 'Indiquez votre nom.');
    marquer('email', !/^[^\s@]+@[^\s@]+\.[^\s@]+$/.test(champ('email').value.trim()), 'L’adresse e-mail n’est pas valide.');
    const mdp = champ('mot_de_passe').value;
    marquer('mot_de_passe', [...mdp].length < 12, 'Le mot de passe doit comporter au moins 12 caractères.');
    marquer('confirmation', mdp !== champ('confirmation').value, 'Les deux mots de passe ne correspondent pas.');
    return [...new Set(fautes)];
  }

  function afficher(message) {
    erreur.textContent = message;
    erreur.hidden = !message;
    if (message) erreur.scrollIntoView({ block: 'nearest' });
  }

  f.addEventListener('submit', async (e) => {
    e.preventDefault();
    const fautes = verifier();
    if (fautes.length) return afficher(fautes.join(' '));
    afficher('');
    bouton.disabled = true;
    bouton.textContent = 'Création en cours…';
    try {
      const r = await window.forceosConfig.envoyer({
        section: champ('section').value.trim(),
        tarif_timbre: montant(champ('tarif_timbre').value),
        tarif_carte: montant(champ('tarif_carte').value),
        prenom: champ('prenom').value.trim(),
        nom: champ('nom').value.trim(),
        email: champ('email').value.trim(),
        mot_de_passe: champ('mot_de_passe').value
      });
      if (!r.ok) throw new Error(r.erreur || 'La configuration a échoué.');
      bouton.textContent = 'Section créée';
    } catch (err) {
      afficher(String(err.message || err).replace(/^Error invoking remote method '[^']+': (Error: )?/, ''));
      bouton.disabled = false;
      bouton.textContent = 'Créer le syndicat et le compte';
    }
  });

  champ('section').focus();
})();
