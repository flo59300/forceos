'use strict';
/**
 * ForceOS : processus principal de FO Gestion
 * Conçu et développé par Florian Bigotte pour Force Ouvrière
 *
 * Séquence de démarrage :
 *   1. Instance unique, contrôle du compte Linux autorisé
 *   2. Clé maîtresse via safeStorage (trousseau libsecret obligatoire)
 *   3. Validation de PHP et de ses extensions, attente de MariaDB
 *   4. Lancement de FO Gestion (PHP intégré) sur 127.0.0.1, port aléatoire
 *   5. Base initiale ou migrations (instantané chiffré, retour arrière)
 *   6. Première configuration si le poste est neuf
 *   7. Verrouillage du réseau de la page, fenêtre principale,
 *      synchronisation des documents de l'IA
 */
const {
  app, BrowserWindow, session, dialog, ipcMain, shell, safeStorage, Menu, powerMonitor
} = require('electron');
const { spawn, spawnSync } = require('node:child_process');
const crypto = require('node:crypto');
const fs = require('node:fs');
const path = require('node:path');
const os = require('node:os');
const { Readable } = require('node:stream');
const { verifierPhp, faillePhp2Shell } = require('./lib/php-check');
const { appelInterne } = require('./lib/interne');
const { creerMoteurPhp, BocalCookies } = require('./lib/moteur-php');
const { exporterSauvegarde, restaurerSauvegarde } = require('./lib/sauvegarde');
const { creerModeServeur } = require('./lib/serveur-reseau');
const { enregistrerModules } = require('./lib/ipc-forceos');
const { ouvrirPremiereConfiguration } = require('./lib/premiere-configuration');

// ---------------------------------------------------------------------
// Constantes
// ---------------------------------------------------------------------
const EST_PACKAGE    = app.isPackaged;
const RACINE_RES     = EST_PACKAGE ? process.resourcesPath : __dirname;
const DOSSIER_PHP    = path.join(RACINE_RES, 'php');
const PHP_INI        = path.join(DOSSIER_PHP, 'php.ini');
const PHP_ROUTEUR    = path.join(DOSSIER_PHP, 'router.php');
const RACINE_WWW     = path.join(RACINE_RES, 'www');
const DOCROOT        = path.join(RACINE_WWW, 'public');
const LOGO           = path.join(RACINE_RES, 'assets', 'logo-fo.png');
// Les variables FORCEOS_* ne servent qu'aux tests en développement : le paquet
// installé les ignore, pour qu'aucun réglage de session ne puisse rediriger
// l'application vers un autre fichier de compte, une autre base ou un autre PHP.
const reglage = (nom, defaut) => (!EST_PACKAGE && process.env[nom]) || defaut;
const PHP_BIN        = reglage('FORCEOS_PHP_BIN', '/usr/bin/php');
const PHP_CGI        = reglage('FORCEOS_PHP_CGI', '/usr/bin/php-cgi');
// Développement seulement : PHP d'une distribution qui corrige sans changer le numéro de version
const PHP_NON_CORRIGE_ACCEPTE = reglage('FORCEOS_PHP_NON_CORRIGE_ACCEPTE', '') === '1';
const CONFIG_SYS     = reglage('FORCEOS_CONFIG_SYS', '/etc/forceos');
const SOCKET_MARIADB = reglage('FORCEOS_SOCKET_DB', '/run/mysqld/mysqld.sock');
const BASE           = 'forceos_gestion';
const OLLAMA_URL     = 'http://127.0.0.1:11434';
const CREDITS        = 'Conçu et développé par Florian Bigotte';

// Hors de GNOME (Cinnamon), Chromium ne détecte pas toujours le trousseau et se rabat sur
// un stockage en clair : libsecret est imposé (avant l'événement « ready »).
app.commandLine.appendSwitch('password-store', 'gnome-libsecret');
app.setPath('userData', path.join(app.getPath('appData'), 'ForceOS'));

