#!/usr/bin/env node
/*
 * LogiProjet — fichier d'import (.json) → consigne Word (.docx) à la charte LogiOne.
 * Le Word et la page vue par les apprentis dans LogiProjet viennent ainsi du même texte.
 *
 * Usage : node consigne_docx.js activite.json sortie.docx [--auteur "Nom Prénom"] [--formation "CFC Logisticien·ne"]
 * Dépendance : le paquet npm « docx » (npm install docx si `require('docx')` échoue).
 */
const fs = require('fs');
const d = require('docx');
const { Document, Packer, Paragraph, TextRun, Table, TableRow, TableCell, WidthType, ShadingType, BorderStyle,
  AlignmentType, VerticalAlign, PageBreak, Footer, PageNumber, TabStopType, LevelFormat, HeightRule } = d;

/* ---------- Entrées ---------- */
const args = process.argv.slice(2);
if (args.length < 2) { console.error('Usage : node consigne_docx.js activite.json sortie.docx [--auteur "…"] [--formation "…"]'); process.exit(1); }
const opt = (name, def) => { const i = args.indexOf('--' + name); return i > -1 ? args[i + 1] : def; };
const A = JSON.parse(fs.readFileSync(args[0], 'utf8'));
if (A.format !== 'logiprojet-travail') { console.error('Ce fichier n’est pas un fichier logiprojet-travail.'); process.exit(1); }
const C0 = A.consigne || {};
const GRADED = A.graded !== false;
const SIZE = Math.max(1, Math.min(6, parseInt(A.group_size || 2, 10)));
const LESSONS = A.lessons || [];
const GRILLE = A.grille || [];
const TOTAL = 2 * GRILLE.length;
const AUTEUR = opt('auteur', A.auteur || '');
const FORMATION = opt('formation', A.formation || 'CFC Logisticien·ne');

const NUMW = { 2: 'deux', 3: 'trois', 4: 'quatre', 5: 'cinq', 6: 'six' };
const groupWord = (n) => (n === 1 ? 'travail individuel' : n === 2 ? 'duo' : n === 3 ? 'trio' : 'groupe de ' + n);
// Groupe avec possibilité de travailler seul·e (allow_solo) : « duo ou seul·e » dans les bandeaux et le pied de page.
const SOLO = !!A.allow_solo && SIZE > 1;
const groupLabel = (short) => (short && SOLO ? groupWord(SIZE).replace(/^./, (c) => c.toUpperCase()) + ' ou seul·e' : 'Travail en ' + groupWord(SIZE) + (SOLO ? ' ou seul·e' : ''));
const DAYS = ['dimanche', 'lundi', 'mardi', 'mercredi', 'jeudi', 'vendredi', 'samedi'];
function fmtDate(v, time) {
  if (!v) { return null; }
  const m = String(v).match(/^(\d{4})-(\d{2})-(\d{2})(?:[T ](\d{2}):(\d{2}))?/);
  if (!m) { return null; }
  const dt = new Date(Date.UTC(+m[1], +m[2] - 1, +m[3]));
  let s = DAYS[dt.getUTCDay()] + ' ' + m[3] + '.' + m[2] + '.' + m[1];
  if (time && m[4]) { s += ' à ' + parseInt(m[4], 10) + ' h' + (m[5] !== '00' ? ' ' + m[5] : ''); }
  return s;
}
function lessonsLabel() {
  const nums = LESSONS.map(l => (String(l.title).match(/^(\d+(?:\.\d+)*)\s/) || [])[1]).filter(Boolean);
  return nums.length >= 2 ? nums[0] + ' à ' + nums[nums.length - 1] : (nums[0] || 'du module');
}
const PERIODES = (C0.etapes || []).length;
const FORMAT_LABELS = { pptx: 'PowerPoint (.pptx)', pdf: 'PDF (.pdf)', docx: 'Word (.docx)', xlsx: 'Excel (.xlsx)', odp: 'Impress (.odp)', key: 'Keynote (.key)', img: 'Image (.jpg, .png)', mp4: 'Vidéo (.mp4)', zip: 'Archive (.zip)' };
const VARS = {
  '{module}': A.module || 'du cours', '{total}': String(TOTAL), '{n}': String(LESSONS.length), '{lecons}': lessonsLabel(),
  '{periodes}': String(PERIODES), '{date_debut}': fmtDate(A.date_debut, false), '{date_fin}': fmtDate(A.due_at, true),
};
const TITRES = Object.assign({
  mission: 'Votre mission', contenu: 'Ce que vous devez présenter', plan: 'Le plan de votre présentation (8 à 12 slides)',
  page2: 'Comment réaliser votre présentation', regles: 'Les règles de réalisation', etapes: 'Les étapes de travail en classe',
  rendu: 'Le rendu', jourj: 'Le jour de la présentation', checklist: 'Check-list avant de rendre',
  grille: GRADED ? 'Grille d’évaluation de la présentation' : 'Critères de réussite',
}, C0.titres || {});
const INTRO_LECONS = C0.intro_lecons !== undefined ? C0.intro_lecons : 'Votre présentation couvre **les {n} leçons du module {module}** sur logiprof :';
const INTRO_ACTIONS = C0.intro_actions !== undefined ? C0.intro_actions : 'Pour **chaque leçon**, votre présentation doit faire ces {k} choses :';

