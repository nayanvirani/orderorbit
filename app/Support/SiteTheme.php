<?php

namespace App\Support;

use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Throwable;

/**
 * The public website's design, edited in the super admin (Website design): every colour, the
 * fonts and the corner radii. Each setting is a CSS variable that public/css/site.css reads, so a
 * saved change reaches every public page, the coming-soon page and the error pages at once.
 * The defaults are the site's built-in look.
 */
class SiteTheme
{
    private const KEY = 'site-theme:v1';

    /** group => [key => [label, type, default, css variable, help]] */
    public const FIELDS = [
        'Page' => [
            'bg' => ['Page background', 'color', '#faf9fe', '--c-bg', null],
            'bg_alt' => ['Soft background', 'color', '#f2f0fc', '--c-bg-alt', 'Alternate sections, tiles, thumbnails and hover states.'],
            'surface' => ['Cards and panels', 'color', '#ffffff', '--c-surface', 'Cards, menus, pop-ups and the floating widget cards.'],
            'heading' => ['Headings', 'color', '#15123b', '--c-heading', null],
            'text' => ['Body text', 'color', '#15123b', '--c-text', null],
            'muted' => ['Secondary text', 'color', '#4a4766', '--c-muted', 'Descriptions and supporting copy.'],
            'dim' => ['Labels and hints', 'color', '#6b6889', '--c-dim', 'Small labels, captions and placeholders.'],
            'line' => ['Borders', 'color', '#e7e4f2', '--c-line', null],
            'line_strong' => ['Strong borders', 'color', '#d8d4ec', '--c-line-strong', 'Inputs, outline buttons and dividers that need more contrast.'],
            'outline' => ['Dark buttons and selected filters', 'color', '#15123b', '--c-outline', 'The dark "Add to cart" buttons in product previews and the selected filter chip.'],
        ],
        'Buttons & accent' => [
            'accent' => ['Primary buttons and accent', 'color', '#5b45f0', '--c-accent', 'Primary buttons, highlighted words in headings, icons, call-to-action banners and selected states.'],
            'on_accent' => ['Text on primary buttons', 'color', '#ffffff', '--c-on-accent', null],
        ],
        'Header & menu' => [
            'header_bg' => ['Header background', 'color', '#ffffff', '--c-header-bg', 'Shown slightly see-through over the page.'],
            'nav' => ['Menu links', 'color', '#15123b', '--c-nav', null],
            'nav_hover' => ['Menu links on hover', 'color', '#5b45f0', '--c-nav-hover', null],
            'nav_hover_bg' => ['Menu hover background', 'color', '#f2f0fc', '--c-nav-hover-bg', null],
        ],
        'Dark sections' => [
            'dark_bg' => ['Background', 'color', '#120e33', '--c-dark-bg', 'Footer, analytics section, featured plan, Help Center header and code blocks.'],
            'dark_bg_alt' => ['Raised background', 'color', '#1c1747', '--c-dark-bg-alt', 'Cards inside dark sections.'],
            'dark_surface' => ['Raised background, hover', 'color', '#241e57', '--c-dark-surface', null],
            'dark_heading' => ['Headings', 'color', '#ffffff', '--c-dark-heading', null],
            'dark_text' => ['Text', 'color', '#edebff', '--c-dark-text', 'Also used, see-through, for borders and dots in dark sections.'],
            'dark_muted' => ['Secondary text', 'color', '#c9c5ea', '--c-dark-muted', null],
            'dark_dim' => ['Labels and hints', 'color', '#a9a5cc', '--c-dark-dim', null],
            'dark_accent' => ['Buttons and accent', 'color', '#a99cff', '--c-dark-accent', null],
            'dark_on_accent' => ['Text on buttons', 'color', '#120e33', '--c-dark-on-accent', null],
        ],
        'Messages' => [
            'success' => ['Success', 'color', '#0f8a5f', '--c-success', 'Ticks, "ok" notes and positive numbers.'],
            'danger' => ['Errors', 'color', '#b42318', '--c-danger', 'Form errors and error messages.'],
            'danger_bg' => ['Error background', 'color', '#fff4f2', '--c-danger-bg', null],
        ],
        'Fonts' => [
            'font_heading' => ['Headings', 'font', 'Roboto', '--f-heading', 'Any Google Fonts family name, e.g. Inter, Poppins, Playfair Display.'],
            'font_body' => ['Body text and buttons', 'font', 'Roboto', '--f-body', null],
            'font_label' => ['Small uppercase labels', 'font', 'Roboto', '--f-label', null],
            'heading_weight' => ['Heading weight', 'weight', '700', '--fw-heading', 'Large headings and display text.'],
            'body_size' => ['Body text size', 'px', '16', '--fs-body', 'In pixels (14–20).'],
        ],
        'Shape' => [
            'radius' => ['Card corner radius', 'px', '18', '--radius', 'In pixels (0–40).'],
            'button_radius' => ['Button corner radius', 'px', '12', '--radius-btn', '999 for pill buttons, 0 for square.'],
            'page_width' => ['Page width', 'px', '1360', '--wrap', 'The widest the content gets on large screens (1000–1600).'],
        ],
    ];

    /** Suggestions shown in the font fields; any Google Fonts family works. */
    public const FONTS = ['Roboto', 'Inter', 'Open Sans', 'Lato', 'Montserrat', 'Poppins', 'Nunito', 'Work Sans', 'DM Sans', 'Manrope', 'Plus Jakarta Sans', 'Outfit', 'Rubik', 'Raleway', 'Source Sans 3', 'IBM Plex Sans', 'Space Grotesk', 'Playfair Display', 'Lora', 'Merriweather', 'Instrument Serif', 'DM Serif Display', 'Fraunces', 'Roboto Mono', 'JetBrains Mono'];

