<?php

namespace App\Http\Controllers\Admin;

use App\Experiences\Registry;
use App\Http\Controllers\Controller;
use App\Http\Controllers\GoodbyeController;
use App\Models\Experience;
use App\Models\Store;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\View\View;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * Usage insights (G27): installs, activation, plan mix, feature use and why stores uninstalled.
 * Every number counts stores from the store list, so totals match it.
 */
class UsageInsightsController extends Controller
{
    private const RANGES = ['30' => 'Last 30 days', '90' => 'Last 90 days', 'all' => 'All time'];

    public function index(Request $request): View
    {
        $range = array_key_exists((string) $request->query('range'), self::RANGES) ? (string) $request->query('range') : '30';
        $from = $range === 'all' ? null : now()->subDays((int) $range);
        $stores = Store::get(['id', 'shop_domain', 'name', 'plan', 'plan_expires_at', 'installed_at', 'uninstalled_at', 'entitlements']);
        $active = $stores->filter(fn (Store $s) => $s->installed_at && ! $s->uninstalled_at);
        $inRange = fn ($date) => $date && (! $from || $date->gte($from));
        $installed = $stores->filter(fn (Store $s) => $inRange($s->installed_at));

        // Activation for stores installed in the range: created a widget, a widget live in the theme, paid.
        $ids = $installed->pluck('id');
        $created = Experience::whereIn('store_id', $ids)->distinct()->pluck('store_id');
        $placed = Experience::whereIn('store_id', $ids)->where('status', 'published')->whereIn('placement_status', ['placed', 'external'])->distinct()->pluck('store_id');
        $paid = $installed->filter(fn (Store $s) => ! in_array($s->effectivePlan(), [null, 'free'], true));
        $n = max(1, $installed->count());
        $funnel = [
            ['Installed', $installed->count()],
            ['Created a widget', $created->count()],
            ['Widget live in theme', $placed->count()],
            ['Upgraded to a paid plan', $paid->count()],
        ];

        // Plan mix of stores that have the app now.
        $plans = (array) config('shopify.billing.plans');
        $mix = $active->groupBy(fn (Store $s) => $s->effectivePlan() ?? 'none')->map->count();
        $mixRows = collect(array_keys($plans))->push('none')->map(fn ($k) => ['plan' => $k === 'none' ? 'No plan chosen' : $plans[$k]['name'], 'n' => (int) ($mix[$k] ?? 0)])
            ->filter(fn ($r) => $r['n'] > 0 || $r['plan'] !== 'No plan chosen')->values();

        // Feature use: active stores with something live of each kind, and how many started in the range.
        $activeIds = $active->pluck('id');
        $live = Experience::whereIn('store_id', $activeIds)->where('status', 'published')->get(['store_id', 'type'])->groupBy('type');
        $firsts = Experience::whereIn('store_id', $activeIds)->selectRaw('store_id, type, min(created_at) as first_at')->groupBy('store_id', 'type')->get()->groupBy('type');
        $features = collect(Registry::features())->map(function ($f, $key) use ($live, $firsts, $active, $from) {
            $using = collect($f['types'])->flatMap(fn ($t) => ($live[$t] ?? collect())->pluck('store_id'))->unique()->count();
            $new = collect($f['types'])->flatMap(fn ($t) => ($firsts[$t] ?? collect())->filter(fn ($r) => ! $from || \Carbon\Carbon::parse($r->first_at)->gte($from))->pluck('store_id'))->unique()->count();

            return ['name' => $f['label'], 'n' => $using, 'p' => $active->count() ? round($using / $active->count() * 100) : 0, 'new' => $new];
        })->values();
        $features = $features->concat($this->extraFeatures($activeIds, $active->count(), $from))->sortByDesc('n')->values();

        $reasons = DB::table('uninstall_feedback')->when($from, fn ($q) => $q->where('updated_at', '>=', $from))
            ->selectRaw('reason, count(*) as n')->groupBy('reason')->orderByDesc('n')->get()
            ->map(fn ($r) => ['r' => GoodbyeController::REASONS[$r->reason] ?? $r->reason, 'n' => (int) $r->n]);
        $notes = DB::table('uninstall_feedback')->when($from, fn ($q) => $q->where('updated_at', '>=', $from))->whereNotNull('note')->latest('updated_at')->limit(10)->get(['reason', 'note', 'updated_at']);

        return view('admin.usage', [
            'range' => $range, 'ranges' => self::RANGES,
            'kpis' => [
                ['Installs', $installed->count(), self::RANGES[$range]],
                ['Stores with the app', $active->count(), $stores->count().' in the store list'],
                ['Activation', round($placed->count() / $n * 100).'%', 'installs with a widget live in the theme'],
                ['On a paid plan', $active->filter(fn (Store $s) => ! in_array($s->effectivePlan(), [null, 'free'], true))->count(), 'stores with the app now'],
                ['Uninstalls', $stores->filter(fn (Store $s) => $inRange($s->uninstalled_at))->count(), self::RANGES[$range]],
            ],
            'funnel' => array_map(fn ($f) => ['label' => $f[0], 'count' => $f[1], 'pct' => $installed->count() ? round($f[1] / $n * 100) : 0], $funnel),
            'mix' => $mixRows, 'activeCount' => $active->count(), 'features' => $features, 'reasons' => $reasons, 'notes' => $notes,
        ]);
    }

