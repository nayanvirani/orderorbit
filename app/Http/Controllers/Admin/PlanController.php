<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\AuditLog;
use App\Models\Plan;
use App\Models\Store;
use App\Services\Usage;
use App\Support\Modules;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

/**
 * Plans and modules: prices, sales limits, usage limits, which modules each plan includes and
 * what the pricing pages say. Plans are never deleted (stores may be on them): turn them off.
 */
class PlanController extends Controller
{
    public function index(): View
    {
        $plans = Plan::orderBy('position')->orderBy('id')->get();
        $stores = Store::whereNotNull('installed_at')->whereNull('uninstalled_at')->get();

        return view('admin.plans.index', [
            'plans' => $plans,
            'storeCounts' => $stores->groupBy(fn ($s) => $s->effectivePlan() ?? 'none')->map->count(),
            'customized' => $stores->filter(fn ($s) => ! empty($s->entitlements))->count(),
        ]);
    }

    public function create(): View
    {
        return view('admin.plans.edit', ['plan' => new Plan(['modules' => array_keys(Modules::ALL), 'limits' => [], 'features' => [], 'is_public' => true, 'is_active' => true, 'position' => Plan::max('position') + 1])]);
    }

    public function edit(int $plan): View
    {
        return view('admin.plans.edit', ['plan' => Plan::findOrFail($plan)]);
    }

    public function store(Request $request): RedirectResponse
    {
        $plan = new Plan;
        $this->fill($plan, $request, true)->save();
        AuditLog::record('admin.plan_created', null, ['plan' => $plan->key, 'by' => $request->user()->email]);

        return redirect()->route('admin.plans')->with('status', "Plan “{$plan->name}” created. Add a plan with the same name to Managed Pricing in the Partner Dashboard so Shopify can charge it.");
    }

    public function update(Request $request, int $plan): RedirectResponse
    {
        $plan = Plan::findOrFail($plan);
        $before = $plan->only(['price', 'sales_limit', 'modules', 'limits', 'is_active', 'is_public']);
        $this->fill($plan, $request, false)->save();
        AuditLog::record('admin.plan_updated', null, ['plan' => $plan->key, 'before' => $before, 'after' => $plan->only(array_keys($before)), 'by' => $request->user()->email]);

        return redirect()->route('admin.plans')->with('status', "Plan “{$plan->name}” saved.");
    }

    /** The plan × module grid on the Plans page. */
    public function matrix(Request $request): RedirectResponse
    {
        $grid = (array) $request->input('grid', []);
        foreach (Plan::all() as $plan) {
            $modules = array_values(array_intersect(array_keys(Modules::ALL), array_keys(array_filter((array) ($grid[$plan->key] ?? [])))));
            if ($modules !== array_values($plan->modules ?? [])) {
                $plan->forceFill(['modules' => $modules])->save();
                AuditLog::record('admin.plan_modules_changed', null, ['plan' => $plan->key, 'modules' => $modules, 'by' => $request->user()->email]);
            }
        }

        return redirect()->route('admin.plans')->with('status', 'Modules saved. Stores pick up the change on their next page load.');
    }

    private function fill(Plan $plan, Request $request, bool $creating): Plan
    {
        $data = $request->validate([
            'key' => $creating ? ['required', 'regex:/^[a-z0-9_]{2,40}$/', Rule::unique('plans', 'key')] : ['prohibited'],
            'name' => ['required', 'string', 'max:80'],
            'shopify_name' => ['required', 'string', 'max:120'],
            'price' => ['required', 'numeric', 'min:0', 'max:100000'],
            'trial_days' => ['nullable', 'integer', 'min:0', 'max:365'],
            'sales_limit' => ['nullable', 'numeric', 'min:0'],
            'modules' => ['array'], 'modules.*' => [Rule::in(array_keys(Modules::ALL))],
            'limits' => ['array'], 'limits.*' => ['nullable', 'integer', 'min:0'],
            'features' => ['nullable', 'string', 'max:4000'],
            'description' => ['nullable', 'string', 'max:300'],
            'position' => ['nullable', 'integer', 'min:0', 'max:1000'],
        ]);
        if ($creating) {
            $plan->key = $data['key'];
        }
        $limits = [];
        foreach (array_keys(Usage::METERS) as $meter) {
            $value = $data['limits'][$meter] ?? null;
            $limits[$meter] = $value === null || $value === '' ? null : (int) $value;
        }

        return $plan->fill([
            'name' => $data['name'], 'shopify_name' => $data['shopify_name'], 'price' => $data['price'],
            'trial_days' => (int) ($data['trial_days'] ?? 0), 'sales_limit' => $data['sales_limit'] ?? null,
            'modules' => array_values($data['modules'] ?? []), 'limits' => $limits,
            'features' => array_values(array_filter(array_map('trim', preg_split('/\R/', (string) ($data['features'] ?? ''))))),
            'description' => $data['description'] ?? null, 'position' => (int) ($data['position'] ?? $plan->position ?? 0),
            'is_public' => $request->boolean('is_public'), 'is_active' => $request->boolean('is_active'),
        ]);
    }
}
