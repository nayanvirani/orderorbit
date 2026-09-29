<?php

namespace App\Services\Shopify;

use App\Models\Store;
use Throwable;

/**
 * Pulls shop details, the main theme and granted scopes, and works out which
 * Shopify surfaces this store can use (section 26: only expose supported targets).
 */
class StoreSync
{
    public function __construct(private readonly AdminApi $api) {}

    public function sync(Store $store): Store
    {
        $data = $this->api->graphql($store, <<<'GQL'
            {
              shop {
                name email currencyCode ianaTimezone
                enabledPresentmentCurrencies
                plan { displayName partnerDevelopment shopifyPlus }
              }
              themes(first: 1, roles: [MAIN]) { nodes { id name } }
              currentAppInstallation { accessScopes { handle } }
            }
            GQL);

        $shop = $data['shop'];
        $theme = $data['themes']['nodes'][0] ?? null;
        $plan = $shop['plan'] ?? [];

        $capabilities = [
            'plus' => (bool) ($plan['shopifyPlus'] ?? false),
            'development_store' => (bool) ($plan['partnerDevelopment'] ?? false),
            'presentment_currencies' => $shop['enabledPresentmentCurrencies'] ?? [],
            'online_store_2' => $theme ? $this->isOnlineStore2($store, $theme['id']) : null,
            'new_customer_accounts' => $this->usesNewCustomerAccounts($store),
        ];
        // In-checkout blocks need Shopify Plus; development stores can preview them.
        $capabilities['checkout_blocks'] = $capabilities['plus'] || $capabilities['development_store'];
        $capabilities['thank_you_blocks'] = true;

        $store->forceFill([
            'name' => $shop['name'],
            'email' => $shop['email'],
            'currency' => $shop['currencyCode'],
            'timezone' => $shop['ianaTimezone'],
            'shopify_plan' => $plan['displayName'] ?? null,
            'theme_name' => $theme['name'] ?? null,
            'scopes' => implode(',', array_column($data['currentAppInstallation']['accessScopes'] ?? [], 'handle')) ?: $store->scopes,
            'capabilities' => $capabilities,
            'capabilities_checked_at' => now(),
        ])->save();

        return $store;
    }

    /**
     * App blocks need an Online Store 2.0 theme, which has JSON templates.
     */
    private function isOnlineStore2(Store $store, string $themeId): ?bool
    {
        try {
            $data = $this->api->graphql($store, <<<'GQL'
                query ($id: ID!) { theme(id: $id) { files(filenames: ["templates/product.json"], first: 1) { nodes { filename } } } }
                GQL, ['id' => $themeId]);

            return ! empty($data['theme']['files']['nodes']);
        } catch (Throwable $e) {
            report($e);

            return null;
        }
    }

    private function usesNewCustomerAccounts(Store $store): ?bool
    {
        try {
            $data = $this->api->graphql($store, '{ shop { customerAccountsV2 { customerAccountsVersion } } }');

            return ($data['shop']['customerAccountsV2']['customerAccountsVersion'] ?? null) === 'NEW_CUSTOMER_ACCOUNTS';
        } catch (Throwable $e) {
            report($e);

            return null;
        }
    }
}
