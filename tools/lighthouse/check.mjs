// Runs Lighthouse (mobile) on every landing page of a running Guul 12 backend and fails below the scores
// we keep: performance 95+, accessibility, best practices and SEO 100. The Somali and Amharic pages are
// drafts with "noindex" on purpose, so their SEO score is not checked; instead the check makes sure the
// noindex is still there.
//
//   php artisan serve --port=8000        (in backend/)
//   npm ci && npm run check              (here; BASE_URL defaults to http://127.0.0.1:8000)

import { appendFileSync } from 'node:fs';
import * as chromeLauncher from 'chrome-launcher';
import lighthouse from 'lighthouse';

const BASE = (process.env.BASE_URL ?? 'http://127.0.0.1:8000').replace(/\/$/, '');
const MIN = { performance: 0.95, accessibility: 1, 'best-practices': 1, seo: 1 };
const PAGES = [
  { path: '/' },
  { path: '/app' },
  { path: '/premium' },
  { path: '/privacy' },
  { path: '/terms' },
  { path: '/delete-account' },
  { path: '/so', draft: true },
  { path: '/so/premium', draft: true },
  { path: '/am', draft: true },
  { path: '/am/premium', draft: true },
];

async function audit(url) {
  const result = await lighthouse(url, { port: chrome.port, output: 'json', logLevel: 'error' });
  if (!result) throw new Error(`Lighthouse returned nothing for ${url}`);
  return result.lhr;
}

const chrome = await chromeLauncher.launch({ chromeFlags: ['--headless=new', '--no-sandbox'] });
const rows = [];
const problems = [];
try {
  for (const page of PAGES) {
    const url = BASE + page.path;
    let lhr = await audit(url);
    // Performance moves a little between runs (CPU noise). If it misses, run twice more and keep the
    // median run, as Lighthouse advises. The other scores are checked on whichever run is kept.
    if ((lhr.categories.performance?.score ?? 0) < MIN.performance) {
      const runs = [lhr, await audit(url), await audit(url)];
      runs.sort((x, y) => (x.categories.performance?.score ?? 0) - (y.categories.performance?.score ?? 0));
      lhr = runs[1];
    }
    const { categories, audits, runtimeError } = lhr;
    if (runtimeError) problems.push(`${page.path}: ${runtimeError.message}`);

    const scores = {};
    for (const [id, min] of Object.entries(MIN)) {
      const score = categories[id]?.score ?? 0;
      scores[id] = Math.round(score * 100);
      if (id === 'seo' && page.draft) continue;
      if (score < min) {
        const failed = Object.values(audits)
          .filter((a) => a.score !== null && a.score < 1 && categories[id].auditRefs.some((r) => r.id === a.id && r.weight > 0))
          .map((a) => a.id);
        problems.push(`${page.path}: ${id} ${scores[id]} < ${min * 100} (${failed.join(', ') || 'see report'})`);
      }
    }
    if (page.draft && audits['is-crawlable']?.score !== 0) {
      problems.push(`${page.path}: draft page must keep "noindex" until a native speaker approves it`);
    }
    rows.push(`| ${page.path} | ${scores.performance} | ${scores.accessibility} | ${scores['best-practices']} | ${page.draft ? `${scores.seo} (draft, noindex)` : scores.seo} |`);
  }
} finally {
  await chrome.kill();
}

const table = ['| Page | Performance | Accessibility | Best practices | SEO |', '|---|---|---|---|---|', ...rows].join('\n');
console.log(table);
if (process.env.GITHUB_STEP_SUMMARY) appendFileSync(process.env.GITHUB_STEP_SUMMARY, `### Lighthouse (mobile)\n\n${table}\n`);

if (problems.length > 0) {
  console.error('\n' + problems.join('\n'));
  process.exit(1);
}
console.log('\nAll landing pages meet the scores.');
