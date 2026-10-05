<?php

namespace App\Services\Billing;

use App\Experiences\Registry;
use App\Models\AuditLog;
use App\Models\Experience;
use App\Models\Store;
use App\Services\Experiences\StorefrontPublisher;
use Throwable;

/**
 * Keeps a store's live offers within its plan after a plan change. Offers over a plan's limit
 * are paused, never deleted: the most recently published stay live.
 */
class PlanLimits
{
    /**
     * @return int how many offers were paused
     */
    public function apply(Store $store, bool $sync = true): int
    {
        if (! $store->hasPlanAccess()) {
            return 0;
        }

        $paused = 0;
        // Only experiences the plan still includes are live (and count); the newest stay live.
        $live = Experience::with('publishedVersion')->where('store_id', $store->id)->where('status', 'published')
            ->orderByDesc('published_at')->orderByDesc('id')->get()
            ->filter(fn (Experience $e) => $store->allowsExperience($e->type, $e->publishedVersion?->config))->values();
        $pause = function (Experience $experience) use ($store, &$paused, &$live) {
            $experience->forceFill(['status' => 'paused'])->save();
            AuditLog::record('experience.paused_by_plan', $store, ['plan' => $store->effectivePlan()], $experience);
            $live = $live->reject(fn ($e) => $e->id === $experience->id)->values();
            $paused++;
        };

        foreach (array_unique(Registry::meters()) as $meter) {
            $limit = $store->planLimit($meter);
            if ($limit === null) {
                continue;
            }
            $types = array_keys(Registry::meters(), $meter, true);
            $live->filter(fn ($e) => in_array($e->type, $types, true))->values()->slice($limit)->each($pause);
        }
        if (($limit = $store->planLimit('active_experiences')) !== null) {
            $live->filter(fn ($e) => \App\Services\Usage::countsAsActive($e->type))->values()->slice($limit)->each($pause);
        }

        // Workflows over the plan's limit are switched off, newest first; they stay saved.
        $workflows = $store->planLimit('workflows');
        if ($workflows !== null) {
            \App\Models\Automation\Workflow::where('store_id', $store->id)->where('status', 'enabled')
                ->orderByDesc('updated_at')->orderByDesc('id')->get()->slice($workflows)
                ->each(function ($workflow) use ($store, &$paused) {
                    $workflow->forceFill(['status' => 'disabled'])->save();
                    AuditLog::record('workflow.disabled_by_plan', $store, ['plan' => $store->effectivePlan()], $workflow);
                    $paused++;
                });
        }

        if ($paused > 0 && $sync) {
            try {
                app(StorefrontPublisher::class)->sync($store);
            } catch (Throwable $e) {
                report($e);
            }
        }

        return $paused;
    }
}
