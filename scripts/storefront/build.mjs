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

// Types that add to the cart get the shared commerce helpers prepended (they
// define themselves once, whichever file loads first).
const COMMERCE = ['bundles.js', 'quantity-breaks.js', 'bogo.js', 'upsells.js', 'free-gifts.js', 'sticky-atc.js'];

// One source file can serve several types.
const outputs = {
  'core.js': ['orderorbit.js'],
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
  const prefix = COMMERCE.includes(input.replace('types/', '')) ? readFileSync(join(src, 'commerce.js'), 'utf8') + '\n' : '';
  const result = await minify(prefix + readFileSync(join(src, input), 'utf8'), {
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
