/*
 * "Buy again" in each order's menu, shown while a published Reorder block has the menu action on.
 * Pressing it opens the reorder dialog (ReorderAction.jsx).
 */
import '@shopify/ui-extensions/preact';
import { render } from 'preact';
import { useEffect, useState } from 'preact/hooks';
import { loadPayload } from './data.js';

export default async () => {
  render(<Menu />, document.body);
};

function Menu() {
  const [exp, setExp] = useState(null);
  useEffect(() => {
    loadPayload().then((payload) => {
      const found = ((payload && payload.experiences) || []).filter((e) => e.type === 'account-reorder' && (e.content || {}).menu_action !== false)
        .sort((a, b) => (b.priority || 0) - (a.priority || 0))[0];
      setExp(found || null);
    });
  }, []);
  if (!exp) return null;
  return <s-button>{(exp.content || {}).button_text || 'Buy again'}</s-button>;
}
