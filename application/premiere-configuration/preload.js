'use strict';
/* ForceOS : pont de la fenêtre de première configuration (Florian Bigotte).
   Une seule fonction exposée, sans accès à Node ni à ipcRenderer brut. */
const { contextBridge, ipcRenderer } = require('electron');

contextBridge.exposeInMainWorld('forceosConfig', {
  envoyer: (donnees) => ipcRenderer.invoke('premiere-configuration:envoyer', donnees)
});
