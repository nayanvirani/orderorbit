import { useState } from 'react';
import { ActionButton, Field, Form, useForm } from '../../components/form.jsx';
import { ago, Page } from '../../components/ui.jsx';
import { route, useRouter } from '../../router.jsx';

const STATUS = { active: ['success', 'Active'], pending: ['info', 'Pending invite'], removed: ['critical', 'Access removed'] };

export default function Users({ users, assignable, roles, matrix, staffAccess }) {
  const { submit } = useRouter();
  const invite = useForm({ email: '', role: assignable.includes('staff') ? 'staff' : assignable[0] });

  return (
    <Page heading="Settings">
      <s-section heading="Staff">
        <s-paragraph>Everyone with access to Growvia in Shopify admin appears here the first time they open the app. Give someone a role in advance by inviting their Shopify staff email.</s-paragraph>
        <div className="oo-scroll">
          <table className="oo-table stack">
            <thead><tr><th>Name</th><th>Role</th><th>Status</th><th>Last active</th><th /></tr></thead>
            <tbody>
              {users.map((u) => (
                <tr key={u.id}>
                  <td>
                    <strong>{u.name}</strong>{u.me && <span className="oo-muted"> (you)</span>}
                    {u.email && u.name !== u.email && <div className="oo-muted oo-small">{u.email}</div>}
                    {u.account_owner && <div className="oo-muted oo-small">Shopify store owner</div>}
                  </td>
                  <td>
                    {u.locked || u.disabled ? roles[u.role] : (
                      <select className="oo-select" value={u.role} aria-label={`Role for ${u.name}`} onChange={(e) => submit(route('app.settings.users.role', { user: u.id }), { role: e.target.value })}>
                        {assignable.map((r) => <option key={r} value={r}>{roles[r]}</option>)}
                      </select>
                    )}
                  </td>
                  <td>{STATUS[u.status] && <s-badge tone={STATUS[u.status][0]}>{STATUS[u.status][1]}</s-badge>}</td>
                  <td className="oo-muted" data-label="Last active">{u.last_active_at ? ago(u.last_active_at) : '—'}</td>
                  <td style={{ textAlign: 'right' }}>
                    {!u.locked && (u.disabled ? (
                      <ActionButton variant="tertiary" url={route('app.settings.users.restore', { user: u.id })}>Restore access</ActionButton>
                    ) : (
                      <ActionButton variant="tertiary" tone="critical" url={route('app.settings.users.remove', { user: u.id })} confirm={u.pending ? 'Cancel this invite?' : `Remove ${u.name}'s access to Growvia?`}>
                        {u.pending ? 'Cancel invite' : 'Remove'}
                      </ActionButton>
                    ))}
                  </td>
                </tr>
              ))}
            </tbody>
          </table>
        </div>
      </s-section>

      {staffAccess && <StaffAccess data={staffAccess} />}

      <s-section heading="Invite staff">
        <Form className="oo-form-row" onSubmit={async () => { const r = await invite.post(route('app.settings.users.invite')); if (r.ok) invite.set('email', ''); }}>
          <Field label="Shopify staff email" className="grow" error={invite.errors.email}>
            <input type="email" required placeholder="name@yourstore.com" {...invite.bind('email')} style={{ minWidth: 260 }} />
          </Field>
          <Field label="Role">
            <select {...invite.bind('role')}>{assignable.map((r) => <option key={r} value={r}>{roles[r]}</option>)}</select>
          </Field>
          <s-button type="submit" variant="primary" loading={invite.processing || undefined}>Invite</s-button>
        </Form>
        <s-paragraph><span className="oo-muted oo-small">They also need access to Growvia in Shopify admin (Settings → Users). When they first open the app, they get this role.</span></s-paragraph>
      </s-section>

      <s-section heading="What each role can do">
        <div className="oo-scroll">
          <table className="oo-table">
            <thead><tr><th>Permission</th>{Object.values(roles).map((label) => <th key={label} style={{ textAlign: 'center' }}>{label}</th>)}</tr></thead>
            <tbody>
              {matrix.map((p) => (
                <tr key={p.label}>
                  <td>{p.label}</td>
                  {Object.keys(roles).map((r) => <td key={r} style={{ textAlign: 'center' }}>{p.roles.includes(r) ? '✓' : <span className="oo-muted">—</span>}</td>)}
                </tr>
              ))}
            </tbody>
          </table>
        </div>
      </s-section>
    </Page>
  );
}

/** Staff access (G28): which parts of Growvia each person opens; Profit by plan seats. */
function StaffAccess({ data }) {
  const { submit } = useRouter();
  const [people, setPeople] = useState(data.people);
  const [error, setError] = useState(null);
  const modules = Object.entries(data.modules).filter(([k]) => k !== 'profit' || data.profit);
  const seeing = people.filter((p) => p.access.profit).length;
  const [, limit] = data.seats;
  const flip = (id, key, value) => setPeople((ps) => ps.map((p) => (p.id === id ? (key === 'weekly_email' ? { ...p, weekly_email: value } : { ...p, access: { ...p.access, [key]: value } }) : p)));
  const save = async () => {
    const r = await submit(route('app.settings.users.access'), {
      access: Object.fromEntries(people.filter((p) => !p.owner).map((p) => [p.id, p.access])),
      weekly: Object.fromEntries(people.map((p) => [p.id, p.weekly_email])),
    });
    setError(r.errors?.access || null);
  };
  return (
    <s-section heading="Staff access">
      <s-paragraph>Choose which parts of Growvia each staff member can open. Cost and profit numbers can stay with the owner. The store owner always has full access.</s-paragraph>
      {data.profit && <s-paragraph><b>{data.plan} plan: {seeing} of {limit === null ? 'unlimited' : limit} {limit === 1 ? 'person' : 'people'} can see profit.</b></s-paragraph>}
      <div className="oo-scroll">
        <table className="oo-table stack">
          <thead><tr><th>Staff member</th>{modules.map(([k, l]) => <th key={k}>{l}</th>)}{data.profit && <th>Weekly email</th>}</tr></thead>
          <tbody>
            {people.map((p) => (
              <tr key={p.id}>
                <td data-label="Staff member">{p.name} <span className="oo-muted">· {p.role}</span></td>
                {modules.map(([k, l]) => (
                  <td key={k} data-label={l}>
                    <input type="checkbox" aria-label={`${p.name}: ${l}`} checked={!!p.access[k]} disabled={p.owner} onChange={(e) => flip(p.id, k, e.target.checked)} />
                  </td>
                ))}
                {data.profit && <td data-label="Weekly email"><input type="checkbox" aria-label={`${p.name}: weekly email`} checked={!!p.weekly_email} disabled={!p.access.profit} onChange={(e) => flip(p.id, 'weekly_email', e.target.checked)} /></td>}
              </tr>
            ))}
          </tbody>
        </table>
      </div>
      {error && <s-banner tone="warning">{error}</s-banner>}
      <s-paragraph><span className="oo-muted">People who can see profit also get the Monday summary email; each person can turn it off here or from the email.</span></s-paragraph>
      <s-button variant="primary" onClick={save}>Save access</s-button>
    </s-section>
  );
}