/* ---------- Charte ---------- */
const F = 'Arial';
const C = { ink: '313A45', deep: '252C35', soft: '4A5563', green: '067030', mint: 'DCEFE3', paper: 'EEF0EB', line: 'D9DDD6',
  yellow: 'F5C400', ysoft: 'FFF4C2', yink: '5C4800', red: 'B42318', muted: '5B6672', white: 'FFFFFF', light: 'C9D1D9' };
const W = 11906 - 2 * 850;
const none = { style: BorderStyle.NONE, size: 0, color: 'FFFFFF' };
const NB = { top: none, bottom: none, left: none, right: none };
const ln = (c = C.line, s = 4) => ({ style: BorderStyle.SINGLE, size: s, color: c });
const BOX = (c = C.line, s = 4) => ({ top: ln(c, s), bottom: ln(c, s), left: ln(c, s), right: ln(c, s) });
const r = (text, o = {}) => new TextRun({ text, font: F, size: o.size || 19, bold: o.bold, italics: o.it, color: o.color || C.ink, highlight: o.hl, allCaps: o.caps, characterSpacing: o.sp });
const todo = (t, o = {}) => new TextRun({ text: t, font: F, size: o.size || 19, bold: true, color: C.ink, highlight: 'yellow' });

/* Texte avec **gras** et repères {module}… ; une date absente devient une zone jaune à compléter. */
function rich(s, o = {}) {
  const out = [];
  // Les repères connus sont remplacés d'abord, pour que **gras** puisse les englober.
  const txt = String(s == null ? '' : s).replace(/\{[a-z_]+\}/g, k => (VARS[k] !== null && VARS[k] !== undefined ? VARS[k] : k));
  txt.split(/(\{[a-z_]+\})/).forEach(part => {
    if (/^\{[a-z_]+\}$/.test(part)) {
      out.push(todo(part === '{date_fin}' ? 'jj.mm.aaaa à __ h' : part === '{date_debut}' ? 'jj.mm.aaaa' : part, o));
      return;
    }
    part.split(/(\*\*[^*]+\*\*)/).forEach(seg => {
      if (!seg) { return; }
      const b = /^\*\*[^*]+\*\*$/.test(seg);
      out.push(r(b ? seg.slice(2, -2) : seg, Object.assign({}, o, { bold: b || o.bold })));
    });
  });
  return out;
}
const p = (runs, o = {}) => new Paragraph({ children: Array.isArray(runs) ? runs : [typeof runs === 'string' ? r(runs, o) : runs], alignment: o.align,
  spacing: { before: o.before ?? 0, after: o.after ?? 60, line: o.line ?? 264 }, keepNext: o.keepNext, keepLines: true, tabStops: o.tabs });
function cell(children, o = {}) {
  return new TableCell({ children: Array.isArray(children) ? children : [children], width: { size: o.w, type: WidthType.DXA },
    shading: o.fill ? { type: ShadingType.CLEAR, color: 'auto', fill: o.fill } : undefined, borders: o.borders || NB,
    margins: { top: o.mt ?? 90, bottom: o.mb ?? 90, left: o.ml ?? 140, right: o.mr ?? 140 }, verticalAlign: o.va || VerticalAlign.TOP, columnSpan: o.span });
}
const table = (widths, rows, o = {}) => new Table({ width: { size: widths.reduce((a, b) => a + b, 0), type: WidthType.DXA }, columnWidths: widths, rows,
  borders: Object.assign({ top: none, bottom: none, left: none, right: none, insideHorizontal: none, insideVertical: none }, o.borders || {}) });
