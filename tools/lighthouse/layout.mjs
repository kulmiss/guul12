// Checks every landing page at phone, tablet and computer widths: the page never scrolls sideways,
// and no part shows a horizontal scrollbar. (On phones the app pictures may be swiped sideways,
// but with the scrollbar hidden.)
//
//   php artisan serve --port=8000        (in backend/)
//   npm ci && npm run layout             (here; BASE_URL defaults to http://127.0.0.1:8000)

import * as chromeLauncher from 'chrome-launcher';
import puppeteer from 'puppeteer-core';

const BASE = (process.env.BASE_URL ?? 'http://127.0.0.1:8000').replace(/\/$/, '');
const PAGES = ['/', '/app', '/privacy', '/terms', '/delete-account', '/so', '/am', '/so/app', '/am/app'];
const WIDTHS = [320, 360, 390, 768, 1024, 1280, 1920];

const chrome = await chromeLauncher.launch({ chromeFlags: ['--headless=new', '--no-sandbox'] });
const problems = [];
try {
  const browser = await puppeteer.connect({ browserURL: `http://127.0.0.1:${chrome.port}` });
  const tab = await browser.newPage();
  for (const width of WIDTHS) {
    await tab.setViewport({ width, height: 800 });
    for (const path of PAGES) {
      const response = await tab.goto(BASE + path, { waitUntil: 'load' });
      if (!response?.ok()) {
        problems.push(`${path} at ${width}px: HTTP ${response?.status()}`);
        continue;
      }
      const found = await tab.evaluate(() => {
        const out = [];
        const doc = document.documentElement;
        if (doc.scrollWidth > doc.clientWidth) out.push(`page is ${doc.scrollWidth - doc.clientWidth}px too wide`);
        for (const el of document.querySelectorAll('*')) {
          const cs = getComputedStyle(el);
          if (!['auto', 'scroll'].includes(cs.overflowX) || el.scrollWidth <= el.clientWidth) continue;
          // A visible scrollbar takes room: offsetHeight minus borders is bigger than clientHeight.
          const bar = el.offsetHeight - parseFloat(cs.borderTopWidth) - parseFloat(cs.borderBottomWidth) - el.clientHeight;
          if (bar > 0) out.push(`<${el.tagName.toLowerCase()} class="${el.className}"> shows a horizontal scrollbar`);
        }
        return out;
      });
      for (const f of found) problems.push(`${path} at ${width}px: ${f}`);
    }
  }
  await browser.disconnect();
} finally {
  await chrome.kill();
}

if (problems.length > 0) {
  console.error(problems.join('\n'));
  process.exit(1);
}
console.log(`No sideways scrolling and no horizontal scrollbars on ${PAGES.length} pages × ${WIDTHS.length} widths.`);
