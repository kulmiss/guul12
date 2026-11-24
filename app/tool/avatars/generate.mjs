// Makes the built-in avatars (bundled in the app, nothing is loaded from the internet).
// Styles are CC0 1.0 (DiceBear): no credit needed. Run: npm ci && npm run generate
import { mkdirSync, rmSync, writeFileSync } from 'node:fs';
import { createAvatar } from '@dicebear/core';
import { loreleiNeutral, notionistsNeutral, thumbs } from '@dicebear/collection';

// Must match MeController::AVATAR_STYLES on the backend and lib/ui/avatars.dart in the app.
const styles = {
  'notionists-neutral': notionistsNeutral,
  'lorelei-neutral': loreleiNeutral,
  thumbs,
};
const seeds = Array.from({ length: 12 }, (_, i) => `a${String(i + 1).padStart(2, '0')}`);
const out = new URL('../../assets/avatars/', import.meta.url);

rmSync(out, { recursive: true, force: true });
for (const [name, style] of Object.entries(styles)) {
  const dir = new URL(`${name}/`, out);
  mkdirSync(dir, { recursive: true });
  for (const seed of seeds) {
    const meta = style.meta ?? {};
    if (meta.license?.name !== 'CC0 1.0') throw new Error(`${name} is not CC0`);
    const svg = createAvatar(style, { seed, size: 96, backgroundColor: ['transparent'] }).toString();
    writeFileSync(new URL(`${seed}.svg`, dir), `${svg}\n`);
  }
}
console.log(`${Object.keys(styles).length * seeds.length} avatars written`);
