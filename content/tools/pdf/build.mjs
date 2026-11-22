// Builds (or checks) the chapter summary PDFs.
//
//   node build.mjs            build missing or out-of-date PDFs
//   node build.mjs --force    rebuild every PDF
//   node build.mjs --check    fail if any PDF is missing, out of date, changed by hand or left over
//
// PDFs go to content/pdf/grade12/<subject>/uN-cM.pdf; content/pdf/manifest.json records,
// for each PDF, the hash of what it was built from and the hash of the PDF file itself.
import { existsSync, mkdirSync, readFileSync, readdirSync, rmSync, statSync, writeFileSync } from 'node:fs';
import { tmpdir } from 'node:os';
import { dirname, join, relative, resolve } from 'node:path';
import { fileURLToPath, pathToFileURL } from 'node:url';
import YAML from 'yaml';
import { SUBJECT_NAMES, chapterHtml, footerTemplate, sha256, sourceHash } from './lib.mjs';

const here = dirname(fileURLToPath(import.meta.url));
const contentDir = resolve(here, '../..');
const pdfRoot = join(contentDir, 'pdf');
const manifestPath = join(pdfRoot, 'manifest.json');
const fontDir = resolve(contentDir, '../app/assets/fonts');
const katexCss = join(here, 'node_modules/katex/dist/katex.min.css');

const args = new Set(process.argv.slice(2));
const checkOnly = args.has('--check');
const force = args.has('--force');

/** Every written chapter in the curriculum, with what its PDF needs. */
export function listChapters() {
  const grades = readdirSync(contentDir).filter((d) => /^grade\d+$/.test(d));
  const out = [];
  for (const grade of grades) {
    const curriculum = YAML.parse(readFileSync(join(contentDir, grade, 'curriculum.yaml'), 'utf8'));
    for (const subject of curriculum.subjects) {
      const subjectName = SUBJECT_NAMES[subject.code];
      if (!subjectName) throw new Error(`No display name for subject "${subject.code}" in lib.mjs`);
      for (const unit of subject.units ?? []) {
        for (const ch of unit.chapters ?? []) {
          if (!ch.file) continue;
          const chapter = YAML.parse(readFileSync(join(contentDir, grade, ch.file), 'utf8'));
          const meta = {
            subjectName,
            unitNumber: unit.number,
            unitTitle: unit.title,
            chapterNumber: ch.number,
            chapterTitle: ch.title,
            textbookUrl: subject.textbook_url ?? null,
          };
          out.push({
            pdf: `${grade}/${ch.file.replace(/\.yaml$/, '.pdf')}`,
            meta,
            chapter,
            source: sourceHash(meta, chapter),
          });
        }
      }
    }
  }
  return out;
}

function readManifest() {
  if (!existsSync(manifestPath)) return { files: {} };
  return JSON.parse(readFileSync(manifestPath, 'utf8'));
}

function pdfFilesOnDisk(dir = pdfRoot) {
  if (!existsSync(dir)) return [];
  return readdirSync(dir).flatMap((name) => {
    const p = join(dir, name);
    if (statSync(p).isDirectory()) return pdfFilesOnDisk(p);
    return name.endsWith('.pdf') ? [relative(pdfRoot, p)] : [];
  });
}

function check(chapters, manifest) {
  const problems = [];
  const wanted = new Set(chapters.map((c) => c.pdf));
  for (const c of chapters) {
    const entry = manifest.files[c.pdf];
    const file = join(pdfRoot, c.pdf);
    if (!existsSync(file)) problems.push(`${c.pdf}: missing`);
    else if (!entry) problems.push(`${c.pdf}: not in manifest.json`);
    else if (entry.source !== c.source) problems.push(`${c.pdf}: out of date (the chapter changed)`);
    else if (entry.pdf !== sha256(readFileSync(file))) problems.push(`${c.pdf}: file differs from manifest.json`);
  }
  for (const f of pdfFilesOnDisk()) if (!wanted.has(f)) problems.push(`${f}: no chapter for this PDF`);
  for (const f of Object.keys(manifest.files)) if (!wanted.has(f)) problems.push(`${f}: left over in manifest.json`);
  return problems;
}

async function build(chapters, manifest) {
  const { default: puppeteer } = await import('puppeteer-core');
  const executablePath =
    process.env.CHROME_PATH ?? ['/usr/bin/google-chrome', '/usr/bin/chromium', '/usr/bin/chromium-browser'].find(existsSync);
  if (!executablePath) throw new Error('Chrome not found: set CHROME_PATH');

  const browser = await puppeteer.launch({ executablePath, args: ['--no-sandbox', '--font-render-hinting=none'] });
  const assets = { katexCss: pathToFileURL(katexCss).href, fontDir: pathToFileURL(fontDir).href };
  const tmp = join(tmpdir(), `guul12-pdf-${process.pid}.html`);
  const files = {};
  let built = 0;
  try {
    const page = await browser.newPage();
    for (const c of chapters) {
      const file = join(pdfRoot, c.pdf);
      const old = manifest.files[c.pdf];
      if (!force && old && old.source === c.source && existsSync(file) && old.pdf === sha256(readFileSync(file))) {
        files[c.pdf] = old;
        continue;
      }
      writeFileSync(tmp, chapterHtml(c.meta, c.chapter, assets));
      await page.goto(pathToFileURL(tmp).href, { waitUntil: 'load' });
      await page.evaluate(() => document.fonts.ready);
      const pdf = await page.pdf({
        format: 'A4',
        printBackground: true,
        preferCSSPageSize: true,
        displayHeaderFooter: true,
        headerTemplate: '<span></span>',
        footerTemplate: footerTemplate(),
        tagged: true,
      });
      mkdirSync(dirname(file), { recursive: true });
      writeFileSync(file, pdf);
      files[c.pdf] = { source: c.source, pdf: sha256(pdf) };
      built++;
    }
  } finally {
    await browser.close();
    rmSync(tmp, { force: true });
  }

  const wanted = new Set(chapters.map((c) => c.pdf));
  for (const f of pdfFilesOnDisk()) if (!wanted.has(f)) rmSync(join(pdfRoot, f));

  const sorted = Object.fromEntries(Object.entries(files).sort(([a], [b]) => a.localeCompare(b)));
  mkdirSync(pdfRoot, { recursive: true });
  writeFileSync(manifestPath, `${JSON.stringify({ files: sorted }, null, 2)}\n`);
  return built;
}

if (process.argv[1] && resolve(process.argv[1]) === fileURLToPath(import.meta.url)) {
  const chapters = listChapters();
  const manifest = readManifest();
  if (checkOnly) {
    const problems = check(chapters, manifest);
    if (problems.length > 0) {
      console.error(problems.join('\n'));
      console.error(`${problems.length} problem(s). Run: cd content/tools/pdf && npm run build`);
      process.exit(1);
    }
    console.log(`${chapters.length} summary PDFs are up to date.`);
  } else {
    const built = await build(chapters, manifest);
    console.log(`Built ${built} PDF(s); ${chapters.length} in total.`);
  }
}
