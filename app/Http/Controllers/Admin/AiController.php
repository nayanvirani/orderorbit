<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Ai\AiProvider;
use App\Models\AuditLog;
use App\Services\Ai\Ai;
use App\Support\AiSettings;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

/**
 * Internal Admin › AI providers: the services Growvia's AI features use (free tiers welcome),
 * tried in order with failover; each plan's monthly AI budget and a request cap per store.
 */
class AiController extends Controller
{
    public function index(): View
    {
        $since = now()->startOfMonth();
        $providers = AiProvider::orderBy('priority')->orderBy('id')->get();
        $usage = $providers->mapWithKeys(fn (AiProvider $p) => [$p->id => $p->usage()])->all();

        return view('admin.ai.index', [
            'providers' => $providers, 'usage' => $usage, 'limits' => AiProvider::LIMITS,
            'allBlocked' => $providers->isNotEmpty() && ! $providers->contains(fn (AiProvider $p) => $p->usable() && $p->limitReached(0, $usage[$p->id]) === null),
            'settings' => AiSettings::get(), 'presets' => AiProvider::PRESETS,
            'envKey' => (string) config('services.anthropic.key') !== '',
            'stats' => [
                'requests' => DB::table('ai_usage')->where('created_at', '>=', $since)->count(),
                'failed' => DB::table('ai_usage')->where('created_at', '>=', $since)->where('outcome', 'failed')->count(),
                'cost' => round((float) DB::table('ai_usage')->where('created_at', '>=', $since)->sum('cost'), 2),
                'stores' => DB::table('ai_usage')->where('created_at', '>=', $since)->distinct()->count('store_id'),
            ],
            'recent' => DB::table('ai_usage')->orderByDesc('id')->limit(15)->get(),
        ]);
    }

    public function saveSettings(Request $request): RedirectResponse
    {
        $data = $request->validate(['budget_premium' => ['required', 'numeric', 'min:0', 'max:1000'], 'requests_per_store' => ['required', 'integer', 'min:0', 'max:100000']]);
        AiSettings::save(['enabled' => $request->boolean('enabled'), 'budget_premium' => (float) $data['budget_premium'], 'requests_per_store' => (int) $data['requests_per_store']]);
        AuditLog::record('admin.ai_settings_saved', null, ['by' => $request->user()->email]);

        return back()->with('status', 'AI settings saved.');
    }

    public function create(Request $request): View
    {
        $preset = array_key_exists((string) $request->query('preset'), AiProvider::PRESETS) ? (string) $request->query('preset') : 'gemini';

        return view('admin.ai.form', ['provider' => null, 'preset' => $preset, 'presets' => AiProvider::PRESETS, 'presets_limits' => AiProvider::SUGGESTED_LIMITS, 'limits' => AiProvider::LIMITS]);
    }

    public function edit(AiProvider $provider): View
    {
        return view('admin.ai.form', ['provider' => $provider, 'preset' => $provider->preset, 'presets' => AiProvider::PRESETS, 'presets_limits' => AiProvider::SUGGESTED_LIMITS, 'limits' => AiProvider::LIMITS]);
    }

    public function store(Request $request, Ai $ai): RedirectResponse
    {
        $provider = new AiProvider(['priority' => (int) AiProvider::max('priority') + 1]);

        return $this->save($request, $provider, $ai, true);
    }

    public function update(Request $request, AiProvider $provider, Ai $ai): RedirectResponse
    {
        return $this->save($request, $provider, $ai, false);
    }

    private function save(Request $request, AiProvider $provider, Ai $ai, bool $new): RedirectResponse
    {
        $preset = (string) $request->input('preset', $provider->preset);
        abort_unless(array_key_exists($preset, AiProvider::PRESETS), 422);
        [$label, $driver, $baseUrl] = AiProvider::PRESETS[$preset];
        $data = $request->validate([
            'name' => ['required', 'string', 'max:80'],
            'model' => ['required', 'string', 'max:120'],
            'base_url' => [$driver === 'openai' && $preset === 'custom' ? 'required' : 'nullable', 'nullable', 'url', 'starts_with:https://,http://', 'max:300'],
            'api_key' => [$new ? 'required' : 'nullable', 'nullable', 'string', 'max:300'],
            'price_in' => ['nullable', 'numeric', 'min:0', 'max:1000'],
            'price_out' => ['nullable', 'numeric', 'min:0', 'max:1000'],
            ...array_fill_keys(array_keys(AiProvider::LIMITS), ['nullable', 'integer', 'min:1', 'max:100000000000']),
        ]);
        $provider->fill([
            'name' => $data['name'], 'preset' => $preset, 'driver' => $driver, 'model' => trim($data['model']),
            'base_url' => $preset === 'custom' ? rtrim((string) $data['base_url'], '/') : $baseUrl,
            'price_in' => (float) ($data['price_in'] ?? 0), 'price_out' => (float) ($data['price_out'] ?? 0),
            ...collect(AiProvider::LIMITS)->map(fn ($_, $field) => isset($data[$field]) ? (int) $data[$field] : null)->all(),
        ]);
        if (! empty($data['api_key'])) {
            $provider->credentials = ['api_key' => trim($data['api_key'])];
        }
        $provider->save();
        AuditLog::record($new ? 'admin.ai_provider_added' : 'admin.ai_provider_updated', null, ['provider' => $provider->name, 'by' => $request->user()->email]);
        // Checked at once, so a wrong key or model shows straight away.
        $error = $ai->test($provider);

        return redirect()->route('admin.ai')->with('status', $error ? "Saved, but the test failed: {$error}" : "{$provider->name} saved and working.");
    }

    public function test(AiProvider $provider, Ai $ai): RedirectResponse
    {
        $error = $ai->test($provider);

        return back()->with('status', $error ? "{$provider->name}: {$error}" : "{$provider->name} answered correctly.");
    }

    public function action(Request $request, AiProvider $provider, string $action): RedirectResponse
    {
        match ($action) {
            'toggle' => $provider->forceFill(['is_active' => ! $provider->is_active])->save(),
            'resume' => $provider->forceFill(['paused_until' => null, 'status' => 'ok', 'last_error' => null])->save(),
            'up', 'down' => $this->move($provider, $action),
            'delete' => $provider->delete(),
        };
        AuditLog::record('admin.ai_provider_'.$action, null, ['provider' => $provider->name, 'by' => $request->user()->email]);

        return redirect()->route('admin.ai')->with('status', $action === 'delete' ? 'Provider removed.' : 'Saved.');
    }

    private function move(AiProvider $provider, string $direction): void
    {
        $list = AiProvider::orderBy('priority')->orderBy('id')->get()->values();
        $i = $list->search(fn ($p) => $p->id === $provider->id);
        $j = $direction === 'up' ? $i - 1 : $i + 1;
        if (! isset($list[$j])) {
            return;
        }
        [$list[$i], $list[$j]] = [$list[$j], $list[$i]];
        $list->each(fn ($p, $n) => $p->forceFill(['priority' => $n + 1])->save());
    }
}
