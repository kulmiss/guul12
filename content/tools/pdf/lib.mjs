// Pure helpers for the summary PDFs (no browser, no files), so they can be unit-tested.
import { createHash } from 'node:crypto';
import katex from 'katex';

/** Bump when the page layout changes, so every PDF is rebuilt. */
export const GENERATOR_VERSION = 1;

/** The price-free landing page (DECISIONS #3). Never the pricing page. */
export const LANDING_URL = 'https://guul12.kulmiss.com/app';

/** Must match backend/database/seeders/CurriculumSeeder.php (a test checks this). */
export const SUBJECT_NAMES = {
  english: 'English',
  mathematics: 'Mathematics',
  aptitude: 'Scholastic Aptitude',
  physics: 'Physics',
  chemistry: 'Chemistry',
  biology: 'Biology',
  geography: 'Geography',
  history: 'History',
  economics: 'Economics',
};

/**
 * Splits text into plain and LaTeX parts, exactly like the app's parseMath
 * (app/lib/ui/widgets/math_text.dart). `\$` is a literal dollar sign; an
 * opening `$` with no closing `$` stays plain text.
 *
 * @param {string} input
 * @returns {{kind: 'text'|'inline'|'block', value: string}[]}
 */
export function parseMath(input) {
  const out = [];
  let buffer = '';
  let i = 0;
  const flush = () => {
    if (buffer !== '') {
      out.push({ kind: 'text', value: buffer });
      buffer = '';
    }
  };

  while (i < input.length) {
    const ch = input[i];
    if (ch === '\\' && input[i + 1] === '$') {
      buffer += '$';
      i += 2;
      continue;
    }
    if (ch === '$') {
      const isBlock = input[i + 1] === '$';
      const delimiter = isBlock ? '$$' : '$';
      const start = i + delimiter.length;
      const end = findClosing(input, start, delimiter);
      if (end === -1) {
        buffer += input.substring(i);
        break;
      }
      const latex = input.substring(start, end).trim();
      if (latex === '') {
        buffer += input.substring(i, end + delimiter.length);
      } else {
        flush();
        out.push({ kind: isBlock ? 'block' : 'inline', value: latex });
      }
      i = end + delimiter.length;
      continue;
    }
    buffer += ch;
    i++;
  }
  flush();
  return out;
}

function findClosing(input, from, delimiter) {
  let i = from;
  while (i < input.length) {
    if (input[i] === '\\') {
      i += 2;
      continue;
    }
    if (input.startsWith(delimiter, i)) {
      if (delimiter === '$' && input[i + 1] === '$') return -1;
      return i;
    }
    i++;
  }
  return -1;
}

export function escapeHtml(text) {
  return String(text)
    .replaceAll('&', '&amp;')
    .replaceAll('<', '&lt;')
    .replaceAll('>', '&gt;')
    .replaceAll('"', '&quot;')
    .replaceAll("'", '&#39;');
}

/** LaTeX → HTML. Bad LaTeX throws, so a broken formula can never reach a PDF. */
export function renderLatex(latex, displayMode) {
  return katex.renderToString(latex, { displayMode, throwOnError: true, strict: 'error', output: 'html' });
}

/**
 * Text with $…$ maths → safe HTML. Punctuation right after inline maths stays on
 * the same line as the maths (no line starting with ")" or ",").
 */
export function renderRich(text) {
  const parts = parseMath(String(text));
  let html = '';
  for (let i = 0; i < parts.length; i++) {
    const part = parts[i];
    if (part.kind === 'text') {
      html += escapeHtml(part.value);
      continue;
    }
    if (part.kind === 'block') {
      html += renderLatex(part.value, true);
      continue;
    }
    const next = parts[i + 1];
    const glued = next?.kind === 'text' ? (next.value.match(/^[).,;:!?%]+/) ?? [''])[0] : '';
    if (glued === '') {
      html += renderLatex(part.value, false);
    } else {
      html += `<span class="nowrap">${renderLatex(part.value, false)}${escapeHtml(glued)}</span>`;
      parts[i + 1] = { kind: 'text', value: next.value.slice(glued.length) };
    }
  }
  return html;
}

/** What a PDF is built from. Any change here means the PDF must be rebuilt. */
export function pdfSource(chapterMeta, chapter) {
  return {
    generator: GENERATOR_VERSION,
    landing: LANDING_URL,
    subject: chapterMeta.subjectName,
    unit: [chapterMeta.unitNumber, chapterMeta.unitTitle],
    chapter: [chapterMeta.chapterNumber, chapterMeta.chapterTitle],
    textbook: chapterMeta.textbookUrl ?? null,
    summary: chapter.summary ?? null,
    solved_exercises: chapter.solved_exercises ?? [],
    flashcards: chapter.flashcards ?? [],
  };
}

export function sha256(data) {
  return createHash('sha256').update(data).digest('hex');
}

export function sourceHash(chapterMeta, chapter) {
  return sha256(JSON.stringify(pdfSource(chapterMeta, chapter)));
}

/**
 * The full HTML page of one summary PDF.
 *
 * @param {object} meta  subjectName, unitNumber, unitTitle, chapterNumber, chapterTitle, textbookUrl
 * @param {object} chapter  parsed chapter YAML
 * @param {{katexCss: string, fontDir: string}} assets  file:// URLs
 */
