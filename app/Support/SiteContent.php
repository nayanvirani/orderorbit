<?php

namespace App\Support;

use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\HtmlString;
use Throwable;

/**
 * Every line of the public website, editable in the super admin (Website content).
 *
 * Built-in text lives in resources/content: one file per page (resources/content/pages/*.php)
 * plus the feature, solution, guide, blog, help, template and pricing-question files. An edit
 * is saved as a whole copy of that page or item in site_contents and replaces the built-in
 * text; resetting deletes it. Saved copies are always read through the built-in shape, so a
 * field added in code later still shows its built-in text.
 *
 * Keys: page.{name}, feature.{slug}, solution.{slug}, guide.{slug}, list.{blog|help|pricing}.
 */
class SiteContent
{
    private const KEY = 'site-content:v1';

    /** Pages with their own copy file, in the order the admin lists them. */
    public const PAGES = [
        'site' => ['Header & footer', 'Menu, footer columns and the site-wide call to action'],
        'home' => ['Home', '/'],
        'features' => ['Features', '/features'],
        'pricing' => ['Pricing', '/pricing'],
        'templates' => ['Templates', '/templates'],
        'solutions' => ['Solutions', '/solutions'],
        'how' => ['How it works', '/how-it-works'],
        'about' => ['About', '/about'],
        'contact' => ['Contact', '/contact'],
        'resources' => ['Resources', '/resources'],
        'help' => ['Help Center', '/help'],
        'blog' => ['Blog', '/blog'],
        'docs' => ['Docs home', '/docs'],
        'security' => ['Security', '/security'],
        'legal' => ['Legal index', '/legal'],
        'errors' => ['Error pages', '404, 500 and other errors'],
        'coming_soon' => ['Coming soon', 'Shown while the site is locked'],
    ];

    /** Whole-file lists. */
    public const LISTS = [
        'help' => ['Help topics & articles', '/help'],
        'pricing' => ['Pricing questions', '/pricing'],
    ];

    /** Field names the editor shows in plain words. */
    public const LABELS = [
        'seo_title' => 'Browser tab & Google title', 'seo_description' => 'Google description', 'eyebrow' => 'Small label above the heading',
        'h1' => 'Main heading', 'title' => 'Heading', 'lead' => 'Intro text', 'hero' => 'Intro text', 'text' => 'Text', 'body' => 'Text',
        'label' => 'Label', 'href' => 'Link', 'cta' => 'Button', 'cta_primary' => 'Main button', 'cta_secondary' => 'Second button',
        'items' => 'Items', 'faqs' => 'Questions', 'name' => 'Name', 'menu' => 'Menu description', 'summary' => 'Short description',
        'overview' => 'Overview paragraphs', 'benefits' => 'Benefits', 'steps' => 'Steps', 'example' => 'Example', 'note' => 'Note',
        'tagline' => 'Tagline', 'setup' => 'Setup steps', 'articles' => 'Articles', 'excerpt' => 'Excerpt', 'category' => 'Category',
        'sections' => 'Sections', 'placeholder' => 'Placeholder text', 'heading' => 'Heading', 'intro' => 'Intro text',
    ];

    /** Names of sections and groups of fields (where a key holds more than one line). */
    public const GROUPS = [
        'hero' => 'Top of the page', 'cta' => 'Call to action (banner near the bottom)', 'faq' => 'Questions', 'how' => 'How it works',
        'stack' => 'Replace your apps', 'day_one' => 'Day one', 'surfaces' => 'Where it shows', 'features' => 'Features', 'pricing' => 'Pricing',
        'analytics' => 'Analytics', 'menu' => 'Menu labels', 'footer' => 'Footer', 'mockup' => 'Product preview', 'detail' => 'Labels on each detail page',
        'form' => 'Form', 'groups' => 'Groups', 'start' => 'Not sure where to start', 'store' => 'What happens on your store', 'report' => 'Report an issue',
        'contact' => 'Contact', 'independent' => 'Dark card', 'merchant' => 'Merchant card', 'product_menu' => 'Product menu', 'solutions_menu' => 'Solutions menu',
        'resources_menu' => 'Resources menu', 'cta_primary' => 'Main button', 'cta_secondary' => 'Second button', 'primary' => 'Main button',
        'secondary' => 'Second button', 'link' => 'Link', 'button' => 'Button', 'install' => 'Install button', 'tiers' => 'Bundle tiers', 'columns' => 'Columns',
        'links' => 'Links', 'topics' => 'Topic choices', 'cards' => 'Cards', 'principles' => 'Principles', 'paragraphs' => 'Paragraphs', 'example' => 'Example',
        'checks' => 'Ticks under the buttons', 'numbers' => 'Number strip', 'journey' => 'Buying journey', 'stages' => 'Stages', 'trust' => 'Trust badges', 'metrics' => 'Example results', 'apps' => 'Apps', 'stats' => 'Numbers', 'steps' => 'Steps', 'plan' => 'Plan cards', 'items' => 'Items',
    ];