// État global
let moteur = null;
let portPhp = null;
const cookies = new BocalCookies();
let jetonPhp = null;
let jetonInterne = null;
let origine = null;
let contexteMoteur = null;
let modeServeur = null;
let fenetre = null;
let arretEnCours = false;
let synchroniserDocuments = async () => null;

// ---------------------------------------------------------------------
// Utilitaires
// ---------------------------------------------------------------------
function erreurFatale(titre, message) {
  dialog.showErrorBox(`FO Gestion : ${titre}`, `${message}\n\nAssistance : Florian Bigotte.`);
  arreterPhp(true);
  app.exit(1);
}

function preparerDossiers() {
  const base = app.getPath('userData');
  const sous = ['', 'logs', 'tmp', 'instantanes', 'documents-ia',
    'writable', 'writable/cache', 'writable/logs', 'writable/session',
    'writable/uploads', 'writable/debugbar', 'writable/backups'];
  for (const s of sous) {
    const d = path.join(base, s);
    fs.mkdirSync(d, { recursive: true, mode: 0o700 });
    fs.chmodSync(d, 0o700);
  }
  return base;
}

/** Un seul compte Linux peut ouvrir l'application (fichier géré par root). */
function verifierUtilisateurAutorise() {
  const fichier = path.join(CONFIG_SYS, 'utilisateur-autorise');
  let st;
  try { st = fs.statSync(fichier); } catch {
    return { ok: false, raison: `Le fichier de compte autorisé est absent de ${CONFIG_SYS}.` };
  }
  if ((EST_PACKAGE && st.uid !== 0) || (st.mode & 0o022) !== 0) {
    return { ok: false, raison: 'Le fichier de compte autorisé a des droits non conformes.' };
  }
  const autorise = fs.readFileSync(fichier, 'utf8').trim();
  const courant = os.userInfo().username;
  return autorise === courant
    ? { ok: true }
    : { ok: false, raison: `Le compte « ${courant} » n’est pas autorisé à ouvrir FO Gestion sur ce poste.` };
}

/** Clé maîtresse : générée au premier lancement, protégée par le trousseau. */
/**
 * Trousseau GNOME prêt à l'emploi. Sous Cinnamon, le service peut ne pas être
 * enregistré sur le bus de la session : on le démarre (sans effet s'il tourne
 * déjà), puis on laisse à Chromium le temps d'initialiser son stockage chiffré.
 */
async function attendreTrousseau(delaiMs = 8000) {
  try {
    spawnSync('/usr/bin/gnome-keyring-daemon', ['--start', '--components=secrets'], { timeout: 5000, stdio: 'ignore' });
  } catch { /* absent : le contrôle ci-dessous le signalera */ }
  const fin = Date.now() + delaiMs;
  while (Date.now() < fin) {
    const pret = await safeStorage.isAsyncEncryptionAvailable().catch(() => false);
    if (pret) return true;
    await new Promise((r) => setTimeout(r, 400));
  }
  return false;
}

// API asynchrone de safeStorage uniquement : avec Electron 45, l'API synchrone
// (dépréciée) peut se dire indisponible au premier lancement alors que le
// trousseau répond déjà.
async function chargerCleMaitresse(dossier) {
  if (!(await safeStorage.isAsyncEncryptionAvailable())) throw new Error('Le trousseau sécurisé du système est indisponible.');
  const backend = safeStorage.getSelectedStorageBackend?.() ?? 'gnome_libsecret';
  if (backend === 'basic_text' || backend === 'unknown') {
    throw new Error(`Trousseau non sécurisé détecté (${backend}). Vérifiez que gnome-keyring est actif.`);
  }
  const fichier = path.join(dossier, 'cle-maitresse.enc');
  if (fs.existsSync(fichier)) {
    const { result, shouldReEncrypt } = await safeStorage.decryptStringAsync(fs.readFileSync(fichier));
    if (shouldReEncrypt) await ecrireCleMaitresse(fichier, result);
    return result;
  }
  const cle = crypto.randomBytes(32).toString('base64');
  await ecrireCleMaitresse(fichier, cle);
  return cle;
}

