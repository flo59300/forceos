'use strict';
/**
 * ForceOS : synchronisation du dossier Documents_IA avec la base documentaire
 * de FO Gestion, consultée par l'assistant IA
 * Conçu et développé par Florian Bigotte pour Force Ouvrière
 *
 * Le texte de chaque document (PDF, PDF scanné par OCR, Word, LibreOffice,
 * texte) est extrait par lib/documents.js, dans un bac à sable Firejail, puis
 * transmis à FO Gestion qui l'indexe avec sa propre recherche. Le texte
 * extrait est gardé en cache, par empreinte du fichier : un PDF scanné n'est
 * passé à l'OCR qu'une seule fois, même après un redémarrage.
 */
const crypto = require('node:crypto');
const fs = require('node:fs');
const fsp = require('node:fs/promises');
const path = require('node:path');
const { extraireDocument, EXT_DOCUMENTS } = require('./documents');
const { lireJson, ecrireJson } = require('./stockage');

const PROFONDEUR_MAX = 3;
const FICHIERS_MAX = 500;

async function empreinteFichier(chemin) {
  const h = crypto.createHash('sha256');
  for await (const morceau of fs.createReadStream(chemin)) h.update(morceau);
  return h.digest('hex');
}

/**
 * @param {{dossier:string, dossierCache:string, dossierTmp:string,
 *          appel:(chemin:string, json?:object) => Promise<object>}} o
 */
function creerDocumentsIA({ dossier, dossierCache, dossierTmp, appel }) {
  const cheminEtat = path.join(dossierCache, 'etat.json');
  const dossierExtraits = path.join(dossierCache, 'extraits');
  let enCours = null;

  async function lister() {
    const fichiers = [];
    async function parcourir(d, profondeur) {
      let entrees;
      try { entrees = await fsp.readdir(d, { withFileTypes: true }); } catch { return; }
      for (const e of entrees) {
        if (e.name.startsWith('.') || fichiers.length >= FICHIERS_MAX) continue;
        const c = path.join(d, e.name);
        // Les liens symboliques sont ignorés (ni isDirectory ni isFile)
        if (e.isDirectory() && profondeur < PROFONDEUR_MAX) await parcourir(c, profondeur + 1);
        else if (e.isFile() && EXT_DOCUMENTS.has(path.extname(e.name).toLowerCase())) fichiers.push(c);
      }
    }
    await parcourir(dossier, 0);
    return fichiers.sort();
  }

  function synchroniser(progres) {
    if (enCours) return enCours;
    enCours = (async () => {
      await fsp.mkdir(dossierExtraits, { recursive: true, mode: 0o700 });
      const fichiers = await lister();
      const documents = [];
      const erreurs = [];
      const conserves = new Set();

      for (const [rang, chemin] of fichiers.entries()) {
        progres?.({ rang: rang + 1, total: fichiers.length, fichier: path.basename(chemin) });
        try {
          const empreinte = await empreinteFichier(chemin);
          const cache = path.join(dossierExtraits, `${empreinte}.json`);
          conserves.add(`${empreinte}.json`);
          let extrait = lireJson(cache, null);
          if (!extrait) {
            const doc = await extraireDocument(chemin, { dossierTmp });
            const texte = doc.sections.map((s) => (s.section ? `${s.section}\n${s.texte}` : s.texte)).join('\n\n').trim();
            if (!texte) throw new Error('aucun texte exploitable');
            extrait = { titre: doc.titre, texte };
            await ecrireJson(cache, extrait, { compact: true });
          }
          documents.push({ empreinte, titre: extrait.titre, texte: extrait.texte });
        } catch (e) {
          erreurs.push(`${path.basename(chemin)} : ${e.message}`);
        }
        await new Promise(setImmediate); // laisse respirer le processus principal
      }

      // Purge des extraits de fichiers supprimés ou modifiés
      for (const nom of await fsp.readdir(dossierExtraits)) {
        if (!conserves.has(nom)) await fsp.rm(path.join(dossierExtraits, nom), { force: true });
      }

      const r = await appel('/interne/kb/synchroniser', { documents });
      const etat = {
        documents: documents.length,
        ajoutes: r.ajoutes ?? 0,
        retires: r.retires ?? 0,
        passages: r.stats?.chunks ?? 0,
        erreurs: [...erreurs, ...(r.erreurs ?? [])],
        maj: Date.now()
      };
      await ecrireJson(cheminEtat, etat);
      return etat;
    })().finally(() => { enCours = null; });
    return enCours;
  }

  /** @returns {Promise<{titre:string, section:string, texte:string, source:string}[]>} */
  async function rechercher(question, nombre = 5) {
    const r = await appel('/interne/kb/rechercher', { question, nombre });
    return (r.resultats ?? []).map((x) => ({
      titre: x.titre,
      section: `passage ${x.passage}`,
      texte: x.texte,
      source: x.source
    }));
  }

  function etat() {
    const e = lireJson(cheminEtat, { documents: 0, passages: 0, maj: null, erreurs: [] });
    return { ...e, enCours: Boolean(enCours) };
  }

  return { synchroniser, rechercher, etat, dossier };
}

module.exports = { creerDocumentsIA };
