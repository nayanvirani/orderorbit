<?php

namespace App\Services\Experiences;

use App\Experiences\Registry;
use App\Models\Experience;
use App\Models\Store;
use App\Services\Shopify\AdminApi;

/**
 * Checks the live theme for OrderOrbit blocks, so the app can warn about
 * "Published but not placed" experiences (section D2) and a disabled app embed.
 */
class PlacementDetector
{
    public function __construct(private readonly AdminApi $api) {}

    /**
     * @return array{types: list<string>, handles: list<string>, embed: bool}
     */
    public function scan(Store $store): array
    {
        $theme = $this->api->graphql($store, '{ themes(first: 1, roles: [MAIN]) { nodes { id } } }')['themes']['nodes'][0]['id'] ?? null;
        if ($theme === null) {
            return ['types' => [], 'handles' => [], 'embed' => false];
        }

        $data = $this->api->graphql($store, <<<'GQL'
            query ($id: ID!) {
              theme(id: $id) {
                files(filenames: ["templates/*.json", "sections/*.json", "config/settings_data.json"], first: 250) {
                  nodes { filename body { ... on OnlineStoreThemeFileBodyText { content } } }
                }
              }
            }
            GQL, ['id' => $theme]);

        $found = ['types' => [], 'handles' => [], 'embed' => false];

        foreach ($data['theme']['files']['nodes'] ?? [] as $file) {
            $json = self::decode($file['body']['content'] ?? '');
            if ($json === null) {
                continue;
            }

            if ($file['filename'] === 'config/settings_data.json') {
                foreach ($json['current']['blocks'] ?? [] as $block) {
                    if (str_contains((string) ($block['type'] ?? ''), '/blocks/app-embed/') && ! ($block['disabled'] ?? false)) {
                        $found['embed'] = true;
                    }
                }

                continue;
            }

            self::walk($json, $found);
        }

        $found['types'] = array_values(array_unique($found['types']));
        $found['handles'] = array_values(array_unique($found['handles']));

        return $found;
    }

    /**
     * Scans the theme and records placement for every non-archived experience.
     */
    public function refresh(Store $store): array
    {
        $found = $this->scan($store);

        Experience::where('store_id', $store->id)->where('status', '!=', 'archived')->get()
            ->each(function (Experience $e) use ($found) {
                // Global types (Sales pop) need no block: the app embed shows them on every page.
                $placed = Registry::has($e->type) && Registry::type($e->type)['surface'] === 'global'
                    ? $found['embed']
                    : in_array($e->handle, $found['handles'], true) || in_array($e->type, $found['types'], true);
                $e->forceFill(['placement_status' => $placed ? 'placed' : 'not_placed', 'placement_checked_at' => now()])->save();
            });

        $store->forceFill(['capabilities' => array_merge($store->capabilities ?? [], ['app_embed' => $found['embed']])])->save();

        return $found;
    }

    private static function walk(array $node, array &$found): void
    {
        if (isset($node['type']) && is_string($node['type']) && str_contains($node['type'], '/blocks/experience/') && ! ($node['disabled'] ?? false)) {
            $settings = $node['settings'] ?? [];
            if (! empty($settings['experience_id'])) {
                $found['handles'][] = trim((string) $settings['experience_id']);
            } elseif (! empty($settings['experience_type'])) {
                $found['types'][] = (string) $settings['experience_type'];
            }
        }

        foreach ($node as $value) {
            if (is_array($value)) {
                self::walk($value, $found);
            }
        }
    }

    /**
     * Theme JSON files may start with a /* comment *\/ header.
     */
    private static function decode(string $content): ?array
    {
        $content = preg_replace('#^\s*/\*.*?\*/#s', '', $content);
        $json = json_decode((string) $content, true);

        return is_array($json) ? $json : null;
    }
}