const gap = (n = 120) => new Paragraph({ children: [], spacing: { before: 0, after: n, line: 120 } });
const split = (w, n) => { const x = Math.floor(w / n); return Array.from({ length: n }, (_, i) => (i === n - 1 ? w - x * (n - 1) : x)); };
const pipe = (line) => { const i = String(line).indexOf('|'); return i < 0 ? [line, ''] : [line.slice(0, i).trim(), line.slice(i + 1).trim()]; };

function band(kicker, title, sub, s1, s2) {
  const lw = 2350, sw = 1650, mw = W - lw - sw;
  const stamp = table([1250], [new TableRow({ children: [cell([
    p([r(s1, { bold: true, size: s1.replace(/\s/g, '').length > 6 && !/\s/.test(s1) ? 17 : 24, color: C.deep })], { align: AlignmentType.CENTER, after: 0, line: 230 }),
    p([r(s2, { bold: true, size: 15, color: C.deep })], { align: AlignmentType.CENTER, after: 0 })], { w: 1250, fill: C.yellow, mt: 100, mb: 100, ml: 80, mr: 80 })] })]);
  return table([lw, mw, sw], [new TableRow({ children: [
    cell([p([r('LogiProf', { bold: true, size: 30, color: C.white })], { after: 0 }), p([r(FORMATION, { size: 16, color: C.light })], { after: 0 })],
      { w: lw, fill: C.ink, va: VerticalAlign.CENTER, ml: 260, mt: 220, mb: 220, borders: { ...NB, right: ln(C.soft, 6) } }),
    cell([p([r(kicker.toUpperCase(), { bold: true, size: 15, color: C.yellow, sp: 30 })], { after: 20 }),
      p([r(title, { bold: true, size: 32, color: C.white })], { before: 30, after: 30, line: 276 }),
      ...(sub ? [p([r(sub, { size: 18, color: C.light })], { after: 0 })] : [])], { w: mw, fill: C.ink, va: VerticalAlign.CENTER, ml: 260, mt: 200, mb: 200 }),
    cell([stamp], { w: sw, fill: C.ink, va: VerticalAlign.CENTER, ml: 120, mr: 260, mt: 200, mb: 200 }),
  ] })]);
}
const stripe = () => table([W], [new TableRow({ height: { value: 110, rule: HeightRule.EXACT }, children: [cell(p(''), { w: W, fill: C.yellow, mt: 0, mb: 0 })] })]);
let SEC = 0;
function h2(text) {
  SEC += 1;
  return [table([420, W - 420], [new TableRow({ children: [
    cell(p([r(String(SEC), { bold: true, size: 18, color: C.white })], { align: AlignmentType.CENTER, after: 0 }), { w: 420, fill: C.green, mt: 40, mb: 40, ml: 0, mr: 0, va: VerticalAlign.CENTER }),
    cell(p(rich(text, { bold: true, size: 23 }), { after: 0, keepNext: true }), { w: W - 420, va: VerticalAlign.CENTER, mt: 40, mb: 40, ml: 160 }),
  ] })]), gap(60)];
}
function tiles(list) {
  const widths = split(W, list.length);
  return table(widths, [new TableRow({ children: list.map((t, i) => cell([...(t.title ? [p(t.title, { after: 60 })] : []), ...t.body],
    { w: widths[i], fill: t.fill, borders: { top: ln(t.top || C.green, 18), bottom: ln(C.line), left: ln(C.white, 30), right: ln(C.white, 30) }, ml: 160, mr: 160, mt: 120, mb: 110 })) })]);
}
const bullet = (s) => new Paragraph({ children: rich(s), numbering: { reference: 'puces', level: 0 }, spacing: { before: 0, after: 40, line: 252 } });
const check = (s) => new Paragraph({ children: [r('☐  ', { size: 22 }), ...rich(s)], spacing: { before: 0, after: 50, line: 252 } });
const has = (k) => Array.isArray(C0[k]) && C0[k].length > 0;

