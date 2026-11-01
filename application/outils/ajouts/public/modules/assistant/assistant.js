'use strict';
/* =====================================================================
   ForceOS : assistant syndical FO (Qwen 2.5 et whisper.cpp, hors ligne)
   Conçu et développé par Florian Bigotte pour Force Ouvrière

   L'historique n'existe qu'en mémoire : rien n'est enregistré sur disque.
   Aucun contenu produit par l'IA n'est inséré en HTML : rendu par nœuds texte.
   ===================================================================== */
(() => {
  const $ = (s) => document.querySelector(s);
  const api = window.forceos;
  if (!api?.ia) {
    document.querySelector('main').textContent = 'L’assistant n’est disponible que dans l’application FO Gestion de ForceOS.';
    return;
  }

  const BOUTONS = ['question', 'accord', 'tract', 'prudhommes'];
  const ICONES = { question: '❓', accord: '📑', tract: '📣', prudhommes: '⚖️' };
  const AIDES = {
    question: 'Posez votre question : la réponse sera courte et directe.',
    accord: 'Collez ici le texte du projet d’accord, ou joignez le document.',
    tract: 'Décrivez le sujet, les faits, les revendications et le public visé.',
    prudhommes: 'Décrivez la situation du salarié : contrat, faits, dates, échanges avec l’employeur.',
    audio: 'Posez une question complémentaire sur le compte rendu.'
  };
  const AVEC_DOCS_PAR_DEFAUT = new Set(['question', 'prudhommes']);
  const ETAPES = {
    conversion: 'Préparation du fichier audio', transcription: 'Transcription', analyse: 'Analyse du texte long',
    extraction: 'Extraction du texte', ocr: 'Reconnaissance du texte scanné (OCR)'
  };
  const EXT_AUDIO = /\.(mp3|wav|m4a|ogg|opus|flac|aac|webm)$/i;
  const EXT_DOCUMENT = /\.(pdf|docx|odt|txt|md|epub)$/i;

  let libelles = {};
  let mode = 'question';
  let historique = [];
  let requete = null;
  let rendu = 0;

  const nettoyerErreur = (e) => String(e?.message ?? e).replace(/^Error invoking remote method '[^']+': (Error: )?/, '');

  // --- Rendu sûr du texte de l'IA -----------------------------------------
  function enrichir(el, texte) {
    for (const m of texte.split(/(\*\*[^*]+\*\*)/)) {
      if (/^\*\*[^*]+\*\*$/.test(m)) {
        const b = document.createElement('strong');
        b.textContent = m.slice(2, -2);
        el.append(b);
      } else if (m) {
        el.append(document.createTextNode(m));
      }
    }
  }

  function rendre(conteneur, texte) {
    conteneur.replaceChildren();
    let liste = null;
    for (const ligne of texte.split('\n')) {
      const t = ligne.trim();
      if (!t) { liste = null; continue; }
      const titre = /^#{1,4}\s+(.*)$/.exec(t);
      const puce = /^[-*•]\s+(.*)$/.exec(t) ?? /^\d+[.)]\s+(.*)$/.exec(t);
      let el;
      if (titre) {
        el = document.createElement('h3'); enrichir(el, titre[1]); liste = null; conteneur.append(el);
      } else if (puce) {
        if (!liste) { liste = document.createElement('ul'); conteneur.append(liste); }
        el = document.createElement('li'); enrichir(el, puce[1]); liste.append(el);
      } else {
        el = document.createElement('p'); enrichir(el, t); liste = null; conteneur.append(el);
      }
    }
  }

  // --- Messages ------------------------------------------------------------
  function ajouterMessage(role, texte = '') {
    $('#accueil').hidden = true;
    const bulle = document.createElement('div');
    bulle.className = `message ${role}`;
    const corps = document.createElement('div');
    bulle.append(corps);
    if (role === 'assistant') rendre(corps, texte); else corps.textContent = texte;
    $('#conversation').append(bulle);
    bulle.scrollIntoView({ block: 'end', behavior: 'smooth' });
    return { bulle, corps };
  }

  function ajouterOutils(bulle, texte) {
    const outils = document.createElement('div');
    outils.className = 'outils';
    const copier = document.createElement('button');
    copier.type = 'button';
    copier.className = 'fo-bouton discret';
    copier.textContent = 'Copier';
    copier.addEventListener('click', async () => {
      await navigator.clipboard.writeText(texte);
      copier.textContent = 'Copié';
      setTimeout(() => { copier.textContent = 'Copier'; }, 1500);
    });
    outils.append(copier);
    bulle.append(outils);
  }

  function ajouterTranscription(bulle, texte) {
    const d = document.createElement('details');
    d.className = 'transcription';
    const s = document.createElement('summary');
    s.textContent = 'Voir la transcription intégrale';
    const pre = document.createElement('pre');
    pre.textContent = texte;
    d.append(s, pre);
    bulle.append(d);
  }

  function occupe(oui) {
    $('#btn-envoyer').disabled = oui;
    $('#texte').disabled = oui;
    $('#btn-stop').hidden = !oui;
  }

  function demarrer(id, attente, role = 'assistant') {
    const { bulle, corps } = ajouterMessage(role);
    corps.textContent = attente;
    const progression = document.createElement('div');
    progression.className = 'progression';
    const etiquette = document.createElement('span');
    const piste = document.createElement('div');
    piste.className = 'barre';
    const barre = document.createElement('div');
    piste.append(barre);
    progression.append(etiquette, piste);
    progression.hidden = true;
    bulle.append(progression);
    requete = { id, bulle, corps, texte: '', progression, etiquette, barre, sources: null };
  }

  function terminer(ok) {
    if (ok && requete.texte) {
      historique.push({ role: 'assistant', content: requete.texte.slice(0, 24000) });
      if (requete.sources) {
        const s = document.createElement('div');
        s.className = 'sources';
        s.textContent = requete.sources.length
          ? `Sources consultées : ${requete.sources.map((x, i) => `📄 [${i + 1}] ${x.titre}, ${x.section}`).join(' ; ')}`
          : 'Aucun extrait pertinent trouvé dans les documents de l’IA.';
        requete.bulle.append(s);
      }
      ajouterOutils(requete.bulle, requete.texte);
    }
    requete.progression.remove();
    requete = null;
    occupe(false);
    $('#texte').focus();
  }

  // --- Réception des flux ---------------------------------------------------
  api.ia.surFlux((d) => {
    if (!requete || d.id !== requete.id) return;
    switch (d.type) {
      case 'sources':
        requete.sources = d.sources;
        break;
      case 'transcription':
        ajouterTranscription(requete.bulle, d.texte);
        requete.corps.textContent = 'Rédaction du compte rendu…';
        break;
      case 'jeton':
        requete.progression.hidden = true;
        requete.texte += d.texte;
        cancelAnimationFrame(rendu);
        rendu = requestAnimationFrame(() => {
          if (!requete) return;
          rendre(requete.corps, requete.texte);
          requete.bulle.scrollIntoView({ block: 'end' });
        });
        break;
      case 'fin':
        cancelAnimationFrame(rendu);
        rendre(requete.corps, requete.texte);
        terminer(true);
        break;
      case 'annule':
        requete.texte += '\n\n(Réponse interrompue)';
        rendre(requete.corps, requete.texte);
        terminer(true);
        break;
      case 'erreur':
        requete.bulle.classList.add('erreur');
        requete.corps.textContent = `Erreur : ${d.texte}`;
        terminer(false);
        break;
    }
  });

  api.ia.surProgres((p) => {
    if (!requete || p.id !== requete.id) return;
    requete.progression.hidden = false;
    requete.etiquette.textContent = `${ETAPES[p.etape] ?? p.etape} : ${p.pourcentage ?? 0} %`;
    requete.barre.style.width = `${p.pourcentage ?? 0}%`;
  });

  // --- Envoi d'un message ---------------------------------------------------
  async function envoyer() {
    const texte = $('#texte').value.trim();
    if (!texte || requete) return;
    ajouterMessage('utilisateur', texte);
    $('#texte').value = '';
    occupe(true);
    const id = crypto.randomUUID();
    demarrer(id, 'Réflexion en cours…');
    try {
      await api.ia.envoyer({ id, action: mode, texte, historique, bibliotheque: $('#avec-biblio').checked });
      historique.push({ role: 'user', content: texte.slice(0, 24000) });
    } catch (e) {
      requete.corps.textContent = `Erreur : ${nettoyerErreur(e)}`;
      requete.bulle.classList.add('erreur');
      terminer(false);
    }
  }

  $('#saisie').addEventListener('submit', (e) => { e.preventDefault(); envoyer(); });
  $('#texte').addEventListener('keydown', (e) => {
    if (e.key === 'Enter' && e.ctrlKey) { e.preventDefault(); envoyer(); }
  });
  $('#btn-stop').addEventListener('click', () => { if (requete) api.ia.annuler(requete.id); });

  // --- Audio -------------------------------------------------------------------
  async function traiterAudio(fichier) {
    if (requete) return;
    if (!EXT_AUDIO.test(fichier.name)) {
      ajouterMessage('systeme', 'Format non pris en charge : MP3, WAV, M4A, OGG, OPUS, FLAC, AAC ou WEBM.');
      return;
    }
    const consigne = $('#texte').value.trim();
    choisirMode('audio');
    ajouterMessage('utilisateur', `🎙️ ${fichier.name}${consigne ? `\nConsigne : ${consigne}` : ''}`);
    $('#texte').value = '';
    occupe(true);
    const id = crypto.randomUUID();
    demarrer(id, 'Préparation du fichier audio…');
    try {
      await api.audio.traiter({ id, chemin: api.audio.chemin(fichier), consigne });
      historique.push({ role: 'user', content: `Compte rendu de l’enregistrement « ${fichier.name} ». ${consigne}` });
    } catch (e) {
      requete.corps.textContent = `Erreur : ${nettoyerErreur(e)}`;
      requete.bulle.classList.add('erreur');
      terminer(false);
    }
  }

  const zone = $('#zone-audio');
  ['dragenter', 'dragover'].forEach((t) => zone.addEventListener(t, (e) => { e.preventDefault(); zone.classList.add('survol'); }));
  ['dragleave', 'drop'].forEach((t) => zone.addEventListener(t, () => zone.classList.remove('survol')));
  zone.addEventListener('drop', (e) => { e.preventDefault(); const f = e.dataTransfer.files[0]; if (f) traiterAudio(f); });
  zone.addEventListener('click', () => $('#choix-audio').click());
  zone.addEventListener('keydown', (e) => { if (e.key === 'Enter' || e.key === ' ') $('#choix-audio').click(); });
  $('#choix-audio').addEventListener('change', (e) => { const f = e.target.files[0]; if (f) traiterAudio(f); e.target.value = ''; });
  ['dragover', 'drop'].forEach((t) => document.addEventListener(t, (e) => e.preventDefault()));

  // --- Document joint (PDF, PDF scanné, Word, LibreOffice, texte) --------------
  async function importerDocument(fichier) {
    if (requete) return;
    if (!EXT_DOCUMENT.test(fichier.name)) {
      ajouterMessage('systeme', 'Formats acceptés : PDF, Word, LibreOffice, texte, Markdown ou ePub.');
      return;
    }
    if (mode === 'question') choisirMode('accord');
    occupe(true);
    const id = crypto.randomUUID();
    demarrer(id, `Extraction du texte de « ${fichier.name} »…`, 'systeme');
    try {
      const r = await api.documents.extraire({ id, chemin: api.documents.chemin(fichier) });
      const actuel = $('#texte').value.trim();
      $('#texte').value = `${actuel ? `${actuel}\n\n` : ''}Document : ${r.titre}\n\n${r.texte}`;
      requete.corps.textContent = `Texte extrait de « ${fichier.name} ». Relisez-le si besoin, ajoutez votre consigne, puis cliquez sur Envoyer.`;
    } catch (e) {
      requete.corps.textContent = `Erreur : ${nettoyerErreur(e)}`;
    } finally {
      requete.progression.remove();
      requete = null;
      occupe(false);
      $('#texte').focus();
    }
  }

  $('#btn-document').addEventListener('click', () => $('#choix-document').click());
  $('#choix-document').addEventListener('change', (e) => { const f = e.target.files[0]; if (f) importerDocument(f); e.target.value = ''; });
  $('#texte').addEventListener('drop', (e) => {
    const f = e.dataTransfer.files[0];
    if (!f) return;
    e.preventDefault();
    e.stopPropagation();
    importerDocument(f);
  });

  // --- Modes et accueil -----------------------------------------------------------
  function choisirMode(cle) {
    mode = cle;
    $('#mode').textContent = libelles[cle] ?? cle;
    $('#texte').placeholder = AIDES[cle] ?? '';
    $('#avec-biblio').checked = AVEC_DOCS_PAR_DEFAUT.has(cle);
    document.querySelectorAll('.action').forEach((b) => b.classList.toggle('actif', b.dataset.cle === cle));
  }

  function nouvelleConversation() {
    if (requete) api.ia.annuler(requete.id);
    historique = [];
    $('#conversation').replaceChildren();
    $('#accueil').hidden = false;
    choisirMode('question');
  }
  $('#btn-nouveau').addEventListener('click', nouvelleConversation);

  async function chargerActions() {
    const config = await api.ia.lirePrompts();
    libelles = Object.fromEntries(Object.entries(config.actions).map(([k, a]) => [k, a.libelle]));
    const conteneur = $('#actions');
    conteneur.replaceChildren();
    for (const cle of BOUTONS) {
      const b = document.createElement('button');
      b.type = 'button';
      b.className = 'action';
      b.dataset.cle = cle;
      const icone = document.createElement('span');
      icone.className = 'icone';
      icone.textContent = ICONES[cle];
      const titre = document.createElement('strong');
      titre.textContent = libelles[cle];
      const aide = document.createElement('small');
      aide.textContent = AIDES[cle];
      b.append(icone, titre, aide);
      b.addEventListener('click', () => { choisirMode(cle); $('#texte').focus(); });
      conteneur.append(b);
    }
    choisirMode(mode);
  }

  async function verifierEtat() {
    try {
      const e = await api.ia.etat();
      const p = $('#etat-ia');
      p.textContent = e.message;
      p.className = `fo-pastille ${e.disponible ? 'ok' : 'ko'}`;
    } catch { /* l'état sera revérifié */ }
  }

  // --- Documents de l'IA ------------------------------------------------------------
  async function afficherDocs() {
    try {
      const e = await api.bibliotheque.etat();
      const illisibles = e.erreurs?.length ? ` (${e.erreurs.length} fichier(s) illisible(s))` : '';
      $('#etat-biblio').textContent = e.enCours
        ? 'Documents de l’IA : indexation en cours…'
        : `Documents de l’IA : ${e.documents ?? 0} document(s), ${(e.passages ?? 0).toLocaleString('fr-FR')} passages consultables${illisibles}`;
    } catch { /* affiché au prochain passage */ }
  }
  api.bibliotheque.surProgres((p) => { $('#etat-biblio').textContent = `Indexation ${p.rang} sur ${p.total} : ${p.fichier}`; });
  $('#btn-biblio-dossier').addEventListener('click', () => api.bibliotheque.ouvrirDossier());
  $('#btn-biblio-indexer').addEventListener('click', async () => {
    $('#btn-biblio-indexer').disabled = true;
    try { await api.bibliotheque.indexer(); } catch (e) { ajouterMessage('systeme', nettoyerErreur(e)); }
    finally { $('#btn-biblio-indexer').disabled = false; afficherDocs(); }
  });

  // --- Consignes (modification réservée à l'administrateur) -----------------------
  async function ouvrirConsignes() {
    const config = await api.ia.lirePrompts();
    const form = $('#form-consignes');
    form.replaceChildren();
    $('#erreur-consignes').textContent = '';
    const champ = (libelle, valeur, lignes, chemin) => {
      const l = document.createElement('label');
      l.textContent = libelle;
      const c = document.createElement(lignes ? 'textarea' : 'input');
      if (lignes) c.rows = lignes;
      c.value = valeur;
      c.dataset.chemin = chemin;
      l.append(c);
      form.append(l);
    };
    champ('Consigne générale', config.systeme, 8, 'systeme');
    for (const [cle, a] of Object.entries(config.actions)) {
      champ(`Libellé : ${cle}`, a.libelle, 0, `actions.${cle}.libelle`);
      champ(`Consigne : ${a.libelle}`, a.consigne, 6, `actions.${cle}.consigne`);
    }
    $('#btn-enregistrer-consignes').onclick = async () => {
      const nouvelle = structuredClone(config);
      for (const c of form.querySelectorAll('[data-chemin]')) {
        const parties = c.dataset.chemin.split('.');
        let cible = nouvelle;
        while (parties.length > 1) cible = cible[parties.shift()];
        cible[parties[0]] = c.value;
      }
      try {
        await api.ia.enregistrerPrompts(nouvelle);
        $('#dlg-consignes').close();
        chargerActions();
      } catch (e) {
        $('#erreur-consignes').textContent = nettoyerErreur(e);
      }
    };
    $('#dlg-consignes').showModal();
  }
  $('#btn-consignes').addEventListener('click', ouvrirConsignes);
  $('#btn-fermer-consignes').addEventListener('click', () => $('#dlg-consignes').close());

  // --- Démarrage ----------------------------------------------------------------------
  chargerActions().catch((e) => ajouterMessage('systeme', nettoyerErreur(e)));
  verifierEtat();
  afficherDocs();
  setInterval(verifierEtat, 30000);
  setInterval(afficherDocs, 60000);
})();