    /** Names of the parts of [a, b, …] rows, by the list they're in. */
    public const ROWS = [
        'faqs' => ['Question', 'Answer', 'Code sample (optional)'], 'articles' => ['Question', 'Answer', 'Code sample (optional)'],
        'benefits' => ['Title', 'Text'], 'setup' => ['Title', 'Text'], 'metrics' => ['Label', 'Value'], 'problem' => ['Overview heading', 'Overview intro (optional)'], 'stack' => ['Job', 'Typical price'],
        'promises' => ['Title', 'Text'], 'numbers' => ['Number', 'Label'], 'steps' => ['Title', 'Text'], 'cards' => ['Title', 'Text'], 'stats' => ['Label', 'Value'],
    ];

    /** Fields used by the code, not shown as copy (icons, groups, links between items). */
    public const HIDDEN = ['icon', 'group', 'visual', 'features', 'feature', 'type', 'surface', 'status', 'grid', 'keyword', 'steps_heading', 'templates', 'secondary_cta', 'later'];

    // ------------------------------------------------------------------ reading

    /** A page's copy: built-in text with the admin's edits. */
    public static function page(string $name): array
    {
        return self::get('page.'.$name);
    }

    public static function get(string $key): array
    {
        $default = self::defaults($key);
        $saved = self::saved()[$key] ?? null;

        return is_array($saved) ? self::clean($default, $saved) : $default;
    }

    /** Applies saved item edits to a keyed collection (features, solutions, guides). */
    public static function applyItems(string $prefix, array $items): array
    {
        $saved = self::saved();
        foreach ($items as $slug => $item) {
            if (is_array($saved[$prefix.'.'.$slug] ?? null)) {
                $items[$slug] = self::clean($item, $saved[$prefix.'.'.$slug]);
            }
        }

        return $items;
    }

    /** Applies a saved whole-list edit. */
    public static function applyList(string $name, array $list): array
    {
        $saved = self::saved()['list.'.$name] ?? null;

        return is_array($saved) ? self::clean($list, $saved) : $list;
    }

    public static function isEdited(string $key): bool
    {
        return isset(self::saved()[$key]);
    }

    /** @return array<string, array> key => saved copy */
    private static function saved(): array
    {
        try {
            return Cache::rememberForever(self::KEY, fn () => DB::table('site_contents')->pluck('data', 'key')
                ->map(fn ($v) => json_decode((string) $v, true))->all());
        } catch (Throwable) {
            return [];
        }
    }

    /** The built-in copy behind a key. */
    public static function defaults(string $key): array
    {
        [$kind, $name] = array_pad(explode('.', $key, 2), 2, '');

        return match ($kind) {
            'page' => isset(self::PAGES[$name]) ? require resource_path("content/pages/{$name}.php") : abort(404),
            'feature' => Content::baseFeatures()[$name] ?? abort(404),
            'solution' => Content::base('solutions')[$name] ?? abort(404),
            'guide' => Content::base('docs')[$name] ?? abort(404),
            'list' => isset(self::LISTS[$name]) ? Content::base($name === 'help' ? 'help' : $name) : abort(404),
            default => abort(404),
        };
    }

    /** What the admin lists: group => [key => [title, where]]. */
    public static function catalogue(): array
    {
        return [
            'Pages' => collect(self::PAGES)->mapWithKeys(fn ($p, $k) => ['page.'.$k => $p])->all(),
            'Feature pages' => collect(Content::baseFeatures())->mapWithKeys(fn ($f, $s) => ['feature.'.$s => [$f['name'], '/features/'.$s]])->all(),
            'Solution pages' => collect(Content::base('solutions'))->mapWithKeys(fn ($f, $s) => ['solution.'.$s => [$f['name'], '/solutions/'.$s]])->all(),
            'Guides' => collect(Content::base('docs'))->mapWithKeys(fn ($f, $s) => ['guide.'.$s => [$f['title'], '/docs/'.$s]])->all(),
            'Lists' => collect(self::LISTS)->mapWithKeys(fn ($p, $k) => ['list.'.$k => $p])->all(),
        ];
    }

    public static function title(string $key): string
    {
        foreach (self::catalogue() as $items) {
            if (isset($items[$key])) {
                return $items[$key][0];
            }
        }

        return $key;
    }

    // ------------------------------------------------------------------ writing

    /** Saves an edited copy, cleaned against the built-in shape. */
    public static function save(string $key, array $data, ?string $by = null): array
    {
        $clean = self::clean(self::defaults($key), $data);
        DB::table('site_contents')->updateOrInsert(['key' => $key], ['data' => json_encode($clean, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES), 'updated_by' => $by, 'updated_at' => now(), 'created_at' => now()]);
        Cache::forget(self::KEY);

        return $clean;
    }

