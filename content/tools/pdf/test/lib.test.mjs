import assert from 'node:assert/strict';
import { readFileSync } from 'node:fs';
import { dirname, resolve } from 'node:path';
import { test } from 'node:test';
import { fileURLToPath } from 'node:url';
import { listChapters } from '../build.mjs';
import {
  LANDING_URL,
  SUBJECT_NAMES,
  chapterHtml,
  footerTemplate,
  parseMath,
  renderLatex,
  renderRich,
  sourceHash,
} from '../lib.mjs';

const here = dirname(fileURLToPath(import.meta.url));
const assets = { katexCss: 'file:///katex.css', fontDir: 'file:///fonts' };
const meta = {
  subjectName: 'Physics',
  unitNumber: 2,
  unitTitle: 'Two-Dimensional Motion',
  chapterNumber: 1,
  chapterTitle: 'Projectile motion',
  textbookUrl: null,
};

test('parseMath splits like the app', () => {
  assert.deepEqual(parseMath('Costs \\$3.00 a day'), [{ kind: 'text', value: 'Costs $3.00 a day' }]);
  assert.deepEqual(parseMath('So $x = 2$.'), [
    { kind: 'text', value: 'So ' },
    { kind: 'inline', value: 'x = 2' },
    { kind: 'text', value: '.' },
  ]);
  assert.deepEqual(parseMath('$$a^2$$'), [{ kind: 'block', value: 'a^2' }]);
  assert.deepEqual(parseMath('Only $5 left'), [{ kind: 'text', value: 'Only $5 left' }]);
  assert.deepEqual(parseMath('Empty $$ here'), [{ kind: 'text', value: 'Empty $$ here' }]);
  assert.deepEqual(parseMath('$\\$x$'), [{ kind: 'inline', value: '\\$x' }]);
});

test('renderRich escapes HTML outside maths', () => {
  const html = renderRich('<script>alert(1)</script> & "q"');
  assert.ok(!html.includes('<script>'));
  assert.ok(html.includes('&lt;script&gt;') && html.includes('&amp;') && html.includes('&quot;'));
});

test('renderRich keeps punctuation after inline maths on the same line', () => {
  const html = renderRich('angles ($30^\\circ$) give');
  assert.match(html, /<span class="nowrap"><span class="katex">[\s\S]*<\/span>\)<\/span> give$/);
  assert.ok(!renderRich('$x$ and').includes('nowrap'));
});

test('bad LaTeX throws instead of reaching a PDF', () => {
  assert.throws(() => renderLatex('\\frac{1}{', false));
  assert.throws(() => renderRich('Bad $\\notacommand{x}$'));
});

test('subject names match the backend seeder', () => {
  const seeder = readFileSync(resolve(here, '../../../../backend/database/seeders/CurriculumSeeder.php'), 'utf8');
  const fromSeeder = Object.fromEntries([...seeder.matchAll(/'([a-z]+)' => \['([^']+)'/g)].map((m) => [m[1], m[2]]));
  assert.deepEqual(fromSeeder, SUBJECT_NAMES);
});

test('the page links only to the price-free landing page and has no payment words', () => {
  assert.equal(LANDING_URL, 'https://guul12.kulmiss.com/app');
  const html = chapterHtml(meta, { summary: { key_points: [], formulas: [] } }, assets) + footerTemplate();
  const links = [...html.matchAll(/href="(https?:[^"]+)"/g)].map((m) => m[1]);
  assert.deepEqual(links, [LANDING_URL]);
  for (const word of ['price', 'birr', 'buy', 'pay', 'whatsapp', 'premium', 'subscribe', 'telegram']) {
    assert.ok(!html.toLowerCase().includes(word), `template must not contain "${word}"`);
  }
});

test('the official textbook link is added when the subject has one', () => {
  const url = 'https://elearn.moe.gov.et/storage/2023%20Text%20books/Grade%2012/G12-Physics-STB-2023-web.pdf';
  const html = chapterHtml({ ...meta, textbookUrl: url }, { summary: {} }, assets);
  assert.ok(html.includes(`href="${url}"`));
});

test('source hash changes only when the chapter content changes', () => {
  const chapter = { summary: { key_points: ['A'], formulas: [] }, questions: [{ uid: 'q1' }] };
  const a = sourceHash(meta, chapter);
  assert.equal(sourceHash(meta, structuredClone(chapter)), a);
  assert.equal(sourceHash(meta, { ...chapter, questions: [] }), a, 'questions are not in the PDF');
  assert.notEqual(sourceHash(meta, { ...chapter, summary: { key_points: ['B'], formulas: [] } }), a);
  assert.notEqual(sourceHash({ ...meta, chapterTitle: 'Other' }, chapter), a);
});

test('every real chapter renders (all maths is valid)', () => {
  const chapters = listChapters();
  assert.ok(chapters.length >= 82, `found ${chapters.length} chapters`);
  for (const c of chapters) {
    assert.doesNotThrow(() => chapterHtml(c.meta, c.chapter, assets), c.pdf);
  }
});
