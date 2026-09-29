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
        'network' => ['We couldn\'t complete that request. Please try again.', true],
        'permission' => ['You don\'t have permission to perform this action.', true],
        'shopify' => ['Your Shopify connection needs attention. Open Settings → Store to review it.', true],
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
