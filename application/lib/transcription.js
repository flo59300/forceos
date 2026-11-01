'use strict';
/**
 * ForceOS : transcription audio hors ligne (ffmpeg puis whisper.cpp)
 * Conçu et développé par Florian Bigotte pour Force Ouvrière
 */
const crypto = require('node:crypto');
const fsp = require('node:fs/promises');
const os = require('node:os');
const path = require('node:path');
const { executer } = require('./outils');

const FFMPEG = '/usr/bin/ffmpeg';
const WHISPER = '/usr/lib/forceos/whisper/whisper-cli';
const MODELE = '/usr/share/forceos/whisper/ggml-small.bin';
const EXTENSIONS = new Set(['.mp3', '.wav', '.m4a', '.ogg', '.opus', '.flac', '.aac', '.webm']);
const TAILLE_MAX = 500 * 1024 * 1024;

async function verifierFichierAudio(chemin) {
  if (typeof chemin !== 'string' || !path.isAbsolute(chemin)) throw new Error('Fichier audio invalide.');
  const reel = await fsp.realpath(chemin);
  const st = await fsp.stat(reel);
  if (!st.isFile()) throw new Error('Fichier audio invalide.');
  if (!EXTENSIONS.has(path.extname(reel).toLowerCase())) {
    throw new Error('Format non pris en charge : MP3, WAV, M4A, OGG, OPUS, FLAC, AAC ou WEBM.');
  }
  if (st.size > TAILLE_MAX) throw new Error('Fichier trop volumineux (500 Mo au maximum).');
  return reel;
}

/** @param {{dossierTmp:string, signal?:AbortSignal, progres?:Function}} o */
async function transcrire(fichier, { dossierTmp, signal, progres }) {
  await fsp.mkdir(dossierTmp, { recursive: true, mode: 0o700 });
  const base = path.join(dossierTmp, `audio-${crypto.randomUUID()}`);
  const wav = `${base}.wav`;
  try {
    // Préfixe « file: » et liste blanche : un nom de fichier piégé n'est jamais interprété
    progres?.({ etape: 'conversion', pourcentage: 0 });
    await executer(FFMPEG, ['-nostdin', '-hide_banner', '-loglevel', 'error', '-protocol_whitelist', 'file',
      '-i', `file:${fichier}`, '-vn', '-ac', '1', '-ar', '16000', '-c:a', 'pcm_s16le', '-f', 'wav', `file:${wav}`], { signal });
    progres?.({ etape: 'conversion', pourcentage: 100 });

    const fils = String(Math.max(1, os.cpus().length - 1));
    await executer(WHISPER, ['-m', MODELE, '-l', 'fr', '-t', fils, '-f', wav, '-otxt', '-of', base, '-pp'], {
      signal,
      surStderr: (ligne) => {
        const m = /progress\s*=\s*(\d+)\s*%/.exec(ligne);
        if (m) progres?.({ etape: 'transcription', pourcentage: Number(m[1]) });
      }
    });
    return (await fsp.readFile(`${base}.txt`, 'utf8')).replace(/\s+\n/g, '\n').trim();
  } finally {
    await fsp.rm(wav, { force: true });
    await fsp.rm(`${base}.txt`, { force: true });
  }
}

module.exports = { transcrire, verifierFichierAudio };
