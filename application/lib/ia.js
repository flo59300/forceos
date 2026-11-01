'use strict';
/**
 * ForceOS : moteur de l'assistant IA (Ollama, Qwen 2.5 3B, 100 % local)
 * Conçu et développé par Florian Bigotte pour Force Ouvrière
 *
 * Les consignes sont relues à chaque requête : une modification par
 * l'administrateur s'applique immédiatement. Les textes trop longs pour le
 * modèle sont découpés, résumés par parties, puis traités.
 */
const fs = require('node:fs');

// Réglages de test ignorés dans le paquet installé : un militant ne peut pas
// faire lire à l'assistant d'autres consignes que celles de l'administrateur.
const { app } = require('electron');
const reglage = (nom, defaut) => (!app.isPackaged && process.env[nom]) || defaut;
const OLLAMA_URL = reglage('FORCEOS_OLLAMA', 'http://127.0.0.1:11434');
const FICHIER_PROMPTS = reglage('FORCEOS_PROMPTS', '/etc/forceos/ia/prompts.json');
const ACTIONS_REQUISES = ['question', 'accord', 'tract', 'prudhommes', 'audio', 'extraction'];
const ACTIONS_PUBLIQUES = ['question', 'accord', 'tract', 'prudhommes', 'audio'];
const LIMITE_TEXTE_DIRECT = 24000;
const TAILLE_MORCEAU = 12000;
const MAX_HISTORIQUE = 30;
const CONSIGNE_BIBLIO_DEFAUT = 'Appuie-toi en priorité sur les extraits fournis et cite leur numéro entre crochets.';

// ---------------------------------------------------------------------
// Consignes
// ---------------------------------------------------------------------
const estChaine = (v, max) => typeof v === 'string' && v.trim().length > 0 && v.length <= max;
const temperatureValide = (t) => typeof t === 'number' && t >= 0 && t <= 1.5;

function validerPrompts(c) {
  const e = [];
  if (!c || typeof c !== 'object') return ['Le contenu n’est pas un objet JSON.'];
  if (c.version !== 1) e.push('Version de format inconnue.');
  if (!estChaine(c.modele, 100) || !/^[a-z0-9][a-z0-9._-]*(:[a-z0-9._-]+)?$/.test(c.modele)) e.push('Nom de modèle invalide.');
  if (!estChaine(c.systeme, 20000)) e.push('Consigne générale absente ou trop longue.');
  if (c.bibliotheque_consigne !== undefined && !estChaine(c.bibliotheque_consigne, 5000)) e.push('Consigne des documents de référence invalide.');
  const p = c.parametres ?? {};
  if (p.num_ctx !== undefined && !(Number.isInteger(p.num_ctx) && p.num_ctx >= 2048 && p.num_ctx <= 32768)) e.push('num_ctx doit être un entier entre 2048 et 32768.');
  if (p.temperature !== undefined && !temperatureValide(p.temperature)) e.push('Température générale invalide.');
  for (const cle of ACTIONS_REQUISES) {
    const a = c.actions?.[cle];
    if (!a) { e.push(`Action « ${cle} » manquante.`); continue; }
    if (!estChaine(a.libelle, 80)) e.push(`Libellé invalide pour « ${cle} ».`);
    if (!estChaine(a.consigne, 20000)) e.push(`Consigne invalide pour « ${cle} ».`);
    if (a.temperature !== undefined && !temperatureValide(a.temperature)) e.push(`Température invalide pour « ${cle} ».`);
  }
  return e;
}

function chargerPrompts() {
  const c = JSON.parse(fs.readFileSync(FICHIER_PROMPTS, 'utf8'));
  const erreurs = validerPrompts(c);
  if (erreurs.length) throw new Error(`Consignes de l’IA invalides : ${erreurs.join(' ')}`);
  return c;
}

function validerHistorique(h) {
  if (!Array.isArray(h) || h.length > 60) throw new Error('Historique de conversation invalide.');
  let total = 0;
  for (const m of h) {
    if (!m || !['user', 'assistant'].includes(m.role) || typeof m.content !== 'string') throw new Error('Historique de conversation invalide.');
    total += m.content.length;
  }
  if (total > 200000) throw new Error('Conversation trop longue : commencez une nouvelle conversation.');
}

// ---------------------------------------------------------------------
// Dialogue avec Ollama
// ---------------------------------------------------------------------
function corpsRequete(config, action, messages, complement = '') {
  const a = config.actions[action];
  return {
    model: config.modele,
    stream: true,
    keep_alive: '10m',
    options: { ...(config.parametres ?? {}), ...(a.temperature !== undefined ? { temperature: a.temperature } : {}) },
    messages: [{ role: 'system', content: `${config.systeme}\n\n${a.consigne}${complement}` }, ...messages]
  };
}

