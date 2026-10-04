<?php

namespace App\Http\Controllers\App;

use App\Experiences\Registry;
use App\Http\Controllers\Controller;
use App\Models\Audiences\PersonalizationRule;
use App\Models\Audiences\Segment;
use App\Models\Experience;
use App\Models\Store;
use App\Services\Audiences\Audiences;
use App\Services\Experiences\StorefrontPublisher;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;
use Throwable;

/**
 * Audiences & Personalization (Phase 10): segments and rules.
 */
class AudienceController extends Controller
{
    public function __construct(private readonly Audiences $audiences, private readonly StorefrontPublisher $publisher) {}

    // ------------------------------------------------------------------ segments

    public function segments(Request $request, Store $store): View
    {
        $archived = $request->query('archived') === '1';
        $segments = Segment::where('store_id', $store->id)->when($archived, fn ($q) => $q->whereNotNull('archived_at'), fn ($q) => $q->whereNull('archived_at'))->orderBy('name')->get();

        return view('app.audiences.segments', $this->shared($store) + [
            'segments' => $segments,
            'usage' => $segments->mapWithKeys(fn ($s) => [$s->id => Audiences::usage($store, $s->id)]),
            'archived' => $archived,
        ]);
    }

    public function createSegment(Request $request, Store $store): RedirectResponse
    {
        $template = Audiences::TEMPLATES[$request->input('template')] ?? null;
        $segment = Segment::create([
            'store_id' => $store->id,
            'name' => $template['name'] ?? 'New segment',
            'description' => $template['description'] ?? null,
            'template' => $template ? $request->input('template') : null,
            'match' => $template['match'] ?? 'all',
            'rules' => $template['rules'] ?? [['field' => 'orders_count', 'op' => 'gte', 'value' => 1]],
        ]);
        if (Audiences::normalize(['match' => $segment->match, 'rules' => $segment->rules])[2] === []) {
            $this->audiences->count($store, $segment);
        }

        return redirect()->to(app_route('app.audiences.segments.edit', ['segment' => $segment->id, 'notice' => 'segment_created']));
    }

    public function editSegment(Request $request, Store $store, int $segment): View
    {
        return $this->segmentEditor($store, $this->segment($store, $segment), []);
    }

    public function updateSegment(Request $request, Store $store, int $segment): View|RedirectResponse
    {
        $segment = $this->segment($store, $segment);
        [$match, $rules, $errors] = Audiences::normalize($request->all());
        $name = mb_substr(trim(strip_tags((string) $request->input('name'))), 0, 80);
        if ($name === '') {
            $errors['name'] = 'Give the segment a name.';
        }
        $segment->fill(['name' => $name ?: $segment->name, 'description' => mb_substr(trim(strip_tags((string) $request->input('description'))), 0, 300) ?: null, 'match' => $match, 'rules' => $rules])->save();
        if ($errors) {
            return $this->segmentEditor($store, $segment, $errors);
        }
        $this->audiences->count($store, $segment);
        $this->sync($store);

        return redirect()->to(app_route('app.audiences.segments.edit', ['segment' => $segment->id, 'notice' => 'saved']));
    }

    public function duplicateSegment(Request $request, Store $store, int $segment): RedirectResponse
    {
        $copy = $this->segment($store, $segment)->replicate(['member_count', 'counted_at', 'archived_at']);
        $copy->name = mb_substr($copy->name.' (copy)', 0, 80);
        $copy->save();

        return redirect()->to(app_route('app.audiences.segments.edit', ['segment' => $copy->id, 'notice' => 'segment_created']));
    }

    public function archiveSegment(Request $request, Store $store, int $segment): RedirectResponse
    {
        $segment = $this->segment($store, $segment);
        if ($segment->archived_at) {
            $segment->forceFill(['archived_at' => null])->save();

            return redirect()->to(app_route('app.audiences.segments', ['notice' => 'segment_restored']));
        }
        $usage = Audiences::usage($store, $segment->id);
        if (array_filter($usage)) {
            return redirect()->to(app_route('app.audiences.segments.edit', ['segment' => $segment->id, 'error' => 'This segment is in use. Remove it from the experiences, rules, tests and workflows listed below first.']));
        }
        $segment->forceFill(['archived_at' => now()])->save();

        return redirect()->to(app_route('app.audiences.segments', ['notice' => 'segment_archived']));
    }

    public function countSegment(Request $request, Store $store, int $segment): RedirectResponse
    {
        $segment = $this->segment($store, $segment);
        $this->audiences->count($store, $segment);

        return redirect()->to(app_route('app.audiences.segments.edit', ['segment' => $segment->id]));
    }

    private function segmentEditor(Store $store, Segment $segment, array $errors): View
    {
        return view('app.audiences.segment', $this->shared($store) + [
            'segment' => $segment,
            'fieldErrors' => $errors,
            'usage' => Audiences::usage($store, $segment->id),
            'experiences' => Experience::where('store_id', $store->id)->where('status', '!=', 'archived')->orderBy('name')->get(['handle', 'name']),
        ]);
    }

    // ------------------------------------------------------------------ rules

    public function rules(Request $request, Store $store): View
    {
        $rules = PersonalizationRule::with('experience')->where('store_id', $store->id)->orderBy('position')->orderBy('id')->get();

        return view('app.audiences.rules', $this->shared($store) + [
            'rules' => $rules,
            'conflicts' => Audiences::conflicts($rules),
            'segmentNames' => Segment::where('store_id', $store->id)->pluck('name', 'id'),
        ]);
    }

    public function createRule(Request $request, Store $store): View|RedirectResponse
    {
        return $this->ruleEditor($store, new PersonalizationRule(['enabled' => true, 'outcome' => 'show', 'segments' => [], 'conditions' => []]), []);
    }

