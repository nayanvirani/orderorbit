<?php

namespace App\Http\Controllers\App;

use App\Experiences\Registry;
use App\Experiences\Schema;
use App\Http\Controllers\Controller;
use App\Models\AuditLog;
use App\Models\Experience;
use App\Models\Store;
use App\Services\Experiences\ExperienceManager;
use App\Services\Experiences\PlacementDetector;
use App\Services\SalesPop\RecentOrders;
use App\Services\Experiences\PublishException;
use App\Services\Experiences\TemplateLibrary;
use App\Services\Usage;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;
use Symfony\Component\HttpFoundation\StreamedResponse;
use Throwable;

class ExperienceController extends Controller
{
    public function __construct(private readonly ExperienceManager $manager) {}

    public function overview(Store $store, Usage $usage): View
    {
        $counts = $store->experiences()->selectRaw('status, count(*) as total')->groupBy('status')->pluck('total', 'status');

        return view('app.cro.overview', [
            'store' => $store,
            'counts' => $counts,
            'byType' => $store->experiences()->notArchived()->selectRaw('type, count(*) as total')->groupBy('type')->pluck('total', 'type'),
            'notPlaced' => $store->experiences()->where('status', 'published')->where('placement_status', 'not_placed')->count(),
            'recent' => $store->experiences()->notArchived()->latest('updated_at')->limit(6)->get(),
            'activeLimit' => $store->hasPlanAccess() ? $store->planLimit('active_experiences') : 0,
            'activeUsed' => $usage->current($store, 'active_experiences'),
            'summary' => app(\App\Services\Analytics\Analytics::class)->summary($store, 30),
            // A live preview of each feature's first template for the feature cards.
            'features' => collect(Registry::features())->map(function ($feature, $key) {
                $type = $feature['types'][0];
                $template = match ($type) {
                    'bundles' => 'qb-classic',
                    'progressive-gifts' => 'pg-steps',
                    'countdown' => 'flip-clock',
                    default => array_key_first(Registry::templates($type)),
                };

                return $feature + ['key' => $key, 'type' => $type, 'preview' => TemplateLibrary::preview($type, $template)];
            })->all(),
        ]);
    }

    public function index(Request $request, Store $store, ?string $type = null): View
    {
        abort_if($type !== null && ! Registry::has($type), 404);

        $filters = [
            'q' => trim((string) $request->query('q', '')),
            'type' => $type ?? (Registry::has($request->query('type')) ? $request->query('type') : null),
            'status' => in_array($request->query('status'), [...Experience::STATUSES, 'scheduled', 'not_placed'], true) ? $request->query('status') : null,
        ];

        $query = $store->experiences()->with('author');
        if ($filters['q'] !== '') {
            $query->where(fn ($q) => $q->where('name', 'like', '%'.$filters['q'].'%')->orWhere('handle', $filters['q']));
        }
        if ($filters['type']) {
            $query->where('type', $filters['type']);
        }
        match ($filters['status']) {
            null => $query->where('status', '!=', 'archived'),
            'scheduled' => $query->where('status', 'published')->where('starts_at', '>', now()),
            'not_placed' => $query->where('status', 'published')->where('placement_status', 'not_placed'),
            default => $query->where('status', $filters['status']),
        };

        return view('app.cro.index', [
            'store' => $store,
            'type' => $type,
            'filters' => $filters,
            'experiences' => $query->latest('updated_at')->paginate(20),
        ]);
    }

    public function create(Request $request, Store $store): View|RedirectResponse
    {
        $type = Registry::has($request->query('type')) ? $request->query('type') : null;
        if ($type === 'bundles' || $type === 'progressive-gifts') {
            return redirect()->to(app_route($type === 'bundles' ? 'app.bundles.types' : 'app.gifts.models'));
        }

        return view('app.cro.create', [
            'store' => $store,
            'type' => $type,
            'previews' => $type ? collect(Registry::templates($type))->map(fn ($t, $key) => TemplateLibrary::preview($type, $key))->values()->all() : [],
        ]);
    }

    public function store(Request $request, Store $store): RedirectResponse
    {
        $type = (string) $request->input('type');
        abort_unless(Registry::has($type), 404);

        $experience = $this->manager->create($store, $type, (string) $request->input('template'), $this->user($request), $request->input('name'));

        return redirect()->to(app_route('app.cro.experiences.edit', ['experience' => $experience->id, 'notice' => 'created']));
    }

