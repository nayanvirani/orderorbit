<?php

namespace Tests\Unit;

use App\Support\Modules;
use PHPUnit\Framework\TestCase;

/** G29: today's four plans keep their prices, modules and limits; the next MVP only adds keys and Premium. */
class PlanDefaultsTest extends TestCase
{
    private const TODAY = [
        'free' => [0, ['bundles', 'progressive_gifts', 'countdown', 'sticky_atc', 'trust', 'preorder', 'sales_pop', 'offer_analytics', 'automation'],
            ['active_experiences' => 4, 'bundles' => 1, 'free_gifts' => 1, 'shipping_bars' => 1, 'countdowns' => 1, 'sticky_atc' => 1, 'trust' => 2, 'preorders' => 1, 'sales_pop' => 1, 'workflows' => 1, 'automation_executions' => 50,
                'product_upsells' => 0, 'cart_upsells' => 0, 'checkout_blocks' => 0, 'thank_you_blocks' => 0, 'post_purchase' => 0, 'account_blocks' => 0, 'running_tests' => 0, 'personalization_rules' => 0, 'segments' => 0]],
        'starter' => [14.99, ['quantity_breaks', 'product_upsells', 'cart_upsells'],
            ['active_experiences' => 15, 'bundles' => 3, 'free_gifts' => 2, 'shipping_bars' => 2, 'product_upsells' => 3, 'cart_upsells' => 3, 'countdowns' => 5, 'sticky_atc' => 2, 'trust' => 5, 'preorders' => 5, 'sales_pop' => 2, 'workflows' => 5, 'automation_executions' => 500,
                'checkout_blocks' => 0, 'thank_you_blocks' => 0, 'post_purchase' => 0, 'account_blocks' => 0, 'running_tests' => 0, 'personalization_rules' => 0, 'segments' => 0]],
        'growth' => [39.99, ['checkout', 'thank_you', 'post_purchase', 'advanced_analytics', 'funnels_attribution', 'customer_journeys', 'automation_branching', 'automation_webhooks', 'ab_testing', 'ab_traffic_guardrails', 'personalization', 'priority_support'],
            ['account_blocks' => 0, 'running_tests' => 5, 'personalization_rules' => 20, 'segments' => 20, 'automation_executions' => 3000]],
        'scale' => [79.99, ['customer_accounts', 'ab_testing_advanced', 'personalization_advanced'], ['automation_executions' => 10000]],
    ];

    public function test_todays_plans_keep_prices_modules_and_limits(): void
    {
        $plans = (require dirname(__DIR__, 2).'/config/shopify.php')['billing']['plans'];
        $modules = [];
        foreach (self::TODAY as $key => [$price, $adds, $limits]) {
            $modules = [...$modules, ...$adds];
            $this->assertSame($price, $plans[$key]['price'], $key);
            $this->assertSame($modules, array_values(array_diff($plans[$key]['includes'], Modules::NEXT_MVP[$key])), "$key keeps today's modules in order");
            foreach ($limits as $meter => $value) {
                $this->assertSame($value, $plans[$key]['limits'][$meter], "$key.$meter");
            }
            // Every other of today's limits is unlimited, as before.
            foreach ($plans[$key]['limits'] as $meter => $value) {
                if (! isset($limits[$meter]) && ! array_key_exists($meter, Modules::NEXT_MVP_LIMITS[$key])) {
                    $this->assertNull($value, "$key.$meter");
                }
            }
        }
    }

    public function test_premium_sits_above_scale_with_the_new_limits(): void
    {
        $plans = (require dirname(__DIR__, 2).'/config/shopify.php')['billing']['plans'];
        $this->assertSame(['free', 'starter', 'growth', 'scale', 'premium'], array_keys($plans));
        $this->assertSame(129.99, $plans['premium']['price']);
        $this->assertSame(25000, $plans['premium']['limits']['automation_executions']);
        $this->assertEmpty(array_diff($plans['scale']['includes'], $plans['premium']['includes']), 'Premium includes everything in Scale.');
        $this->assertSame([100, null], [$plans['premium']['limits']['url_monitors'], $plans['premium']['limits']['profit_viewers']]);
        $this->assertSame([0, 0, 20, 50, 100], array_map(fn ($k) => $plans[$k]['limits']['url_monitors'], array_keys($plans)));
        $this->assertSame([1, 2, 3, 10, null], array_map(fn ($k) => $plans[$k]['limits']['profit_viewers'], array_keys($plans)));
        foreach (Modules::NEXT_MVP as $plan => $keys) {
            foreach ($keys as $key) {
                $this->assertArrayHasKey($key, Modules::ALL, "$plan: $key is a known module");
                $parent = Modules::ALL[$key][3];
                $this->assertTrue($parent === null || in_array($parent, $plans[$plan]['includes'], true), "$plan: $key needs $parent");
            }
        }
    }
}