export function chapterHtml(meta, chapter, assets) {
  const summary = chapter.summary ?? {};
  const keyPoints = summary.key_points ?? [];
  const formulas = summary.formulas ?? [];
  const exercises = chapter.solved_exercises ?? [];
  const cards = chapter.flashcards ?? [];
  const title = `${meta.subjectName} · Unit ${meta.unitNumber}.${meta.chapterNumber}`;

  const sections = [];
  if (keyPoints.length > 0) {
    sections.push(`<h2>Key points</h2><ul class="points">${keyPoints
      .map((p) => `<li>${renderRich(p)}</li>`)
      .join('')}</ul>`);
  }
  if (summary.body) {
    sections.push(`<p>${renderRich(summary.body)}</p>`);
  }
  if (formulas.length > 0) {
    sections.push(`<h2>Formulas</h2><table class="formulas">${formulas
      .map((f) => `<tr><td class="label">${renderRich(f.label)}</td><td class="math">${renderLatex(f.latex, true)}</td></tr>`)
      .join('')}</table>`);
  }
  if (exercises.length > 0) {
    sections.push(`<h2>Solved exercises</h2>${exercises
      .map(
        (ex, i) => `<div class="exercise"><p class="problem"><b>${i + 1}.</b> ${renderRich(ex.problem)}</p><ol>${(ex.steps ?? [])
          .map((s) => `<li>${renderRich(s)}</li>`)
          .join('')}</ol><p class="answer"><b>Answer:</b> ${renderRich(ex.answer)}</p></div>`,
      )
      .join('')}`);
  }
  if (cards.length > 0) {
    sections.push(`<h2>Flashcards</h2><table class="cards">${cards
      .map((c) => `<tr><td class="term">${renderRich(c.term)}</td><td>${renderRich(c.definition)}</td></tr>`)
      .join('')}</table>`);
  }

  const textbook = meta.textbookUrl
    ? `<p class="small">Official textbook (Ministry of Education): <a href="${escapeHtml(meta.textbookUrl)}">${escapeHtml(meta.textbookUrl)}</a></p>`
    : '';

  return `<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="utf-8">
<title>${escapeHtml(`${title} — ${meta.chapterTitle}`)}</title>
<link rel="stylesheet" href="${assets.katexCss}">
<style>
@font-face { font-family: 'Noto Sans'; src: url('${assets.fontDir}/NotoSans-Regular.ttf'); font-weight: 400; }
@font-face { font-family: 'Noto Sans'; src: url('${assets.fontDir}/NotoSans-Bold.ttf'); font-weight: 700; }
@font-face { font-family: 'Nunito'; src: url('${assets.fontDir}/Nunito-ExtraBold.ttf'); font-weight: 800; }
@page { size: A4; margin: 16mm 15mm 18mm 15mm; }
* { box-sizing: border-box; }
body { font-family: 'Noto Sans', sans-serif; font-size: 11pt; line-height: 1.45; color: #1d2a2e; margin: 0; }
header { background: #0E5E6F; color: #fff; padding: 10px 14px; border-radius: 8px; }
header .brand { font-family: 'Nunito', sans-serif; font-weight: 800; font-size: 13pt; }
header .brand span { color: #F28C28; }
header .where { font-size: 9.5pt; opacity: 0.9; }
h1 { font-family: 'Nunito', sans-serif; font-weight: 800; font-size: 18pt; color: #0E5E6F; margin: 14px 0 4px; }
h2 { font-family: 'Nunito', sans-serif; font-weight: 800; font-size: 13pt; color: #0E5E6F; border-bottom: 2px solid #F28C28; padding-bottom: 2px; margin: 16px 0 8px; break-after: avoid; }
ul.points li { margin-bottom: 5px; }
table { width: 100%; border-collapse: collapse; }
td { vertical-align: top; padding: 6px 8px; border-bottom: 1px solid #e3ddd2; }
tr { break-inside: avoid; }
td.label, td.term { width: 34%; font-weight: 700; }
td.math .katex-display { margin: 0; text-align: left; }
.exercise { background: #FFF8EE; border-left: 4px solid #F28C28; padding: 6px 12px; margin-bottom: 10px; break-inside: avoid; }
.exercise ol { margin: 4px 0; }
.answer { margin: 4px 0; }
.problem { margin: 4px 0; }
.small { font-size: 9pt; color: #55656a; }
.cta { margin-top: 18px; padding: 10px 14px; border: 2px solid #0E5E6F; border-radius: 8px; break-inside: avoid; }
a { color: #0E5E6F; }
.nowrap { white-space: nowrap; }
</style>
</head>
<body>
<header><div class="brand">Guul <span>12</span></div><div class="where">${escapeHtml(meta.subjectName)} · Grade 12 · Unit ${meta.unitNumber}: ${escapeHtml(meta.unitTitle)}</div></header>
<h1>${escapeHtml(meta.chapterTitle)}</h1>
<p class="small">Chapter summary written by Guul 12. It is our own short summary, not the textbook.</p>
${sections.join('\n')}
<div class="cta">Practise this chapter with exam-style questions and explanations in the Guul 12 app: <a href="${LANDING_URL}">${LANDING_URL.replace('https://', '')}</a></div>
${textbook}
</body>
</html>`;
}

/** Footer printed on every page (Chrome footer template: inline styles only). */
export function footerTemplate() {
  return `<div style="width:100%;font-size:8px;color:#55656a;padding:0 15mm;display:flex;justify-content:space-between;font-family:sans-serif;">
<span>Guul 12 · ${LANDING_URL.replace('https://', '')}</span><span>Page <span class="pageNumber"></span> of <span class="totalPages"></span></span></div>`;
}
