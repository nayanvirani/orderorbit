<?php

namespace Tests\Unit;

use App\Experiences\Layout;
use PHPUnit\Framework\TestCase;

/** G37 step 2: text and layout per screen size become offer-scoped CSS, one rule per @media block. */
class LayoutTest extends TestCase
{
    public function test_clean_keeps_known_parts_settings_and_ranges(): void
    {
        $clean = json_decode(json_encode(Layout::clean('countdown', [
            'desktop' => ['title' => ['size' => '28', 'weight' => 800, 'font' => 'heading', 'pad' => 4, 'case' => 'shout'], 'box_block' => ['pad' => 200]],
            'tablet' => ['nope' => ['size' => 1]],
            'mobile' => ['title' => ['size' => 18]],
        ])), true);
        $this->assertSame(['desktop' => ['title' => ['size' => 28, 'weight' => 800, 'font' => 'heading'], 'box_block' => ['pad' => 80]], 'mobile' => ['title' => ['size' => 18]]], $clean);
    }

    public function test_css_is_scoped_to_the_offer_with_mobile_after_desktop(): void
    {
        $css = Layout::css('countdown', ['mobile' => ['title' => ['size' => 18]], 'desktop' => ['title' => ['size' => 28], 'box_block' => ['pad' => 12]]], 'cd-1"x');
        $this->assertSame(implode("\n", [
            '@media all{[data-oo-exp="cd-1x"] .oo-title{font-size:28px!important}}',
            '@media all{[data-oo-exp="cd-1x"]{padding:12px!important}}',
            '@media (max-width: 749px){[data-oo-exp="cd-1x"] .oo-title{font-size:18px!important}}',
        ]), $css);
        $this->assertStringContainsString('display:grid!important;grid-template-columns:repeat(2, minmax(0, 1fr))!important', Layout::css('cart-upsells', ['desktop' => ['box_cards' => ['cols' => 2]]], 'x'));
    }

    public function test_every_part_has_a_known_kind(): void
    {
        foreach (Layout::all() as $type => $parts) {
            foreach ($parts as $key => [$label, $selector, $kind]) {
                $this->assertContains($kind, ['text', 'box', 'grid'], "$type.$key");
                $this->assertDoesNotMatchRegularExpression('/[{}@]/', $selector, "$type.$key");
            }
        }
    }
}