/* ---------- Page 1 ---------- */
const kicker = (SIZE > 1 ? groupLabel() : 'Travail individuel') + (A.module ? ' · Module ' + A.module : '');
const page1 = [band(kicker, A.title || 'Activité', (A.course && A.course.title) || '', GRADED ? 'TRAVAIL NOTÉ' : 'ACTIVITÉ', GRADED ? 'note sur 6' : 'non notée'), stripe(), gap(160)];
if (C0.note) {
  const fill = GRADED ? C.ysoft : C.mint, bc = GRADED ? C.yellow : C.green;
  page1.push(table([1500, W - 1500], [new TableRow({ children: [
    cell([p([r(GRADED ? '1 – 6' : 'Formatif', { bold: true, size: GRADED ? 40 : 28, color: C.deep })], { after: 0, align: AlignmentType.CENTER }),
      p([r(GRADED ? 'note du ' + (SIZE === 1 || SOLO ? 'travail' : (SIZE > 3 ? 'groupe' : groupWord(SIZE))) : 'non noté', { bold: true, size: 16, color: C.deep })], { after: 0, align: AlignmentType.CENTER })],
    { w: 1500, fill, va: VerticalAlign.CENTER, borders: { top: ln(bc, 8), bottom: ln(bc, 8), left: ln(bc, 36), right: none } }),
    cell(p(rich(C0.note, { color: GRADED ? C.yink : C.ink }), { after: 0 }), { w: W - 1500, fill, va: VerticalAlign.CENTER, borders: { top: ln(bc, 8), bottom: ln(bc, 8), right: ln(bc, 8), left: none } }),
  ] })]), gap(140));
}
{ // Cartouche
  const lab = (t) => p([r(t, { bold: true, size: 14, color: C.muted, caps: true, sp: 20 })], { after: 40 });
  const names = Array.from({ length: SIZE }, (_, i) => ('Apprenti·e ' + (SIZE > 1 ? i + 1 : '').toString()).trim() + (SOLO && i > 0 ? ' (si ' + groupWord(SIZE) + ')' : ''));
  const row1 = [...names, 'Classe'];
  const w1 = split(W, row1.length);
  const w2 = split(W, 3);
  page1.push(table(w1, [new TableRow({ children: row1.map((t, i) => cell([lab(t), p('')], { w: w1[i], borders: BOX(), mt: 70, mb: 90 })) })]));
  page1.push(table(w2, [new TableRow({ children: [
    cell([lab('Date de début'), p(rich('{date_debut}'), { after: 0 })], { w: w2[0], borders: BOX(), mt: 70, mb: 90 }),
    cell([lab('Date de fin · rendu du fichier'), p(rich('{date_fin}'), { after: 0 })], { w: w2[1], borders: BOX(), mt: 70, mb: 90 }),
    cell([lab('Durée'), p([r(PERIODES ? PERIODES + ' période' + (PERIODES > 1 ? 's' : '') + ' en classe' : '', { bold: true })], { after: 0 })], { w: w2[2], borders: BOX(), mt: 70, mb: 90 }),
  ] })]), gap(200));
}
if (has('mission')) {
  page1.push(...h2(TITRES.mission), table([W], [new TableRow({ children: [cell(C0.mission.map((m, i) => p(rich(m), { after: i === C0.mission.length - 1 ? 0 : 60 })),
    { w: W, fill: C.paper, ml: 220, mr: 220, mt: 140, mb: 120, borders: { top: ln(C.green, 18), bottom: none, left: none, right: none } })] })]), gap(200));
}
if (LESSONS.length || has('actions')) {
  page1.push(...h2(TITRES.contenu));
  if (LESSONS.length && INTRO_LECONS) {
    page1.push(p(rich(INTRO_LECONS), { after: 80 }));
    page1.push(tiles(LESSONS.map(l => { const m = String(l.title).match(/^(\d+(?:\.\d+)*)\s+(.*)$/); return { body: [p([r(m ? m[1] : '', { bold: true, color: C.green })], { after: 0 }), p([r(m ? m[2] : l.title, { size: 18 })], { after: 0 })] }; })), gap(100));
  }
  if (has('actions')) {
    if (INTRO_ACTIONS) { page1.push(p(rich(INTRO_ACTIONS.replace('{k}', String(C0.actions.length))), { after: 80 })); }
    page1.push(tiles(C0.actions.map(a => { const x = pipe(a); return { title: rich(x[0], { bold: true, size: 20, color: C.green }), fill: C.paper, top: C.paper, body: [p(rich(x[1], { size: 18 }), { after: 0 })] }; })));
  }
  page1.push(gap(200));
}
if (has('plan')) {
  const w = [1900, W - 1900];
  page1.push(...h2(TITRES.plan), table(w, C0.plan.map(l => { const x = pipe(l); return new TableRow({ children: [
    cell(p(rich(x[0], { bold: true, color: C.green }), { after: 0 }), { w: w[0], borders: { ...NB, bottom: ln() }, mt: 60, mb: 60 }),
    cell(p(rich(x[1]), { after: 0 }), { w: w[1], borders: { ...NB, bottom: ln() }, mt: 60, mb: 60 }),
  ] }); })));
}

