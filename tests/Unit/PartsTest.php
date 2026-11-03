<?php

namespace Tests\Unit;

use App\Experiences\Parts;
use PHPUnit\Framework\TestCase;

/** Texts and visibility: every offer type has its parts; unknown keys are dropped; hidden parts become CSS. */
class PartsTest extends TestCase
{
    public function test_clean_keeps_known_parts_and_words_only(): void
    {
        $clean = Parts::clean('countdown', ['hide' => ['badge', 'nope', 'code'], 'words' => ['days' => ' Jours <b>', 'evil' => 'x', 'hours' => '']]);
        $this->assertSame(['badge', 'code'], $clean['hide']);
        $this->assertSame(['days' => 'Jours', 'hours' => ''], (array) $clean['words']);
    }

    public function test_css_hides_the_chosen_storefront_parts(): void
    {
        $this->assertSame('.oo-cd-badge, .oo-cd-code{display:none!important}', Parts::css('countdown', ['badge', 'code']));
        $this->assertSame('', Parts::css('countdown', []));
        $this->assertSame('', Parts::css('checkout-gift', ['bar']), 'Extension blocks hide parts when they draw.');
    }

    public function test_meta_labels_every_part(): void
    {
        $meta = Parts::meta('post-purchase');
        $this->assertSame('Product picture', $meta['parts']['picture']['label']);
        $this->assertSame('{percent}% off', $meta['words']['off']['default']);
    }
}
