// The storefront runtime (extensions/orderorbit-theme/assets), loaded once for live previews so
// they render with exactly the code shoppers get.
import { useEffect, useState } from 'react';

const loading = {};

function load(file) {
  if (loading[file]) return loading[file];
  loading[file] = new Promise((resolve, reject) => {
    const src = `/storefront/${file}`;
    if (file.endsWith('.css')) {
      const link = Object.assign(document.createElement('link'), { rel: 'stylesheet', href: src, onload: resolve, onerror: resolve });
      document.head.appendChild(link);
    } else {
      const script = Object.assign(document.createElement('script'), { src, onload: resolve, onerror: reject });
      document.head.appendChild(script);
    }
  });
  return loading[file];
}

/** Loads the runtime (and optional extra files like oo-experiments.js). */
export function loadRuntime(extra = []) {
  return Promise.all([load('orderorbit.css'), load('orderorbit.js')]).then(() => Promise.all(extra.map(load)));
}

/** true once window.OrderOrbit is ready. */
export function useRuntime(extra = []) {
  const [ready, setReady] = useState(!!window.OrderOrbit);
  useEffect(() => {
    let alive = true;
    loadRuntime(extra).then(() => alive && setReady(true));
    return () => { alive = false; };
    // eslint-disable-next-line react-hooks/exhaustive-deps
  }, []);
  return ready;
}
