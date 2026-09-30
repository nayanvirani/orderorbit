<?php

namespace App\Http\Controllers\App;

use App\Experiences\GiftSchema;
use App\Http\Controllers\Controller;
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
 * The Progressive gifts module: list, template chooser and editor (Rewards / Settings / Design).
 */
class GiftController extends Controller
{
    public function __construct(private readonly ExperienceManager $manager) {}

    public function index(Store $store): View
    {
        $items = $store->experiences()->where('type', 'progressive-gifts')->where('status', '!=', 'archived')->latest('updated_at')->get();

        return view('app.gifts.index', [
            'store' => $store,
            'items' => $items,
            'stats' => app(\App\Services\Analytics\Analytics::class)->forExperiences($store, $items->pluck('handle')->all()),
        ]);
    }

    public function models(Store $store): View
    {
        return view('app.gifts.models', [
            'store' => $store,
            'groups' => collect(GiftSchema::models())->map(fn ($m, $key) => $m + ['key' => $key, 'preview' => TemplateLibrary::giftPreview($key)])->groupBy('group')->all(),
        ]);
    }

    public function store(Request $request, Store $store): RedirectResponse
    {
        $model = (string) $request->input('model');
        abort_unless(GiftSchema::model($model), 404);
        $experience = $this->manager->create($store, 'progressive-gifts', $model, $request->attributes->get('storeUser'), 'Progressive gifts · '.GiftSchema::model($model)['name']);

        return redirect()->to(app_route('app.gifts.edit', ['gift' => $experience->id]));
    }

    public function edit(Store $store, int $gift): View
    {
        $experience = $this->find($store, $gift);

        return $this->editor($store, $experience, GiftSchema::normalize($experience->draft_config, $store->timezone ?? 'UTC')[0], []);
    }

    public function update(Request $request, Store $store, int $gift): RedirectResponse|View
    {
        $experience = $this->find($store, $gift);
        $input = json_decode((string) $request->input('config_json', '{}'), true);
        [$config, $errors] = GiftSchema::normalize(is_array($input) ? $input : [], $store->timezone ?? 'UTC');
        $name = trim(strip_tags((string) $request->input('name', ''))) ?: $experience->name;

        $this->manager->saveDraft($experience, $config, ['name' => mb_substr($name, 0, 120)], $request->attributes->get('storeUser'));

        if ($request->input('action') !== 'publish') {
            return $errors
                ? $this->editor($store, $experience->fresh(), $config, $errors, 'Draft saved. Some settings need attention before you can publish.')
                : redirect()->to(app_route('app.gifts.edit', ['gift' => $experience->id, 'notice' => 'saved']));
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

            return $this->editor($store, $experience->fresh(), $config, [], 'Shopify didn’t accept the rewards: '.$e->getMessage());
        }

        return redirect()->to(app_route('app.gifts.edit', ['gift' => $experience->id, 'notice' => 'published']));
    }

    public function toggle(Request $request, Store $store, int $gift): RedirectResponse
    {
        $experience = $this->find($store, $gift);

        try {
            match (true) {
                $experience->status === 'published' => $this->manager->pause($experience),
                $experience->status === 'paused' && ! $experience->has_unpublished_changes => $this->manager->resume($experience),
                default => $this->manager->publish($experience, $request->attributes->get('storeUser')),
            };
            $notice = $experience->fresh()->status === 'published' ? 'published' : 'paused';
        } catch (PublishException $e) {
            return redirect()->to(app_route('app.gifts.edit', ['gift' => $experience->id, 'error' => $e->getMessage()]));
        } catch (Throwable $e) {
            report($e);
            $notice = 'shopify';
        }

        return redirect()->to(app_route('app.gifts.index', ['notice' => $notice]));
    }

    private function editor(Store $store, Experience $experience, array $config, array $errors, ?string $banner = null): View
    {
        return view('app.gifts.editor', [
            'store' => $store,
            'experience' => $experience,
            'config' => $config,
            'fieldErrors' => $errors,
            'banner' => $banner ?? (request('error') ? (string) request('error') : null),
            'timezone' => $store->timezone ?? 'UTC',
        ]);
    }

    private function find(Store $store, int $id): Experience
    {
        return $store->experiences()->where('type', 'progressive-gifts')->findOrFail($id);
    }
}