/** Écriture atomique de la clé maîtresse chiffrée par le trousseau. */
async function ecrireCleMaitresse(fichier, cle) {
  fs.writeFileSync(`${fichier}.tmp`, await safeStorage.encryptStringAsync(cle), { mode: 0o600 });
  fs.renameSync(`${fichier}.tmp`, fichier);
}

/** Clé de chiffrement propre à CodeIgniter, dérivée de la clé maîtresse. */
function cleCodeIgniter(cle) {
  const derivee = crypto.hkdfSync('sha256', Buffer.from(cle, 'base64'), Buffer.alloc(0),
    'ForceOS FO Gestion encryption.key', 32);
  return `hex2bin:${Buffer.from(derivee).toString('hex')}`;
}

/** Port de l'adresse d'origine de la fenêtre : rien n'y écoute, Electron intercepte tout. */
function portOrigine() {
  return 20000 + crypto.randomInt(40000);
}

async function attendreMariaDB(delaiMs = 15000) {
  const fin = Date.now() + delaiMs;
  while (Date.now() < fin) {
    if (fs.existsSync(SOCKET_MARIADB)) return true;
    await new Promise((r) => setTimeout(r, 300));
  }
  return false;
}

// ---------------------------------------------------------------------
// FO Gestion : moteur PHP sans port réseau (voir lib/moteur-php.js)
// ---------------------------------------------------------------------
function demarrerPhp({ cle, dossierDonnees }) {
  contexteMoteur = { cle, dossierDonnees };
  moteur = creerMoteurFO({ baseURL: `http://127.0.0.1:${portPhp}/`, port: portPhp });
}

/** Moteur PHP de FO Gestion pour une adresse donnée (fenêtre du PC, ou téléphones en mode serveur). */
function creerMoteurFO({ baseURL, port, securise = false }) {
  const { cle, dossierDonnees } = contexteMoteur;
  const utilisateur = os.userInfo().username;
  const opcache = path.join(dossierDonnees, 'opcache');
  fs.mkdirSync(opcache, { recursive: true, mode: 0o700 });
  return creerMoteurPhp({
    phpCgi: PHP_CGI,
    argsIni: [
      '-c', PHP_INI,
      '-d', `open_basedir=${RACINE_WWW}:${DOSSIER_PHP}:${dossierDonnees}:/usr/share/forceos`,
      '-d', `upload_tmp_dir=${path.join(dossierDonnees, 'tmp')}`,
      '-d', `sys_temp_dir=${path.join(dossierDonnees, 'tmp')}`,
      '-d', `error_log=${path.join(dossierDonnees, 'logs', 'php-erreurs.log')}`,
      '-d', `opcache.file_cache=${opcache}`
    ],
    docroot: DOCROOT,
    routeur: PHP_ROUTEUR,
    port,
    journal: path.join(dossierDonnees, 'logs', 'php-serveur.log'),
    // Environnement reconstruit : rien n'est hérité de la session.
    environnement: {
      PATH: '/usr/bin:/bin',
      LANG: 'fr_FR.UTF-8',
      FORCEOS_JETON: jetonPhp,
      FORCEOS_JETON_INTERNE: jetonInterne,
      FORCEOS_CLE_MAITRESSE: cle,
      FORCEOS_DONNEES: dossierDonnees,
      FORCEOS_OLLAMA: OLLAMA_URL,
      // Configuration de CodeIgniter 4, sans fichier .env
      CI_ENVIRONMENT: 'production',
      'app.baseURL': baseURL,
      ...(securise ? { HTTPS: 'on' } : {}),
      'app.forceGlobalSecureRequests': 'false',
      'cookie.secure': 'false',
      'encryption.key': cleCodeIgniter(cle),
      'database.default.hostname': SOCKET_MARIADB,
      'database.default.username': utilisateur,
      'database.default.password': '',
      'database.default.database': BASE,
      'database.default.DBDriver': 'MySQLi',
      'database.default.charset': 'utf8mb4',
      'database.default.DBCollat': 'utf8mb4_unicode_ci'
    }
  });
}

