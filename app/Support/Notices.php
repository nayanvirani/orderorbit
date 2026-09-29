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
        'deleted' => ['The item was deleted successfully.', false],
        'invited' => ['Invite saved. They get this role when they first open OrderOrbit.', false],
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
        'bulk_done' => ['Changes applied.', false],
        'publish_unavailable' => ['This experience type can be built and previewed now. Publishing opens in an upcoming release.', true],
        'invalid' => ['Fix the highlighted fields before publishing.', true],
        'plan_updated' => ['Your plan is active. Thanks for choosing OrderOrbit!', false],
        'plan_pending' => ['We\'re waiting for Shopify to confirm your plan. This page updates once it does.', false],
        'network' => ['We couldn\'t complete that request. Please try again.', true],
        'permission' => ['You don\'t have permission to perform this action.', true],
        'shopify' => ['Your Shopify connection needs attention. Open Settings → Store to review it.', true],
        'invalid_color' => ['Colours must be hex values like #5b4bff.', true],
        'invalid_email' => ['Enter a valid email address.', true],
        'already_member' => ['That person already has access to OrderOrbit.', true],
        'last_owner' => ['A store needs at least one owner.', true],
        'self' => ['You can\'t change your own role or access.', true],
        'plan_limit' => ['You\'ve reached your plan\'s limit. Upgrade to add more.', true],
        'unexpected' => ['Something went wrong. Please try again. If it continues, contact support with the request ID.', true],
    ];

    public static function get(?string $key): ?array
    {
        return $key !== null ? (self::MESSAGES[$key] ?? null) : null;
    }
}
