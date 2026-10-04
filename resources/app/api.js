// JSON API client. App Bridge adds the session token to same-origin fetches automatically.
const boot = window.OO_BOOT || {};

export async function api(path, { method = 'GET', body } = {}) {
  const url = new URL('/app/api/' + path.replace(/^\//, ''), window.location.origin);
  if (boot.shop) url.searchParams.set('shop', boot.shop);
  const res = await fetch(url, {
    method,
    headers: { Accept: 'application/json', ...(body ? { 'Content-Type': 'application/json' } : {}) },
    body: body ? JSON.stringify(body) : undefined,
  });
  const data = await res.json().catch(() => ({}));
  if (!res.ok) {
    const error = new Error(data.message || 'Something went wrong. Try again.');
    error.status = res.status;
    error.data = data;
    throw error;
  }
  return data;
}

/** A link to a page that isn't part of the React app yet (full page load). */
export function legacy(path, params = {}) {
  const url = new URL(path, window.location.origin);
  if (boot.shop) url.searchParams.set('shop', boot.shop);
  Object.entries(params).forEach(([k, v]) => v != null && url.searchParams.set(k, v));
  return url.pathname + url.search;
}

export { boot };