    public function show(Request $request, Store $store, int $experience): View
    {
        $experience = $this->find($store, $experience);
        $tab = in_array($request->query('tab'), ['overview', 'configuration', 'targeting', 'analytics', 'experiment', 'history'], true) ? $request->query('tab') : 'overview';

        return view('app.cro.show', [
            'store' => $store,
            'experience' => $experience,
            'tab' => $tab,
            'versions' => $experience->versions()->with('author')->orderByDesc('version')->get(),
            'activity' => AuditLog::with('actor')->where('store_id', $store->id)->where('entity_type', 'Experience')->where('entity_id', $experience->id)->latest('id')->limit(30)->get(),
            'fields' => Schema::fields($experience->type),
        ]);
    }

    public function edit(Request $request, Store $store, int $experience): View|RedirectResponse
    {
        $experience = $this->find($store, $experience);
        if ($experience->type === 'bundles') {
            // Bundles and progressive gifts have their own editors.
            return redirect()->to(app_route('app.bundles.edit', array_filter(['bundle' => $experience->id, 'notice' => $request->query('notice')])));
        }
        if ($experience->type === 'progressive-gifts') {
            return redirect()->to(app_route('app.gifts.edit', array_filter(['gift' => $experience->id, 'notice' => $request->query('notice')])));
        }

        return $this->builder($store, $experience, $experience->draft_config, []);
    }

    public function update(Request $request, Store $store, int $experience): RedirectResponse|View
    {
        $experience = $this->find($store, $experience);
        $action = $request->input('action', 'save');

        [$config, $errors] = Schema::normalize($experience->type, (array) $request->input('config', []), $store->timezone ?? 'UTC');
        $meta = [
            'name' => trim(strip_tags((string) $request->input('name', $experience->name))) ?: $experience->name,
            'description' => trim(strip_tags((string) $request->input('description', ''))) ?: null,
            'template_key' => Registry::template($experience->type, (string) $request->input('template_key')) ? $request->input('template_key') : $experience->template_key,
        ];

        // Drafts save even with problems so work is never lost; publishing requires a clean config.
        $this->manager->saveDraft($experience, $config, $meta, $this->user($request));

        if ($action !== 'publish') {
            return $errors
                ? $this->builder($store, $experience, $config, $errors, 'Draft saved. Some fields need attention before you can publish.')
                : redirect()->to(app_route('app.cro.experiences.edit', ['experience' => $experience->id, 'notice' => 'saved']));
        }

        if ($errors) {
            return $this->builder($store, $experience, $config, $errors, 'Fix the highlighted fields before publishing.');
        }

        return $this->doPublish($experience, $request, 'app.cro.experiences.show');
    }

    public function publish(Request $request, Store $store, int $experience): RedirectResponse
    {
        return $this->doPublish($this->find($store, $experience), $request, 'app.cro.experiences.show');
    }

    public function lifecycle(Request $request, Store $store, int $experience, string $action): RedirectResponse
    {
        $experience = $this->find($store, $experience);

        try {
            match ($action) {
                'pause' => $this->manager->pause($experience),
                'resume' => $this->manager->resume($experience),
                'archive' => $this->manager->archive($experience),
                'unarchive' => $this->manager->unarchive($experience),
                'discard' => $this->manager->discardChanges($experience),
                default => abort(404),
            };
        } catch (PublishException $e) {
            return $this->back($experience, $e->reason === 'plan' ? 'plan_limit' : 'publish_unavailable');
        } catch (Throwable $e) {
            report($e);

            return $this->back($experience, 'shopify');
        }

        $notice = ['pause' => 'paused', 'resume' => 'resumed', 'archive' => 'archived', 'unarchive' => 'unarchived', 'discard' => 'discarded'][$action];

        return $action === 'archive'
            ? redirect()->to(app_route('app.cro.experiences.index', ['notice' => $notice]))
            : $this->back($experience, $notice);
    }

    public function duplicate(Request $request, Store $store, int $experience): RedirectResponse
    {
        $copy = $this->manager->duplicate($this->find($store, $experience), $this->user($request));

        return redirect()->to(app_route('app.cro.experiences.edit', ['experience' => $copy->id, 'notice' => 'duplicated']));
    }

    public function restoreVersion(Request $request, Store $store, int $experience, int $version): RedirectResponse
    {
        $experience = $this->find($store, $experience);
        $this->manager->restoreVersion($experience, $experience->versions()->findOrFail($version), $this->user($request));

        return redirect()->to(app_route('app.cro.experiences.edit', ['experience' => $experience->id, 'notice' => 'version_restored']));
    }

