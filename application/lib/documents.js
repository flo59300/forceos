'use strict';
/**
 * ForceOS : extraction du texte des documents
 * (PDF, PDF scanné par OCR, ePub, Word, LibreOffice, texte, Markdown)
 * Conçu et développé par Florian Bigotte pour Force Ouvrière
 *
 * Renvoie toujours { titre, sections: [{ section, texte }] }.
 * Les outils externes tournent dans un bac à sable sans réseau (outils.js).
 */
const fsp = require('node:fs/promises');
const path = require('node:path');
const yauzl = require('yauzl');
const { XMLParser } = require('fast-xml-parser');
const { executer } = require('./outils');

const PDFTOTEXT = '/usr/bin/pdftotext';
const PDFINFO = '/usr/bin/pdfinfo';
const PDFTOPPM = '/usr/bin/pdftoppm';
const TESSERACT = '/usr/bin/tesseract';

const EXT_LIVRES = new Set(['.epub']);
const EXT_DOCUMENTS = new Set(['.pdf', '.txt', '.md', '.docx', '.odt']);
const EXTENSIONS = new Set([...EXT_LIVRES, ...EXT_DOCUMENTS]);
const TAILLE_MAX = 100 * 1024 * 1024;
const TEXTE_MAX = 20 * 1024 * 1024;
const PAGES_OCR_MAX = 300;
const ZIP_ENTREE_MAX = 20 * 1024 * 1024;
const EPUB_TOTAL_MAX = 200 * 1024 * 1024;

const tableau = (x) => (x == null ? [] : Array.isArray(x) ? x : [x]);
const texteNoeud = (v) => {
  if (Array.isArray(v)) return texteNoeud(v[0]);
  if (v && typeof v === 'object') return String(v['#text'] ?? '');
  return v == null ? '' : String(v);
};

async function verifierDocument(chemin) {
  if (typeof chemin !== 'string' || !path.isAbsolute(chemin)) throw new Error('Document invalide.');
  const reel = await fsp.realpath(chemin);
  const st = await fsp.stat(reel);
  if (!st.isFile()) throw new Error('Document invalide.');
  if (!EXTENSIONS.has(path.extname(reel).toLowerCase())) {
    throw new Error('Formats acceptés : PDF, Word, LibreOffice, texte, Markdown ou ePub.');
  }
  if (st.size > TAILLE_MAX) throw new Error('Document trop volumineux (100 Mo au maximum).');
  return reel;
}