/* ---------- Page 2 ---------- */
const page2 = [];
if (has('faire') || has('eviter') || has('etapes') || has('rendu') || has('jourj') || has('checklist')) {
  page2.push(new Paragraph({ children: [new PageBreak()], spacing: { after: 0 } }),
    band((SIZE > 1 ? groupLabel(true) : 'Travail individuel') + (A.module ? ' · Module ' + A.module : '') + (GRADED ? ' · Travail noté' : ''), TITRES.page2, '', 'À LIRE', 'avant de commencer'), stripe(), gap(180));
  if (has('faire') || has('eviter')) {
    const list = [];
    if (has('faire')) { list.push({ title: [r('✔ À faire', { bold: true, size: 20 })], top: C.green, body: C0.faire.map(bullet) }); }
    if (has('eviter')) { list.push({ title: [r('✘ À éviter', { bold: true, size: 20 })], top: C.red, body: C0.eviter.map(bullet) }); }
    page2.push(...h2(TITRES.regles), tiles(list), gap(200));
  }
  if (has('etapes')) {
    const w = [1300, W - 1300];
    page2.push(...h2(TITRES.etapes + ' (' + PERIODES + ' période' + (PERIODES > 1 ? 's' : '') + ')'), table(w, C0.etapes.map((e, i) => new TableRow({ children: [
      cell(p([r('Période ' + (i + 1), { bold: true, color: C.yellow })], { after: 0, align: AlignmentType.CENTER }), { w: w[0], fill: C.ink, va: VerticalAlign.CENTER, borders: { ...NB, bottom: ln(C.white, 12) }, mt: 80, mb: 80 }),
      cell(p(rich(e), { after: 0 }), { w: w[1], borders: { ...NB, bottom: ln(), right: ln(), top: ln() }, mt: 80, mb: 80, ml: 180 }),
    ] }))), gap(200));
  }
  const two = [];
  if (has('rendu')) { SEC += 1; two.push({ title: [r(SEC + ' · ' + TITRES.rendu, { bold: true, size: 20 })], top: C.yellow, body: C0.rendu.map(bullet) }); }
  if (has('jourj')) { SEC += 1; two.push({ title: [r(SEC + ' · ' + TITRES.jourj, { bold: true, size: 20 })], top: C.yellow, body: C0.jourj.map(bullet) }); }
  if (two.length) { page2.push(tiles(two), gap(200)); }
  if (has('checklist')) {
    const half = Math.ceil(C0.checklist.length / 2);
    page2.push(...h2(TITRES.checklist), tiles([{ top: C.ink, body: C0.checklist.slice(0, half).map(check) }, { top: C.ink, body: C0.checklist.slice(half).map(check) }]));
  }
}

