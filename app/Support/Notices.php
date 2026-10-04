<?php

namespace App\Support;

/**
 * Toast copy for the embedded app (section 2: global success and error copy).
 * Redirects carry ?notice=<key>; the layout shows the toast.
 */
class Notices
{
    public const MESSAGES = [
        'saved' => ['Changes saved successfully.', false],
        'ticket_created' => ['Ticket sent. We usually reply within one business day.', false],
        'reply_sent' => ['Reply sent.', false],
        'analytics_deleted' => ['All analytics data for your store was deleted.', false],
        'segment_archived' => ['Segment archived.', false],
        'segment_restored' => ['Segment restored.', false],
        'experiment_launched' => ['Test launched. Visitors are now split between the variants.', false],
        'experiment_pause' => ['Test paused. Everyone sees the control until you resume.', false],
        'experiment_resume' => ['Test resumed.', false],
        'experiment_stop' => ['Test stopped. The experience stays as published.', false],
        'experiment_apply' => ['Variant applied. The experience now shows it to everyone.', false],
        'retried' => ['Run retried. Check the log for the result.', false],
        'retry_failed' => ['The step failed again. The log shows why; it will retry on its own.', true],
        'analytics_connected' => ['Analytics is connected. New visits and orders will appear here.', false],
        'deleted' => ['The item was deleted successfully.', false],
        'invited' => ['Invite saved. They get this role when they first open OrderOrbit Space.', false],
        'role_changed' => ['Role updated.', false],
        'access_removed' => ['Access removed.', false],
        'access_restored' => ['Access restored.', false],
        'rechecked' => ['Store capabilities re-checked.', false],
        'reconnected' => ['Shopify connection refreshed.', false],
        'created' => ['Draft created.', false],
        'published' => ['Your experience is live. Add or check its block in the Theme Editor.', false],
        'paused' => ['Experience paused.', false],
        'resumed' => ['Experience is live again.', false],
        'archived' => ['Experience archived.', false],
        'unarchived' => ['Experience restored from the archive.', false],
        'duplicated' => ['Experience duplicated.', false],
        'discarded' => ['Unsaved changes discarded.', false],
        'version_restored' => ['Version loaded into your draft. Publish to make it live.', false],
        'placement_checked' => ['Theme placement checked.', false],
        'orders_imported' => ['Recent orders imported. Pops show them on your store within a minute.', false],
        'orders_blocked' => ['Shopify hasn\'t approved OrderOrbit Space to read orders yet. See the steps in the Sales pop panel.', true],
        'orders_scope' => ['OrderOrbit Space needs permission to read orders. Reload the app and approve the updated permissions, then try again.', true],
        'bulk_done' => ['Changes applied.', false],
        'publish_unavailable' => ['This experience has no published version yet. Publish it from the builder first.', true],
        'invalid' => ['Fix the highlighted fields before publishing.', true],
        'plan_updated' => ['Your plan is active. Thanks for choosing OrderOrbit Space!', false],
        'plan_pending' => ['We\'re waiting for Shopify to confirm your plan. This page updates once it does.', false],
        'network' => ['We couldn\'t complete that request. Please try again.', true],
        'permission' => ['You don\'t have permission to perform this action.', true],
        'shopify' => ['Your Shopify connection needs attention. Open Settings → Store to review it.', true],
        'invalid_color' => ['Colours must be hex values like #5b4bff.', true],
        'invalid_email' => ['Enter a valid email address.', true],
        'already_member' => ['That person already has access to OrderOrbit Space.', true],
        'last_owner' => ['A store needs at least one owner.', true],
        'self' => ['You can\'t change your own role or access.', true],
        'upgrade_required' => ['Your store has passed its plan\'s sales limit. Upgrade to turn everything back on.', true],
        'plan_limit' => ['Your plan\'s limit for this feature is reached. Pause the one that\'s live or upgrade for unlimited.', true],
        'unexpected' => ['Something went wrong. Please try again. If it continues, contact support with the request ID.', true],
    ];

    public static function get(?string $key): ?array
    {
        return $key !== null ? (self::MESSAGES[$key] ?? null) : null;
    }
}
