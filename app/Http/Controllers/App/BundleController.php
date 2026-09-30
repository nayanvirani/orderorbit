<?php

namespace App\Http\Controllers\App;

use App\Experiences\BundleSchema;
use App\Http\Controllers\Controller;
use App\Models\CroSetting;
use App\Models\Experience;
use App\Models\Store;
use App\Services\Experiences\ExperienceManager;
use App\Services\Experiences\PublishException;
use App\Services\Experiences\TemplateLibrary;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;
use Throwable;

/**
 * The Bundles module: list, bundle type (step 1), model (step 2), and the
 * Settings / Offers / Design editor with a live preview.
 */
class BundleController extends Controller
{
    public function __construct(private readonly ExperienceManager $manager) {}

    public function index(Request $request, Store $store): View
    {
        $tab = in_array($request->query('tab'), ['active', 'scheduled', 'draft', 'paused', 'archived'], true) ? $request->query('tab') : 'all';
        $all = $store->experiences()->where('type', 'bundles')->with('publishedVersion')->latest('updated_at')->get();

        $bundles = $all->filter(fn (Experience $e) => match ($tab) {
            'all' => $e->status !== 'archived',
            'active' => $e->displayStatus() === 'published',
            'scheduled' => $e->displayStatus() === 'scheduled',
            default => $e->status === $tab,
        });

        return view('app.bundles.index', [
            'store' => $store,
            'tab' => $tab,
            'bundles' => $bundles,
            'stats' => app(\App\Services\Analytics\Analytics::class)->forExperiences($store, $all->pluck('handle')->all()),
            'counts' => [
                'all' => $all->where('status', '!=', 'archived')->count(),
                'active' => $all->filter(fn ($e) => $e->displayStatus() === 'published')->count(),
                'scheduled' => $all->filter(fn ($e) => $e->displayStatus() === 'scheduled')->count(),
                'draft' => $all->where('status', 'draft')->count(),
                'paused' => $all->where('status', 'paused')->count(),
                'archived' => $all->where('status', 'archived')->count(),
            ],
        ]);
    }

    public function types(Store $store): View
    {
        $branding = CroSetting::brandingFor($store);

        return view('app.bundles.types', [
            'store' => $store,
            'types' => collect(BundleSchema::TYPES)->map(fn ($type, $key) => $type + [
                'preview' => TemplateLibrary::bundlePreview(BundleSchema::firstModel($key), $branding),
            ])->all(),
        ]);
    }

    public function models(Store $store, string $type): View
    {
        abort_unless(array_key_exists($type, BundleSchema::TYPES), 404);
        $branding = CroSetting::brandingFor($store);

        return view('app.bundles.models', [
            'store' => $store,
            'typeKey' => $type,
            'type' => BundleSchema::TYPES[$type],
            'models' => collect(BundleSchema::models())->where('type', $type)->map(fn ($model, $key) => $model + [
                'previews' => collect(BundleSchema::PRESETS)->keys()->mapWithKeys(fn ($preset) => [$preset => TemplateLibrary::bundlePreview($key, $branding, $preset)])->all(),
            ])->all(),
            'presets' => BundleSchema::PRESETS,
        ]);
    }

    public function store(Request $request, Store $store): RedirectResponse
    {
        $model = (string) $request->input('model');
        abort_unless(BundleSchema::model($model), 404);
        $preset = array_key_exists($request->input('preset'), BundleSchema::PRESETS) ? $request->input('preset') : 'black';

        $experience = $this->manager->create($store, 'bundles', $model, $request->attributes->get('storeUser'), BundleSchema::model($model)['name']);
        $config = $experience->draft_config;
        $config['design'] = array_merge($config['design'], BundleSchema::designDefaults($preset));
        $experience->update(['draft_config' => $config]);

        return redirect()->to(app_route('app.bundles.edit', ['bundle' => $experience->id]));
    }

    public function edit(Request $request, Store $store, int $bundle): View
    {
        $experience = $this->find($store, $bundle);

        return $this->editor($store, $experience, BundleSchema::normalize($experience->draft_config, $store->timezone ?? 'UTC')[0], []);
    }

    public function update(Request $request, Store $store, int $bundle): RedirectResponse|View
    {
        $experience = $this->find($store, $bundle);
        $input = json_decode((string) $request->input('config_json', '{}'), true);
        [$config, $errors] = BundleSchema::normalize(is_array($input) ? $input : [], $store->timezone ?? 'UTC');
        $name = trim(strip_tags((string) $request->input('name', ''))) ?: $experience->name;

        $this->manager->saveDraft($experience, $config, ['name' => mb_substr($name, 0, 120)], $request->attributes->get('storeUser'));

        if ($request->input('action') !== 'publish') {
            return $errors
                ? $this->editor($store, $experience->fresh(), $config, $errors, 'Draft saved. Some settings need attention before you can publish.')
                : redirect()->to(app_route('app.bundles.edit', ['bundle' => $experience->id, 'notice' => 'saved']));
        }
        if ($errors) {
            return $this->editor($store, $experience->fresh(), $config, $errors, 'Fix the highlighted settings before publishing.');
        }

        try {
            $this->manager->publish($experience->fresh(), $request->attributes->get('storeUser'));
        } catch (PublishException $e) {
            return $this->editor($store, $experience->fresh(), $config, [], $e->getMessage());
        } catch (Throwable $e) {
            report($e);

            return $this->editor($store, $experience->fresh(), $config, [], 'Shopify didn’t accept the bundle: '.$e->getMessage());
        }

        return redirect()->to(app_route('app.bundles.edit', ['bundle' => $experience->id, 'notice' => 'published']));
    }

    /**
     * The on/off switch in the bundles list: publish a draft or paused bundle, pause a live one.
     */
    public function toggle(Request $request, Store $store, int $bundle): RedirectResponse
    {
        $experience = $this->find($store, $bundle);
        $user = $request->attributes->get('storeUser');

        try {
            match (true) {
                $experience->status === 'published' => $this->manager->pause($experience),
                $experience->status === 'paused' && ! $experience->has_unpublished_changes => $this->manager->resume($experience),
                default => $this->manager->publish($experience, $user),
            };
            $notice = $experience->fresh()->status === 'published' ? 'published' : 'paused';
        } catch (PublishException $e) {
            return redirect()->to(app_route('app.bundles.edit', ['bundle' => $experience->id, 'error' => $e->getMessage()]));
        } catch (Throwable $e) {
            report($e);
            $notice = 'shopify';
        }

        return redirect()->to(app_route('app.bundles.index', ['notice' => $notice]));
    }

    private function editor(Store $store, Experience $experience, array $config, array $errors, ?string $banner = null): View
    {
        return view('app.bundles.editor', [
            'store' => $store,
            'experience' => $experience,
            'config' => $config,
            'fieldErrors' => $errors,
            'banner' => $banner ?? (request('error') ? (string) request('error') : null),
            'type' => BundleSchema::TYPES[$config['bundle_type']],
            'timezone' => $store->timezone ?? 'UTC',
        ]);
    }

    private function find(Store $store, int $id): Experience
    {
        return $store->experiences()->where('type', 'bundles')->findOrFail($id);
    }
}