function decoderEntites(s) {
  return s
    .replace(/&nbsp;|&#160;/g, ' ')
    .replace(/&laquo;/g, '«').replace(/&raquo;/g, '»').replace(/&hellip;/g, '…')
    .replace(/&lt;/g, '<').replace(/&gt;/g, '>').replace(/&quot;/g, '"')
    .replace(/&#0*39;|&apos;|&rsquo;/g, "'")
    .replace(/&#(\d+);/g, (_m, n) => (Number(n) < 0x110000 ? String.fromCodePoint(Number(n)) : ''))
    .replace(/&#x([0-9a-f]+);/gi, (_m, h) => { const c = parseInt(h, 16); return c < 0x110000 ? String.fromCodePoint(c) : ''; })
    .replace(/&amp;/g, '&');
}

function texteDepuisHtml(html) {
  return decoderEntites(html
    .replace(/<(script|style|head)[\s\S]*?<\/\1>/gi, ' ')
    .replace(/<\/(p|div|h[1-6]|li|tr|section|blockquote)>|<br\s*\/?>/gi, '\n')
    .replace(/<[^>]*>/g, ' '))
    .replace(/[ \t]+/g, ' ')
    .replace(/\n\s*\n+/g, '\n\n')
    .trim();
}

// --- PDF, avec repli sur l'OCR pour les documents scannés -------------------
async function nombrePages(chemin) {
  const { stdout } = await executer(PDFINFO, [chemin], { capture: true });
  const m = /^Pages:\s+(\d+)/m.exec(stdout);
  return m ? Number(m[1]) : 0;
}

async function ocrPdf(chemin, { dossierTmp, signal, progres }) {
  const pages = await nombrePages(chemin);
  if (pages > PAGES_OCR_MAX) throw new Error(`Document scanné trop long pour la reconnaissance de texte (${PAGES_OCR_MAX} pages au maximum).`);
  await fsp.mkdir(dossierTmp, { recursive: true, mode: 0o700 });
  const dossier = await fsp.mkdtemp(path.join(dossierTmp, 'ocr-'));
  try {
    const sections = [];
    for (let p = 1; p <= pages; p++) {
      progres?.({ etape: 'ocr', pourcentage: Math.round(((p - 1) / pages) * 100) });
      const prefixe = path.join(dossier, 'page');
      await executer(PDFTOPPM, ['-r', '300', '-gray', '-png', '-singlefile', '-f', String(p), '-l', String(p), chemin, prefixe], { signal });
      const { stdout } = await executer(TESSERACT, [`${prefixe}.png`, 'stdout', '-l', 'fra'], { signal, capture: true });
      await fsp.rm(`${prefixe}.png`, { force: true });
      if (stdout.trim()) sections.push({ section: `page ${p}`, texte: stdout.trim() });
    }
    progres?.({ etape: 'ocr', pourcentage: 100 });
    return sections;
  } finally {
    await fsp.rm(dossier, { recursive: true, force: true });
  }
}

async function extrairePdf(chemin, o) {
  o.progres?.({ etape: 'extraction', pourcentage: 0 });
  const { stdout } = await executer(PDFTOTEXT, ['-enc', 'UTF-8', '-eol', 'unix', chemin, '-'], { signal: o.signal, capture: true });
  const pages = stdout.split('\f').map((p) => p.trim());
  if (pages.length && !pages.at(-1)) pages.pop();
  // Moins de 100 caractères utiles par page en moyenne : document scanné
  const utiles = pages.join('').replace(/\s/g, '').length;
  if (utiles >= Math.max(1, pages.length) * 100) {
    return pages.map((t, i) => ({ section: `page ${i + 1}`, texte: t })).filter((s) => s.texte);
  }
  return ocrPdf(chemin, o);
}

// --- Archives ZIP (ePub, Word, LibreOffice) ------------------------------------
const ouvrirZip = (c) => new Promise((res, rej) => {
  yauzl.open(c, { lazyEntries: true, autoClose: false }, (e, z) => (e ? rej(e) : res(z)));
});
const listerEntrees = (zip) => new Promise((res, rej) => {
  const entrees = new Map();
  zip.on('entry', (e) => {
    if (entrees.size > 5000) return rej(new Error('Archive anormale.'));
    entrees.set(e.fileName, e);
    zip.readEntry();
  });
  zip.on('end', () => res(entrees));
  zip.on('error', rej);
  zip.readEntry();
});
const lireEntree = (zip, e) => new Promise((res, rej) => {
  if (e.uncompressedSize > ZIP_ENTREE_MAX) return rej(new Error('Élément d’archive trop volumineux.'));
  zip.openReadStream(e, (err, flux) => {
    if (err) return rej(err);
    const parts = [];
    let n = 0;
    flux.on('data', (d) => {
      n += d.length;
      if (n > ZIP_ENTREE_MAX) { flux.destroy(); rej(new Error('Élément d’archive trop volumineux.')); } else parts.push(d);
    });
    flux.on('end', () => res(Buffer.concat(parts)));
    flux.on('error', rej);
  });
});

function xmlSur(xml) {
  if (/<!ENTITY/i.test(xml)) throw new Error('Document refusé (déclarations XML non autorisées).');
  return new XMLParser({ ignoreAttributes: false, attributeNamePrefix: '@_', processEntities: false }).parse(xml);
}

async function extraireEpub(chemin) {
  const zip = await ouvrirZip(chemin);
  try {
    const entrees = await listerEntrees(zip);
    const lire = async (nom) => {
      const e = entrees.get(nom);
      if (!e) throw new Error(`Fichier absent de l’ePub : ${nom}`);
      return (await lireEntree(zip, e)).toString('utf8');
    };
    const container = xmlSur(await lire('META-INF/container.xml'));
    const opfChemin = tableau(container?.container?.rootfiles?.rootfile)[0]?.['@_full-path'];
    if (!opfChemin) throw new Error('ePub invalide.');
    const opf = xmlSur(await lire(opfChemin));
    const pkg = opf.package ?? opf['opf:package'];
    const meta = pkg?.metadata ?? pkg?.['opf:metadata'] ?? {};
    const titre = texteNoeud(meta['dc:title']).trim() || path.basename(chemin, '.epub');
    const manifeste = new Map(tableau(pkg?.manifest?.item ?? pkg?.['opf:manifest']?.['opf:item']).map((i) => [i['@_id'], i['@_href']]));
    const ordre = tableau(pkg?.spine?.itemref ?? pkg?.['opf:spine']?.['opf:itemref']).map((r) => r['@_idref']);
    const base = path.posix.dirname(opfChemin);
    const sections = [];
    let total = 0;
    for (const [n, idref] of ordre.entries()) {
      const href = manifeste.get(idref);
      if (!href) continue;
      const nom = path.posix.normalize(path.posix.join(base === '.' ? '' : base, decodeURIComponent(href.split('#')[0])));
      if (nom.startsWith('..')) continue; // aucune sortie de l'archive
      const html = await lire(nom);
      total += html.length;
      if (total > EPUB_TOTAL_MAX) throw new Error('ePub trop volumineux.');
      const titreBrut = /<h[1-3][^>]*>([\s\S]*?)<\/h[1-3]>/i.exec(html)?.[1] ?? /<title>([\s\S]*?)<\/title>/i.exec(html)?.[1] ?? '';
      const texte = texteDepuisHtml(html);
      if (texte.length > 50) sections.push({ section: texteDepuisHtml(titreBrut).slice(0, 120) || `partie ${n + 1}`, texte });
    }
    return { titre, sections };
  } finally {
    zip.close();
  }
}

async function lireXmlArchive(chemin, nom) {
  const zip = await ouvrirZip(chemin);
  try {
    const entree = (await listerEntrees(zip)).get(nom);
    if (!entree) throw new Error('Document Office invalide ou protégé par mot de passe.');
    const xml = (await lireEntree(zip, entree)).toString('utf8');
    if (/<!ENTITY/i.test(xml)) throw new Error('Document refusé (déclarations XML non autorisées).');
    return xml;
  } finally {
    zip.close();
  }
}

/** Regroupe les paragraphes en sections, en coupant sur les titres. */
function regrouperParTitres(paragraphes) {
  const sections = [];
  let courant = { section: 'début', texte: '' };
  for (const p of paragraphes) {
    if (!p.texte) continue;
    if (p.titre) {
      if (courant.texte.trim()) sections.push(courant);
      courant = { section: p.texte.slice(0, 120), texte: '' };
    } else {
      courant.texte += `${p.texte}\n`;
    }
  }
  if (courant.texte.trim()) sections.push(courant);
  return sections.map((s) => ({ ...s, texte: s.texte.trim() }));
}

async function extraireDocx(chemin) {
  const xml = await lireXmlArchive(chemin, 'word/document.xml');
  const paragraphes = [...xml.matchAll(/<w:p[\s>][\s\S]*?<\/w:p>/g)].map(([p]) => {
    const style = /<w:pStyle w:val="([^"]+)"/.exec(p)?.[1] ?? '';
    const texte = [...p.replace(/<w:tab\/>/g, '<w:t> </w:t>').matchAll(/<w:t(?:\s[^>]*)?>([^<]*)<\/w:t>/g)].map((m) => m[1]).join('');
    // Styles de titre : « Heading1 » en anglais, « Titre1 » dans Word en français
    return { titre: /^(heading|titre)\s?\d/i.test(style), texte: decoderEntites(texte).trim() };
  });
  return regrouperParTitres(paragraphes);
}

async function extraireOdt(chemin) {
  const xml = await lireXmlArchive(chemin, 'content.xml');
  const corps = xml.replace(/<text:(s|tab)\b[^>]*\/>/g, ' ').replace(/<text:line-break\/>/g, '\n');
  const paragraphes = [...corps.matchAll(/<text:(h|p)\b[^>]*>([\s\S]*?)<\/text:\1>/g)].map(([, type, contenu]) => ({
    titre: type === 'h',
    texte: decoderEntites(contenu.replace(/<[^>]*>/g, '')).trim()
  }));
  return regrouperParTitres(paragraphes);
}

// --- Texte et Markdown (découpage sur les titres) ------------------------------
async function extraireTexte(chemin) {
  const st = await fsp.stat(chemin);
  if (st.size > TEXTE_MAX) throw new Error('Fichier texte trop volumineux.');
  const paragraphes = (await fsp.readFile(chemin, 'utf8')).split('\n').map((ligne) => {
    const h = /^#{1,3}\s+(.+)$/.exec(ligne);
    return h ? { titre: true, texte: h[1].trim() } : { titre: false, texte: ligne.trimEnd() };
  });
  // Les lignes vides séparent les paragraphes : elles sont conservées dans le texte
  const sections = [];
  let courant = { section: 'début', texte: '' };
  for (const p of paragraphes) {
    if (p.titre) {
      if (courant.texte.trim()) sections.push(courant);
      courant = { section: p.texte.slice(0, 120), texte: '' };
    } else {
      courant.texte += `${p.texte}\n`;
    }
  }
  if (courant.texte.trim()) sections.push(courant);
  return sections.map((s) => ({ ...s, texte: s.texte.trim() }));
}

async function extraireDocument(chemin, o = {}) {
  const ext = path.extname(chemin).toLowerCase();
  if (ext === '.epub') return extraireEpub(chemin);
  const titre = path.basename(chemin, ext);
  if (ext === '.pdf') return { titre, sections: await extrairePdf(chemin, o) };
  if (ext === '.docx') return { titre, sections: await extraireDocx(chemin) };
  if (ext === '.odt') return { titre, sections: await extraireOdt(chemin) };
  return { titre, sections: await extraireTexte(chemin) };
}

module.exports = { extraireDocument, verifierDocument, EXT_LIVRES, EXT_DOCUMENTS };
