'use strict';
/**
 * ForceOS : sauvegarde et restauration chiffrées de FO Gestion
 * Conçu et développé par Florian Bigotte pour Force Ouvrière
 *
 * Export, entièrement en mémoire :
 *   FO Gestion (dump SQL en flux) -> SHA-256 -> gzip -> AES-256-GCM -> support externe
 * Clé dérivée de la phrase de passe par scrypt (128 Mo de mémoire).
 * La clé maîtresse du poste est incluse dans la partie chiffrée : une
 * sauvegarde se restaure sur un autre poste avec la seule phrase de passe.
 *
 * Format .sql.enc :
 *   « FOSBAK01 » | scrypt N, r, p | sel (16) | IV (12) | données chiffrées | étiquette GCM (16)
 *   L'en-tête de 48 octets est authentifié comme donnée associée.
 */
const crypto = require('node:crypto');
const fs = require('node:fs');
const fsp = require('node:fs/promises');
const path = require('node:path');
const zlib = require('node:zlib');
const { pipeline } = require('node:stream/promises');
const { Transform, Writable } = require('node:stream');
const { StringDecoder } = require('node:string_decoder');
const { appelInterne } = require('./interne');

const MAGIE = Buffer.from('FOSBAK01', 'ascii');
const SCRYPT = { N: 2 ** 17, r: 8, p: 1 };
const SCRYPT_MAXMEM = 256 * 1024 * 1024;
const LONG_ENTETE = 48;
const LONG_TAG = 16;
const MARQUEUR_FIN = '-- FIN DE SAUVEGARDE FORCEOS';
const MOTIF_CLE = /^-- FORCEOS-CLE-MAITRESSE: ([A-Za-z0-9+/]{43}=)$/;
const PHRASE_MIN = 12;
const TAILLE_LOT = 4 * 1024 * 1024;

// ---------------------------------------------------------------------
// Clé et en-tête
// ---------------------------------------------------------------------
function verifierPhrase(phrase) {
  if (typeof phrase !== 'string' || [...phrase].length < PHRASE_MIN) {
    throw new Error(`La phrase de passe doit comporter au moins ${PHRASE_MIN} caractères.`);
  }
}

function deriverCle(phrase, sel, { N, r, p }) {
  return new Promise((resolve, reject) => {
    crypto.scrypt(phrase.normalize('NFC'), sel, 32, { N, r, p, maxmem: SCRYPT_MAXMEM },
      (err, cle) => (err ? reject(err) : resolve(cle)));
  });
}

function construireEntete(sel, iv) {
  const b = Buffer.alloc(LONG_ENTETE);
  MAGIE.copy(b, 0);
  b.writeUInt32BE(SCRYPT.N, 8);
  b.writeUInt32BE(SCRYPT.r, 12);
  b.writeUInt32BE(SCRYPT.p, 16);
  sel.copy(b, 20);
  iv.copy(b, 36);
  return b;
}

function lireEntete(b) {
  if (b.length !== LONG_ENTETE || !b.subarray(0, 8).equals(MAGIE)) {
    throw new Error('Ce fichier n’est pas une sauvegarde ForceOS.');
  }
  const params = { N: b.readUInt32BE(8), r: b.readUInt32BE(12), p: b.readUInt32BE(16) };
  // Bornes : un fichier piégé ne doit pas saturer la mémoire
  if (params.N > 2 ** 20 || params.r > 16 || params.p > 4) throw new Error('Paramètres de chiffrement hors limites.');
  return { params, sel: b.subarray(20, 36), iv: b.subarray(36, 48) };
}

// ---------------------------------------------------------------------
// Déchiffrement
// ---------------------------------------------------------------------
/**
 * Déchiffre vers un flux. GCM n'authentifie qu'à la fin : la restauration
 * fait donc un premier passage complet sans rien écrire.
 */