/* ---------- Page 3 : grille ---------- */
const page3 = [];
if (GRILLE.length) {
  const blocs = [];
  GRILLE.forEach(c => { const last = blocs[blocs.length - 1]; if (!last || last.name !== c.bloc) { blocs.push({ name: c.bloc, items: [c] }); } else { last.items.push(c); } });
  const gw = [W - 3 * 620, 620, 620, 620];
  const rows = [new TableRow({ tableHeader: true, children: ['Critère', '0', '1', '2'].map((t, i) => cell(p([r(t, { bold: true, size: 16, color: C.white, caps: true })], { after: 0, align: i ? AlignmentType.CENTER : AlignmentType.LEFT }), { w: gw[i], fill: C.ink, mt: 70, mb: 70 })) })];
  blocs.forEach(b => {
    rows.push(new TableRow({ children: [cell(p([r(b.name, { bold: true, size: 17, color: C.green }), new TextRun({ text: GRADED ? '\t' + (2 * b.items.length) + ' pts' : '', font: F, size: 17, bold: true, color: C.ink })],
      { after: 0, tabs: [{ type: TabStopType.RIGHT, position: W - 300 }] }), { w: W, span: 4, fill: C.mint, mt: 50, mb: 50, borders: { ...NB, top: ln() } })] }));
    b.items.forEach(c => {
      const m = String(c.label).match(/^(\d+(?:\.\d+)* [^:]+)( : .*)$/);
      rows.push(new TableRow({ children: [
        cell(p(m ? [r(m[1], { bold: true, size: 18 }), r(m[2], { size: 18 })] : [r(c.label, { size: 18 })], { after: 0 }), { w: gw[0], borders: { ...NB, top: ln() }, mt: 55, mb: 55, va: VerticalAlign.CENTER }),
        ...[1, 2, 3].map(i => cell(p([r('☐', { size: 24, color: C.soft })], { after: 0, align: AlignmentType.CENTER }), { w: gw[i], borders: { ...NB, top: ln() }, mt: 40, mb: 40, va: VerticalAlign.CENTER })),
      ] }));
    });
  });
  if (GRADED) {
    rows.push(new TableRow({ children: [cell(p([r('Total du support', { bold: true, size: 20, color: C.white })], { after: 0 }), { w: gw[0] + gw[1], span: 2, fill: C.ink, mt: 80, mb: 80 }),
      cell(p([r('/ ' + TOTAL, { bold: true, size: 20, color: C.white })], { after: 0, align: AlignmentType.RIGHT }), { w: gw[2] + gw[3], span: 2, fill: C.ink, mt: 80, mb: 80 })] }));
  }
  page3.push(new Paragraph({ children: [new PageBreak()], spacing: { after: 0 } }),
    band((A.module ? 'Module ' + A.module + ' · ' : '') + (SIZE > 1 ? groupLabel(true) : 'Travail individuel') + (GRADED ? ' · Travail noté' : ''), TITRES.grille, '', GRADED ? '/ ' + TOTAL : 'FORMATIF', GRADED ? 'points' : 'non noté'), stripe(), gap(140));
  const lab = (t) => p([r(t, { bold: true, size: 14, color: C.muted, caps: true, sp: 20 })], { after: 30 });
  const row1 = [...Array.from({ length: SIZE }, (_, i) => ('Apprenti·e ' + (SIZE > 1 ? i + 1 : '')).trim() + (SOLO && i > 0 ? ' (si ' + groupWord(SIZE) + ')' : '')), 'Classe'];
  const w1 = split(W, row1.length);
  page3.push(table(w1, [new TableRow({ children: row1.map((t, i) => cell([lab(t), p('')], { w: w1[i], borders: BOX(), mt: 50, mb: 50 })) })]),
    p([r('Chaque critère :  ', { size: 16, color: C.muted }), r('0', { bold: true, size: 16 }), r(' = absent ou faux    ', { size: 16, color: C.muted }), r('1', { bold: true, size: 16 }), r(' = partiel    ', { size: 16, color: C.muted }), r('2', { bold: true, size: 16 }), r(' = atteint     Une seule case par ligne.', { size: 16, color: C.muted })], { before: 80, after: 60 }),
    table(gw, rows, { borders: BOX() }), gap(140));
  if (GRADED) {
    // Conversion points → note (échelle suisse) et bonus oral.
    const conv = []; let cur = null;
    for (let pts = 0; pts <= TOTAL; pts++) { const n = Math.max(1, Math.min(6, Math.floor((pts / TOTAL * 5 + 1) * 2 + 0.5) / 2)); if (!cur || cur.n !== n) { cur = { n, min: pts, max: pts }; conv.push(cur); } else { cur.max = pts; } }
    const cw0 = 640, cws = split(5400 - cw0, conv.length), cvW = [cw0, ...cws];
    const cvRow = (label, f) => new TableRow({ children: [cell(p([r(label, { bold: true, size: 13, color: C.muted })], { after: 0 }), { w: cw0, fill: C.paper, borders: BOX(), ml: 40, mr: 30, mt: 30, mb: 30 }),
      ...conv.map((c, i) => cell(p([r(f(c), { size: 14 })], { after: 0, align: AlignmentType.CENTER }), { w: cws[i], fill: c.n >= 4 ? C.mint : undefined, borders: BOX(), ml: 10, mr: 10, mt: 30, mb: 30 }))] });
    const oralW = W - 5400 - 200;
    const oral = A.oral_bonus === false ? [cell(p(''), { w: oralW })] : [cell([p([r('Bonus oral · seulement si tiré au sort', { bold: true, size: 17 })], { after: 50 }),
      ...['Clarté de la présentation', 'Réponses aux questions', 'Temps respecté (10 min ± 1)'].map(t => p([r(t, { size: 17 }), new TextRun({ text: '\t☐ 0   ☐ 1   ☐ 2', font: F, size: 17, color: C.ink })], { after: 30, tabs: [{ type: TabStopType.RIGHT, position: oralW - 300 }] })),
      p([r('4 / 6 ou plus : ', { size: 15, color: C.muted }), r('+0.5', { bold: true, size: 15 }), r(' sur la note (maximum 6).', { size: 15, color: C.muted })], { after: 0 })],
    { w: oralW, borders: { top: ln(C.yellow, 18), bottom: ln(), left: ln(), right: ln() }, mt: 90, mb: 90 })];
    page3.push(table([5400, 200, oralW], [new TableRow({ children: [
      cell([p([r('Conversion points → note', { bold: true, size: 17 })], { after: 50 }), table(cvW, [cvRow('Points', c => (c.min === c.max ? '' + c.min : c.min + '-' + c.max)), cvRow('Note', c => String(c.n))]),
        p([r('Note = points ÷ ' + TOTAL + ' × 5 + 1, arrondie au demi-point.', { size: 14, color: C.muted })], { before: 40, after: 0 })], { w: 5400, ml: 0, mr: 0, mt: 0, mb: 0 }),
      cell(p(''), { w: 200, ml: 0, mr: 0 }), ...oral] })]), gap(140));
    const rw = split(W, 4);
    const res = (label, val, fill, bc, i) => cell([p([r(label, { bold: true, size: 14, color: C.muted, caps: true })], { after: 40, align: AlignmentType.CENTER }), p([r(val, { bold: true, size: 26 })], { after: 0, align: AlignmentType.CENTER })],
      { w: rw[i], fill, borders: { top: ln(bc, 12), bottom: ln(bc, 12), left: ln(bc, 12), right: ln(bc, 12) }, mt: 70, mb: 90 });
    page3.push(table(rw, [new TableRow({ children: [res('Points', '      / ' + TOTAL, undefined, C.ink, 0), res('Note du support', '', undefined, C.ink, 1), res('Bonus oral', '+', undefined, C.ink, 2), res('Note finale', '', C.ysoft, C.yellow, 3)] })]), gap(120));
  }
  page3.push(table([W], [new TableRow({ height: { value: 1250, rule: HeightRule.ATLEAST }, children: [cell([p([r(GRADED ? 'Points forts et piste d’amélioration' : 'Retour de l’enseignant', { bold: true, size: 14, color: C.muted, caps: true })], { after: 0 })], { w: W, borders: BOX(), mt: 70 })] })]));
}