    /** Automation, A/B tests and product documents, counted like the offers. */
    private function extraFeatures(Collection $ids, int $active, $from): array
    {
        $row = fn (string $name, Collection $using, Collection $new) => ['name' => $name, 'n' => $using->unique()->count(), 'p' => $active ? round($using->unique()->count() / $active * 100) : 0, 'new' => $new->unique()->count()];
        $wf = \App\Models\Automation\Workflow::whereIn('store_id', $ids);
        $tests = \App\Models\Experiments\Experiment::whereIn('store_id', $ids);
        $docs = \App\Models\Documents\ProductDocument::whereIn('store_id', $ids);

        return [
            $row('Automation workflows (on)', (clone $wf)->where('status', 'enabled')->pluck('store_id'), (clone $wf)->when($from, fn ($q) => $q->where('created_at', '>=', $from))->pluck('store_id')),
            $row('A/B tests (running)', (clone $tests)->where('status', 'running')->pluck('store_id'), (clone $tests)->when($from, fn ($q) => $q->where('created_at', '>=', $from))->pluck('store_id')),
            $row('Product documents', (clone $docs)->where('active', true)->pluck('store_id'), (clone $docs)->when($from, fn ($q) => $q->where('created_at', '>=', $from))->pluck('store_id')),
        ];
    }

    /** One row per store: plan, install dates, live widgets and what it uses. */
    public function export(): StreamedResponse
    {
        $live = Experience::where('status', 'published')->get(['store_id', 'type'])->groupBy('store_id');
        $reasons = DB::table('uninstall_feedback')->pluck('reason', 'store_id');

        return response()->streamDownload(function () use ($live, $reasons) {
            $out = fopen('php://output', 'w');
            fputcsv($out, ['Store', 'Name', 'Plan', 'Installed', 'Uninstalled', 'Live widgets', 'Live widget types', 'Uninstall reason']);
            Store::orderBy('id')->each(function (Store $s) use ($out, $live, $reasons) {
                $types = ($live[$s->id] ?? collect())->pluck('type');
                fputcsv($out, [$s->shop_domain, $s->name, $s->effectivePlan() ?? '', $s->installed_at?->toDateString(), $s->uninstalled_at?->toDateString(),
                    $types->count(), $types->unique()->implode(' '), GoodbyeController::REASONS[$reasons[$s->id] ?? ''] ?? '']);
            });
            fclose($out);
        }, 'growvia-usage-'.now()->format('Y-m-d').'.csv', ['Content-Type' => 'text/csv']);
    }
}
