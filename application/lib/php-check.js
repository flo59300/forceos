'use strict';
/**
 * ForceOS : validation de l'interpréteur PHP avant tout démarrage
 * Conçu et développé par Florian Bigotte pour Force Ouvrière
 *
 * PHP est lancé avec EXACTEMENT le même php.ini que le serveur, puis on
 * vérifie la version, les extensions et quelques tests fonctionnels (une
 * extension peut être chargée mais incomplète).
 */
const { execFile } = require('node:child_process');

const EXTENSIONS_REQUISES = {
  openssl:   'OpenSSL (chiffrement des sauvegardes)',
  sodium:    'Sodium (chiffrement RGPD des adhérents)',
  curl:      'cURL (flux RSS, cours de bourse)',
  zip:       'Zip (archives et exports)',
  xml:       'XML',
  simplexml: 'SimpleXML (flux RSS)',
  dom:       'DOM (documents Word)',
  mbstring:  'mbstring (textes en UTF-8)',
  iconv:     'iconv (conversion d’encodages)',
  intl:      'intl (requis par CodeIgniter 4)',
  mysqli:    'MySQLi (pilote de base de CodeIgniter 4)',
  pdo_mysql: 'PDO MySQL (installation et instantanés)'
};
const EXTENSIONS_IMAGE = ['gd', 'imagick'];
const VERSION_MINIMALE_ID = 80200; // PHP 8.2 (Debian 12) ; Debian 13 fournit PHP 8.4

// PHP2Shell (CVE-2026-109618 et CVE-2026-109619, corrigées le 21 octobre 2026) :
// version minimale corrigée pour chaque branche. Debian 13 : php8.4 8.4.27-1~deb13u1
// (DSA-6574-1), dont le numéro de version annonce bien 8.4.27.
const CORRECTIFS_PHP2SHELL = { 80200: 80235, 80300: 80336, 80400: 80427, 80500: 80512 };

/** Message d'erreur si cette version de PHP est exposée à PHP2Shell, sinon null. */
function faillePhp2Shell(versionId, version) {
  const branche = Math.floor(Number(versionId) / 100) * 100;
  const minimum = CORRECTIFS_PHP2SHELL[branche];
  if (minimum === undefined && branche > 80500) return null; // branche plus récente que la faille
  if (minimum !== undefined && Number(versionId) >= minimum) return null;
  return `PHP ${version} est exposé à la faille PHP2Shell (CVE-2026-109618 et CVE-2026-109619). `
    + 'Installez les mises à jour de sécurité de Debian (php8.4 8.4.27 ou plus récent) avant d’ouvrir FO Gestion.';
}

const SCRIPT_DIAGNOSTIC = `
$ext = array_map('strtolower', get_loaded_extensions());
$tests = [
  'sodium_kdf'     => function_exists('sodium_crypto_kdf_derive_from_key'),
  'sodium_flux'    => function_exists('sodium_crypto_secretstream_xchacha20poly1305_init_push'),
  'openssl_aes256' => function_exists('openssl_get_cipher_methods')
                      && in_array('aes-256-gcm', openssl_get_cipher_methods(), true),
  'gd_jpeg'        => function_exists('gd_info') ? (bool)(gd_info()['JPEG Support'] ?? false) : null,
];
$ini = [
  'allow_url_include' => ini_get('allow_url_include'),
  'disable_functions' => ini_get('disable_functions'),
  'upload_progress'   => ini_get('session.upload_progress.enabled'),
];
echo json_encode(['version' => PHP_VERSION, 'version_id' => PHP_VERSION_ID,
                  'extensions' => $ext, 'tests' => $tests, 'ini' => $ini]);
`;

/**
 * @returns {Promise<{ok:boolean, erreurs:string[], avertissements:string[], details:object|null}>}
 */
function verifierPhp(phpBin, phpIni, { accepterNonCorrige = false } = {}) {
  return new Promise((resolve) => {
    execFile(phpBin, ['-c', phpIni, '-r', SCRIPT_DIAGNOSTIC],
      { timeout: 8000, env: { PATH: '/usr/bin:/bin', LANG: 'fr_FR.UTF-8' } },
      (err, stdout) => {
        if (err) {
          return resolve({ ok: false, erreurs: [`Impossible d’exécuter PHP (${phpBin}) : ${err.message}`], avertissements: [], details: null });
        }
        let d;
        try { d = JSON.parse(stdout); } catch {
          return resolve({ ok: false, erreurs: ['Réponse illisible de l’interpréteur PHP.'], avertissements: [], details: null });
        }
        const erreurs = [];
        const avertissements = [];
        if (d.version_id < VERSION_MINIMALE_ID) erreurs.push(`PHP ${d.version} détecté : la version 8.2 au minimum est requise.`);
        const faille = faillePhp2Shell(d.version_id, d.version);
        if (faille && !accepterNonCorrige) erreurs.push(faille);
        else if (faille) avertissements.push(faille);
        if (d.ini.upload_progress !== '' && d.ini.upload_progress !== '0') erreurs.push('session.upload_progress est actif : le php.ini embarqué n’a pas été chargé.');
        for (const [nom, libelle] of Object.entries(EXTENSIONS_REQUISES)) {
          if (!d.extensions.includes(nom)) erreurs.push(`Extension manquante : ${libelle}.`);
        }
        if (!EXTENSIONS_IMAGE.some((e) => d.extensions.includes(e))) erreurs.push('Aucune extension d’image (GD ou Imagick) n’est chargée.');
        if (!d.tests.sodium_kdf || !d.tests.sodium_flux) erreurs.push('Sodium est chargé mais incomplet.');
        if (!d.tests.openssl_aes256) erreurs.push('OpenSSL ne propose pas AES-256-GCM.');
        if (d.tests.gd_jpeg === false) avertissements.push('GD ne gère pas le JPEG : certaines photos ne seront pas traitées.');
        if (d.ini.allow_url_include !== '' && d.ini.allow_url_include !== '0') erreurs.push('allow_url_include est actif : le php.ini embarqué n’a pas été chargé.');
        if (!String(d.ini.disable_functions).includes('phpinfo')) erreurs.push('Les fonctions dangereuses ne sont pas désactivées.');
        resolve({ ok: erreurs.length === 0, erreurs, avertissements, details: d });
      });
  });
}

module.exports = { verifierPhp, faillePhp2Shell, EXTENSIONS_REQUISES };
