'use strict';
/**
 * ForceOS : lecture et écriture atomique de fichiers JSON du militant
 * Conçu et développé par Florian Bigotte pour Force Ouvrière
 */
const fs = require('node:fs');
const fsp = require('node:fs/promises');
const path = require('node:path');
const crypto = require('node:crypto');

function lireJson(chemin, defaut) {
  try { return JSON.parse(fs.readFileSync(chemin, 'utf8')); } catch { return defaut; }
}

/** Écriture dans un fichier temporaire puis renommage : jamais de fichier à moitié écrit. */
async function ecrireJson(chemin, donnees, { compact = false } = {}) {
  await fsp.mkdir(path.dirname(chemin), { recursive: true, mode: 0o700 });
  const tmp = `${chemin}.${crypto.randomBytes(4).toString('hex')}.tmp`;
  await fsp.writeFile(tmp, JSON.stringify(donnees, null, compact ? 0 : 2), { mode: 0o600 });
  await fsp.rename(tmp, chemin);
}

module.exports = { lireJson, ecrireJson };
