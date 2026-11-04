<?php

namespace App\Support;

use App\Models\Store;
use App\Models\StoreUser;

/**
 * Staff access (G28): which parts of Growvia each staff member can open. Owners always see
 * everything. Others see offers, analytics and insights unless an owner turns them off, and see
 * Profit (costs and profit) only when an owner turns it on, up to the plan's number of people.
 */
class StaffAccess
{
    /** module => [label, route names it covers, default for admins and staff] */
    public const MODULES = [
        'offers' => ['Offers and widgets', ['app.cro.*', 'app.bundles.*', 'app.gifts.*', 'app.features.*'], true],
        'analytics' => ['Analytics', ['app.analytics', 'app.analytics.*'], true],
        'profit' => ['Profit and costs', ['app.profit.*'], false],
        'protect' => ['Protect', ['app.protect.*'], true],
        'insights' => ['Insights', ['app.insights.*'], true],
    ];

    public static function can(?StoreUser $user, string $module): bool
    {
        if ($user === null) {
            return false;
        }
        if ($user->role === 'owner') {
            return true;
        }
        $access = (array) ($user->access ?? []);

        return array_key_exists($module, $access) ? (bool) $access[$module] : (self::MODULES[$module][2] ?? true);
    }

    /** The module a route belongs to, if any. */
    public static function moduleFor(?string $route): ?string
    {
        foreach (self::MODULES as $key => [, $patterns]) {
            foreach ($patterns as $p) {
                if ($route !== null && \Illuminate\Support\Str::is($p, $route)) {
                    return $key;
                }
            }
        }

        return null;
    }

    /** People who can see profit now, and how many the plan allows (null = unlimited). */
    public static function profitSeats(Store $store): array
    {
        $users = $store->users()->whereNull('disabled_at')->get();

        return [$users->filter(fn ($u) => self::can($u, 'profit'))->count(), $store->planLimit('profit_viewers')];
    }

    /** The store owner's first name, for "ask your store owner, Maya". */
    public static function ownerName(Store $store): ?string
    {
        $owner = $store->users()->where('role', 'owner')->whereNull('disabled_at')->orderByDesc('account_owner')->first();

        return $owner ? (trim((string) $owner->first_name) ?: $owner->displayName()) : null;
    }
}