    public const WEIGHTS = ['300' => 'Light', '400' => 'Regular', '500' => 'Medium', '600' => 'Semibold', '700' => 'Bold'];

    private const LIMITS = ['body_size' => [14, 20], 'radius' => [0, 40], 'button_radius' => [0, 999], 'page_width' => [1000, 1600]];

    /** @return array<string, array{0: string, 1: string, 2: string, 3: string, 4: ?string}> key => field */
    public static function fields(): array
    {
        return array_merge(...array_values(self::FIELDS));
    }

    public static function defaults(): array
    {
        return array_map(fn ($f) => $f[2], self::fields());
    }

    /** The saved design over the defaults, plus the Google Fonts request that loads its fonts. */
    public static function get(): array
    {
        $saved = self::saved();

        return array_merge(self::defaults(), array_intersect_key($saved, self::fields()));
    }

    /** The saved settings; the defaults when none are saved or the database can't be read (error pages). */
    private static function saved(): array
    {
        try {
            return Cache::rememberForever(self::KEY, fn () => (array) json_decode((string) DB::table('platform_settings')->where('key', 'site_theme')->value('value'), true));
        } catch (Throwable) {
            return [];
        }
    }

    /**
     * Cleans and saves a design. Colours must be #rrggbb, fonts a Google Fonts family name, sizes
     * within their limits; anything else keeps its current value. Returns the fields that were rejected.
     *
     * @return list<string>
     */
    public static function save(array $input): array
    {
        $current = self::get();
        $clean = [];
        $rejected = [];
        $groupOf = [];
        foreach (self::FIELDS as $group => $fields) {
            foreach (array_keys($fields) as $key) {
                $groupOf[$key] = $group;
            }
        }
        foreach (self::fields() as $key => [$label, $type]) {
            $value = trim((string) ($input[$key] ?? $current[$key]));
            $ok = match ($type) {
                'color' => (bool) preg_match('/^#[0-9a-f]{6}$/i', $value),
                'font' => (bool) preg_match('/^[A-Za-z0-9][A-Za-z0-9 ]{0,39}$/', $value),
                'weight' => isset(self::WEIGHTS[$value]),
                'px' => ctype_digit($value) && (int) $value >= self::LIMITS[$key][0] && (int) $value <= self::LIMITS[$key][1],
                default => false,
            };
            if ($ok && $type === 'font' && $value !== $current[$key] && self::fontQuery($value) === null) {
                $ok = false;
            }
            if (! $ok) {
                $rejected[] = $groupOf[$key].' → '.$label;
                $value = $current[$key];
            }
            $clean[$key] = $type === 'color' ? strtolower($value) : $value;
        }
        // How each font family is requested from Google Fonts (not every family has every weight).
        $clean['font_queries'] = collect(['font_heading', 'font_body', 'font_label'])->map(fn ($k) => $clean[$k])->unique()
            ->mapWithKeys(fn ($family) => [$family => self::saved()['font_queries'][$family] ?? self::fontQuery($family) ?? str_replace(' ', '+', $family)])->all();

        self::store($clean);

        return $rejected;
    }

    public static function reset(): void
    {
        self::store([]);
    }

    private static function store(array $values): void
    {
        DB::table('platform_settings')->updateOrInsert(['key' => 'site_theme'], ['value' => json_encode($values), 'updated_at' => now(), 'created_at' => now()]);
        Cache::forget(self::KEY);
    }

    /**
     * The Google Fonts query for a family: every weight the site uses plus italics when the family
     * has them, else whatever it offers. Null when Google Fonts doesn't know the family.
     */
    public static function fontQuery(string $family): ?string
    {
        $name = str_replace(' ', '+', $family);
        foreach ([$name.':ital,wght@0,300;0,400;0,500;0,600;0,700;1,400;1,500', $name.':wght@300;400;500;600;700', $name.':ital@0;1', $name] as $query) {
            try {
                if (Http::timeout(5)->get('https://fonts.googleapis.com/css2?family='.$query.'&display=swap')->successful()) {
                    return $query;
                }
            } catch (Throwable) {
                return null;
            }
        }

        return null;
    }

    /** The stylesheet URL that loads the design's fonts. */
    public static function fontsUrl(): string
    {
        $theme = self::get();
        $queries = (array) (self::saved()['font_queries'] ?? []);
        $families = array_unique([$theme['font_heading'], $theme['font_body'], $theme['font_label']]);
        $default = 'Roboto:ital,wght@0,300;0,400;0,500;0,600;0,700;1,400;1,500';

        return 'https://fonts.googleapis.com/css2?'.implode('&', array_map(fn ($f) => 'family='.($queries[$f] ?? ($f === 'Roboto' ? $default : str_replace(' ', '+', $f))), $families)).'&display=swap';
    }

    /** The design as CSS variables for :root. */
    public static function css(): string
    {
        $theme = self::get();
        $vars = [];
        foreach (self::fields() as $key => [, $type, , $var]) {
            $value = $theme[$key];
            $vars[] = $var.': '.match ($type) {
                'font' => '"'.$value.'"',
                'px' => $value.'px',
                default => $value,
            };
        }

        return ':root { '.implode('; ', $vars).'; }';
    }
}
