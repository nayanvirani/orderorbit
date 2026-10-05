<?php

namespace App\Services\Experiments;

use App\Experiences\Registry;
use App\Experiences\Schema;
use App\Models\Experience;
use App\Models\Experiments\Experiment;
use App\Models\Experiments\ExperimentVariant;
use App\Models\Store;
use App\Models\StoreUser;
use App\Services\Experiences\ExperienceManager;
use App\Services\Experiences\StorefrontPublisher;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use RuntimeException;
use Throwable;

/**
 * A/B tests on published storefront experiences. Variants can change the template, the design
 * and the text, never the offer itself (products, prices, discounts and thresholds stay as
 * published, so checkout always gives what the widget promised). A holdout variant hides the
 * experience to measure its overall effect; it isn't offered for types that apply discounts.
 */
class ExperimentManager
{
    public const PRIMARY = [
        'conversion_rate' => 'Conversion rate',
        'revenue_per_visitor' => 'Revenue per visitor',
        'revenue' => 'Revenue (tested per visitor)',
        'click_rate' => 'Click-through rate',
    ];

    public const SECONDARY = [
        'add_to_cart' => 'Add to cart rate',
        'checkout_started' => 'Checkout started rate',
        'purchase' => 'Purchase rate',
        'aov' => 'Average order value',
        'units_per_order' => 'Units per order',
        'upsell_acceptance' => 'Upsell acceptance',
        'bundle_completion' => 'Bundle completion',
        'click_rate' => 'Click-through rate',
    ];

    public const GUARDRAILS = [
        'cart_abandonment' => 'Cart abandonment',
        'negative_interactions' => 'Negative interactions (closes and declines)',
    ];

    /** Audience fields shown in setup: the same live context the storefront knows. */
    public const AUDIENCE = ['device', 'countries', 'products', 'collections', 'cart_min', 'cart_max', 'utm_source', 'utm_campaign', 'customer', 'segments'];

    /** What checkout and Thank You pages know about a buyer: cart value and country. */
    public const CHECKOUT_AUDIENCE = ['countries', 'cart_min', 'cart_max'];

    /** Audience fields for an experience's surface. */
    public static function audienceFor(Experience $experience): array
    {
        return in_array(Registry::type($experience->type)['surface'] ?? '', ['checkout', 'thank-you'], true) ? self::CHECKOUT_AUDIENCE : self::AUDIENCE;
    }

    public function __construct(private readonly StorefrontPublisher $publisher, private readonly ExperienceManager $experiences) {}

    /** Why an experience can't be tested, or null. */
    public static function unsupported(Experience $experience): ?string
    {
        if (! Registry::has($experience->type)) {
            return 'This experience type no longer exists.';
        }
        if (in_array($experience->type, ['bundles', 'progressive-gifts'], true)) {
            return 'Bundles and Progressive gifts set prices at checkout, so they can\'t be split-tested yet.';
        }
        $surface = Registry::type($experience->type)['surface'];
        if ($surface === 'post-purchase') {
            return 'The post-purchase offer is chosen by the server for each order, so it can\'t be split-tested yet.';
        }
        if ($surface === 'account') {
            return 'Customer account blocks can\'t be tested yet.';
        }

        return null;
    }

    public static function canHoldout(Experience $experience): bool
    {
        return ! (Registry::type($experience->type)['discount'] ?? false);
    }

    /** Text fields a variant may change. */
    public static function textFields(string $type): array
    {
        return array_filter(Schema::fields($type)['content'] ?? [], fn ($f) => in_array($f['type'], ['text', 'textarea'], true));
    }

    public function create(Store $store, Experience $experience, ?StoreUser $user): Experiment
    {
        if ($reason = self::unsupported($experience)) {
            throw new RuntimeException($reason);
        }

        return DB::transaction(function () use ($store, $experience, $user) {
            $experiment = Experiment::create([
                'store_id' => $store->id, 'experience_id' => $experience->id, 'handle' => 'x'.Str::lower(Str::random(10)),
                'name' => mb_substr($experience->name.' test', 0, 120), 'status' => 'draft',
                // On Thank You and Order Status the order is already placed: clicks are what the block can move.
                'audience' => [], 'primary_metric' => (Registry::type($experience->type)['surface'] === 'thank-you') ? 'click_rate' : 'conversion_rate',
                'secondary_metrics' => (Registry::type($experience->type)['surface'] === 'thank-you') ? ['purchase'] : ['add_to_cart', 'aov'],
                'guardrails' => [['metric' => 'cart_abandonment', 'threshold' => 5]],
            ]);
            $template = $experience->publishedVersion?->template_key ?? $experience->template_key;
            $experiment->variants()->create(['key' => 'A', 'name' => 'Control', 'allocation' => 50, 'template_key' => $template]);
            $experiment->variants()->create(['key' => 'B', 'name' => 'Variant B', 'allocation' => 50, 'template_key' => $template]);
            $experiment->log('Draft created.', $user);

            return $experiment;
        });
    }

