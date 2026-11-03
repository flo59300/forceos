'use strict';
/**
 * ForceOS : mode serveur de FO Gestion pour les téléphones
 * Conçu et développé par Florian Bigotte pour Force Ouvrière
 *
 * Le temps choisi par le secrétaire, FO Gestion est servie en HTTPS sur le
 * réseau local : soit le Wi-Fi créé par le PC, soit le Wi-Fi existant.
 * Chaque téléphone se connecte avec son propre compte FO Gestion.
 *
 * L'ouverture du réseau (point d'accès, pare-feu) est faite par un outil
 * root lancé avec pkexec (mot de passe administrateur). Cet outil reste en
 * vie pendant toute la durée du mode serveur et referme tout dès que son
 * entrée standard se ferme : arrêt demandé, verrouillage de l'écran,
 * fermeture ou plantage de FO Gestion, bouton d'urgence.
 *
 * Côté téléphones, seul FO Gestion est servi : les adresses internes, la
 * sauvegarde, le mode serveur lui-même et l'assistant IA (qui dépend du
 * poste) sont refusés ; les essais de connexion sont limités par adresse.
 */
const https = require('node:https');
const fs = require('node:fs');
const path = require('node:path');
const crypto = require('node:crypto');
const { spawn, spawnSync } = require('node:child_process');
const { Readable } = require('node:stream');
const QRCode = require('qrcode');

const PORT = 8443;
const OUTIL = '/usr/libexec/forceos/serveur-reseau';
const CORPS_MAX = 64 * 1024 * 1024;
const REFUSES = /^\/(interne|forceos|modules\/assistant|__sante)(\/|$)/;
const LIMITE_REQUETES = { fenetreMs: 60_000, max: 400 };
const LIMITE_CONNEXIONS = { fenetreMs: 5 * 60_000, max: 10 };

/** Certificat auto-signé du poste pour l'adresse donnée (refait si l'adresse change). */
function certificat(dossier, ip) {
  fs.mkdirSync(dossier, { recursive: true, mode: 0o700 });
  const cle = path.join(dossier, 'cle.pem');
  const cert = path.join(dossier, 'certificat.pem');
  const repere = path.join(dossier, 'adresse');
  const deja = fs.existsSync(cle) && fs.existsSync(cert) && fs.existsSync(repere)
    && fs.readFileSync(repere, 'utf8').trim() === ip;
  if (!deja) {
    const r = spawnSync('/usr/bin/openssl', ['req', '-x509', '-newkey', 'ec', '-pkeyopt', 'ec_paramgen_curve:prime256v1',
      '-nodes', '-days', '825', '-subj', '/CN=FO Gestion ForceOS/O=Force Ouvriere',
      '-addext', `subjectAltName=IP:${ip}`, '-keyout', cle, '-out', cert], { stdio: 'ignore', timeout: 20000 });
    if (r.status !== 0) throw new Error('Impossible de créer le certificat du mode serveur.');
    fs.chmodSync(cle, 0o600);
    fs.writeFileSync(repere, `${ip}\n`, { mode: 0o600 });
  }
  const empreinte = new crypto.X509Certificate(fs.readFileSync(cert)).fingerprint256;
  return { key: fs.readFileSync(cle), cert: fs.readFileSync(cert), empreinte };
}

/** Limiteur simple par adresse IP sur une fenêtre glissante. */
function limiteur({ fenetreMs, max }) {
  const compteurs = new Map();
  return (ip) => {
    const maintenant = Date.now();
    const liste = (compteurs.get(ip) ?? []).filter((t) => maintenant - t < fenetreMs);
    liste.push(maintenant);
    compteurs.set(ip, liste);
    return liste.length <= max;
  };
}

const svg = (texte) => QRCode.toString(texte, { type: 'svg', margin: 1, errorCorrectionLevel: 'M' })
  .then((s) => `data:image/svg+xml;base64,${Buffer.from(s).toString('base64')}`);
