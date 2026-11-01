'use strict';
/**
 * ForceOS : exécution des outils externes dans un bac à sable Firejail
 * Conçu et développé par Florian Bigotte pour Force Ouvrière
 *
 * Sans réseau, sans capacités, sans élévation de privilèges, avec un filtre
 * d'appels système : un PDF ou un fichier audio piégé ne peut ni
 * communiquer vers l'extérieur ni prendre le contrôle du poste.
 */
const { spawn } = require('node:child_process');
const fs = require('node:fs');
const os = require('node:os');
const path = require('node:path');

const FIREJAIL = '/usr/bin/firejail';
const ISOLATION = ['--quiet', '--noprofile', '--net=none', '--caps.drop=all', '--nonewprivs', '--seccomp', '--nogroups', '--'];
const FIREJAIL_PRESENT = fs.existsSync(FIREJAIL);
const SORTIE_MAX = 64 * 1024 * 1024;

/**
 * @param {string} bin     chemin absolu de l'outil
 * @param {string[]} args
 * @param {{signal?:AbortSignal, surStderr?:Function, capture?:boolean, isoler?:boolean}} o
 * @returns {Promise<{stdout:string}>}
 */
function executer(bin, args, { signal, surStderr, capture = false, isoler = true } = {}) {
  const [commande, argv] = isoler && FIREJAIL_PRESENT ? [FIREJAIL, [...ISOLATION, bin, ...args]] : [bin, args];
  return new Promise((resolve, reject) => {
    const p = spawn(commande, argv, {
      env: { PATH: '/usr/bin:/bin', LANG: 'fr_FR.UTF-8', HOME: os.homedir() },
      stdio: ['ignore', capture ? 'pipe' : 'ignore', 'pipe'],
      signal
    });
    const morceaux = [];
    let taille = 0;
    let fin = '';
    if (capture) {
      p.stdout.on('data', (d) => {
        taille += d.length;
        if (taille > SORTIE_MAX) p.kill('SIGKILL'); else morceaux.push(d);
      });
    }
    p.stderr.setEncoding('utf8');
    p.stderr.on('data', (d) => {
      fin = (fin + d).slice(-2000);
      if (surStderr) d.split(/\r?\n|\r/).forEach(surStderr);
    });
    p.on('error', reject);
    p.on('close', (code) => {
      if (taille > SORTIE_MAX) return reject(new Error(`${path.basename(bin)} : sortie trop volumineuse.`));
      if (code === 0) return resolve({ stdout: capture ? Buffer.concat(morceaux).toString('utf8') : '' });
      const derniere = fin.trim().split('\n').pop() ?? '';
      reject(new Error(`${path.basename(bin)} a échoué (code ${code}) : ${derniere}`));
    });
  });
}

module.exports = { executer };
