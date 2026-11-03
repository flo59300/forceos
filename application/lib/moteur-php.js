'use strict';
/**
 * ForceOS : moteur PHP de FO Gestion, sans aucun port réseau
 * Conçu et développé par Florian Bigotte pour Force Ouvrière
 *
 * Protection contre PHP2Shell (CVE-2026-109618 et CVE-2026-109619) et
 * contre toute faille future du même genre :
 *
 *   - Aucun serveur n'écoute. L'ancien serveur intégré (php -S) ouvrait un
 *     port sur 127.0.0.1 : n'importe quel programme du poste, et surtout
 *     n'importe quelle page web ouverte dans le navigateur, pouvait lui
 *     envoyer une requête. PHP l'analysait AVANT que le routeur ne vérifie
 *     le jeton : la faille PHP2Shell se déclenchait donc sans jeton.
 *     Désormais, seules les requêtes de la fenêtre FO Gestion (interceptées
 *     par Electron) et du processus principal atteignent PHP.
 *
 *   - Un processus PHP neuf par requête (php-cgi). L'attaque PHP2Shell
 *     enchaîne deux requêtes sur le même processus réutilisé (état global
 *     laissé périmé par la première) : ici, aucun processus n'est réutilisé.
 *
 *   - Cache OPcache sur fichier uniquement, sans mémoire partagée entre
 *     processus (la confusion de type d'OPcache visait cette mémoire).
 */
const { spawn } = require('node:child_process');
const fs = require('node:fs');
const path = require('node:path');

const TYPES = {
  '.css': 'text/css; charset=utf-8', '.js': 'text/javascript; charset=utf-8', '.mjs': 'text/javascript; charset=utf-8',
  '.json': 'application/json', '.map': 'application/json', '.webmanifest': 'application/manifest+json',
  '.html': 'text/html; charset=utf-8', '.txt': 'text/plain; charset=utf-8', '.xml': 'application/xml',
  '.png': 'image/png', '.jpg': 'image/jpeg', '.jpeg': 'image/jpeg', '.gif': 'image/gif', '.webp': 'image/webp',
  '.svg': 'image/svg+xml', '.ico': 'image/x-icon', '.avif': 'image/avif',
  '.woff': 'font/woff', '.woff2': 'font/woff2', '.ttf': 'font/ttf', '.otf': 'font/otf',
  '.pdf': 'application/pdf', '.csv': 'text/csv; charset=utf-8', '.mp3': 'audio/mpeg', '.mp4': 'video/mp4'
};
const SANS_CORPS = new Set([204, 205, 304]);
const FIN_ENTETES = Buffer.from('\r\n\r\n');

/** Cookies de la fenêtre, gardés en mémoire par le processus principal. */
class BocalCookies {
  constructor() { this.cookies = new Map(); }

