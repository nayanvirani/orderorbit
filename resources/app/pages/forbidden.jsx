import { Page } from '../components/ui.jsx';

/** Shown when the signed-in staff member's role doesn't allow a page. */
export default function Forbidden({ message, removed }) {
  return (
    <Page heading={removed ? 'Access removed' : 'Permission needed'}>
      <s-section>
        <s-paragraph>{message || 'Your role doesn\'t include this page. Ask a store owner or admin to change your role in Settings → Users & roles.'}</s-paragraph>
      </s-section>
    </Page>
  );
}