    /**
     * Saves the setup. Returns field errors keyed by input name; the draft is saved either way.
     *
     * @return array<string, string>
     */
    public function save(Experiment $experiment, array $input): array
    {
        $experience = $experiment->experience;
        $type = $experience->type;
        $errors = [];
        $editable = in_array($experiment->status, ['draft', 'paused'], true);

        $experiment->name = mb_substr(trim(strip_tags((string) ($input['name'] ?? $experiment->name))), 0, 120) ?: $experiment->name;
        $experiment->hypothesis = mb_substr(trim(strip_tags((string) ($input['hypothesis'] ?? ''))), 0, 1000) ?: null;

        if ($editable) {
            // Audience: the shared targeting fields, cleaned by the experience schema.
            $targeting = Schema::shared()['targeting'];
            $audience = [];
            foreach (self::audienceFor($experience) as $key) {
                if (array_key_exists($key, $input['audience'] ?? [])) {
                    [$value, $error] = Schema::field($targeting[$key], $input['audience'][$key], 'UTC');
                    if ($error) {
                        $errors["audience.{$key}"] = $error;
                    }
                    if ($value !== null && $value !== '' && $value !== [] && $value !== 'all') {
                        $audience[$key] = $value;
                    }
                }
            }
            $experiment->audience = $audience;

            $experiment->primary_metric = array_key_exists($input['primary_metric'] ?? '', self::PRIMARY) ? $input['primary_metric'] : 'conversion_rate';
            $experiment->secondary_metrics = array_values(array_intersect(array_keys(self::SECONDARY), (array) ($input['secondary_metrics'] ?? [])));
            $experiment->guardrails = collect((array) ($input['guardrails'] ?? []))
                ->filter(fn ($g) => is_array($g) && isset(self::GUARDRAILS[$g['metric'] ?? '']) && ! empty($g['enabled']))
                ->map(fn ($g) => ['metric' => $g['metric'], 'threshold' => round(max(0.1, min(100, (float) ($g['threshold'] ?? 5))), 1)])
                ->values()->all();
            $experiment->min_days = max(7, min(90, (int) ($input['min_days'] ?? 7)));
            $experiment->min_visitors = max(1000, min(1_000_000, (int) ($input['min_visitors'] ?? 1000)));
            $experiment->min_conversions = max(100, min(100_000, (int) ($input['min_conversions'] ?? 100)));
            $experiment->ends_at = ! empty($input['ends_at']) ? rescue(fn () => now()->parse($input['ends_at']), null, false) : null;
            if ($experiment->ends_at && $experiment->ends_at->lt(now()->addDays($experiment->min_days))) {
                $errors['ends_at'] = 'The end date must leave at least the minimum duration ('.$experiment->min_days.' days).';
            }

            $errors += $this->saveVariants($experiment, (array) ($input['variants'] ?? []), $type);
        }

        $experiment->save();

        return $errors;
    }