/* ---------- Document ---------- */
const footText = ['LogiProf', AUTEUR, FORMATION, A.module ? 'Module ' + A.module : '', SIZE > 1 ? groupLabel() : 'Travail individuel'].filter(Boolean).join(' · ');
const doc = new Document({
  creator: AUTEUR || 'LogiProf', title: A.title || 'Activité', description: 'Consigne LogiProjet',
  styles: { default: { document: { run: { font: F, size: 19, color: C.ink } } } },
  numbering: { config: [{ reference: 'puces', levels: [{ level: 0, format: LevelFormat.BULLET, text: '•', alignment: AlignmentType.LEFT, style: { paragraph: { indent: { left: 300, hanging: 220 } }, run: { color: C.green } } }] }] },
  sections: [{ properties: { page: { size: { width: 11906, height: 16838 }, margin: { top: 680, bottom: 800, left: 850, right: 850, footer: 380 } } },
    footers: { default: new Footer({ children: [new Paragraph({ border: { top: ln(C.line, 4) }, spacing: { before: 0, after: 0 }, tabStops: [{ type: TabStopType.RIGHT, position: W }],
      children: [r(footText, { size: 14, color: C.muted }), new TextRun({ children: ['\tPage ', PageNumber.CURRENT, ' / ', PageNumber.TOTAL_PAGES], font: F, size: 14, color: C.muted })] })] }) },
    children: [...page1, ...page2, ...page3] }],
});
Packer.toBuffer(doc).then(b => { fs.writeFileSync(args[1], b); console.log('Word créé : ' + args[1]); });
