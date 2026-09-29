<?php

namespace App\Services\Shopify;

use App\Models\AuditLog;
use App\Models\Store;
use App\Models\Subscription;
use Illuminate\Support\Carbon;
use InvalidArgumentException;
use RuntimeException;

/**
 * Shopify Billing API: recurring app subscriptions for Starter / Growth / Scale.
 */
class Billing
{
    public function __construct(private readonly AdminApi $api) {}

    /**
     * Creates a subscription and returns the merchant confirmation URL.
     */
    public function createSubscription(Store $store, string $planKey): string
    {
        $plan = config("shopify.billing.plans.{$planKey}");

        if ($plan === null) {
            throw new InvalidArgumentException("Unknown plan [{$planKey}].");
        }

        $returnUrl = sprintf('https://admin.shopify.com/store/%s/apps/%s/app/settings/billing', $store->handle(), config('shopify.api_key'));

        $data = $this->api->graphql($store, <<<'GQL'
            mutation Subscribe($name: String!, $returnUrl: URL!, $trialDays: Int, $test: Boolean, $lineItems: [AppSubscriptionLineItemInput!]!) {
              appSubscriptionCreate(name: $name, returnUrl: $returnUrl, trialDays: $trialDays, test: $test, lineItems: $lineItems) {
                confirmationUrl
                appSubscription { id status }
                userErrors { field message }
              }
            }
            GQL, [
            'name' => "OrderOrbit {$plan['name']}",
            'returnUrl' => $returnUrl,
            'trialDays' => $store->subscriptions()->exists() ? 0 : config('shopify.billing.trial_days'),
            'test' => (bool) config('shopify.billing.test'),
            'lineItems' => [[
                'plan' => [
                    'appRecurringPricingDetails' => [
                        'price' => ['amount' => $plan['price'], 'currencyCode' => config('shopify.billing.currency')],
                        'interval' => 'EVERY_30_DAYS',
                    ],
                ],
            ]],
        ]);

        $result = $data['appSubscriptionCreate'];

        if (! empty($result['userErrors'])) {
            throw new RuntimeException('Billing error: '.$result['userErrors'][0]['message']);
        }

        AuditLog::record('billing.subscription_requested', $store, ['plan' => $planKey]);

        return $result['confirmationUrl'];
    }

    /**
     * Pulls the active subscription from Shopify and mirrors it locally.
     */
    public function sync(Store $store): ?Subscription
    {
        $data = $this->api->graphql($store, <<<'GQL'
            {
              currentAppInstallation {
                activeSubscriptions { id name status test trialDays createdAt currentPeriodEnd }
              }
            }
            GQL);

        $active = $data['currentAppInstallation']['activeSubscriptions'][0] ?? null;

        if ($active === null) {
            $store->subscriptions()->where('status', 'ACTIVE')->update(['status' => 'CANCELLED', 'cancelled_at' => now()]);
            $store->forceFill(['plan' => null])->save();

            return null;
        }

        return $this->mirror($store, $active['id'], $active['status'], $active['name'], [
            'test' => $active['test'],
            'trial_ends_at' => $active['trialDays'] > 0 ? Carbon::parse($active['createdAt'])->addDays($active['trialDays']) : null,
            'current_period_ends_at' => $active['currentPeriodEnd'] ? Carbon::parse($active['currentPeriodEnd']) : null,
        ]);
    }

    /**
     * Applies an app_subscriptions/update webhook payload.
     */
    public function applyWebhook(Store $store, array $payload): Subscription
    {
        $sub = $payload['app_subscription'];

        return $this->mirror($store, $sub['admin_graphql_api_id'], $sub['status'], $sub['name']);
    }

    private function mirror(Store $store, string $gid, string $status, string $name, array $extra = []): Subscription
    {
        $planKey = self::planKeyFromName($name);

        $subscription = Subscription::updateOrCreate(
            ['shopify_subscription_id' => $gid],
            array_merge([
                'store_id' => $store->id,
                'plan' => $planKey,
                'status' => $status,
                'price' => config("shopify.billing.plans.{$planKey}.price", 0),
            ], $extra),
        );

        if ($status === 'ACTIVE' && $subscription->activated_at === null) {
            $subscription->forceFill(['activated_at' => now()])->save();
        }

        if (in_array($status, ['CANCELLED', 'EXPIRED', 'DECLINED'], true) && $subscription->cancelled_at === null) {
            $subscription->forceFill(['cancelled_at' => now()])->save();
        }

        if ($status === 'ACTIVE' && $store->plan !== $planKey) {
            $store->forceFill(['plan' => $planKey])->save();
            AuditLog::record('billing.plan_activated', $store, ['plan' => $planKey]);
        } elseif ($status !== 'ACTIVE' && $store->plan === $planKey && ! $store->activeSubscription()->exists()) {
            $store->forceFill(['plan' => null])->save();
        }

        return $subscription;
    }

    private static function planKeyFromName(string $name): string
    {
        foreach (config('shopify.billing.plans') as $key => $plan) {
            if (strcasecmp($name, "OrderOrbit {$plan['name']}") === 0) {
                return $key;
            }
        }

        return 'unknown';
    }
}
