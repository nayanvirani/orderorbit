<?php

namespace Tests\Feature;

use App\Automation\Context;
use App\Automation\Engine;
use App\Automation\ReorderTiming;
use App\Automation\WorkflowManager;
use App\Models\Automation\WorkflowRun;
use App\Models\FeatureFlag;
use App\Services\Profit\OrderSync;
use App\Support\Features;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Tests\Concerns\InteractsWithShopify;
use Tests\TestCase;

/** Predictive reorder timing (G21). */
class ReorderTimingTest extends TestCase
{
    use InteractsWithShopify, RefreshDatabase;

    public function test_a_wait_uses_the_products_usual_reorder_gap_or_falls_back(): void
    {
        $this->setUpShopify();
        Http::fake(['*' => Http::response(['data' => []])]);
        FeatureFlag::create(['key' => 'reorder_timing', 'mode' => 'all', 'enabled' => true]);
        Features::forget();
        $store = $this->installedStore(['plan' => 'premium']);
        $m = fn ($v) => ['shop_money' => ['amount' => (string) $v]];
        // 6 customers each rebuy product 7 after 28 days (one after 30): median 28.
        $id = 1;
        foreach (range(1, 6) as $c) {
            foreach ([0, $c === 6 ? 30 : 28] as $offset) {
                app(OrderSync::class)->fromWebhook($store, ['id' => $id++, 'processed_at' => now()->subDays(60 - $offset)->toIso8601String(), 'customer' => ['id' => 500 + $c], 'total_shipping_price_set' => $m(0), 'total_tax_set' => $m(0), 'total_price_set' => $m(30),
                    'line_items' => [['id' => $id, 'product_id' => 7, 'variant_id' => 70, 'title' => 'Coffee', 'quantity' => 1, 'price_set' => $m(30), 'discount_allocations' => []]]]);
            }
        }
        $this->assertSame(28, app(ReorderTiming::class)->gap($store, 7));
        $this->assertNull(app(ReorderTiming::class)->gap($store, 99), 'No history.');

        $manager = app(WorkflowManager::class);
        $workflow = $manager->create($store, null, null, 'Reorder');
        $this->assertSame([], $manager->saveDraft($workflow, ['trigger' => 'order_paid', 'steps' => [['type' => 'wait', 'amount' => 45, 'unit' => 'days', 'reorder' => true]]], 'Reorder'));
        $manager->publish($workflow->fresh(), null);

        $order = ['id' => 9001, 'name' => '#9001', 'current_total_price' => '30', 'customer' => ['id' => 501, 'orders_count' => 2, 'total_spent' => '60'], 'line_items' => [['product_id' => 7, 'variant_id' => 70, 'title' => 'Coffee', 'quantity' => 1]]];
        app(Engine::class)->trigger($store, 'order_paid', Context::fromOrder($order), 'order_paid:9001');
        $run = WorkflowRun::sole();
        $this->assertEqualsWithDelta(now()->addDays(28)->timestamp, $run->resume_at->timestamp, 5);

        // Another product with no history: the fixed 45 days.
        app(Engine::class)->trigger($store, 'order_paid', Context::fromOrder(['id' => 9002] + array_replace($order, ['line_items' => [['product_id' => 99, 'variant_id' => 990, 'title' => 'New', 'quantity' => 1]]])), 'order_paid:9002');
        $this->assertEqualsWithDelta(now()->addDays(45)->timestamp, WorkflowRun::latest('id')->first()->resume_at->timestamp, 5);
    }
}