    public static function reset(string $key): void
    {
        DB::table('site_contents')->where('key', $key)->delete();
        Cache::forget(self::KEY);
    }

    /**
     * Reads $input through the shape of $default: keys of a group come from the built-in copy
     * (missing ones keep their built-in value, unknown ones are dropped); lists take the edited
     * items, each read through the shape of the built-in items; text is capped in length.
     */
    public static function clean(mixed $default, mixed $input, int $depth = 0): mixed
    {
        if (is_array($default)) {
            if (! is_array($input) || $depth > 8) {
                return $default;
            }
            if (array_is_list($default) && $default !== []) {
                $shape = $default[0];
                $out = [];
                foreach (array_slice(array_values($input), 0, 300) as $item) {
                    // Rows of mixed shapes (guide blocks, [question, answer] pairs) are kept as given.
                    $out[] = is_array($shape) && ! array_is_list($shape) ? self::clean($shape, $item, $depth + 1) : self::loose($item, $depth + 1);
                }

                return $out;
            }
            if ($default === []) {
                return self::loose($input, $depth + 1);
            }
            $out = [];
            foreach ($default as $k => $v) {
                $out[$k] = array_key_exists($k, $input) ? self::clean($v, $input[$k], $depth + 1) : $v;
            }

            return $out;
        }

        return match (true) {
            is_bool($default) => (bool) $input,
            is_int($default) => is_numeric($input) ? (int) $input : $default,
            is_float($default) => is_numeric($input) ? (float) $input : $default,
            default => is_scalar($input) || $input === null ? mb_substr(trim((string) $input), 0, 10000) : (is_string($default) ? $default : ''),
        };
    }

    private static function loose(mixed $value, int $depth): mixed
    {
        if (is_array($value)) {
            return $depth > 8 ? [] : array_map(fn ($v) => self::loose($v, $depth + 1), array_slice($value, 0, 300, true));
        }

        return is_bool($value) || is_int($value) || is_float($value) ? $value : mb_substr(trim((string) $value), 0, 10000);
    }

    // ------------------------------------------------------------------ output

    /**
     * *highlight*, **bold** and [text](link), with everything else escaped. Placeholders:
     * {year}, {templates} (template count), {from_price} / {max_price} (paid plans) and any in $vars.
     */
    public static function md(?string $text, array $vars = []): HtmlString
    {
        $html = e(self::fill((string) $text, $vars));
        $html = preg_replace_callback('/\[([^\]\n]+)\]\(([^)\s]+)\)/', fn ($m) => '<a href="'.e(self::url(html_entity_decode($m[2], ENT_QUOTES))).'">'.$m[1].'</a>', $html);
        $html = preg_replace('/\*\*(.+?)\*\*/s', '<strong>$1</strong>', $html);
        $html = preg_replace('/\*(.+?)\*/s', '<span class="hl">$1</span>', $html);

        return new HtmlString($html);
    }

    /** Plain text without the markup (page titles, attributes). */
    public static function plain(?string $text, array $vars = []): string
    {
        return trim(preg_replace(['/\[([^\]\n]+)\]\([^)\s]+\)/', '/\*{1,2}(.+?)\*{1,2}/s'], ['$1', '$1'], self::fill((string) $text, $vars)));
    }

    /** Replaces the placeholders in a line of copy. */
    public static function fill(string $text, array $vars = []): string
    {
        if (! str_contains($text, '{')) {
            return $text;
        }
        try {
            $prices = array_filter(array_map(fn ($p) => (float) $p['price'], Plans::public()));
            $templates = count(Content::templates());
            $tools = count(Content::features());
        } catch (Throwable) {
            $prices = [];
            $templates = 0;
        }
        $globals = ['{year}' => date('Y'), '{templates}' => (string) $templates, '{tools}' => (string) count(Content::features()),
            '{from_price}' => $prices ? number_format(min($prices), 2) : '0', '{max_price}' => $prices ? number_format(max($prices), 2) : '0'];

        return strtr($text, $globals + collect($vars)->mapWithKeys(fn ($v, $k) => ['{'.$k.'}' => (string) $v])->all());
    }

    /** A link from the copy: {install} and {signin} become the Shopify links; anything unsafe becomes "#". */
    public static function url(?string $href): string
    {
        $href = trim((string) $href);
        $href = strtr($href, ['{install}' => (string) config('shopify.install_url'), '{signin}' => (string) config('shopify.sign_in_url')]);

        if (in_array($href, ['javascript:history.back()', 'javascript:location.reload()'], true)) {
            return $href; // error pages: "Go back" and "Try again"
        }

        return preg_match('~^(/(?!/)|#|https?://|mailto:)~i', $href) ? $href : '#';
    }
}
