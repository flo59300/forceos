'use strict';
/**
 * ForceOS : pont isolé entre FO Gestion et le processus principal
 * Conçu et développé par Florian Bigotte pour Force Ouvrière
 *
 * Seules ces fonctions sont accessibles aux pages. Les abonnements ne
 * transmettent jamais l'objet événement d'Electron, seulement les données.
 */
const { contextBridge, ipcRenderer, webUtils } = require('electron');

const appeler = (canal) => (...args) => ipcRenderer.invoke(canal, ...args);
function abonner(canal, rappel) {
  const f = (_evt, donnees) => rappel(donnees);
  ipcRenderer.on(canal, f);
  return () => ipcRenderer.removeListener(canal, f);
}

contextBridge.exposeInMainWorld('forceos', {
  infos: appeler('forceos:infos'),
  serveur: {
    demarrer: appeler('serveur:demarrer'),       // { ticket, mode, minutes }
    arreter: appeler('serveur:arreter'),
    etat: appeler('serveur:etat'),
    surEtat: (rappel) => abonner('serveur:etat', rappel)
  },
  sauvegarde: {
    exporter: appeler('sauvegarde:exporter'),     // { ticket, phrase }
    restaurer: appeler('sauvegarde:restaurer')    // { ticket, phrase }
  },
  ia: {
    etat: appeler('ia:etat'),
    envoyer: appeler('ia:envoyer'),
    annuler: appeler('ia:annuler'),
    lirePrompts: appeler('ia:lirePrompts'),
    enregistrerPrompts: appeler('ia:enregistrerPrompts'),
    surFlux: (rappel) => abonner('ia:flux', rappel),
    surProgres: (rappel) => abonner('ia:progres', rappel)
  },
  audio: {
    // Electron ne fournit plus le chemin des fichiers glissés : webUtils le donne
    chemin: (fichier) => webUtils.getPathForFile(fichier),
    traiter: appeler('audio:traiter')
  },
  bibliotheque: {
    etat: appeler('bibliotheque:etat'),
    indexer: appeler('bibliotheque:indexer'),
    ouvrirDossier: appeler('bibliotheque:ouvrirDossier'),
    surProgres: (rappel) => abonner('bibliotheque:progres', rappel)
  },
  documents: {
    chemin: (fichier) => webUtils.getPathForFile(fichier),
    extraire: appeler('documents:extraire')
  }
});
