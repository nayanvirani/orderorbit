import { Page } from '../components/ui.jsx';
import { appUrl, route } from '../router.jsx';

/** Staff access (G28): shown to staff members who weren't given this part of Growvia. */
export default function Private({ module, owner }) {
  return (
    <Page heading={module}>
      <s-section>
        <s-heading>{module} is private for this store</s-heading>
        <s-paragraph>Ask your store owner{owner ? `, ${owner},` : ''} to give you access in Growvia, under Settings › Team and permissions.</s-paragraph>
        <s-button href={appUrl(route('app.dashboard'))}>Back to Home</s-button>
      </s-section>
    </Page>
  );
}