/** Contrôle de santé : PHP (php-cgi) répond, il est corrigé, et la base est joignable. */
async function attendrePhp(delaiMs = 15000) {
  const fin = Date.now() + delaiMs;
  let derniere = null;
  while (Date.now() < fin) {
    try {
      const r = await moteur.requeteComplete({ chemin: '/__sante', entetes: { 'X-ForceOS-Jeton': jetonPhp } });
      derniere = JSON.parse(r.corps.toString('utf8'));
    } catch { derniere = null; }
    if (derniere?.php === 'ok' && derniere.base === 'ok') break;
    await new Promise((r) => setTimeout(r, 250));
  }
  if (derniere?.php !== 'ok') throw new Error('FO Gestion ne répond pas (php-cgi est-il installé ?).');
  if (derniere.base !== 'ok') throw new Error('FO Gestion ne peut pas se connecter à sa base de données.');
  const faille = faillePhp2Shell(derniere.version_id, derniere.version);
  if (faille && !PHP_NON_CORRIGE_ACCEPTE) throw new Error(faille);
  if (derniere.upload_progress !== '0' && derniere.upload_progress !== '') {
    throw new Error('Le php.ini de FO Gestion n’est pas appliqué (session.upload_progress actif).');
  }
}

function arreterPhp() {
  arretEnCours = true;
  moteur?.arreter();
}

const appel = (chemin, json) => appelInterne({ moteur, jeton: jetonPhp, jetonInterne, chemin, json });

/** Base initiale au premier lancement, puis migrations en attente. */
async function executerMigrations() {
  try {
    await appelInterne({ moteur, jeton: jetonPhp, jetonInterne, chemin: '/interne/migrer', methode: 'POST' });
    return true;
  } catch (e) {
    const instantane = e.donnees?.instantane;
    const { response } = await dialog.showMessageBox({
      type: 'error',
      title: 'FO Gestion : mise à jour de la base',
      message: 'La mise à jour de la base de données a échoué.',
      detail: `${e.message}\n\n${instantane ? 'Vous pouvez revenir à l’état précédent grâce à l’instantané automatique, puis contacter Florian Bigotte.' : 'Contactez Florian Bigotte.'}`,
      buttons: instantane ? ['Quitter', 'Revenir à l’état précédent'] : ['Quitter'],
      defaultId: instantane ? 1 : 0,
      cancelId: 0
    });
    if (instantane && response === 1) {
      try {
        await appel('/interne/instantane/restaurer', { fichier: instantane });
        dialog.showMessageBoxSync({ type: 'info', message: 'La base a retrouvé son état précédent.', detail: 'FO Gestion va se fermer. Contactez le support avant de la relancer.' });
      } catch (e2) {
        dialog.showErrorBox('FO Gestion', `Le retour à l’état précédent a échoué : ${e2.message}`);
      }
    }
    return false;
  }
}

// ---------------------------------------------------------------------
// Verrouillage réseau de la page
// ---------------------------------------------------------------------
// FO Gestion utilise Alpine.js (« unsafe-eval ») : la politique interdit
// tout chargement extérieur sans casser l'interface. La barrière principale
// reste le filtre de requêtes ci-dessous.
const CSP = [
  "default-src 'self' data: blob:",
  "script-src 'self' 'unsafe-inline' 'unsafe-eval'",
  "style-src 'self' 'unsafe-inline'",
  "img-src 'self' data: blob:",
  "media-src 'self' blob:",
  "font-src 'self' data:",
  "connect-src 'self'",
  "object-src 'none'",
  "base-uri 'self'",
  "form-action 'self'",
  "frame-ancestors 'none'"
].join('; ');