const echapperWifi = (s) => String(s).replace(/([\\;,:"])/g, '\\$1');

/**
 * @param {{dossier:string, jeton:string, creerMoteur:(baseURL:string)=>object, csp:string,
 *          surChangement:(etat:object)=>void, modeTest?:boolean}} o
 */
function creerModeServeur({ dossier, jeton, creerMoteur, csp, surChangement, modeTest = false }) {
  let actif = null;   // { serveur, outil, moteur, minuterie, etat }

  function etat() {
    return actif ? { ...actif.etat } : { actif: false };
  }

  async function arreter(raison = 'arrêt demandé') {
    if (!actif) return etat();
    const a = actif;
    actif = null;
    clearTimeout(a.minuterie);
    try { a.serveur.closeAllConnections?.(); a.serveur.close(); } catch { /* déjà fermé */ }
    try { a.outil?.stdin.end(); } catch { /* déjà arrêté */ }
    try { await a.moteur.arreter?.(); } catch { /* ignoré */ }
    surChangement({ actif: false, raison });
    return etat();
  }

  /** Lance l'outil root (pkexec) et attend sa première ligne : adresse, réseau Wi-Fi. */
  function ouvrirReseau(mode, minutes) {
    if (modeTest) return Promise.resolve({ outil: null, infos: { ip: '127.0.0.1', interface: 'lo' } });
    return new Promise((resolve, reject) => {
      const outil = spawn('/usr/bin/pkexec', [OUTIL, mode, String(PORT), String(minutes)], { stdio: ['pipe', 'pipe', 'pipe'] });
      let tampon = '';
      let erreur = '';
      const delai = setTimeout(() => { outil.kill(); reject(new Error('Le réseau n’a pas pu être préparé à temps.')); }, 90000);
      outil.stderr.setEncoding('utf8');
      outil.stderr.on('data', (d) => { erreur = (erreur + d).slice(-500); });
      outil.stdout.setEncoding('utf8');
      outil.stdout.on('data', (d) => {
        tampon += d;
        const i = tampon.indexOf('\n');
        if (i < 0) return;
        clearTimeout(delai);
        try { resolve({ outil, infos: JSON.parse(tampon.slice(0, i)) }); } catch { outil.kill(); reject(new Error('Réponse inattendue de l’outil réseau.')); }
      });
      outil.on('error', (e) => { clearTimeout(delai); reject(e); });
      outil.on('exit', (code) => {
        clearTimeout(delai);
        if (code === 126 || code === 127) reject(new Error('Mot de passe administrateur refusé ou annulé.'));
        else if (code) reject(new Error(erreur.trim().split('\n').pop() || 'Le réseau n’a pas pu être ouvert.'));
        // Arrêt de l'outil pendant le mode serveur (bouton d'urgence, erreur) : tout s'arrête
        if (actif && actif.outil === outil) arreter('réseau refermé');
      });
    });
  }

  async function demarrer({ mode, minutes }) {
    if (actif) return etat();
    if (!['wifi-pc', 'wifi-existant'].includes(mode)) throw new Error('Mode réseau inconnu.');
    if (![30, 60, 120, 240].includes(minutes)) throw new Error('Durée non prévue.');
    const { outil, infos } = await ouvrirReseau(mode, minutes);
    try {
      if (!/^\d{1,3}(\.\d{1,3}){3}$/.test(infos.ip ?? '')) throw new Error('Adresse réseau invalide.');
      const { key, cert, empreinte } = certificat(path.join(dossier, 'serveur'), infos.ip);
      const port = modeTest ? 30000 + crypto.randomInt(20000) : PORT;
      const moteur = creerMoteur(`https://${infos.ip}:${port}/`);
      const okRequetes = limiteur(LIMITE_REQUETES);
      const okConnexions = limiteur(LIMITE_CONNEXIONS);

      const serveur = https.createServer({ key, cert, minVersion: 'TLSv1.2' }, async (req, res) => {
        const ip = String(req.socket.remoteAddress || '').replace(/^::ffff:/, '');
        const repondre = (statut, texte) => {
          res.writeHead(statut, { 'Content-Type': 'text/plain; charset=utf-8', 'Cache-Control': 'no-store' });
          res.end(texte);
        };
        let url;
        try { url = new URL(req.url, 'https://serveur.invalid'); } catch { return repondre(400, 'Adresse invalide.'); }
        if (!okRequetes(ip)) return repondre(429, 'Trop de requêtes : patientez une minute.');
        if (REFUSES.test(url.pathname)) return repondre(403, 'Cet écran n’est disponible que sur le PC ForceOS.');
        if (req.method === 'POST' && /^\/connexion\/?$/.test(url.pathname) && !okConnexions(ip)) {
          return repondre(429, 'Trop d’essais de connexion depuis ce téléphone : patientez cinq minutes.');
        }
        const entetes = {};
        for (const [nom, valeur] of Object.entries(req.headers)) {
          if (!/^x-forceos-/i.test(nom) && nom !== 'host' && valeur !== undefined) entetes[nom] = Array.isArray(valeur) ? valeur.join(', ') : valeur;
        }
        entetes['X-ForceOS-Jeton'] = jeton;
        let corps = null;
        if (!['GET', 'HEAD'].includes(req.method)) {
          const morceaux = [];
          let taille = 0;
          for await (const m of req) {
            taille += m.length;
            if (taille > CORPS_MAX) return repondre(413, 'Envoi trop volumineux.');
            morceaux.push(m);
          }
          corps = Buffer.concat(morceaux);
        }
        let r;
        try {
          r = await moteur.requete({ methode: req.method, chemin: url.pathname + url.search, entetes, corps, adresse: ip });
        } catch (e) { return repondre(503, `FO Gestion : ${e.message}`); }
        const sortie = {};
        for (const [nom, valeur] of r.entetes) {
          const n = nom.toLowerCase();
          const v = n === 'set-cookie' && !/;\s*secure/i.test(valeur) ? `${valeur}; Secure` : valeur;
          if (n === 'set-cookie') (sortie[nom] ??= []).push(v); else sortie[nom] = v;
        }
        sortie['Content-Security-Policy'] = csp;
        sortie['X-Frame-Options'] = 'DENY';
        sortie['Referrer-Policy'] = 'no-referrer';
        res.writeHead(r.statut, sortie);
        if (r.flux) Readable.from(r.flux).pipe(res); else res.end();
      });
      serveur.headersTimeout = 20000;
      serveur.requestTimeout = 120000;
      await new Promise((resolve, reject) => {
        serveur.once('error', reject);
        serveur.listen(port, infos.ip, resolve);
      });
      const adresse = `https://${infos.ip}:${port}/`;
      const fin = Date.now() + minutes * 60000;
      const etatPublic = {
        actif: true, mode, adresse, fin, empreinte,
        reseau: infos.ssid ?? null, motDePasseWifi: infos.motdepasse ?? null,
        qrAdresse: await svg(adresse),
        qrWifi: infos.ssid ? await svg(`WIFI:T:WPA;S:${echapperWifi(infos.ssid)};P:${echapperWifi(infos.motdepasse)};;`) : null
      };
      actif = { serveur, outil, moteur, etat: etatPublic, minuterie: setTimeout(() => arreter('durée écoulée'), minutes * 60000) };
      surChangement(etatPublic);
      return etat();
    } catch (e) {
      try { outil?.stdin.end(); } catch { /* ignoré */ }
      throw e;
    }
  }

  return { demarrer, arreter, etat, port: PORT };
}

module.exports = { creerModeServeur };
