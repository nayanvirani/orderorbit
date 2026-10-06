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
/**
 * Scales a drawn preview (CSS zoom, so it lays out wider and shrinks) towards `max` pixels high,
 * between `start` and `min`. Templates taller than that at `min` keep that size, and their card
 * grows instead: a template is never cut off.
 */
function fitPreview(el, max, start = 0.85, min = 0.55) {
  if (!el || !max || !el.isConnected) return;
  let zoom = Number(el.style.zoom) || start;
  for (let i = 0; i < 6; i++) {
    el.style.zoom = zoom;
    const height = el.getBoundingClientRect().height;
    if (!height) return;
    const next = Math.max(min, Math.min(start, zoom * (max / height) * 0.98));
    // Stop when it fits without wasting room, or can't change any more.
    if ((height <= max + 1 && next - zoom < 0.02) || Math.abs(next - zoom) < 0.005) return;
    zoom = next;
  }
}

export function Preview({ experience, context, ready, className = 'oo-preview', empty, fit }) {
  const ref = useRef(null);
  const key = JSON.stringify([experience, context]);
  useEffect(() => {
    if (ref.current && ready && window.OrderOrbit && experience) {
      const el = ref.current;
      Promise.resolve(window.OrderOrbit.render(el, experience, { preview: true, ...context })).then((shown) => {
        if (shown === false && empty) {
          el.hidden = false;
          el.innerHTML = `<p class="b-muted b-empty-preview">${empty}</p>`;
        }
        if (fit) fitPreview(el, fit);
      });
    }
    // eslint-disable-next-line react-hooks/exhaustive-deps
  }, [ready, key]);
  // Fits again whenever the drawn template changes size (fonts, images, the widget's own updates).
  useEffect(() => {
    const el = ref.current;
    if (!fit || !el || !window.ResizeObserver) return undefined;
    let frame = 0;
    const observer = new ResizeObserver(() => {
      cancelAnimationFrame(frame);
      frame = requestAnimationFrame(() => fitPreview(el, fit));
    });
    observer.observe(el);
    return () => { cancelAnimationFrame(frame); observer.disconnect(); };
  }, [fit]);
  return <div className={className} ref={ref} />;
}