function verrouillerSession() {
  const ses = session.defaultSession;

  // La page ne joint QUE FO Gestion. Bourse et RSS passent par PHP,
  // l'IA et l'audio par le processus principal.
  ses.webRequest.onBeforeRequest((d, cb) => {
    const autorise = d.url.startsWith(`${origine}/`) || d.url.startsWith('devtools://')
      || d.url.startsWith('data:') || d.url.startsWith('blob:');
    cb({ cancel: !autorise });
  });

  // Toute requête http de la fenêtre est traitée ici, sans réseau : elle est
  // remise au moteur PHP avec le jeton, les en-têtes réservés que la page
  // tenterait de forger sont retirés, et les cookies sont gérés par le
  // processus principal (jamais visibles par JavaScript).
  ses.protocol.handle('http', async (req) => {
    let url;
    try { url = new URL(req.url); } catch { return new Response('Adresse invalide.', { status: 400 }); }
    if (url.origin !== origine) return new Response('Accès refusé.', { status: 403 });
    const entetes = {};
    req.headers.forEach((valeur, nom) => {
      if (!/^x-forceos-/i.test(nom) && nom.toLowerCase() !== 'cookie') entetes[nom] = valeur;
    });
    entetes['X-ForceOS-Jeton'] = jetonPhp;
    const bocal = cookies.entete();
    if (bocal) entetes.Cookie = bocal;
    const corps = ['GET', 'HEAD'].includes(req.method) ? null : Buffer.from(await req.arrayBuffer());
    let r;
    try {
      r = await moteur.requete({ methode: req.method, chemin: url.pathname + url.search, entetes, corps });
    } catch (e) {
      return new Response(`FO Gestion : ${e.message}`, { status: 503, headers: { 'Content-Type': 'text/plain; charset=utf-8' } });
    }
    const h = new Headers();
    for (const [nom, valeur] of r.entetes) {
      if (nom.toLowerCase() === 'set-cookie') cookies.enregistrer(valeur);
      else h.append(nom, valeur);
    }
    h.set('Content-Security-Policy', CSP);
    return new Response(r.flux ? Readable.toWeb(r.flux) : null, { status: r.statut, headers: h });
  });

  // Aucune permission matérielle : l'audio s'importe par glisser-déposer
  const permises = new Set(['clipboard-sanitized-write']);
  ses.setPermissionRequestHandler((_wc, perm, cb) => cb(permises.has(perm)));
  ses.setPermissionCheckHandler((_wc, perm) => permises.has(perm));
}

function ouvrirExterne(url) {
  try { if (new URL(url).protocol === 'https:') shell.openExternal(url); } catch { /* ignorée */ }
}

app.on('web-contents-created', (_e, wc) => {
  wc.on('will-navigate', (e, url) => {
    const local = origine && url.startsWith(`${origine}/`);
    const fichier = url.startsWith('file://');   // fenêtre de première configuration
    if (!local && !fichier) { e.preventDefault(); ouvrirExterne(url); }
  });
  wc.setWindowOpenHandler(({ url }) => { ouvrirExterne(url); return { action: 'deny' }; });
  wc.on('will-attach-webview', (e) => e.preventDefault());
});

// ---------------------------------------------------------------------
// IPC : informations, sauvegarde et restauration
// ---------------------------------------------------------------------
function emetteurLegitime(evt) {
  return Boolean(origine && evt.senderFrame && evt.senderFrame.url.startsWith(`${origine}/`));
}

ipcMain.handle('forceos:infos', async (evt) => {
  if (!emetteurLegitime(evt)) throw new Error('Émetteur refusé.');
  return { application: app.getName(), version: app.getVersion(), credits: CREDITS };
});

const MOTIF_TICKET = /^[a-f0-9]{64}$/;

