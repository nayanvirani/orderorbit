<?php

namespace App\Support;

use App\Models\Store;

/**
 * Optional Shopify access (approved by the merchant in Shopify's own window, removable any time):
 * what each one is for and what stops working without it. Growvia's offers, bundles and
 * automations never depend on any of these.
 */
class OptionalAccess
{
    /** scope => [name, what it's for, what stops without it] */
    public const SCOPES = [
        'read_inventory' => ['Product costs and stock', 'Shopify\'s "Cost per item" and stock levels, for profit per item and days of stock.', 'Profit and Protect stop until it\'s approved again.'],
        'read_all_orders' => ['Orders older than 60 days', 'Order history for profit trends, breakdowns and reports.', 'Profit and Protect stop until it\'s approved again.'],
        'read_locations' => ['Stock per location', 'Inventory split by location.', 'Inventory shows the total across locations only.'],
        'read_locales' => ['Store languages', 'Your store\'s languages, so offer text can be translated for each one.', 'Offers show in the default language only.'],
        'read_reports' => ['Store reports', 'Store sessions and conversion rate from Shopify\'s reports, so insights cover the whole store.', 'Sales insights stop showing sessions and conversion rate.'],
    ];

    public static function granted(Store $store, string $scope): bool
    {
        $granted = array_filter(explode(',', (string) $store->scopes));

        return in_array($scope, $granted, true) || in_array(str_replace('read_', 'write_', $scope), $granted, true);
    }

    /** For the settings page: each optional scope with its state. */
    public static function list(Store $store): array
    {
        return collect(self::SCOPES)->map(fn ($s, $scope) => ['scope' => $scope, 'name' => $s[0], 'why' => $s[1], 'without' => $s[2], 'on' => self::granted($store, $scope)])->values()->all();
    }
}