  enregistrer(ligne) {
    const [paire, ...attributs] = String(ligne).split(';');
    const i = paire.indexOf('=');
    if (i < 1) return;
    const nom = paire.slice(0, i).trim();
    const valeur = paire.slice(i + 1).trim();
    if (!/^[!#$%&'*+.^_`|~0-9A-Za-z-]+$/.test(nom)) return;
    let expiration = null;
    let maxAge = null;
    for (const a of attributs) {
      const j = a.indexOf('=');
      const cle = (j < 0 ? a : a.slice(0, j)).trim().toLowerCase();
      const val = j < 0 ? '' : a.slice(j + 1).trim();
      if (cle === 'max-age' && /^-?\d+$/.test(val)) maxAge = Date.now() + Number(val) * 1000;
      if (cle === 'expires') { const t = Date.parse(val); if (!Number.isNaN(t)) expiration = t; }
    }
    const fin = maxAge ?? expiration;
    if (valeur === '' || valeur === 'deleted' || (fin !== null && fin <= Date.now())) this.cookies.delete(nom);
    else this.cookies.set(nom, { valeur, fin });
  }

  entete() {
    const maintenant = Date.now();
    const parts = [];
    for (const [nom, { valeur, fin }] of this.cookies) {
      if (fin !== null && fin <= maintenant) { this.cookies.delete(nom); continue; }
      parts.push(`${nom}=${valeur}`);
    }
    return parts.join('; ');
  }

  vider() { this.cookies.clear(); }
}

/**
 * @param {object} o
 * @param {string} o.phpCgi        binaire php-cgi
 * @param {string[]} o.argsIni     options « -c » et « -d » de l'interpréteur
 * @param {object} o.environnement variables propres à FO Gestion
 * @param {string} o.docroot       dossier public/ de FO Gestion
 * @param {string} o.routeur       routeur.php (jeton, adresses internes, contrôleur frontal)
 * @param {number} o.port          port de l'adresse d'origine (aucun serveur n'y écoute)
 * @param {string} o.journal       fichier des erreurs de lancement
 * @param {number} [o.simultanes]  nombre maximal de processus PHP en même temps
 */
function creerMoteurPhp({ phpCgi, argsIni, environnement, docroot, routeur, port, journal, simultanes = 4 }) {
  const racine = fs.realpathSync(docroot);
  let actifs = 0;
  const attente = [];
  const enfants = new Set();
  let arrete = false;

  const prendre = () => new Promise((resolve) => {
    if (actifs < simultanes) { actifs++; resolve(); } else attente.push(resolve);
  });
  const rendre = () => { const suivant = attente.shift(); if (suivant) suivant(); else actifs--; };

  /** Fichier statique de public/ (pas de fichier caché, pas de script PHP, pas de sortie du dossier). */
  function statique(chemin) {
    let decode;
    try { decode = decodeURIComponent(chemin); } catch { return { refuse: true }; }
    if (decode.includes('\0') || /(^|\/)\./.test(decode) || /\.(php\d?|phtml|phar)$/i.test(decode)) return { refuse: true };
    try {
      const fichier = fs.realpathSync(path.join(racine, decode));
      if (!fichier.startsWith(racine + path.sep)) return null;
      const st = fs.statSync(fichier);
      return st.isFile() ? { fichier, taille: st.size } : null;
    } catch { return null; }
  }

  function variablesCgi({ methode, chemin, entetes, longueur, adresse }) {
    const [, requete = ''] = chemin.split(/\?(.*)/s);
    const env = {
      ...environnement,
      GATEWAY_INTERFACE: 'CGI/1.1',
      SERVER_SOFTWARE: 'ForceOS',
      SERVER_PROTOCOL: 'HTTP/1.1',
      SERVER_NAME: '127.0.0.1',
      SERVER_ADDR: '127.0.0.1',
      SERVER_PORT: String(port),
      // Adresse du client : celle du téléphone en mode serveur (limitation des essais)
      REMOTE_ADDR: adresse || '127.0.0.1',
      REMOTE_PORT: '0',
      REQUEST_METHOD: methode,
      REQUEST_URI: chemin,
      QUERY_STRING: requete,
      DOCUMENT_ROOT: racine,
      SCRIPT_FILENAME: routeur,
      SCRIPT_NAME: '/index.php',
      REDIRECT_STATUS: '200'
    };
    if (longueur !== null) env.CONTENT_LENGTH = String(longueur);
    for (const [nom, valeur] of Object.entries(entetes)) {
      const n = nom.toLowerCase();
      if (n === 'content-type') { env.CONTENT_TYPE = valeur; continue; }
      // « Proxy » n'est jamais transmis (httpoxy) ; la longueur vient du corps réel
      if (n === 'content-length' || n === 'proxy' || !/^[a-z0-9-]+$/.test(n)) continue;
      env[`HTTP_${n.toUpperCase().replace(/-/g, '_')}`] = String(valeur);
    }
    env.HTTP_HOST = `127.0.0.1:${port}`;
    return env;
  }

  /**
   * Exécute une requête.
   * @returns {Promise<{statut:number, entetes:Array<[string,string]>, flux:import('node:stream').Readable|null}>}
   */
  async function requete({ methode = 'GET', chemin = '/', entetes = {}, corps = null, adresse = null }) {
    if (arrete) throw new Error('FO Gestion est en cours d’arrêt.');
    const p = chemin.split('?')[0] || '/';
    if (methode === 'GET' || methode === 'HEAD') {
      const s = statique(p);
      if (s?.refuse) return { statut: 404, entetes: [['Content-Type', 'text/plain; charset=utf-8']], flux: null };
      if (s) {
        return {
          statut: 200,
          entetes: [['Content-Type', TYPES[path.extname(s.fichier).toLowerCase()] ?? 'application/octet-stream'],
            ['Content-Length', String(s.taille)], ['X-Content-Type-Options', 'nosniff'], ['Cache-Control', 'no-cache']],
          flux: methode === 'HEAD' ? null : fs.createReadStream(s.fichier)
        };
      }
    }

    await prendre();
    let libere = false;
    const liberer = () => { if (!libere) { libere = true; rendre(); } };
    try {
      const contenu = corps === null ? null : Buffer.isBuffer(corps) ? corps : Buffer.from(corps);
      const enfant = spawn(phpCgi, argsIni, {
        env: variablesCgi({ methode, chemin, entetes, adresse, longueur: contenu ? contenu.length : (methode === 'POST' ? 0 : null) }),
        stdio: ['pipe', 'pipe', 'pipe']
      });
      enfants.add(enfant);
      enfant.on('close', () => { enfants.delete(enfant); liberer(); });
      enfant.on('error', liberer);
      enfant.stdin.on('error', () => {});
      enfant.stdin.end(contenu ?? undefined);
      enfant.stderr.on('data', (d) => fs.appendFile(journal, d, () => {}));

      return await new Promise((resolve, reject) => {
        let tampon = Buffer.alloc(0);
        const surDonnees = (d) => {
          tampon = Buffer.concat([tampon, d]);
          let fin = tampon.indexOf(FIN_ENTETES);
          let saut = 4;
          if (fin < 0) { fin = tampon.indexOf('\n\n'); saut = 2; }
          if (fin < 0) {
            if (tampon.length > 256 * 1024) { enfant.kill('SIGKILL'); reject(new Error('Réponse de PHP invalide.')); }
            return;
          }
          enfant.stdout.off('data', surDonnees);
          enfant.stdout.off('end', surFin);
          const lignes = tampon.subarray(0, fin).toString('latin1').split(/\r?\n/);
          const reste = tampon.subarray(fin + saut);
          let statut = 200;
          let redirection = false;
          const liste = [];
          for (const l of lignes) {
            const i = l.indexOf(':');
            if (i < 1) continue;
            const nom = l.slice(0, i).trim();
            const valeur = l.slice(i + 1).trim();
            if (/^status$/i.test(nom)) { statut = Number.parseInt(valeur, 10) || 500; continue; }
            if (/^x-powered-by$/i.test(nom)) continue;
            if (/^location$/i.test(nom)) redirection = true;
            liste.push([nom, valeur]);
          }
          if (redirection && statut === 200) statut = 302;
          enfant.stdout.pause();
          if (reste.length) enfant.stdout.unshift(reste);
          resolve({ statut, entetes: liste, flux: SANS_CORPS.has(statut) || methode === 'HEAD' ? (enfant.stdout.resume(), null) : enfant.stdout });
        };
        const surFin = () => reject(new Error('FO Gestion n’a rien répondu.'));
        enfant.stdout.on('data', surDonnees);
        enfant.stdout.on('end', surFin);
        enfant.on('error', reject);
      });
    } catch (e) {
      liberer();
      throw e;
    }
  }

  /** Requête complète, réponse entièrement lue. */
  async function requeteComplete(o) {
    const r = await requete(o);
    const morceaux = [];
    if (r.flux) for await (const m of r.flux) morceaux.push(m);
    return { ...r, corps: Buffer.concat(morceaux) };
  }

  function arreter() {
    arrete = true;
    for (const e of enfants) e.kill('SIGKILL');
    for (const r of attente.splice(0)) r();
  }

  return { requete, requeteComplete, arreter };
}

module.exports = { creerMoteurPhp, BocalCookies };
