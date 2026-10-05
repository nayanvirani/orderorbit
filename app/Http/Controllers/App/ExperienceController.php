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
use App\Services\SalesPop\OrdersBlocked;
use App\Services\SalesPop\RecentOrders;
use App\Services\Experiences\PublishException;
use App\Services\Experiences\TemplateLibrary;
use App\Services\Usage;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use App\Experiences\BundleSchema;
use App\Experiences\GiftSchema;
use App\Support\Spa\Page;
use Symfony\Component\HttpFoundation\StreamedResponse;
use Throwable;

class ExperienceController extends Controller
{
    public function __construct(private readonly ExperienceManager $manager) {}

    public function overview(Store $store, Usage $usage): Page
    {
        $counts = $store->experiences()->selectRaw('status, count(*) as total')->groupBy('status')->pluck('total', 'status');
        $byType = $store->experiences()->notArchived()->selectRaw('type, count(*) as total')->groupBy('type')->pluck('total', 'type');
        $summary = app(\App\Services\Analytics\Analytics::class)->summary($store, 30);

        return page('cro/overview', [
            'counts' => ['draft' => (int) ($counts['draft'] ?? 0), 'paused' => (int) ($counts['paused'] ?? 0)],
            'notPlaced' => $store->experiences()->where('status', 'published')->where('placement_status', 'not_placed')->count(),
            'recent' => $store->experiences()->notArchived()->latest('updated_at')->limit(6)->get()->map(fn ($e) => self::row($e)),
            'activeLimit' => $store->hasPlanAccess() ? $store->planLimit('active_experiences') : 0,
            'activeUsed' => $usage->current($store, 'active_experiences'),
            'revenue' => ['amount' => $summary['influenced_revenue'], 'orders' => $summary['influenced_orders'], 'currency' => $summary['currency'] ?? 'USD'],
            // A live preview of each feature's first template for the feature cards.
            'features' => collect(Registry::features())->map(function ($feature, $key) use ($byType) {
                $type = $feature['types'][0];
                $template = match ($type) {
                    'bundles' => 'qb-classic',
                    'progressive-gifts' => 'pg-steps',
                    'countdown' => 'flip-clock',
                    default => array_key_first(Registry::offered($type)),
                };

                return [
                    'key' => $key, 'label' => $feature['label'], 'icon' => $feature['icon'] ?? 'sparkle', 'tone' => $feature['tone'] ?? 'default',
                    'lead' => strip_tags($feature['lead']), 'count' => (int) collect($feature['types'])->sum(fn ($t) => $byType[$t] ?? 0),
                    'href' => isset($feature['module']) ? route($feature['module'], [], false) : route('app.features.show', ['feature' => $key], false),
                    'preview' => TemplateLibrary::preview($type, $template),
                ];
            })->values(),
        ]);
    }

    public function index(Request $request, Store $store, ?string $type = null): Page
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
        $experiences = $query->latest('updated_at')->paginate(20)->withQueryString();
        $experiences->setCollection($experiences->getCollection()->map(fn ($e) => self::row($e)));
        $typeDef = $type ? Registry::type($type) : null;

