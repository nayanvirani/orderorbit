<?php

namespace App\Support\Spa;

use Illuminate\Contracts\Support\Responsable;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * A page of the React admin (resources/app/pages/{component}.jsx).
 *
 * In-app navigation fetches the URL with X-OO-Page and gets {component, props, shared} as JSON.
 * A full page load gets the React shell with the same data embedded, so it's one round trip.
 */
class Page implements Responsable
{
    public const HEADER = 'X-OO-Page';

    public function __construct(public readonly string $component, public readonly array $props = [], public readonly int $status = 200) {}

    public function toResponse($request): Response
    {
        $page = [
            'component' => $this->component,
            'props' => Props::normalize($this->props),
            'shared' => Shared::for($request),
            'url' => self::url($request),
        ];

        if ($request->headers->has(self::HEADER)) {
            return response()->json($page, $this->status, ['Vary' => self::HEADER]);
        }

        return response()->view('spa', ['page' => $page], $this->status)->header('Vary', self::HEADER);
    }

    /** The current URL relative to the app, without the embedded-app parameters. */
    public static function url(Request $request): string
    {
        $query = collect($request->query())->except(['shop', 'host', 'id_token', 'embedded', 'hmac', 'locale', 'session', 'timestamp', 'notice', 'charge_id'])->all();

        return '/'.ltrim($request->path(), '/').($query ? '?'.http_build_query($query) : '');
    }

    /**
     * The built files of a page component and the chunks it imports (from the Vite manifest), so
     * the shell can ask for them alongside the main script. The main entry itself is left out.
     *
     * @return list<string>
     */
    public static function preloads(string $component): array
    {
        static $manifest = null;
        $manifest ??= (function () {
            $file = public_path('spa/manifest.json');

            return is_file($file) ? (json_decode((string) file_get_contents($file), true) ?: []) : [];
        })();
        $key = 'pages/'.$component.'.jsx';
        if ($component === '' || ! isset($manifest[$key])) {
            return [];
        }
        $files = [];
        $walk = function (string $k) use (&$walk, &$files, $manifest) {
            $entry = $manifest[$k] ?? null;
            if (! $entry || ! empty($entry['isEntry']) || isset($files[$entry['file']])) {
                return;
            }
            $files[$entry['file']] = true;
            foreach ((array) ($entry['imports'] ?? []) as $import) {
                $walk($import);
            }
        };
        $walk($key);

        return array_map(fn ($f) => asset('spa/'.$f), array_keys($files));
    }
}