// ---------------------------------------------------------------------
// IPC : mode serveur pour les téléphones (secrétaire, mot de passe administrateur)
// ---------------------------------------------------------------------
const MINUTES_SERVEUR = new Set([30, 60, 120, 240]);
ipcMain.handle('serveur:etat', async (evt) => {
  if (!emetteurLegitime(evt)) throw new Error('Émetteur refusé.');
  return modeServeur ? modeServeur.etat() : { actif: false };
});
ipcMain.handle('serveur:demarrer', async (evt, demande) => {
  if (!emetteurLegitime(evt)) throw new Error('Émetteur refusé.');
  const { ticket, mode, minutes } = demande ?? {};
  if (typeof ticket !== 'string' || !MOTIF_TICKET.test(ticket)) throw new Error('Ticket invalide.');
  if (!MINUTES_SERVEUR.has(minutes)) throw new Error('Durée non prévue.');
  const r = await appel('/interne/serveur/ticket', { ticket });
  if (!r?.ok) throw new Error('Vos droits ne permettent pas d’ouvrir le mode serveur.');
  return modeServeur.demarrer({ mode, minutes });
});
ipcMain.handle('serveur:arreter', async (evt) => {
  if (!emetteurLegitime(evt)) throw new Error('Émetteur refusé.');
  return modeServeur.arreter('arrêt demandé');
});

/**
 * Date de la dernière sauvegarde réussie, lue par le rappel hebdomadaire de
 * ForceOS (forceos-rappel-sauvegarde) et par « À propos de ForceOS ».
 */
function noterDerniereSauvegarde() {
  try {
    const dossier = path.join(app.getPath('home'), '.config', 'forceos');
    fs.mkdirSync(dossier, { recursive: true });
    fs.writeFileSync(path.join(dossier, 'derniere-sauvegarde'), `${new Date().toISOString()}\n`, { mode: 0o600 });
  } catch { /* le rappel se manifestera simplement plus tôt */ }
}

ipcMain.handle('sauvegarde:exporter', async (evt, demande) => {
  if (!emetteurLegitime(evt)) throw new Error('Émetteur refusé.');
  const { ticket, phrase } = demande ?? {};
  if (typeof ticket !== 'string' || !MOTIF_TICKET.test(ticket)) throw new Error('Ticket de sauvegarde invalide.');
  const horodatage = new Date().toISOString().slice(0, 16).replace(/[:T]/g, '-');
  const { canceled, filePath } = await dialog.showSaveDialog(fenetre, {
    title: 'Enregistrer la sauvegarde chiffrée',
    defaultPath: path.join('/media', os.userInfo().username, `sauvegarde-fo-${horodatage}.sql.enc`),
    buttonLabel: 'Chiffrer et enregistrer',
    filters: [{ name: 'Sauvegarde ForceOS', extensions: ['enc'] }]
  });
  if (canceled || !filePath) return { annule: true };
  const destination = filePath.endsWith('.sql.enc') ? filePath : filePath.replace(/(\.sql)?(\.enc)?$/, '.sql.enc');
  const r = await exporterSauvegarde({ moteur, jeton: jetonPhp, jetonInterne, ticket, phrase, destination });
  noterDerniereSauvegarde();
  return { ok: true, fichier: destination, taille: r.taille, empreinte: r.empreinte };
});