        return page('cro/index', [
            'type' => $type,
            'typeDef' => $typeDef ? ['label' => $typeDef['label'], 'singular' => lower_label($typeDef['singular']), 'empty' => $typeDef['empty'] ?? null, 'plural' => lower_label($typeDef['label'])] : null,
            'filters' => $filters,
            'types' => collect(Registry::types())->map(fn ($t) => $t['label'])->all(),
            'experiences' => $experiences,
        ]);
    }

    public function create(Request $request, Store $store): Page|RedirectResponse
    {
        $type = Registry::has($request->query('type')) ? $request->query('type') : null;
        if ($type === 'bundles' || $type === 'progressive-gifts') {
            return redirect()->to(app_route($type === 'bundles' ? 'app.bundles.types' : 'app.gifts.models'));
        }
        $t = $type ? Registry::type($type) : null;

        return page('cro/create', [
            'type' => $type,
            'typeDef' => $t ? [
                'label' => $t['label'], 'singular' => $t['singular'], 'lower' => lower_label($t['singular']),
                'lead' => $t['surface'] === 'account' ? 'Pick a layout, then set the content and style. Customer accounts use your checkout branding.'
                    : (in_array($t['surface'], Schema::CHECKOUT_SURFACES, true) ? 'Pick a layout, then set the content, cart-value and country conditions. Checkout uses your checkout branding.'
                    : 'Every template is fully customisable — content, design, targeting and schedule.'),
                'placeholder' => $t['singular'].' · '.reset($t['templates'])['name'],
                'checkout' => in_array($t['surface'], Schema::CHECKOUT_SURFACES, true) || $t['surface'] === 'account',
            ] : null,
            'creatable' => collect(Registry::creatable())->map(fn ($t, $key) => ['key' => $key, 'label' => $t['label'], 'description' => $t['description']])->values(),
            'previews' => $type ? collect(Registry::offered($type))->map(fn ($tpl, $key) => ['key' => $key, 'name' => $t['templates'][$key]['name'], 'preview' => TemplateLibrary::preview($type, $key)])->values() : [],
            'selected' => $request->query('template'),
            'onboarding' => (bool) $request->query('onboarding'),
        ]);
    }

    public function store(Request $request, Store $store): RedirectResponse
    {
        $type = (string) $request->input('type');
        abort_unless(Registry::has($type), 404);

        $experience = $this->manager->create($store, $type, (string) $request->input('template'), $this->user($request), $request->input('name'));

        return redirect()->to(app_route('app.cro.experiences.edit', ['experience' => $experience->id, 'notice' => 'created']));
    }

    public function show(Request $request, Store $store, int $experience): Page
    {
        $experience = $this->find($store, $experience);
        $tab = in_array($request->query('tab'), ['overview', 'configuration', 'targeting', 'analytics', 'experiment', 'history'], true) ? $request->query('tab') : 'overview';
        $type = Registry::type($experience->type);
        $config = $experience->draft_config;
        $published = $experience->publishedVersion;
        $tz = $store->timezone ?? 'UTC';
        $module = match ($experience->type) {
            'bundles' => ['href' => route('app.bundles.edit', ['bundle' => $experience->id], false), 'summary' => BundleSchema::TYPES[BundleSchema::normalize($config)[0]['bundle_type']]['label'] ?? 'Bundle'],
            'progressive-gifts' => ['href' => route('app.gifts.edit', ['gift' => $experience->id], false), 'summary' => collect(GiftSchema::normalize($config)[0]['milestones'])->pluck('label')->implode(' · ')],
            default => null,
        };
        $describe = fn ($field, $value) => match ($field['type']) {
            'toggle' => $value ? 'On' : 'Off',
            'select' => $field['options'][$value] ?? $value,
            'checkboxes' => collect((array) $value)->map(fn ($v) => $field['options'][$v] ?? $v)->implode(', ') ?: '—',
            'products', 'collections' => collect((array) $value)->pluck('title')->implode(', ') ?: '—',
            'segments' => count((array) $value).' '.\Illuminate\Support\Str::plural('segment', count((array) $value)),
            'list' => count((array) $value).' '.\Illuminate\Support\Str::plural('row', count((array) $value)),
            'datetime' => $value ? \Illuminate\Support\Carbon::parse($value)->setTimezone($tz)->toDayDateTimeString() : '—',
            default => ($value === null || $value === '' || is_array($value)) ? '—' : (string) $value,
        };
        $sections = [];
        if (! $module && in_array($tab, ['configuration', 'targeting'], true)) {
            $fields = Schema::fields($experience->type);
            foreach ($tab === 'configuration' ? ['content', 'design', 'behavior'] : ['targeting', 'schedule'] as $section) {
                $sections[] = ['heading' => ucfirst($section), 'rows' => collect($fields[$section] ?? [])->map(fn ($f, $key) => [$f['label'], $describe($f, $config[$section][$key] ?? null)])->values()];
            }
        }
        $surface = $type['surface'];
        $notPlacedText = match (true) {
            $surface === 'global' => 'Shoppers can\'t see this yet. Turn on the OrderOrbit Space app embed in the Theme Editor (App embeds), then save.',
            $surface === 'post-purchase' => 'Shoppers can\'t see this yet. In Shopify, open Settings → Checkout and choose OrderOrbit Space under Post-purchase page.',
            $surface === 'account' => 'Customers can\'t see this yet. In Shopify\'s customer accounts editor, add the OrderOrbit Space account block and set its type to “'.$experience->type.'”.',
            in_array($surface, Schema::CHECKOUT_SURFACES, true) => 'Shoppers can\'t see this yet. In Shopify\'s checkout editor, add the OrderOrbit Space block and set its type to “'.$experience->type.'”.',
            default => 'Shoppers can\'t see this yet. Add the OrderOrbit Space block in the Theme Editor and choose “'.$type['singular'].'”.',
        };
        $salesPop = null;
        if ($experience->type === 'sales-pop') {
            $days = (int) ($config['content']['max_age_days'] ?? 7);
            $canRead = $store->hasScope('read_orders');
            $salesPop = ['days' => $days, 'recent' => \App\Models\RecentPurchase::where('store_id', $store->id)->where('purchased_at', '>=', now()->subDays($days))->count(),
                'canRead' => $canRead, 'blocked' => $canRead && RecentOrders::blocked($store)];
        }
        $survey = null;
        if ($experience->type === 'ty-survey' && $tab === 'overview') {
            $survey = \App\Models\AnalyticsEvent::where('store_id', $store->id)->where('experience_handle', $experience->handle)->where('event', 'survey')
                ->where('occurred_at', '>=', now()->subDays(30))->whereNotNull('label')->selectRaw('label, count(*) as total')->groupBy('label')->orderByDesc('total')->get()
                ->map(fn ($r) => ['label' => $r->label, 'total' => (int) $r->total])->values();
        }
        $test = \App\Models\Experiments\Experiment::where('experience_id', $experience->id)->whereIn('status', ['running', 'paused', 'draft'])->latest('id')->first();
        $featureKey = Registry::featureFor($experience->type);
        $labels = ['experience.created' => 'Created', 'experience.saved' => 'Saved draft', 'experience.published' => 'Published', 'experience.paused' => 'Paused', 'experience.resumed' => 'Resumed', 'experience.archived' => 'Archived', 'experience.unarchived' => 'Restored from archive', 'experience.duplicated' => 'Created as a copy', 'experience.version_restored' => 'Loaded an earlier version', 'experience.changes_discarded' => 'Discarded changes'];

        return page('cro/show', [
            'tab' => $tab,
            'experience' => self::row($experience) + [
                'description' => $experience->description, 'type_label' => $type['label'], 'singular' => lower_label($type['singular']),
                'template_version' => $experience->templateVersion?->version, 'placement' => $experience->placement_status, 'placement_checked_at' => $experience->placement_checked_at,
                'live' => $published ? ['version' => $published->version, 'at' => $published->published_at] : null,
                'schedule' => $experience->starts_at || $experience->ends_at ? ($experience->starts_at?->setTimezone($tz)->toDayDateTimeString() ?? 'Now').' → '.($experience->ends_at?->setTimezone($tz)->toDayDateTimeString() ?? 'No end') : null,
                'discountUrl' => $experience->shopify_discount_id ? $store->adminUrl('discounts/'.preg_replace('/\D/', '', $experience->shopify_discount_id)) : null,
                'track_views' => (bool) ($config['analytics']['track_views'] ?? true), 'track_clicks' => (bool) ($config['analytics']['track_clicks'] ?? true),
            ],
            'module' => $module,
            'feature' => $featureKey ? ['label' => Registry::feature($featureKey)['label'], 'href' => route('app.features.show', ['feature' => $featureKey], false)] : null,
            'editor' => ['url' => $store->themeEditorUrl($surface), 'label' => $store->editorLabel($surface)],
            'notPlacedText' => $notPlacedText,
            'salesPop' => $salesPop,
            'survey' => $survey,
            'performance' => app(\App\Services\Analytics\Analytics::class)->forExperiences($store, [$experience->handle])[$experience->handle] ?? ['views' => 0, 'adds' => 0, 'orders' => 0, 'revenue' => 0],
            'currency' => $store->currency ?? 'USD',
            'test' => $test ? ['href' => route($test->status === 'draft' ? 'app.experiments.edit' : 'app.experiments.show', ['experiment' => $test->id], false), 'draft' => $test->status === 'draft'] : null,
            'testable' => $experience->status === 'published' && ! \App\Services\Experiments\ExperimentManager::unsupported($experience),
            'sections' => $sections,
            'versions' => $tab === 'history' ? $experience->versions()->with('author')->orderByDesc('version')->get()->map(fn ($v) => [
                'id' => $v->id, 'version' => $v->version, 'published_at' => $v->published_at, 'by' => $v->author?->displayName(), 'note' => $v->change_note, 'live' => $v->id === $experience->published_version_id,
            ]) : [],
            'activity' => $tab === 'history' ? AuditLog::with('actor')->where('store_id', $store->id)->where('entity_type', 'Experience')->where('entity_id', $experience->id)->latest('id')->limit(30)->get()->map(fn ($l) => [
                'id' => $l->id, 'who' => $l->actor?->displayName() ?? 'OrderOrbit Space', 'what' => ($labels[$l->action] ?? $l->action).(isset($l->context['version']) ? ' v'.$l->context['version'] : ''), 'at' => $l->created_at,
            ]) : [],
        ]);
    }

    public function edit(Request $request, Store $store, int $experience): Page|RedirectResponse
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

    public function update(Request $request, Store $store, int $experience): RedirectResponse|Page
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

    public function publish(Request $request, Store $store, int $experience): RedirectResponse|Page
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
        } catch (OrdersBlocked) {
            return $this->back($experience, 'orders_blocked');
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

    private function doPublish(Experience $experience, Request $request, string $route): RedirectResponse|Page
    {
        try {
            $this->manager->publish($experience, $this->user($request), $request->input('change_note'));
        } catch (PublishException $e) {
            if ($e->reason === 'invalid') {
                return redirect()->to(app_route('app.cro.experiences.edit', ['experience' => $experience->id, 'notice' => 'invalid']));
            }

            // Plan limits and checkout requirements: say exactly what's needed, in the editor.
            return $this->builder($experience->store, $experience->fresh(), $experience->fresh()->draft_config, [], $e->getMessage());
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

    private function builder(Store $store, Experience $experience, array $config, array $errors, ?string $banner = null): Page
    {
        $type = Registry::type($experience->type);
        $tz = $store->timezone ?? 'UTC';

        // Settings added after this experience was made start at their defaults (keys only, so
        // saved lists like reviews or badges are never padded with sample rows).
        $defaults = Schema::defaults($experience->type);
        foreach ($defaults as $section => $values) {
            $config[$section] = (array) ($config[$section] ?? []) + $values;
        }

        return page('cro/builder', [
            'experience' => [
                'id' => $experience->id, 'name' => $experience->name, 'description' => $experience->description, 'handle' => $experience->handle,
                'type' => $experience->type, 'template_key' => $experience->template_key,
                'discardable' => $experience->published_version_id && $experience->has_unpublished_changes,
            ],
            'type' => ['label' => $type['label'], 'description' => $type['description'], 'surface' => $type['surface']],
            'fields' => Schema::fields($experience->type),
            'config' => array_map(fn ($section) => (object) $section, $config),
            'fieldErrors' => (object) $errors,
            'banner' => $banner,
            'templates' => collect($type['templates'])->map(fn ($t, $key) => ['key' => $key, 'name' => $t['name'], 'style' => $t['style']])->values(),
            'timezone' => $tz,
            'tzOffset' => now($tz)->format('P'),
            'currency' => $store->currency ?? 'USD',
            'segments' => \App\Models\Audiences\Segment::where('store_id', $store->id)->active()->orderBy('name')->get(['id', 'name']),
            'pageTypes' => Schema::PAGE_TYPES,
            'publishHelp' => $this->publishHelp($experience, $type),
            'editor' => ['url' => $store->themeEditorUrl($type['surface']), 'label' => $store->editorLabel($type['surface'])],
            'designNote' => empty(Schema::fields($experience->type)['design'] ?? []) ? 'checkout' : (isset(Schema::fields($experience->type)['design']['ck_background']) ? 'checkout-boxes' : 'brand'),
            'checkout' => in_array($type['surface'], Schema::CHECKOUT_SURFACES, true) || $type['surface'] === 'account',
        ], $errors ? 422 : 200);
    }

    /** What happens after publishing, for the Publish step (escaped HTML). */
    private function publishHelp(Experience $experience, array $type): array
    {
        $code = fn ($v) => '<code class="b-code-inline">'.e($v).'</code>';
        $notes = [];
        if ($experience->type === 'bundles') {
            $notes[] = 'In the cart, the items a shopper adds from this bundle combine into one line with the bundle price. Orders still list each product, so Shopify deducts inventory from each one. Publishing creates a hidden bundle product in your store for this; pausing or archiving sets it back to draft.';
        } elseif ($type['discount'] ?? false) {
            $notes[] = 'Savings apply automatically in cart and checkout. Publishing creates a Shopify automatic discount for this '.e(lower_label($type['singular'])).'; pausing or archiving it removes the discount. You\'ll see it under <strong>Discounts</strong> in Shopify admin.';
        }
        $notes[] = match (true) {
            $type['surface'] === 'post-purchase' => 'After publishing, open Shopify\'s <strong>Settings → Checkout</strong> and choose <strong>OrderOrbit Space</strong> under Post-purchase page. Shopify shows this page after payments that support it (cards, Shop Pay and others); the offer is added to the same order and charged to the same payment.',
            $type['surface'] === 'account' => 'After publishing, open Shopify\'s customer accounts editor (Settings → Checkout → Customize, then the Orders, Profile or Order status page), add the <strong>OrderOrbit Space account</strong> block and set its type to '.$code($experience->type).'. To show this exact one, put '.$code($experience->handle).' in its Experience ID setting.'.($experience->type === 'account-reorder' ? ' "Buy again" in each order\'s menu appears on its own.' : ''),
            in_array($type['surface'], Schema::CHECKOUT_SURFACES, true) => 'After publishing, open Shopify\'s checkout editor'.($type['surface'] === 'thank-you' ? ' on the Thank You or Order Status page' : '').', add the <strong>OrderOrbit Space</strong> block and set its type to '.$code($experience->type).'. To show this exact one, put '.$code($experience->handle).' in its Experience ID setting.',
            $type['surface'] === 'global' => 'No theme block needed: it shows on every page while the <strong>OrderOrbit Space app embed</strong> is on (Theme Editor → App embeds). Pops use your store\'s real recent orders — product, country and time only, never names — so it starts showing once orders come in.',
            default => 'After publishing, add the <strong>OrderOrbit Space block</strong> in the Theme Editor and pick “'.e($type['singular']).'”, or pin it with ID '.$code($experience->handle).'.',
        };

        return $notes;
    }

    /** An experience as list rows and status badges show it. */
    public static function row(Experience $e): array
    {
        return [
            'id' => $e->id, 'name' => $e->name, 'handle' => $e->handle, 'type' => $e->type,
            'type_label' => Registry::has($e->type) ? Registry::type($e->type)['label'] : $e->type,
            'template' => $e->templateName(), 'status' => $e->status, 'display_status' => $e->displayStatus(),
            'not_placed' => $e->status === 'published' && $e->placement_status === 'not_placed',
            'unpublished' => $e->published_version_id && $e->has_unpublished_changes && $e->status !== 'archived',
            'updated_at' => $e->updated_at,
        ];
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
