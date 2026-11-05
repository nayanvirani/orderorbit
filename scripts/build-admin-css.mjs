// Combines the admin's stylesheets into public/css/admin-app.css, so the embedded app loads one
// stylesheet instead of nine before it can draw (Shopify's admin performance: LCP). The Blade
// pages keep using the separate files. Run by `npm run build:app`.
import { readFileSync, writeFileSync } from 'node:fs';
import { dirname, join } from 'node:path';
import { fileURLToPath } from 'node:url';

const css = join(dirname(fileURLToPath(import.meta.url)), '..', 'public', 'css');
const files = ['app-brand.css', 'app-base.css', 'analytics.css', 'audiences.css', 'automation.css', 'builder.css', 'bundles.css', 'checkout-preview.css', 'experiments.css'];
const out = files.map((f) => `/* ${f} */\n${readFileSync(join(css, f), 'utf8')}`).join('\n');
writeFileSync(join(css, 'admin-app.css'), `/* Built by scripts/build-admin-css.mjs from ${files.join(', ')}. Don't edit: edit those files. */\n${out}`);
console.log(`admin-app.css: ${Math.round(out.length / 1024)} KB from ${files.length} files`);