    private function saveVariants(Experiment $experiment, array $input, string $type): array
    {
        $errors = [];
        $templates = Registry::type($type)['templates'] ?? [];
        $text = self::textFields($type);
        $design = Schema::fields($type)['design'] ?? [];
        $holdout = self::canHoldout($experiment->experience);
        // Variant fields start from the published experience; only changes are kept.
        $base = $experiment->experience->publishedVersion?->config ?? $experiment->experience->draft_config ?? [];
        $keys = array_values(array_intersect(['A', 'B', 'C'], array_keys($input)));
        if (count($keys) < 2 || $keys[0] !== 'A') {
            return ['variants' => 'A test needs the control (A) and at least one variant.'];
        }

        $total = 0;
        $experiment->variants()->whereNotIn('key', $keys)->delete();
        foreach ($keys as $key) {
            $v = (array) $input[$key];
            $allocation = (int) ($v['allocation'] ?? 0);
            $total += $allocation;
            if ($allocation < 1) {
                $errors["variants.{$key}.allocation"] = 'Give every variant at least 1% of traffic.';
            }
            $data = ['name' => mb_substr(trim(strip_tags((string) ($v['name'] ?? ''))), 0, 80) ?: ($key === 'A' ? 'Control' : 'Variant '.$key), 'allocation' => max(0, min(100, $allocation))];
            if ($key === 'A') {
                // The control is the experience as published.
                $data += ['hidden' => false, 'template_key' => $experiment->experience->publishedVersion?->template_key ?? $experiment->experience->template_key, 'content' => null, 'design' => null];
            } else {
                $data['hidden'] = $holdout && ! empty($v['hidden']);
                $data['template_key'] = isset($templates[$v['template_key'] ?? '']) ? $v['template_key'] : null;
                $content = [];
                foreach ($text as $field => $def) {
                    $value = isset($v['content'][$field]) ? trim(strip_tags((string) $v['content'][$field])) : '';
                    if ($value !== '' && $value !== (string) ($base['content'][$field] ?? '')) {
                        $content[$field] = mb_substr($value, 0, $def['max'] ?? 255);
                    }
                }
                $data['content'] = $content ?: null;
                $styles = [];
                foreach ($design as $field => $def) {
                    if (array_key_exists($field, (array) ($v['design'] ?? [])) && ($v['design'][$field] ?? '') !== '') {
                        [$value, $error] = Schema::field($def, $v['design'][$field], 'UTC');
                        if ($error) {
                            $errors["variants.{$key}.design.{$field}"] = $error;
                        } elseif ($value !== ($base['design'][$field] ?? null)) {
                            $styles[$field] = $value;
                        }
                    }
                }
                $data['design'] = $styles ?: null;
            }
            $experiment->variants()->updateOrCreate(['key' => $key], $data);
        }
        if ($total !== 100) {
            $errors['variants.allocation'] = "Traffic must add up to 100% (now {$total}%).";
        }

        return $errors;
    }

    /** Problems that block launching, or []. */
    public function launchProblems(Experiment $experiment): array
    {
        $problems = [];
        $store = $experiment->store;
        $experience = $experiment->experience;
        if (! $store->planIncludes('ab_testing')) {
            $problems[] = 'A/B testing isn\'t included in your plan. Upgrade to launch.';
        }
        if ($reason = self::unsupported($experience)) {
            $problems[] = $reason;
        }
        if (! empty($experiment->audience['segments']) && ! $store->planIncludes('ab_testing_advanced')) {
            $problems[] = 'Testing a chosen audience is Advanced experimentation, which isn\'t in your plan. Remove the segments or upgrade.';
        }
        if (! $store->planIncludes('ab_traffic_guardrails')) {
            $shares = $experiment->variants->pluck('allocation')->unique();
            if ($shares->count() > 1 && $shares->max() - $shares->min() > 1) {
                $problems[] = 'Uneven traffic splits are part of Traffic allocation & guardrails, which isn\'t in your plan. Split traffic evenly or upgrade.';
            }
            if (! empty($experiment->guardrails)) {
                $problems[] = 'Guardrail metrics are part of Traffic allocation & guardrails, which isn\'t in your plan. Remove them or upgrade.';
            }
        }
        if ($experience->status !== 'published') {
            $problems[] = 'Publish the experience first: tests split the traffic of a live experience.';
        }
        if (Experiment::where('experience_id', $experience->id)->whereKeyNot($experiment->id)->whereIn('status', ['running', 'paused'])->exists()) {
            $problems[] = 'This experience is already in a running test. Stop that one first.';
        }
        $variants = $experiment->variants;
        if ($variants->count() < 2 || $variants->sum('allocation') !== 100) {
            $problems[] = 'Traffic must add up to 100% across at least two variants.';
        }
        if ($variants->where('key', '!=', 'A')->every(fn (ExperimentVariant $v) => ! $v->hidden && ! $v->content && ! $v->design && $v->template_key === $variants->firstWhere('key', 'A')?->template_key)) {
            $problems[] = 'Change at least one variant: a different template, text or design, or hide it.';
        }

        return $problems;
    }

