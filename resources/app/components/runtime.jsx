// The storefront runtime (extensions/orderorbit-theme/assets), loaded once for live previews so
// they render with exactly the code shoppers get, plus the checkout block likenesses.
import { useEffect, useRef, useState } from 'react';

const loading = {};
const assets = () => window.OO_ASSETS || { runtime: '/storefront/orderorbit.js', runtimeCss: '/storefront/orderorbit.css', checkoutPreview: '/js/checkout-preview.js' };

function load(src) {
  if (loading[src]) return loading[src];
  loading[src] = new Promise((resolve, reject) => {
    if (src.split('?')[0].endsWith('.css')) {
      document.head.appendChild(Object.assign(document.createElement('link'), { rel: 'stylesheet', href: src, onload: resolve, onerror: resolve }));
    } else {
      document.head.appendChild(Object.assign(document.createElement('script'), { src, onload: resolve, onerror: reject }));
    }
  });
  return loading[src];
}

/** Loads the runtime; { checkout: true } also loads the checkout, Thank You and Order Status previews. */
export function loadRuntime({ checkout = false } = {}) {
  const a = assets();
  return Promise.all([load(a.runtimeCss), load(a.runtime)]).then(() => (checkout ? load(a.checkoutPreview) : null));
}

/** true once window.OrderOrbit (and the checkout previews, if asked) are ready. */
export function useRuntime(options = {}) {
  const [ready, setReady] = useState(false);
  useEffect(() => {
    let alive = true;
    loadRuntime(options).then(() => alive && setReady(true), () => alive && setReady(true));
    return () => { alive = false; };
    // eslint-disable-next-line react-hooks/exhaustive-deps
  }, []);
  return ready;
}

/** Draws an experience into an element with the storefront runtime (again only when it changes). */
export function Preview({ experience, context, ready, className = 'oo-preview' }) {
  const ref = useRef(null);
  const key = JSON.stringify([experience, context]);
  useEffect(() => {
    if (ref.current && ready && window.OrderOrbit && experience) {
      window.OrderOrbit.render(ref.current, experience, { preview: true, ...context });
    }
    // eslint-disable-next-line react-hooks/exhaustive-deps
  }, [ready, key]);
  return <div className={className} ref={ref} />;
}
