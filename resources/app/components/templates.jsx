// Template cards for every template picker: the whole template at one consistent size, its name
// and details, and one action. Picking a template is one click.
import { useState } from 'react';
import { Preview } from './runtime.jsx';

export function TemplateGrid({ children }) {
  return <div className="b-templates">{children}</div>;
}

/**
 * onUse: called when the card or its button is clicked (async: the card shows it's working).
 * href: a link instead (e.g. to the next step). Without either, the card only shows the template.
 */
export function TemplateCard({ preview, context, ready, name, meta, description, action = 'Use this template', onUse, href, tall }) {
  const [busy, setBusy] = useState(false);
  const use = async () => {
    if (!onUse || busy) return;
    setBusy(true);
    try { await onUse(); } finally { setBusy(false); }
  };
  const Tag = href ? 'a' : 'div';
  const clickable = !!(onUse || href);
  return (
    <Tag className={`b-template${clickable ? ' is-clickable' : ''}${busy ? ' is-busy' : ''}`} href={href}
      role={onUse ? 'button' : undefined} tabIndex={onUse ? 0 : undefined} aria-busy={busy || undefined}
      onClick={onUse ? use : undefined} onKeyDown={onUse ? (e) => { if (e.key === 'Enter' || e.key === ' ') { e.preventDefault(); use(); } } : undefined}>
      <div className={`tpl-stage${tall ? ' tpl-tall' : ''}`}>
        <Preview ready={ready} experience={preview} context={context} className="oo-preview" />
      </div>
      <div className="tpl-body">
        <span className="b-template-name">{name}</span>
        {meta}
        {description && <p className="bx-muted">{description}</p>}
        {clickable && <span className="tpl-cta">{busy ? 'Creating…' : action}</span>}
      </div>
    </Tag>
  );
}
