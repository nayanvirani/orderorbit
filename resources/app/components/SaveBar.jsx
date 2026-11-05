// Shopify's contextual save bar (App Bridge ui-save-bar) for editors with unsaved changes: it
// shows Save and Discard at the top of the admin and keeps merchants from leaving without
// choosing one (Built for Shopify 4.1.5).
import { useEffect } from 'react';

const ID = 'oo-save-bar';

export default function SaveBar({ dirty, onSave, onDiscard, saving = false, saveLabel = 'Save' }) {
  useEffect(() => {
    const bar = window.shopify?.saveBar;
    if (!bar) return;
    (dirty ? bar.show(ID) : bar.hide(ID))?.catch?.(() => {});
  }, [dirty]);
  // Leaving the page (in the app) takes the bar away with it.
  useEffect(() => () => { window.shopify?.saveBar?.hide(ID)?.catch?.(() => {}); }, []);

  return (
    <ui-save-bar id={ID} discardConfirmation="">
      <button variant="primary" loading={saving ? '' : undefined} onClick={onSave}>{saveLabel}</button>
      <button onClick={onDiscard}>Discard</button>
    </ui-save-bar>
  );
}