ipcMain.handle('sauvegarde:restaurer', async (evt, demande) => {
  if (!emetteurLegitime(evt)) throw new Error('Émetteur refusé.');
  const { ticket, phrase } = demande ?? {};
  if (typeof ticket !== 'string' || !MOTIF_TICKET.test(ticket)) throw new Error('Ticket de restauration invalide.');
  const { canceled, filePaths } = await dialog.showOpenDialog(fenetre, {
    title: 'Choisir la sauvegarde à restaurer',
    defaultPath: path.join('/media', os.userInfo().username),
    filters: [{ name: 'Sauvegarde ForceOS', extensions: ['enc'] }],
    properties: ['openFile']
  });
  if (canceled || !filePaths.length) return { annule: true };
  // Confirmation affichée par le processus principal : la page ne peut pas la contourner
  const { response } = await dialog.showMessageBox(fenetre, {
    type: 'warning',
    title: 'Restaurer une sauvegarde',
    message: 'Toutes les données actuelles de FO Gestion vont être remplacées par celles de la sauvegarde.',
    detail: 'Un instantané chiffré de l’état actuel sera créé automatiquement avant l’opération. L’application redémarrera ensuite.',
    buttons: ['Annuler', 'Remplacer toutes les données'],
    defaultId: 0,
    cancelId: 0
  });
  if (response !== 1) return { annule: true };
  const dossierDonnees = app.getPath('userData');
  await restaurerSauvegarde({
    moteur, jeton: jetonPhp, jetonInterne, ticket, phrase,
    fichier: filePaths[0],
    dossierTmp: path.join(dossierDonnees, 'tmp'),
    surCle: async (cle) => {
      // Adoption de la clé maîtresse de la sauvegarde, protégée par le trousseau de ce poste
      await ecrireCleMaitresse(path.join(dossierDonnees, 'cle-maitresse.enc'), cle);
    }
  });
  setTimeout(() => { app.relaunch(); app.quit(); }, 1500);
  return { ok: true, redemarrage: true };
});

// ---------------------------------------------------------------------
// Fenêtre principale
// ---------------------------------------------------------------------
/**
 * Poste partagé : chaque militant a son compte FO Gestion. Dès que l'écran
 * se verrouille (veille de Cinnamon, Super+L, bouton d'urgence) ou que le PC
 * se met en veille, la connexion FO Gestion est fermée : la personne
 * suivante doit se reconnecter avec son propre compte.
 */
function deconnecterSession() {
  modeServeur?.arreter('écran verrouillé');
  cookies.vider();
  if (fenetre && !fenetre.isDestroyed() && origine) fenetre.loadURL(`${origine}/connexion`).catch(() => {});
}

function surveillerVerrouillage() {
  powerMonitor.on('lock-screen', deconnecterSession);
  powerMonitor.on('suspend', deconnecterSession);
  // Sous Linux, le verrouillage est signalé par l'économiseur d'écran sur le bus de session
  const filtres = ['org.cinnamon.ScreenSaver', 'org.gnome.ScreenSaver', 'org.freedesktop.ScreenSaver']
    .map((i) => `type='signal',interface='${i}',member='ActiveChanged'`);
  let surveillance;
  try {
    surveillance = spawn('/usr/bin/dbus-monitor', ['--session', ...filtres], { stdio: ['ignore', 'pipe', 'ignore'] });
  } catch { return; }
  surveillance.on('error', () => {});
  let signal = false;
  let reste = '';
  surveillance.stdout.setEncoding('utf8');
  surveillance.stdout.on('data', (morceau) => {
    const lignes = (reste + morceau).split('\n');
    reste = lignes.pop();
    for (const l of lignes) {
      if (l.startsWith('signal ')) signal = /member=ActiveChanged/.test(l);
      else if (signal && /^\s*boolean true\b/.test(l)) { signal = false; deconnecterSession(); }
    }
  });
  app.on('will-quit', () => { try { surveillance.kill(); } catch { /* déjà arrêté */ } });
}

function creerFenetre() {
  fenetre = new BrowserWindow({
    width: 1400,
    height: 900,
    minWidth: 1024,
    minHeight: 700,
    title: 'FO Gestion : ForceOS',
    icon: LOGO,
    backgroundColor: '#002B7F',
    show: false,
    autoHideMenuBar: true,
    webPreferences: {
      preload: path.join(__dirname, 'preload.js'),
      contextIsolation: true,
      nodeIntegration: false,
      sandbox: true,
      webSecurity: true,
      allowRunningInsecureContent: false,
      spellcheck: false,          // le correcteur d'Electron télécharge ses dictionnaires
      devTools: !EST_PACKAGE
    }
  });
  fenetre.once('ready-to-show', () => fenetre.show());
  fenetre.loadURL(`${origine}/`);
}