async function dechiffrerVersFlux(fichier, phrase, destination) {
  const fh = await fsp.open(fichier, 'r');
  try {
    const { size } = await fh.stat();
    if (size < LONG_ENTETE + LONG_TAG) throw new Error('Fichier de sauvegarde tronqué.');
    const entete = Buffer.alloc(LONG_ENTETE);
    await fh.read(entete, 0, LONG_ENTETE, 0);
    const tag = Buffer.alloc(LONG_TAG);
    await fh.read(tag, 0, LONG_TAG, size - LONG_TAG);
    const { params, sel, iv } = lireEntete(entete);
    const cle = await deriverCle(phrase, sel, params);
    const dechiffreur = crypto.createDecipheriv('aes-256-gcm', cle, iv);
    dechiffreur.setAAD(entete);
    dechiffreur.setAuthTag(tag);
    try {
      await pipeline(
        fs.createReadStream(fichier, { start: LONG_ENTETE, end: size - LONG_TAG - 1 }),
        dechiffreur, zlib.createGunzip(), destination
      );
    } catch {
      throw new Error('Phrase de passe incorrecte ou fichier altéré.');
    } finally {
      cle.fill(0);
    }
  } finally {
    await fh.close();
  }
}

// ---------------------------------------------------------------------
// Export
// ---------------------------------------------------------------------
function sondeContenu() {
  const hash = crypto.createHash('sha256');
  let fin = Buffer.alloc(0);
  let taille = 0;
  const flux = new Transform({
    transform(chunk, _enc, cb) {
      hash.update(chunk);
      taille += chunk.length;
      fin = Buffer.concat([fin, chunk]).subarray(-128);
      cb(null, chunk);
    }
  });
  flux.resultat = () => ({ empreinte: hash.digest('hex'), taille, complet: fin.toString('utf8').includes(MARQUEUR_FIN) });
  return flux;
}

async function fluxExport({ moteur, jeton, jetonInterne, ticket }) {
  const r = await moteur.requete({
    methode: 'GET',
    chemin: '/interne/sauvegarde/export',
    entetes: { 'X-ForceOS-Jeton': jeton, 'X-ForceOS-Interne': jetonInterne, 'X-ForceOS-Ticket': ticket }
  });
  if (r.statut !== 200) {
    r.flux?.resume();
    throw new Error(r.statut === 403
      ? 'Sauvegarde refusée : ticket expiré ou droits insuffisants.'
      : `Export refusé par FO Gestion (code ${r.statut}).`);
  }
  return r.flux;
}

async function exporterSauvegarde({ moteur, jeton, jetonInterne, ticket, phrase, destination }) {
  verifierPhrase(phrase);
  const sel = crypto.randomBytes(16);
  const iv = crypto.randomBytes(12);
  const entete = construireEntete(sel, iv);
  const cle = await deriverCle(phrase, sel, SCRYPT);
  const temporaire = `${destination}.partiel`;
  try {
    const chiffreur = crypto.createCipheriv('aes-256-gcm', cle, iv);
    chiffreur.setAAD(entete);
    // « wx » : aucun écrasement silencieux d'un fichier existant
    const sortie = fs.createWriteStream(temporaire, { flags: 'wx', mode: 0o600, flush: true });
    sortie.write(entete);
    const sonde = sondeContenu();
    const reponse = await fluxExport({ moteur, jeton, jetonInterne, ticket });
    await pipeline(reponse, sonde, zlib.createGzip({ level: 6 }), chiffreur, sortie, { end: false });
    await new Promise((res, rej) => sortie.end(chiffreur.getAuthTag(), (e) => (e ? rej(e) : res())));

    const exp = sonde.resultat();
    if (!exp.complet) throw new Error('Le dump SQL est incomplet : la sauvegarde a été annulée.');

    // Relecture intégrale : déchiffrement et comparaison de l'empreinte
    const verif = crypto.createHash('sha256');
    await dechiffrerVersFlux(temporaire, phrase, new Writable({ write(c, _e, cb) { verif.update(c); cb(); } }));
    if (verif.digest('hex') !== exp.empreinte) throw new Error('La vérification de la sauvegarde a échoué.');

    await fsp.rename(temporaire, destination);
    const { size } = await fsp.stat(destination);
    return { taille: size, empreinte: exp.empreinte };
  } catch (e) {
    await fsp.rm(temporaire, { force: true });
    throw e;
  } finally {
    cle.fill(0);
  }
}

