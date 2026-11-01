'use strict';
/**
 * ForceOS : appels internes du processus principal vers FO Gestion
 * Conçu et développé par Florian Bigotte pour Force Ouvrière
 *
 * Seul le processus principal connaît le jeton interne : la page de
 * l'application ne peut jamais atteindre les adresses /interne/…
 * Les appels passent directement au moteur PHP, sans réseau.
 */

/**
 * @param {{moteur:object, jeton:string, jetonInterne:string, chemin:string,
 *          methode?:string, json?:object, corps?:string|Buffer, entetes?:object}} o
 * @returns {Promise<object>} réponse JSON ; en cas d'erreur, l'exception porte
 *          la réponse complète dans « donnees » et le code HTTP dans « code »
 */
async function appelInterne({ moteur, jeton, jetonInterne, chemin, methode, json, corps, entetes = {} }) {
  const contenu = json !== undefined ? Buffer.from(JSON.stringify(json)) : corps ? Buffer.from(corps) : null;
  const verbe = methode ?? (contenu ? 'POST' : 'GET');
  const r = await moteur.requeteComplete({
    methode: verbe,
    chemin,
    corps: contenu ?? (verbe === 'POST' ? Buffer.alloc(0) : null),
    entetes: {
      'X-ForceOS-Jeton': jeton,
      'X-ForceOS-Interne': jetonInterne,
      Accept: 'application/json',
      ...(contenu ? { 'Content-Type': json !== undefined ? 'application/json' : 'application/octet-stream' } : {}),
      ...entetes
    }
  });
  let donnees = {};
  try { donnees = JSON.parse(r.corps.toString('utf8') || '{}'); } catch { /* réponse non JSON */ }
  if (r.statut >= 200 && r.statut < 300) return donnees;
  const e = new Error(donnees.erreur ?? `Erreur interne (code ${r.statut}).`);
  e.donnees = donnees;
  e.code = r.statut;
  throw e;
}

module.exports = { appelInterne };