/** Diffusion mot à mot de la réponse (format NDJSON d'Ollama). */
async function* flux(corps, signal) {
  const res = await fetch(`${OLLAMA_URL}/api/chat`, {
    method: 'POST', headers: { 'Content-Type': 'application/json' }, body: JSON.stringify(corps), signal
  });
  if (!res.ok) throw new Error(`Le moteur IA a répondu ${res.status} : le modèle est-il installé ?`);
  const decodeur = new TextDecoder();
  let reste = '';
  for await (const morceau of res.body) {
    reste += decodeur.decode(morceau, { stream: true });
    let i;
    while ((i = reste.indexOf('\n')) >= 0) {
      const ligne = reste.slice(0, i).trim();
      reste = reste.slice(i + 1);
      if (!ligne) continue;
      const d = JSON.parse(ligne);
      if (d.error) throw new Error(d.error);
      if (d.message?.content) yield d.message.content;
      if (d.done) return;
    }
  }
}

async function completer(config, action, texte, signal) {
  let sortie = '';
  for await (const j of flux(corpsRequete(config, action, [{ role: 'user', content: texte }]), signal)) sortie += j;
  return sortie.trim();
}

// ---------------------------------------------------------------------
// Textes longs : découpage puis extraction
// ---------------------------------------------------------------------
function decouper(texte, taille = TAILLE_MORCEAU) {
  const morceaux = [];
  let courant = '';
  for (const phrase of texte.split(/(?<=[.!?…])\s+|\n+/)) {
    if (!phrase) continue;
    if (phrase.length > taille) {
      if (courant) { morceaux.push(courant); courant = ''; }
      for (let i = 0; i < phrase.length; i += taille) morceaux.push(phrase.slice(i, i + taille));
      continue;
    }
    if (courant.length + phrase.length + 1 > taille) { morceaux.push(courant); courant = ''; }
    courant += (courant ? ' ' : '') + phrase;
  }
  if (courant) morceaux.push(courant);
  return morceaux;
}

async function reduire(config, texte, signal, progres, profondeur = 0) {
  if (texte.length <= LIMITE_TEXTE_DIRECT) return texte;
  if (profondeur >= 3) return texte.slice(0, LIMITE_TEXTE_DIRECT); // garde-fou
  const morceaux = decouper(texte);
  const extraits = [];
  for (const [i, m] of morceaux.entries()) {
    progres?.({ etape: 'analyse', pourcentage: Math.round((i / morceaux.length) * 100) });
    extraits.push(`[Partie ${i + 1} sur ${morceaux.length}]\n${await completer(config, 'extraction', m, signal)}`);
  }
  progres?.({ etape: 'analyse', pourcentage: 100 });
  return reduire(config, extraits.join('\n\n'), signal, progres, profondeur + 1);
}

const blocSources = (sources) => sources.map((s, i) => `[${i + 1}] ${s.titre}, ${s.section}\n${s.texte}`).join('\n\n');

// ---------------------------------------------------------------------
// Point d'entrée
// ---------------------------------------------------------------------
async function* repondre({ action, texte, consigne = '', historique = [], sources = [], signal, progres }) {
  if (!ACTIONS_PUBLIQUES.includes(action)) throw new Error('Action inconnue.');
  const config = chargerPrompts();
  const contenu = await reduire(config, texte, signal, progres);
  const preambule = consigne ? `Consigne particulière de l’utilisateur : ${consigne}\n\n` : '';
  const extraits = sources.length ? `Extraits des documents de référence :\n\n${blocSources(sources)}\n\n---\n\n` : '';
  const complement = sources.length ? `\n\n${config.bibliotheque_consigne ?? CONSIGNE_BIBLIO_DEFAUT}` : '';
  const messages = [
    ...historique.slice(-MAX_HISTORIQUE).map((m) => ({ role: m.role, content: m.content })),
    { role: 'user', content: extraits + preambule + contenu }
  ];
  yield* flux(corpsRequete(config, action, messages, complement), signal);
}

async function etat() {
  let config;
  try { config = chargerPrompts(); } catch (e) { return { disponible: false, message: e.message }; }
  try {
    const r = await fetch(`${OLLAMA_URL}/api/tags`, { signal: AbortSignal.timeout(2000) });
    const { models = [] } = await r.json();
    const present = models.some((m) => m.name === config.modele || m.model === config.modele);
    return { disponible: present, modele: config.modele, message: present ? 'IA prête' : `Modèle ${config.modele} absent` };
  } catch {
    return { disponible: false, modele: config.modele, message: 'Moteur IA arrêté' };
  }
}

module.exports = { repondre, etat, chargerPrompts, validerPrompts, validerHistorique, decouper };
