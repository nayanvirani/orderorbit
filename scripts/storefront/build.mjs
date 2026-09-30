// Builds the storefront runtime into extensions/orderorbit-theme/assets.
// Shopify flags app-block JavaScript over 10 KB, so the core stays small and each
// experience type is its own file, loaded only on pages that show it.
import { readFileSync, writeFileSync, readdirSync, rmSync } from 'node:fs';
import { dirname, join } from 'node:path';
import { fileURLToPath } from 'node:url';
import { minify } from 'terser';

const root = join(dirname(fileURLToPath(import.meta.url)), '../..');
const src = join(root, 'resources/storefront');
const out = join(root, 'extensions/orderorbit-theme/assets');
const LIMIT = 10_000;

// One source file can serve several types. Shared helpers (commerce, timer) are
// their own files; the core loads them before the types that need them.
const outputs = {
  'core.js': ['orderorbit.js'],
  'commerce.js': ['oo-commerce.js'],
  'timer.js': ['oo-timer.js'],
  'thresholds.js': ['oo-thresholds.js'],
  'types/upsells.js': ['oo-product-upsells.js', 'oo-cart-upsells.js'],
};
for (const file of readdirSync(join(src, 'types'))) {
  outputs[`types/${file}`] ??= [`oo-${file}`];
}

for (const file of readdirSync(out)) {
  if (file.startsWith('oo-') && file.endsWith('.js')) rmSync(join(out, file));
}

let failed = false;
for (const [input, targets] of Object.entries(outputs)) {
  const result = await minify(readFileSync(join(src, input), 'utf8'), {
    compress: { passes: 2 },
    mangle: true,
    format: { comments: /^!/ },
  });
  for (const target of targets) {
    writeFileSync(join(out, target), result.code + '\n');
    const size = Buffer.byteLength(result.code);
    const flag = size > LIMIT ? '  ✗ over 10 KB' : '';
    if (flag) failed = true;
    console.log(`${target.padEnd(26)} ${String(size).padStart(6)} B${flag}`);
  }
}
if (failed) process.exit(1);
