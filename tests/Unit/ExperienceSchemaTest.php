<?php

namespace Tests\Unit;

use App\Experiences\Registry;
use App\Experiences\Schema;
use Tests\TestCase;

class ExperienceSchemaTest extends TestCase
{
    public function test_every_type_has_valid_defaults_except_required_picks(): void
    {
        foreach (array_keys(Registry::types()) as $type) {
            [$config, $errors] = Schema::normalize($type, Schema::defaults($type));
            $this->assertSame(Schema::SECTIONS, array_keys($config), $type);

            // Only "choose products" and the countdown deadline need merchant input.
            foreach (array_keys($errors) as $key) {
                $this->assertContains($key, ['content.products', 'content.gift_products', 'content.buy_products', 'content.ends_at'], "{$type}: unexpected default error {$key}");
            }
        }
    }

    public function test_text_is_stripped_and_limited(): void
    {
        $input = Schema::defaults('shipping-bar');
        $input['content']['progress_message'] = '<script>x</script>Almost there';
        [$config] = Schema::normalize('shipping-bar', $input);

        $this->assertSame('xAlmost there', $config['content']['progress_message']);
    }

    public function test_thresholds_must_ascend(): void
    {
        $input = Schema::defaults('shipping-bar');
        $input['content']['thresholds'] = [['amount' => 100, 'reward' => 'a'], ['amount' => 50, 'reward' => 'b']];

        $this->assertArrayHasKey('content.thresholds', Schema::normalize('shipping-bar', $input)[1]);
    }

    public function test_product_picks_keep_only_valid_snapshots(): void
    {
        $input = Schema::defaults('product-upsells');
        $input['content']['products'] = json_encode([
            ['id' => 'gid://shopify/Product/1', 'title' => 'Card', 'price' => '9.5', 'image' => 'javascript:alert(1)', 'variant_id' => 'gid://shopify/ProductVariant/2'],
            ['id' => 'not-a-gid', 'title' => 'Bad'],
        ]);
        [$config, $errors] = Schema::normalize('product-upsells', $input);

        $this->assertArrayNotHasKey('content.products', $errors);
        $this->assertSame([['id' => 'gid://shopify/Product/1', 'title' => 'Card', 'price' => 9.5, 'variant_id' => 'gid://shopify/ProductVariant/2']], $config['content']['products']);
    }

    public function test_targeting_and_schedule_rules(): void
    {
        $input = Schema::defaults('trust');
        $input['targeting']['countries'] = 'US, Canada';
        $input['targeting']['cart_min'] = 100;
        $input['targeting']['cart_max'] = 50;
        $input['schedule'] = ['starts_at' => '2030-01-02 10:00', 'ends_at' => '2030-01-01 10:00'];

        $errors = Schema::normalize('trust', $input)[1];

        $this->assertArrayHasKey('targeting.countries', $errors);
        $this->assertArrayHasKey('targeting.cart_max', $errors);
        $this->assertArrayHasKey('schedule.ends_at', $errors);
    }

    public function test_datetimes_use_the_store_timezone(): void
    {
        $input = Schema::defaults('countdown');
        $input['content']['ends_at'] = '2030-06-01T09:00';
        [$config] = Schema::normalize('countdown', $input, 'America/New_York');

        $this->assertSame('2030-06-01T09:00:00-04:00', $config['content']['ends_at']);
    }

    public function test_list_rows_drop_unknown_keys(): void
    {
        $input = Schema::defaults('quantity-breaks');
        $input['content']['tiers'] = [['quantity' => 2, 'discount' => 10, 'badge' => 'x', 'evil' => 'y']];
        $input['content']['default_tier'] = 1;
        [$config, $errors] = Schema::normalize('quantity-breaks', $input);

        $this->assertSame([], $errors);
        $this->assertSame([['quantity' => 2, 'discount' => 10, 'badge' => 'x']], $config['content']['tiers']);
    }

    public function test_bundle_products_keep_mapped_variants_and_quantities(): void
    {
        $input = Schema::defaults('bundles');
        $input['content']['products'] = [[
            'id' => 'gid://shopify/Product/1', 'title' => 'Tee', 'quantity' => 99,
            'variants' => [['id' => 'gid://shopify/ProductVariant/11', 'title' => 'Blue / M', 'price' => '20'], ['id' => 'nope'], ['id' => 'gid://shopify/ProductVariant/12', 'title' => '<b>Red</b>']],
        ]];
        [$config] = Schema::normalize('bundles', $input);

        $product = $config['content']['products'][0];
        $this->assertSame(20, $product['quantity'], 'Quantities are capped.');
        $this->assertSame([['id' => 'gid://shopify/ProductVariant/11', 'title' => 'Blue / M', 'price' => 20.0], ['id' => 'gid://shopify/ProductVariant/12', 'title' => 'Red']], $product['variants']);

        // Quantities only apply where the field allows them (bundles), not e.g. upsells.
        $upsell = Schema::defaults('product-upsells');
        $upsell['content']['products'] = [['id' => 'gid://shopify/Product/1', 'quantity' => 3]];
        $this->assertArrayNotHasKey('quantity', Schema::normalize('product-upsells', $upsell)[0]['content']['products'][0]);
    }
}
