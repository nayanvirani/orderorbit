<?php

namespace App\Support;

use App\Models\SavedReply;
use App\Models\Store;

/**
 * "Suggest reply" (G35): the saved replies and help-centre answers that best match a message,
 * filled with the store's details. Plain keyword matching in PHP: no AI service, no cost.
 * Replies the team sends often rank a little higher.
 */
class ReplySuggester
{
    private const STOP = ['the', 'a', 'an', 'and', 'or', 'is', 'are', 'to', 'of', 'in', 'on', 'for', 'it', 'my', 'i', 'we', 'you', 'your', 'our', 'with', 'can', 'how', 'do', 'does', 'what', 'when', 'why', 'this', 'that', 'be', 'not', 'at', 'have', 'has', 'me', 'hi', 'hello', 'thanks', 'thank', 'please', 'there', 'from', 'as', 'but', 'if', 'so', 'by', 'any', 'all', 'will', 'would', 'should', 'could', 'just', 'its', "it's", 'am', 'was', 'were', 'growvia', 'app'];

    public const PLACEHOLDERS = ['first_name', 'shop', 'plan', 'live_widgets'];

    /** @return list<array{title: string, body: string, source: string, saved_reply_id: ?int, missing: list<string>, score: float}> */
    public function suggest(string $message, array $context = [], int $limit = 3): array
    {
        $words = self::words($message);
        if ($words === []) {
            return [];
        }
        $candidates = [];
        foreach (SavedReply::all() as $r) {
            $candidates[] = ['title' => $r->title, 'body' => $r->body, 'source' => 'Saved reply: '.$r->title, 'saved_reply_id' => $r->id,
                'score' => $this->score($words, $r->title.' '.$r->keywords, $r->body) * (1 + log(1 + $r->use_count) / 5)];
        }
        foreach (Content::helpCategories() as $cat) {
            foreach ($cat['articles'] ?? [] as [$q, $a]) {
                $candidates[] = ['title' => $q, 'body' => "Hi {{first_name}},\n\n".$a."\n\nGrowvia Support", 'source' => 'Help centre: '.$cat['name'].' › '.$q, 'saved_reply_id' => null,
                    'score' => $this->score($words, $q, $a) * 0.9];
            }
        }
        usort($candidates, fn ($x, $y) => $y['score'] <=> $x['score']);

        return array_values(array_map(function ($c) use ($context) {
            [$c['body'], $c['missing']] = self::fill($c['body'], $context);

            return $c;
        }, array_slice(array_filter($candidates, fn ($c) => $c['score'] > 0), 0, $limit)));
    }

    /** Help-centre articles that match a message, for a merchant to read while they wait (G43). */
    public function articles(string $message, int $limit = 3): array
    {
        $words = self::words($message);
        $found = [];
        foreach (Content::helpCategories() as $cat) {
            foreach ($cat['articles'] ?? [] as [$q, $a]) {
                $score = $words ? $this->score($words, $q, $a) : 0;
                if ($score > 0) {
                    $found[] = ['q' => $q, 'a' => $a, 'score' => $score];
                }
            }
        }
        usort($found, fn ($x, $y) => $y['score'] <=> $x['score']);

        return array_map(fn ($f) => ['q' => $f['q'], 'a' => $f['a']], array_slice($found, 0, $limit));
    }

    /** Placeholders filled from the context; unknown ones stay visible as [FILL: name] and are listed. */
    public static function fill(string $body, array $context): array
    {
        $missing = [];
        $out = preg_replace_callback('/\{\{\s*([a-z_]+)\s*\}\}/', function ($m) use ($context, &$missing) {
            $v = $context[$m[1]] ?? null;
            if ($v === null || $v === '') {
                $missing[] = $m[1];

                return $m[1] === 'first_name' ? 'there' : '[FILL: '.$m[1].']';
            }

            return (string) $v;
        }, $body);

        return [$out, array_values(array_unique(array_diff($missing, ['first_name'])))];
    }

    /** Details for placeholders: the person's first name and, when known, their store. */
    public static function context(?string $name, ?Store $store): array
    {
        return array_filter([
            'first_name' => $name ? trim(explode(' ', trim($name))[0]) : null,
            'shop' => $store?->shop_domain,
            'plan' => $store ? ucfirst($store->effectivePlan()) : null,
            'live_widgets' => $store ? (string) $store->experiences()->where('status', 'published')->count() : null,
        ], fn ($v) => $v !== null && $v !== '');
    }

    private function score(array $words, string $title, string $body): float
    {
        $t = array_count_values(self::words($title, false));
        $b = array_count_values(self::words($body, false));
        $score = 0.0;
        foreach (array_unique($words) as $w) {
            $score += (isset($t[$w]) ? 3 : 0) + min(2, $b[$w] ?? 0) * 0.6;
        }

        return $score;
    }

    /** Lower-case words without stop words, lightly stemmed so "bundles" matches "bundle". */
    private static function words(string $text, bool $unique = true): array
    {
        preg_match_all("/[a-z0-9][a-z0-9'-]{1,}/", mb_strtolower($text), $m);
        $words = array_map(fn ($w) => preg_replace('/(ing|ed|es|s)$/', '', $w) ?: $w, array_diff($m[0], self::STOP));
        $words = array_values(array_filter($words, fn ($w) => strlen($w) > 2 && ! in_array($w, self::STOP, true)));

        return $unique ? array_values(array_unique($words)) : $words;
    }
}
