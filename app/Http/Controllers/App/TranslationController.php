<?php

namespace App\Http\Controllers\App;

use App\Http\Controllers\Controller;
use App\Models\Experience;
use App\Models\Store;
use App\Services\Experiences\StorefrontPublisher;
use App\Services\Translations\OfferTranslations;
use App\Support\Features;
use App\Support\OptionalAccess;
use App\Support\Plans;
use App\Support\Spa\Page;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;

/** Offers › Translations (G22): the live offers' text in each store language. */
class TranslationController extends Controller
{
    public function __construct(private readonly OfferTranslations $translations) {}

    public function show(Request $request, Store $store, StorefrontPublisher $publisher): Page
    {
        abort_unless(Features::enabled('translations', $store), 404);
        $access = OptionalAccess::granted($store, 'read_locales');
        $languages = $access ? $this->translations->languages($store) : [];
        $locale = array_key_exists((string) $request->query('locale'), $languages) ? (string) $request->query('locale') : (array_key_first($languages) ?? null);
        $offers = [];
        if ($locale) {
            $saved = OfferTranslations::forStore($store);
            $names = Experience::where('store_id', $store->id)->pluck('name', 'handle');
            foreach ($publisher->payload($store)['experiences'] as $e) {
                $texts = OfferTranslations::texts((array) ($e['content'] ?? []));
                if (! $texts) {
                    continue;
                }
                $mine = $saved[$e['id']][$locale] ?? [];
                $offers[] = ['handle' => $e['id'], 'name' => $names[$e['id']] ?? $e['id'], 'type' => $e['type'],
                    'fields' => array_map(fn ($path, $text) => ['path' => $path, 'source' => $text, 'translation' => $mine[$path][1] ?? '',
                        'outdated' => isset($mine[$path]) && $mine[$path][0] !== $text], array_keys($texts), $texts)];
            }
        }

        return page('cro/translations', [
            'access' => $access, 'languages' => $languages, 'locale' => $locale, 'offers' => $offers,
            'locked' => $store->planIncludes('translations') ? null : Plans::unlockMessage('translations', 'translate offers', $store),
        ]);
    }

    /** Saves one offer's translations for a language and republishes the storefront. */
    public function save(Request $request, Store $store, StorefrontPublisher $publisher): RedirectResponse
    {
        abort_unless(Features::enabled('translations', $store), 404);
        abort_unless($store->planIncludes('translations'), 403, Plans::unlockMessage('translations', 'translate offers', $store));
        $data = $request->validate(['locale' => ['required', 'string', 'max:12'], 'handle' => ['required', 'string', 'max:120'], 'texts' => ['array'], 'texts.*' => ['nullable', 'string', 'max:1000']]);
        abort_unless(array_key_exists($data['locale'], $this->translations->languages($store)), 422);
        $offer = Experience::where('store_id', $store->id)->where('handle', $data['handle'])->firstOrFail();
        $live = collect($publisher->payload($store)['experiences'])->firstWhere('id', $offer->handle) ?? throw ValidationException::withMessages(['handle' => 'Only live offers can be translated. Publish it first.']);
        $sources = OfferTranslations::texts((array) ($live['content'] ?? []));
        $map = [];
        foreach ((array) ($data['texts'] ?? []) as $path => $text) {
            if (isset($sources[$path]) && trim((string) $text) !== '') {
                $map[$path] = [$sources[$path], trim((string) $text)];
            }
        }
        $all = (array) $offer->translations;
        $all[$data['locale']] = $map;
        $offer->forceFill(['translations' => array_filter($all)])->save();
        $publisher->publish($store);

        return redirect()->to(app_route('app.cro.translations', ['locale' => $data['locale'], 'notice' => 'saved']));
    }
}