// ---------------------------------------------------------------------
// Restauration
// ---------------------------------------------------------------------
/** Découpe le texte déchiffré en lots d'instructions complètes. */
function envoyeurLots(envoyerLot, intercepter) {
  const decodeur = new StringDecoder('utf8');
  let reste = '';
  let lot = [];
  let taille = 0;
  async function traiter(lignes) {
    for (const ligne of lignes) {
      if (intercepter(ligne)) continue;   // la clé maîtresse n'est jamais transmise à PHP
      lot.push(ligne);
      taille += ligne.length + 1;
      if (taille >= TAILLE_LOT && ligne.trimEnd().endsWith(';')) {
        await envoyerLot(lot.join('\n'));
        lot = [];
        taille = 0;
      }
    }
  }
  return new Writable({
    write(chunk, _enc, cb) {
      const lignes = (reste + decodeur.write(chunk)).split('\n');
      reste = lignes.pop();
      traiter(lignes).then(() => cb(), cb);
    },
    final(cb) {
      const dernieres = (reste + decodeur.end()).split('\n').filter((l) => l !== '');
      traiter(dernieres).then(() => (lot.length ? envoyerLot(lot.join('\n')) : null)).then(() => cb(), cb);
    }
  });
}

/**
 * @param {(cle:string) => Promise<void>} o.surCle  adoption de la clé maîtresse de la sauvegarde
 */
async function restaurerSauvegarde({ moteur, jeton, jetonInterne, ticket, phrase, fichier, dossierTmp, surCle }) {
  verifierPhrase(phrase);
  const appel = (etape, extra = {}) => appelInterne({
    moteur, jeton, jetonInterne, chemin: `/interne/restauration/${etape}`, methode: 'POST', ...extra
  });

  // 1. Copie privée : le fichier ne peut plus changer entre vérification et import
  await fsp.mkdir(dossierTmp, { recursive: true, mode: 0o700 });
  const copie = path.join(dossierTmp, `restauration-${crypto.randomUUID()}.sql.enc`);
  await fsp.copyFile(fichier, copie);
  await fsp.chmod(copie, 0o600);
  try {
    // 2. Premier passage : phrase et intégrité vérifiées, rien n'est écrit
    await dechiffrerVersFlux(copie, phrase, new Writable({ write(_c, _e, cb) { cb(); } }));

    // 3. Ouverture : instantané de l'état actuel, puis base vidée
    const { jeton: jetonRestauration } = await appel('debut', { entetes: { 'X-ForceOS-Ticket': ticket } });
    const entetes = { 'X-ForceOS-Restauration': jetonRestauration };
    try {
      let cle = null;
      // 4. Second passage : envoi par lots d'instructions complètes
      await dechiffrerVersFlux(copie, phrase, envoyeurLots(
        (texte) => appel('lot', { corps: texte, entetes }),
        (ligne) => {
          const m = MOTIF_CLE.exec(ligne);
          if (m) cle = m[1];
          return Boolean(m);
        }
      ));
      const fin = await appel('fin', { entetes });
      if (cle) await surCle(cle);
      return { ok: true, cleAdoptee: Boolean(cle), migrations: fin.migrations ?? [] };
    } catch (e) {
      await appel('annuler', { entetes }).catch(() => {});
      throw e;
    }
  } finally {
    await fsp.rm(copie, { force: true });
  }
}

module.exports = { exporterSauvegarde, restaurerSauvegarde, dechiffrerVersFlux, PHRASE_MIN };