    public function launch(Experiment $experiment, ?StoreUser $user): void
    {
        if ($problems = $this->launchProblems($experiment)) {
            throw new RuntimeException($problems[0]);
        }
        $experiment->forceFill(['status' => 'running', 'started_at' => $experiment->started_at ?? now(), 'ended_at' => null, 'result' => null, 'winner_key' => null])->save();
        $experiment->log('Launched with '.$experiment->variants->map(fn ($v) => $v->key.' '.$v->allocation.'%')->implode(', ').'.', $user);
        $this->sync($experiment);
    }

    public function pause(Experiment $experiment, ?StoreUser $user): void
    {
        if ($experiment->status !== 'running') {
            return;
        }
        $experiment->forceFill(['status' => 'paused'])->save();
        $experiment->log('Paused. Everyone sees the control until it resumes.', $user);
        $this->sync($experiment);
    }

    public function resume(Experiment $experiment, ?StoreUser $user): void
    {
        if ($experiment->status !== 'paused') {
            return;
        }
        $this->launch($experiment, $user);
    }

    /** Ends the test, keeping the experience as published. */
    public function stop(Experiment $experiment, ?StoreUser $user, string $status = 'stopped', ?string $message = null): void
    {
        if (! in_array($experiment->status, ['running', 'paused'], true)) {
            return;
        }
        $summary = app(Results::class)->for($experiment)['decision'];
        $experiment->forceFill([
            'status' => $status, 'ended_at' => now(),
            'result' => in_array($summary['state'], ['winner', 'control'], true) ? 'winner' : ($summary['state'] === 'no_winner' ? 'no_winner' : null),
            'winner_key' => $summary['winner'] ?? null,
        ])->save();
        $experiment->log($message ?? ($status === 'stopped' ? 'Stopped early.' : 'Completed.').' '.$summary['headline'], $user);
        $this->sync($experiment);
    }

    /** Publishes the winning variant's template, text and design to the experience and ends the test. */
    public function applyWinner(Experiment $experiment, string $key, ?StoreUser $user): void
    {
        $variant = $experiment->variants->firstWhere('key', $key);
        if (! $variant || $variant->hidden) {
            throw new RuntimeException('Only a visible variant can be applied.');
        }
        $experience = $experiment->experience;
        if ($key !== 'A') {
            $config = $experience->publishedVersion?->config ?? $experience->draft_config;
            $config['content'] = array_merge($config['content'] ?? [], $variant->content ?? []);
            $config['design'] = array_merge($config['design'] ?? [], $variant->design ?? []);
            $this->experiences->saveDraft($experience, $config, ['template_key' => $variant->template_key ?? $experience->template_key], $user);
            $this->experiences->publish($experience->fresh(), $user, 'Applied variant '.$key.' from A/B test “'.$experiment->name.'”');
        }
        if (in_array($experiment->status, ['running', 'paused'], true)) {
            $this->stop($experiment->fresh(), $user, 'completed', 'Completed. Variant '.$key.' ('.$variant->name.') applied to the experience.');
        } else {
            $experiment->log('Variant '.$key.' ('.$variant->name.') applied to the experience.', $user);
        }
    }

    public function duplicate(Experiment $experiment, ?StoreUser $user): Experiment
    {
        return DB::transaction(function () use ($experiment, $user) {
            $copy = $experiment->replicate(['handle', 'status', 'started_at', 'ended_at', 'result', 'winner_key']);
            $copy->fill(['handle' => 'x'.Str::lower(Str::random(10)), 'status' => 'draft', 'name' => mb_substr($experiment->name.' (copy)', 0, 120)])->save();
            foreach ($experiment->variants as $variant) {
                $copy->variants()->create($variant->only(['key', 'name', 'allocation', 'hidden', 'template_key', 'content', 'design']));
            }
            $copy->log('Draft created from “'.$experiment->name.'”.', $user);

            return $copy;
        });
    }

    /** Running tests whose end date has passed are completed. */
    public function completeDue(): int
    {
        $due = Experiment::with(['store', 'experience', 'variants'])->where('status', 'running')->whereNotNull('ends_at')->where('ends_at', '<=', now())->get();
        foreach ($due as $experiment) {
            $this->stop($experiment, null, 'completed', 'Completed on its end date.');
        }

        return $due->count();
    }

    private function sync(Experiment $experiment): void
    {
        try {
            $this->publisher->sync($experiment->store);
        } catch (Throwable $e) {
            report($e);
        }
    }
}
