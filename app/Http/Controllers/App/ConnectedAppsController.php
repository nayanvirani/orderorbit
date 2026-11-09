<?php

namespace App\Http\Controllers\App;

use App\Http\Controllers\Controller;
use App\Models\AuditLog;
use App\Models\Integrations\StoreIntegration;
use App\Models\Store;
use App\Services\Integrations\Ga4;
use App\Support\Features;
use App\Support\Plans;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;

/**
 * Settings › Integrations › Connected apps (phase 6): connect with the app's own keys, choose what
 * to share, send a test, see recent activity, disconnect (keys deleted at once).
 */
class ConnectedAppsController extends Controller
{
    /** provider => [name, plan module, what it does, data shared, key fields [name => [label, help]]] */
    public const APPS = [
        'ga4' => ['Google Analytics 4', 'klaviyo_ga4', 'Offer views, adds from an offer, purchases with an offer and A/B test versions appear in GA4 next to your other traffic.',
            'Events with the shopper\'s anonymous GA client id. No names or emails. Nothing is sent without the shopper\'s analytics consent.',
            ['measurement_id' => ['Measurement ID', 'GA4 › Admin › Data streams › your web stream, e.g. G-XXXXXXXXXX.'], 'api_secret' => ['Measurement Protocol API secret', 'Same page › Measurement Protocol API secrets › Create.']],
            Ga4::EVENTS],
        'klaviyo' => ['Klaviyo', 'klaviyo_ga4', 'Growvia events become Klaviyo metrics you can start flows from (e.g. "Growvia: Reward unlocked"), and the segments you choose sync to Klaviyo lists every day.',
            'The signed-in customer\'s email with each event, and segment members as lists. Only customers who accept email marketing are added to lists.',
            ['api_key' => ['Klaviyo private API key', 'Klaviyo › Settings › API keys › Create private API key, with full access to Lists, Profiles and Events.']],
            \App\Services\Integrations\Klaviyo::EVENTS],
    ];

    /** The apps for the Integrations page. */
    public static function list(Store $store): array
    {
        if (! Features::enabled('integrations', $store)) {
            return [];
        }
        $rows = StoreIntegration::where('store_id', $store->id)->get()->keyBy('provider');

        return collect(self::APPS)->map(function ($a, $key) use ($rows, $store) {
            $i = $rows[$key] ?? null;

            return ['key' => $key, 'name' => $a[0], 'what' => $a[2], 'data' => $a[3], 'fields' => collect($a[4])->map(fn ($f, $name) => ['name' => $name, 'label' => $f[0], 'help' => $f[1]])->values()->all(),
                'events' => collect($a[5])->map(fn ($label, $name) => ['name' => $name, 'label' => $label, 'on' => (bool) ($i?->setting('events', [])[$name] ?? false)])->values()->all(),
                // Klaviyo: segments that can sync to lists (every rule is a customer search field).
                'segments' => $key === 'klaviyo' && $i ? \App\Models\Audiences\Segment::where('store_id', $store->id)->get()
                    ->filter(fn ($s) => \App\Services\Audiences\Audiences::countable($s) && $s->rules)
                    ->map(fn ($s) => ['id' => $s->id, 'name' => $s->name, 'on' => in_array($s->id, (array) $i->setting('segments', []), true)])->values()->all() : null,
                'connected' => (bool) $i, 'status' => $i?->status, 'error' => $i?->last_error, 'log' => array_slice((array) ($i?->log ?? []), 0, 8),
                'locked' => $store->planIncludes($a[1]) ? null : Plans::unlockMessage($a[1], 'connect it', $store)];
        })->values()->all();
    }

    public function connect(Request $request, Store $store, string $app, Ga4 $ga4, \App\Services\Integrations\Klaviyo $klaviyo): RedirectResponse
    {
        $this->gate($store, $app);
        $data = $request->validate(collect(self::APPS[$app][4])->mapWithKeys(fn ($f, $name) => [$name => ['required', 'string', 'max:200']])->all());
        $error = match ($app) {
            'ga4' => $ga4->connect($store, $data['measurement_id'], $data['api_secret']),
            'klaviyo' => $klaviyo->connect($store, $data['api_key']),
        };
        if ($error) {
            throw ValidationException::withMessages(['connect' => $error]);
        }
        AuditLog::record('integration.connected', $store, ['app' => $app]);

        return redirect()->to(app_route('app.settings.integrations', ['notice' => 'saved']));
    }

    public function test(Request $request, Store $store, string $app, Ga4 $ga4, \App\Services\Integrations\Klaviyo $klaviyo): RedirectResponse
    {
        $i = $this->gate($store, $app, true);
        $error = match ($app) {
            'ga4' => $ga4->test($i),
            // A test event for the person clicking.
            'klaviyo' => $klaviyo->test($i, (string) ($request->attributes->get('storeUser')?->email ?: $store->email)),
        };
        if ($error) {
            throw ValidationException::withMessages(['test' => $error]);
        }

        return redirect()->to(app_route('app.settings.integrations', ['notice' => 'saved']));
    }

    /** Which events are shared. */
    public function settings(Request $request, Store $store, string $app): RedirectResponse
    {
        $i = $this->gate($store, $app, true);
        $events = array_intersect_key(array_map('boolval', (array) $request->input('events', [])), self::APPS[$app][5]);
        $settings = ['events' => $events + array_fill_keys(array_keys(self::APPS[$app][5]), false)];
        if ($app === 'klaviyo') {
            $settings['segments'] = \App\Models\Audiences\Segment::where('store_id', $store->id)->whereIn('id', array_map('intval', (array) $request->input('segments', [])))->pluck('id')->all();
        }
        $i->forceFill(['settings' => array_merge((array) $i->settings, $settings)])->save();

        return redirect()->to(app_route('app.settings.integrations', ['notice' => 'saved']));
    }

    /** Disconnects: the keys are deleted at once. */
    public function disconnect(Store $store, string $app): RedirectResponse
    {
        abort_unless(isset(self::APPS[$app]), 404);
        StoreIntegration::where('store_id', $store->id)->where('provider', $app)->delete();
        AuditLog::record('integration.disconnected', $store, ['app' => $app]);

        return redirect()->to(app_route('app.settings.integrations', ['notice' => 'saved']));
    }

    private function gate(Store $store, string $app, bool $connected = false): ?StoreIntegration
    {
        abort_unless(isset(self::APPS[$app]) && Features::enabled('integrations', $store), 404);
        abort_unless($store->planIncludes(self::APPS[$app][1]), 403, Plans::unlockMessage(self::APPS[$app][1], 'connect it', $store));

        return $connected ? StoreIntegration::where('store_id', $store->id)->where('provider', $app)->firstOrFail() : null;
    }
}