// ---------------------------------------------------------------------
// Démarrage
// ---------------------------------------------------------------------
async function demarrer() {
  if (EST_PACKAGE) Menu.setApplicationMenu(null);

  // 1. Compte autorisé
  const autorisation = verifierUtilisateurAutorise();
  if (!autorisation.ok) return erreurFatale('accès refusé', autorisation.raison);
  const donnees = preparerDossiers();

  // 2. Clé maîtresse
  let cle;
  if (!(await attendreTrousseau())) {
    return erreurFatale('trousseau sécurisé', 'Le trousseau sécurisé du système est indisponible.\nFermez la session, rouvrez-la, puis relancez FO Gestion.');
  }
  try { cle = await chargerCleMaitresse(donnees); } catch (e) { return erreurFatale('trousseau sécurisé', e.message); }

  // 3. PHP, extensions, MariaDB
  const diag = await verifierPhp(PHP_BIN, PHP_INI, { accepterNonCorrige: PHP_NON_CORRIGE_ACCEPTE });
  if (!diag.ok) return erreurFatale('configuration PHP', diag.erreurs.join('\n'));
  if (diag.avertissements.length) {
    fs.appendFileSync(path.join(donnees, 'logs', 'demarrage.log'), `${new Date().toISOString()} ${diag.avertissements.join(' | ')}\n`, { mode: 0o600 });
  }
  if (!(await attendreMariaDB())) return erreurFatale('base de données', 'Le service MariaDB local ne répond pas.');

  // 4. FO Gestion
  portPhp = portOrigine();
  jetonPhp = crypto.randomBytes(32).toString('hex');
  jetonInterne = crypto.randomBytes(32).toString('hex');
  origine = `http://127.0.0.1:${portPhp}`;
  demarrerPhp({ cle, dossierDonnees: donnees });
  cle = null; // la chaîne ne peut pas être effacée, on retire au moins la référence
  try { await attendrePhp(); } catch (e) { return erreurFatale('démarrage', e.message); }

  // Modules de l'assistant IA (texte, audio, documents)
  ({ synchroniserDocuments } = enregistrerModules({
    ipcMain, emetteurLegitime, dossierDonnees: donnees, dossierDocuments: app.getPath('documents'), appel
  }));

  // 5. Base initiale ou migrations
  if (!(await executerMigrations())) { arreterPhp(true); return app.exit(1); }

  // 6. Première configuration
  const { configure } = await appel('/interne/etat');
  if (!configure && !(await ouvrirPremiereConfiguration({ appel, icone: LOGO }))) {
    arreterPhp(true);
    return app.quit();
  }

  // 7. Fenêtre principale
  verrouillerSession();
  modeServeur = creerModeServeur({
    dossier: donnees,
    jeton: jetonPhp,
    csp: CSP,
    modeTest: process.env.FORCEOS_SERVEUR_TEST === '1',
    creerMoteur: (baseURL) => creerMoteurFO({ baseURL, port: Number(new URL(baseURL).port), securise: true }),
    surChangement: (e) => { if (fenetre && !fenetre.isDestroyed()) fenetre.webContents.send('serveur:etat', e); }
  });
  app.on('will-quit', () => { modeServeur?.arreter('fermeture de FO Gestion'); });
  creerFenetre();
  surveillerVerrouillage();
  synchroniserDocuments();
}

if (!app.requestSingleInstanceLock()) {
  app.quit();
} else {
  app.on('second-instance', () => {
    if (fenetre) { if (fenetre.isMinimized()) fenetre.restore(); fenetre.focus(); }
  });
  app.whenReady().then(demarrer).catch((e) => erreurFatale('démarrage', e.message));
}

app.on('window-all-closed', () => { if (fenetre) app.quit(); });
app.on('before-quit', () => arreterPhp(false));
process.on('exit', () => arreterPhp(true));
// Bouton d'urgence (étape 3) : arrêt immédiat, sans dialogue
process.on('SIGUSR2', () => { arreterPhp(true); app.exit(0); });
process.on('SIGTERM', () => app.quit());

module.exports = { cleCodeIgniter }; // utilisé par les tests
