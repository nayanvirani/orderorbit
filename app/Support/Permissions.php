<?php

namespace App\Support;

/**
 * Role-based permissions for merchant staff (section 2: Owner / Admin / Staff).
 */
class Permissions
{
    public const ROLES = [
        'owner' => 'Owner',
        'admin' => 'Admin',
        'staff' => 'Staff',
    ];

    public const MATRIX = [
        'view_dashboard' => ['label' => 'View dashboard and reports', 'roles' => ['owner', 'admin', 'staff']],
        'manage_experiences' => ['label' => 'Create and publish experiences, workflows and tests', 'roles' => ['owner', 'admin', 'staff']],
        'manage_settings' => ['label' => 'Change store settings', 'roles' => ['owner', 'admin']],
        'manage_users' => ['label' => 'Invite staff and change roles', 'roles' => ['owner', 'admin']],
        'view_activity' => ['label' => 'View the activity log', 'roles' => ['owner', 'admin']],
        'manage_billing' => ['label' => 'Change plan and billing', 'roles' => ['owner']],
    ];

    public static function allows(?string $role, string $permission): bool
    {
        return $role !== null && in_array($role, self::MATRIX[$permission]['roles'] ?? [], true);
    }

    /**
     * Roles a user with $role may assign to others.
     */
    public static function assignableBy(string $role): array
    {
        return match ($role) {
            'owner' => ['owner', 'admin', 'staff'],
            'admin' => ['admin', 'staff'],
            default => [],
        };
    }
}