    public function checkPlacement(Request $request, Store $store, int $experience, PlacementDetector $detector): RedirectResponse
    {
        $experience = $this->find($store, $experience);

        try {
            $detector->refresh($store);
        } catch (Throwable $e) {
            report($e);

            return $this->back($experience, 'shopify');
        }

        return $this->back($experience, 'placement_checked');
    }

    /**
     * Sales pop: imports the store's recent orders so pops can start straight away.
     */
    public function importOrders(Request $request, Store $store, int $experience, RecentOrders $orders): RedirectResponse
    {
        $experience = $this->find($store, $experience);
        if (! $store->hasScope('read_orders')) {
            return $this->back($experience, 'orders_scope');
        }

        try {
            $orders->import($store);
        } catch (Throwable $e) {
            report($e);

            return $this->back($experience, 'shopify');
        }

        return $this->back($experience, 'orders_imported');
    }

    public function bulk(Request $request, Store $store): RedirectResponse
    {
        $ids = array_map('intval', (array) $request->input('ids', []));
        $action = $request->input('bulk_action');
        $experiences = $store->experiences()->whereIn('id', $ids)->get();

        try {
            foreach ($experiences as $experience) {
                match ($action) {
                    'pause' => $experience->status === 'published' ? $this->manager->pause($experience) : null,
                    'archive' => $experience->status !== 'archived' ? $this->manager->archive($experience) : null,
                    default => null,
                };
            }
        } catch (Throwable $e) {
            report($e);

            return redirect()->to(app_route('app.cro.experiences.index', ['notice' => 'shopify']));
        }

        return redirect()->to(app_route('app.cro.experiences.index', ['notice' => 'bulk_done']));
    }

    public function export(Store $store): StreamedResponse
    {
        $rows = $store->experiences()->orderBy('id')->get();

        return response()->streamDownload(function () use ($rows) {
            $out = fopen('php://output', 'w');
            fputcsv($out, ['ID', 'Name', 'Type', 'Template', 'Status', 'Placement', 'Published', 'Updated']);
            foreach ($rows as $e) {
                fputcsv($out, [$e->handle, $e->name, Registry::type($e->type)['label'] ?? $e->type, $e->templateName(), $e->displayStatus(), $e->placement_status, $e->published_at?->toDateTimeString(), $e->updated_at?->toDateTimeString()]);
            }
            fclose($out);
        }, 'orderorbit-experiences-'.now()->format('Y-m-d').'.csv', ['Content-Type' => 'text/csv']);
    }

    private function doPublish(Experience $experience, Request $request, string $route): RedirectResponse
    {
        try {
            $this->manager->publish($experience, $this->user($request), $request->input('change_note'));
        } catch (PublishException $e) {
            $notice = match ($e->reason) {
                'plan' => 'plan_limit',
                'invalid' => 'invalid',
                default => 'publish_unavailable',
            };

            return redirect()->to(app_route('app.cro.experiences.edit', ['experience' => $experience->id, 'notice' => $notice]));
        } catch (Throwable $e) {
            report($e);

            return redirect()->to(app_route('app.cro.experiences.edit', ['experience' => $experience->id, 'notice' => 'shopify']));
        }

        // Best effort: flag "published but not placed" straight away.
        try {
            app(PlacementDetector::class)->refresh($experience->store);
        } catch (Throwable $e) {
            report($e);
        }

        return redirect()->to(app_route($route, ['experience' => $experience->id, 'notice' => 'published']));
    }

    private function builder(Store $store, Experience $experience, array $config, array $errors, ?string $banner = null): View
    {
        $type = Registry::type($experience->type);

        return view('app.cro.builder', [
            'store' => $store,
            'experience' => $experience,
            'type' => $type,
            'fields' => Schema::fields($experience->type),
            'config' => $config,
            'fieldErrors' => $errors,
            'banner' => $banner,
            'templates' => $type['templates'],
            'timezone' => $store->timezone ?? 'UTC',
        ]);
    }

    private function find(Store $store, int $id): Experience
    {
        return $store->experiences()->findOrFail($id);
    }

    private function back(Experience $experience, string $notice): RedirectResponse
    {
        return redirect()->to(app_route('app.cro.experiences.show', ['experience' => $experience->id, 'notice' => $notice]));
    }

    private function user(Request $request)
    {
        return $request->attributes->get('storeUser');
    }
}
