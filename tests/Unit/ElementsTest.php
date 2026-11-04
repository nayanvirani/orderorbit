<?php

namespace Tests\Unit;

use App\Experiences\Elements;
use PHPUnit\Framework\TestCase;

/** G37 step 1: every part colour is a plain CSS rule; only real colours make CSS. */
class ElementsTest extends TestCase
{
    public function test_every_storefront_part_has_a_rule_with_a_colour_slot(): void
    {
        foreach (Elements::all() as $type => $parts) {
            foreach ($parts as $key => [$label, $rule]) {
                $this->assertStringContainsString('%s', $rule, "$type.$key");
                $this->assertMatchesRegularExpression('/^[^{}]+\{[^{}]+\}([^{}]+\{[^{}]+\})*$/', $rule, "$type.$key is one or more plain CSS rules");
            }
        }
        $this->assertSame('', Elements::css('countdown', ['digits' => 'url(x)']));
    }

    public function test_clean_keeps_known_parts_and_hex_colours(): void
    {
        $this->assertSame(['digits' => '#ffd166', 'units' => '#abc'], (array) Elements::clean('countdown', ['digits' => '#FFD166', 'boxes' => 'red', 'nope' => '#000000', 'units' => '#abc']));
        $this->assertSame('.oo-u b{color:#ffd166!important}', Elements::css('countdown', ['digits' => '#ffd166']));
        $this->assertSame([], (array) Elements::clean('checkout-gift', ['bar' => '#000000']), 'Checkout blocks use Shopify styles, not colours.');
    }
}
