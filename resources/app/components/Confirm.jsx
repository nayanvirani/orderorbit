// "Are you sure?" in Shopify's own modal (App Bridge ui-modal) instead of the browser's confirm box
// (Built for Shopify 4.1.6). Call confirmDialog(message) anywhere; ConfirmHost renders the modal once.
import { useEffect, useRef, useState } from 'react';

const ID = 'oo-confirm';
let openDialog = null;

/** Resolves true when the merchant confirms. Outside Shopify's admin it uses the browser's box. */
export function confirmDialog(message, { title = 'Please confirm', action = 'Confirm', destructive = true } = {}) {
  if (!openDialog || !window.shopify?.modal) return Promise.resolve(window.confirm(message));
  return new Promise((resolve) => openDialog({ message, title, action, destructive, resolve }));
}

export default function ConfirmHost() {
  const [dialog, setDialog] = useState(null);
  const modal = useRef(null);

  useEffect(() => {
    openDialog = setDialog;
    return () => { openDialog = null; };
  }, []);

  // Show the modal once its text is in place; closing it any other way counts as Cancel.
  useEffect(() => {
    if (!dialog) return undefined;
    const el = modal.current;
    const onHide = () => close(false);
    el?.addEventListener('hide', onHide);
    window.shopify.modal.show(ID);
    return () => el?.removeEventListener('hide', onHide);
  }, [dialog]);

  const close = (answer) => {
    setDialog((d) => {
      if (d) d.resolve(answer);
      return null;
    });
    window.shopify?.modal?.hide(ID);
  };

  return (
    <ui-modal id={ID} ref={modal}>
      <p style={{ margin: 0, padding: 16 }}>{dialog?.message}</p>
      <ui-title-bar title={dialog?.title || 'Please confirm'}>
        <button variant="primary" tone={dialog?.destructive ? 'critical' : undefined} onClick={() => close(true)}>{dialog?.action || 'Confirm'}</button>
        <button onClick={() => close(false)}>Cancel</button>
      </ui-title-bar>
    </ui-modal>
  );
}
