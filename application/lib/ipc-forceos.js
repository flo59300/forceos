'use strict';
/**
 * ForceOS : canaux IPC de l'assistant IA (texte, audio, documents)
 * Conçu et développé par Florian Bigotte pour Force Ouvrière
 *
 * La bourse et les actualités RSS ne passent plus par ici : ce sont les
 * modules de FO Gestion 3 qui les assurent, côté serveur PHP.
 * La base documentaire de l'assistant est celle de FO Gestion, alimentée
 * automatiquement par le dossier Documents_IA.
 */
const path = require('node:path');
const fsp = require('node:fs/promises');
const { spawn } = require('node:child_process');
const { shell } = require('electron');
const ia = require('./ia');
const { transcrire, verifierFichierAudio } = require('./transcription');
const { extraireDocument, verifierDocument } = require('./documents');
const { creerDocumentsIA } = require('./documents-ia');

const MOTIF_ID = /^[0-9a-f]{8}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{12}$/;
const DELAI_SYNCHRO_MS = 5000;

/**
 * @param {{ipcMain, emetteurLegitime:Function, dossierDonnees:string,
 *          dossierDocuments:string, appel:(chemin:string, json?:object)=>Promise<object>}} o
 * @returns {{synchroniserDocuments: () => Promise<object>}}
 */
