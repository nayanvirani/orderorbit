// AI offer text (G20): three options for an offer's headline, subheadline and button. Choosing one
// fills the fields; nothing is saved until the merchant saves the offer.
import { useState } from 'react';
import { api } from '../api.js';

export default function AiCopy({ context, fields, onPick }) {
  const [options, setOptions] = useState(null);
  const [busy, setBusy] = useState(false);
  const [error, setError] = useState(null);
  const ask = async () => {
    setBusy(true);
    setError(null);
    try {
      setOptions((await api('ai/copy', { method: 'POST', body: context })).options);
    } catch (e) {
      setError(e.message);
    } finally {
      setBusy(false);
    }
  };
  return (
    <div className="ai-copy">
      <div className="ai-copy-head">
        <span><b>Suggest text</b> <small className="oo-muted">Three options from AI. You choose and edit; nothing saves until you save.</small></span>
        <s-button loading={busy ? '' : undefined} onClick={ask}>{options ? 'Try again' : 'Suggest'}</s-button>
      </div>
      {error && <p className="b-error">{error}</p>}
      {options && options.map((o, i) => (
        <button type="button" className="ai-copy-option" key={i} onClick={() => onPick(o)}>
          {fields.headline && <b>{o.headline}</b>}
          {fields.subheadline && o.subheadline && <span>{o.subheadline}</span>}
          {fields.cta && <em>{o.cta}</em>}
        </button>
      ))}
    </div>
  );
}
