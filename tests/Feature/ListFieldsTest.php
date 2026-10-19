<?php

namespace Tests\Feature;

use App\Experiences\Schema;
use Tests\TestCase;

/**
 * List fields (reviews, trust points, tiers…): blank rows are dropped, each row is checked
 * field by field, and errors point at the row and box they belong to.
 */
class ListFieldsTest extends TestCase
{
    private function reviews(array $rows): array
    {
        $input = Schema::defaults('checkout-reviews');
        $input['content']['reviews'] = $rows;

        return Schema::normalize('checkout-reviews', $input);
    }

    public function test_a_blank_review_row_is_dropped_instead_of_blocking_the_save(): void
    {
        [$config, $errors] = $this->reviews([
            ['author' => 'Sarah K.', 'rating' => 5, 'title' => '', 'quote' => 'Lovely serum.', 'date' => ''],
            ['author' => '', 'rating' => 5, 'title' => '', 'quote' => '', 'date' => ''],
            ['author' => '', 'rating' => '', 'title' => ' ', 'quote' => '', 'date' => ''],
        ]);

        $this->assertSame([], $errors);
        $this->assertCount(1, $config['content']['reviews']);
        $this->assertSame('Lovely serum.', $config['content']['reviews'][0]['quote']);
    }

    public function test_a_review_needs_a_quote_and_the_error_names_its_row(): void
    {
        [, $errors] = $this->reviews([
            ['author' => 'Sarah K.', 'rating' => 5, 'title' => '', 'quote' => 'Lovely serum.', 'date' => ''],
            ['author' => 'Ben', 'rating' => 4, 'title' => 'Great', 'quote' => '', 'date' => ''],
        ]);

        $this->assertArrayHasKey('content.reviews.1.quote', $errors);
        $this->assertArrayNotHasKey('content.reviews.0.quote', $errors);
    }

    public function test_only_blank_rows_still_count_as_no_reviews(): void
    {
        [$config, $errors] = $this->reviews([['author' => '', 'rating' => 5, 'title' => '', 'quote' => '', 'date' => '']]);

        $this->assertSame([], $config['content']['reviews']);
        $this->assertArrayHasKey('content.reviews', $errors);
    }

    public function test_stars_outside_one_to_five_are_rejected(): void
    {
        [, $errors] = $this->reviews([['author' => 'Ana', 'rating' => 9, 'title' => '', 'quote' => 'Good.', 'date' => '']]);

        $this->assertArrayHasKey('content.reviews.0.rating', $errors);
    }

    public function test_error_keys_keep_the_rows_position_after_a_blank_row(): void
    {
        [$config, $errors] = $this->reviews([
            ['author' => '', 'rating' => 5, 'title' => '', 'quote' => '', 'date' => ''],
            ['author' => 'Ben', 'rating' => 4, 'title' => '', 'quote' => '', 'date' => ''],
        ]);

        $this->assertArrayHasKey('content.reviews.1.quote', $errors);
        $this->assertCount(1, $config['content']['reviews']);
    }

    public function test_trust_badges_with_only_the_default_icon_are_dropped(): void
    {
        $input = Schema::defaults('trust');
        $input['content']['badges'] = [['icon' => 'shipping', 'label' => ''], ['icon' => 'returns', 'label' => 'Free returns']];
        [$config] = Schema::normalize('trust', $input);

        $this->assertSame([['icon' => 'returns', 'label' => 'Free returns']], $config['content']['badges']);
    }
}