function enregistrerModules({ ipcMain, emetteurLegitime, dossierDonnees, dossierDocuments, appel }) {
  const dossierTmp = path.join(dossierDonnees, 'tmp');
  const enCours = new Map(); // identifiant de requête -> AbortController
  const docs = creerDocumentsIA({
    dossier: path.join(dossierDocuments, 'Documents_IA'),
    dossierCache: path.join(dossierDonnees, 'documents-ia'),
    dossierTmp,
    appel
  });

  const garde = (fn) => async (evt, ...args) => {
    if (!emetteurLegitime(evt)) throw new Error('Émetteur refusé.');
    return fn(evt, ...args);
  };
  const envoyer = (evt, canal, donnees) => {
    if (!evt.sender.isDestroyed()) evt.sender.send(canal, donnees);
  };
  function ouvrirRequete(id) {
    if (typeof id !== 'string' || !MOTIF_ID.test(id) || enCours.has(id)) {
      throw new Error('Identifiant de requête invalide.');
    }
    const ctrl = new AbortController();
    enCours.set(id, ctrl);
    return ctrl;
  }
  async function diffuser(evt, id, generateur) {
    try {
      for await (const jeton of generateur) envoyer(evt, 'ia:flux', { id, type: 'jeton', texte: jeton });
      envoyer(evt, 'ia:flux', { id, type: 'fin' });
    } catch (e) {
      envoyer(evt, 'ia:flux', { id, type: e.name === 'AbortError' ? 'annule' : 'erreur', texte: e.message });
    } finally {
      enCours.delete(id);
    }
  }

  // --- Assistant IA ----------------------------------------------------------
  ipcMain.handle('ia:etat', garde(() => ia.etat()));

  ipcMain.handle('ia:envoyer', garde((evt, d) => {
    const { id, action, texte, historique = [], bibliotheque: avecDocs = false } = d ?? {};
    if (typeof texte !== 'string' || !texte.trim() || texte.length > 400000) throw new Error('Texte invalide.');
    ia.validerHistorique(historique);
    const ctrl = ouvrirRequete(id);
    const progres = (p) => envoyer(evt, 'ia:progres', { id, ...p });

    (async () => {
      let sources = [];
      if (avecDocs) {
        // La question précédente aide à comprendre les relances courtes
        const precedente = historique.filter((m) => m.role === 'user').at(-1)?.content ?? '';
        try {
          sources = await docs.rechercher(`${precedente.slice(-500)} ${texte}`.slice(-2000));
        } catch { /* recherche indisponible : réponse sans extraits */ }
        envoyer(evt, 'ia:flux', {
          id, type: 'sources',
          sources: sources.map((s) => ({ titre: s.titre, section: s.section, source: s.source }))
        });
      }
      await diffuser(evt, id, ia.repondre({ action, texte, historique, sources, signal: ctrl.signal, progres }));
    })();
    return { id };
  }));

  ipcMain.handle('ia:annuler', garde((_evt, id) => { enCours.get(id)?.abort(); }));
  ipcMain.handle('ia:lirePrompts', garde(() => ia.chargerPrompts()));
  // Enregistrement protégé par le mot de passe administrateur (polkit, étape 4)
  ipcMain.handle('ia:enregistrerPrompts', garde(async (_evt, config) => {
    const erreurs = ia.validerPrompts(config);
    if (erreurs.length) throw new Error(erreurs.join(' '));
    await new Promise((resolve, reject) => {
      const p = spawn('/usr/bin/pkexec', ['/usr/libexec/forceos/ecrire-prompts'], { stdio: ['pipe', 'ignore', 'pipe'] });
      let err = '';
      p.stderr.on('data', (x) => { err += x; });
      p.on('error', reject);
      p.on('close', (code) => {
        if (code === 0) return resolve();
        reject(new Error(code === 126 || code === 127
          ? 'Enregistrement annulé : le mot de passe administrateur est requis.'
          : err.trim() || 'Enregistrement refusé.'));
      });
      p.stdin.end(JSON.stringify(config, null, 2));
    });
    return { ok: true };
  }));

  // --- Audio -------------------------------------------------------------------
  ipcMain.handle('audio:traiter', garde(async (evt, d) => {
    const { id, chemin, consigne = '' } = d ?? {};
    const fichier = await verifierFichierAudio(chemin);
    const ctrl = ouvrirRequete(id);
    const progres = (p) => envoyer(evt, 'ia:progres', { id, ...p });
    (async () => {
      try {
        const transcription = await transcrire(fichier, { dossierTmp, signal: ctrl.signal, progres });
        if (!transcription) throw new Error('Aucune parole n’a été détectée dans ce fichier.');
        envoyer(evt, 'ia:flux', { id, type: 'transcription', texte: transcription });
        await diffuser(evt, id, ia.repondre({
          action: 'audio', texte: transcription, consigne: String(consigne).slice(0, 2000), signal: ctrl.signal, progres
        }));
      } catch (e) {
        envoyer(evt, 'ia:flux', { id, type: e.name === 'AbortError' ? 'annule' : 'erreur', texte: e.message });
        enCours.delete(id);
      }
    })();
    return { id };
  }));

  // --- Documents de l'IA (dossier Documents_IA) -----------------------------
  ipcMain.handle('bibliotheque:etat', garde(() => docs.etat()));
  ipcMain.handle('bibliotheque:indexer', garde((evt) =>
    docs.synchroniser((p) => envoyer(evt, 'bibliotheque:progres', p))));
  ipcMain.handle('bibliotheque:ouvrirDossier', garde(async () => {
    await fsp.mkdir(docs.dossier, { recursive: true });
    const erreur = await shell.openPath(docs.dossier);
    if (erreur) throw new Error(erreur);
  }));

  // --- Document joint ponctuellement à une question ----------------------------
  ipcMain.handle('documents:extraire', garde(async (evt, d) => {
    const { id, chemin } = d ?? {};
    const fichier = await verifierDocument(chemin);
    const ctrl = ouvrirRequete(id);
    try {
      const { titre, sections } = await extraireDocument(fichier, {
        dossierTmp, signal: ctrl.signal, progres: (p) => envoyer(evt, 'ia:progres', { id, ...p })
      });
      const texte = sections.map((s) => s.texte).join('\n\n').trim();
      if (!texte) throw new Error('Aucun texte n’a pu être extrait de ce document.');
      return { titre, texte: texte.slice(0, 400000), sections: sections.length };
    } finally {
      enCours.delete(id);
    }
  }));

  return {
    // Appelée par main.js une fois FO Gestion prête et le poste configuré
    synchroniserDocuments: () => new Promise((resolve) => {
      setTimeout(() => docs.synchroniser().then(resolve, () => resolve(null)), DELAI_SYNCHRO_MS);
    })
  };
}

module.exports = { enregistrerModules };
