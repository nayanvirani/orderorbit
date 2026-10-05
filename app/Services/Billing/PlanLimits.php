<?php

namespace App\Services\Billing;

use App\Services\Usage;
use App\Models\AuditLog;
use App\Models\Experience;
use App\Models\Store;
use App\Services\Experiences\StorefrontPublisher;
use Throwable;

/**
 * Keeps a store within its plan after any change to it. Offers the plan doesn't include, or over
 * a limit, are paused and marked (never deleted); the newest stay live. When the plan allows them
 * again they come back on their own. Workflows, tests and rules over their limits are switched off.
 */
class PlanLimits
{
    /**
     * @return int how many offers were paused
     */
    public function apply(Store $store, bool $sync = true): int
    {
        $changes = 0;
        $plan = $store->effectivePlan();
        $limit = fn (string $meter) => $store->hasPlanAccess() ? $store->planLimit($meter) : 0;
        $meterOf = fn (Experience $e) => Usage::meterFor($e->type);
        $allowed = fn (Experience $e) => $store->hasPlanAccess() && $store->allowsExperience($e->type, $e->publishedVersion?->config);

        $pause = function (Experience $experience) use ($store, $plan, &$changes) {
            $experience->forceFill(['status' => 'paused', 'paused_by_plan' => true])->save();
            AuditLog::record('experience.paused_by_plan', $store, ['plan' => $plan], $experience);
            $changes++;
        };

        // 1. Offers the plan doesn't include are paused (marked, so they come back on their own).
        $live = Experience::with('publishedVersion')->where('store_id', $store->id)->where('status', 'published')
            ->orderByDesc('published_at')->orderByDesc('id')->get();
        [$keep, $drop] = $live->partition($allowed);
        $drop->each($pause);

        // 2. Over a type's limit or the total: the newest stay live.
        $counts = [];
        $total = 0;
        $keep->each(function (Experience $e) use (&$counts, &$total, $limit, $meterOf, $pause) {
            $meter = $meterOf($e);
            $typeLimit = $meter ? $limit($meter) : null;
            $totalLimit = $limit('active_experiences');
            if (($typeLimit !== null && ($counts[$meter] ?? 0) >= $typeLimit) || ($totalLimit !== null && $total >= $totalLimit)) {
                $pause($e);

                return;
            }
            if ($meter) {
                $counts[$meter] = ($counts[$meter] ?? 0) + 1;
            }
            $total++;
        });

        // 3. Offers a plan change paused come back when the plan has room again, most recent first.
        Experience::with('publishedVersion')->where('store_id', $store->id)->where('status', 'paused')->where('paused_by_plan', true)
            ->orderByDesc('published_at')->orderByDesc('id')->get()
            ->filter($allowed)
            ->each(function (Experience $e) use (&$counts, &$total, $limit, $meterOf, $store, $plan, &$changes) {
                $meter = $meterOf($e);
                $typeLimit = $meter ? $limit($meter) : null;
                $totalLimit = $limit('active_experiences');
                if (($typeLimit !== null && ($counts[$meter] ?? 0) >= $typeLimit) || ($totalLimit !== null && $total >= $totalLimit)) {
                    return;
                }
                $e->forceFill(['status' => 'published', 'paused_by_plan' => false])->save();
                AuditLog::record('experience.resumed_by_plan', $store, ['plan' => $plan], $e);
                if ($meter) {
                    $counts[$meter] = ($counts[$meter] ?? 0) + 1;
                }
                $total++;
                $changes++;
            });

        // 4. Workflows, running A/B tests and personalization rules over their limits are switched
        //    off, newest first. Nothing is deleted.
        $over = function ($query, string $meter, array $off, string $event) use ($store, $plan, $limit, &$changes) {
            $max = $limit($meter);
            if ($max === null) {
                return;
            }
            $query->orderByDesc('updated_at')->orderByDesc('id')->get()->slice($max)->each(function ($model) use ($off, $event, $store, $plan, &$changes) {
                $model->forceFill($off)->save();
                AuditLog::record($event, $store, ['plan' => $plan], $model);
                $changes++;
            });
        };
        $over(\App\Models\Automation\Workflow::where('store_id', $store->id)->where('status', 'enabled'), 'workflows', ['status' => 'disabled'], 'workflow.disabled_by_plan');
        $over(\App\Models\Experiments\Experiment::where('store_id', $store->id)->where('status', 'running'), 'running_tests', ['status' => 'paused'], 'experiment.paused_by_plan');
        $over(\App\Models\Audiences\PersonalizationRule::where('store_id', $store->id)->where('enabled', true), 'personalization_rules', ['enabled' => false], 'rule.disabled_by_plan');

        if ($changes > 0 && $sync) {
            try {
                app(StorefrontPublisher::class)->sync($store);
            } catch (Throwable $e) {
                report($e);
            }
        }

        return $changes;
    }
}
