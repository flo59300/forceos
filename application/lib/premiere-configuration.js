'use strict';
/**
 * ForceOS : fenêtre de première configuration de FO Gestion
 * Conçu et développé par Florian Bigotte pour Force Ouvrière
 *
 * Ouverte par main.js quand /interne/etat indique un poste non configuré.
 * La fenêtre vit dans une session séparée, sans aucun accès réseau : elle ne
 * peut charger que ses propres fichiers. Le formulaire est transmis par le
 * processus principal à FO Gestion (/interne/premiere-configuration), qui
 * vérifie toutes les saisies.
 */
const path = require('node:path');
const { BrowserWindow, ipcMain, session } = require('electron');

const DOSSIER = path.join(__dirname, '..', 'premiere-configuration');
const CHAMPS = ['section', 'tarif_timbre', 'tarif_carte', 'prenom', 'nom', 'email', 'mot_de_passe'];

/**
 * @param {{appel:(chemin:string, json?:object)=>Promise<object>, icone:string}} o
 * @returns {Promise<boolean>} true si la configuration a réussi, false si la fenêtre a été fermée
 */
function ouvrirPremiereConfiguration({ appel, icone }) {
  return new Promise((resolve) => {
    const ses = session.fromPartition('forceos-premiere-configuration', { cache: false });
    const prefixe = `file://${encodeURI(DOSSIER)}/`;
    ses.webRequest.onBeforeRequest((d, cb) => cb({ cancel: !d.url.startsWith(prefixe) && !d.url.startsWith('devtools://') }));
    ses.setPermissionRequestHandler((_wc, _perm, cb) => cb(false));

    const fenetre = new BrowserWindow({
      width: 820,
      height: 800,
      minWidth: 640,
      minHeight: 560,
      title: 'Bienvenue dans FO Gestion',
      icon: icone,
      backgroundColor: '#F3F5FA',
      autoHideMenuBar: true,
      show: false,
      webPreferences: {
        session: ses,
        preload: path.join(DOSSIER, 'preload.js'),
        contextIsolation: true,
        nodeIntegration: false,
        sandbox: true,
        spellcheck: false,
        devTools: false
      }
    });
    fenetre.webContents.on('will-navigate', (e) => e.preventDefault());
    fenetre.webContents.setWindowOpenHandler(() => ({ action: 'deny' }));

    let reussi = false;
    const gestionnaire = async (evt, donnees) => {
      if (evt.sender !== fenetre.webContents) throw new Error('Émetteur refusé.');
      // Seuls les champs attendus sont transmis, sous forme de texte ou de nombre
      const propre = {};
      for (const c of CHAMPS) {
        const v = donnees?.[c];
        if (typeof v === 'string' || typeof v === 'number') propre[c] = String(v).slice(0, 300);
      }
      try {
        const r = await appel('/interne/premiere-configuration', propre);
        reussi = true;
        setTimeout(() => fenetre.close(), 700);
        return { ok: true, code: r.code };
      } catch (e) {
        // 422 : saisie refusée par FO Gestion, message à afficher tel quel
        return { ok: false, erreur: e.message };
      }
    };
    ipcMain.handle('premiere-configuration:envoyer', gestionnaire);

    fenetre.once('ready-to-show', () => fenetre.show());
    fenetre.on('closed', () => {
      ipcMain.removeHandler('premiere-configuration:envoyer');
      resolve(reussi);
    });
    fenetre.loadFile(path.join(DOSSIER, 'index.html'));
  });
}

module.exports = { ouvrirPremiereConfiguration };