    public function editRule(Request $request, Store $store, int $rule): View
    {
        return $this->ruleEditor($store, $this->rule($store, $rule), []);
    }

    public function saveRule(Request $request, Store $store, ?int $rule = null): View|RedirectResponse
    {
        $model = $rule ? $this->rule($store, $rule) : new PersonalizationRule(['store_id' => $store->id, 'position' => (int) PersonalizationRule::where('store_id', $store->id)->max('position') + 1]);
        $errors = [];
        $experience = Experience::where('store_id', $store->id)->find((int) $request->input('experience_id'));
        if (! $experience || ! Audiences::personalizable($experience)) {
            $errors['experience_id'] = 'Choose a storefront experience (Bundles, Progressive gifts and checkout blocks can\'t be personalized).';
        }
        $outcome = array_key_exists($request->input('outcome'), Audiences::OUTCOMES) ? $request->input('outcome') : 'show';
        $template = $request->input('template_key');
        if ($outcome === 'swap' && (! $experience || ! isset(Registry::type($experience->type)['templates'][$template]))) {
            $errors['template_key'] = 'Choose the template to show.';
        }
        $segmentIds = Segment::where('store_id', $store->id)->active()->whereIn('id', array_map('intval', (array) $request->input('segments', [])))->pluck('id')->all();
        $c = (array) $request->input('conditions', []);
        $conditions = array_filter([
            'device' => in_array($c['device'] ?? '', ['mobile', 'desktop'], true) ? $c['device'] : null,
            'cart_min' => is_numeric($c['cart_min'] ?? null) ? max(0, (float) $c['cart_min']) : null,
            'cart_max' => is_numeric($c['cart_max'] ?? null) ? max(0, (float) $c['cart_max']) : null,
            'utm_source' => mb_substr(trim(strip_tags((string) ($c['utm_source'] ?? ''))), 0, 100) ?: null,
            'utm_campaign' => mb_substr(trim(strip_tags((string) ($c['utm_campaign'] ?? ''))), 0, 100) ?: null,
        ], fn ($v) => $v !== null);
        if (! $segmentIds && ! $conditions) {
            $errors['segments'] = 'Choose at least one segment or condition: who is this rule for?';
        }
        if (isset($conditions['cart_min'], $conditions['cart_max']) && $conditions['cart_min'] > $conditions['cart_max']) {
            $errors['conditions'] = 'The maximum cart value must be at least the minimum.';
        }
        $name = mb_substr(trim(strip_tags((string) $request->input('name'))), 0, 80);

        $model->fill([
            'name' => $name ?: (($experience->name ?? 'Experience').': '.Audiences::OUTCOMES[$outcome]),
            'experience_id' => $experience->id ?? $model->experience_id,
            'outcome' => $outcome, 'template_key' => $outcome === 'swap' ? $template : null,
            'segments' => $segmentIds, 'conditions' => $conditions ?: null,
            'enabled' => $request->boolean('enabled', true),
        ]);
        if ($errors) {
            return $this->ruleEditor($store, $model, $errors);
        }
        $model->store_id = $store->id;
        $model->save();
        $this->sync($store);

        return redirect()->to(app_route('app.audiences.rules', ['notice' => 'saved']));
    }

    public function ruleAction(Request $request, Store $store, int $rule, string $action): RedirectResponse
    {
        $rule = $this->rule($store, $rule);
        if ($action === 'delete') {
            $rule->delete();
        } elseif ($action === 'toggle') {
            $rule->forceFill(['enabled' => ! $rule->enabled])->save();
        } else {
            // Move up or down: swap positions with the neighbour.
            $ordered = PersonalizationRule::where('store_id', $store->id)->orderBy('position')->orderBy('id')->get()->values();
            $i = $ordered->search(fn ($r) => $r->id === $rule->id);
            $j = $action === 'up' ? $i - 1 : $i + 1;
            if (isset($ordered[$j])) {
                $ordered[$i] = $ordered[$j];
                $ordered[$j] = $rule;
            }
            $ordered->each(fn ($r, $position) => $r->forceFill(['position' => $position])->save());
        }
        $this->sync($store);

        return redirect()->to(app_route('app.audiences.rules', ['notice' => $action === 'delete' ? 'deleted' : 'saved']));
    }

    private function ruleEditor(Store $store, PersonalizationRule $rule, array $errors): View
    {
        $experiences = Experience::where('store_id', $store->id)->where('status', '!=', 'archived')->orderBy('name')->get()->filter(fn ($e) => Audiences::personalizable($e))->values();

        return view('app.audiences.rule', $this->shared($store) + [
            'rule' => $rule,
            'fieldErrors' => $errors,
            'experiences' => $experiences,
            'templates' => $experiences->mapWithKeys(fn ($e) => [$e->id => collect(Registry::type($e->type)['templates'] ?? [])->map(fn ($t) => $t['name'])->all()]),
            'segments' => Segment::where('store_id', $store->id)->active()->orderBy('name')->get(),
        ]);
    }

    private function shared(Store $store): array
    {
        return ['store' => $store, 'enabled' => $store->planIncludes('personalization')];
    }

    private function segment(Store $store, int $id): Segment
    {
        return Segment::where('store_id', $store->id)->findOrFail($id);
    }

    private function rule(Store $store, int $id): PersonalizationRule
    {
        return PersonalizationRule::where('store_id', $store->id)->findOrFail($id);
    }

    private function sync(Store $store): void
    {
        try {
            $this->publisher->sync($store);
        } catch (Throwable $e) {
            report($e);
        }
    }
}
