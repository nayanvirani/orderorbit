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
    public function apply(Store $store): int
    {
        if (! $store->hasPlanAccess()) {
            return 0;
        }

        $paused = 0;
        foreach (array_unique(Registry::meters()) as $meter) {
            $limit = $store->planLimit($meter);
            if ($limit === null) {
                continue;
            }
            $types = array_keys(Registry::meters(), $meter, true);

            Experience::where('store_id', $store->id)->whereIn('type', $types)->where('status', 'published')
                ->orderByDesc('published_at')->orderByDesc('id')->get()
                ->slice($limit)
                ->each(function (Experience $experience) use ($store, &$paused) {
                    $experience->forceFill(['status' => 'paused'])->save();
                    AuditLog::record('experience.paused_by_plan', $store, ['plan' => $store->effectivePlan()], $experience);
                    $paused++;
                });
        }

        if ($paused > 0) {
            try {
                app(StorefrontPublisher::class)->sync($store);
            } catch (Throwable $e) {
                report($e);
            }
        }

        return $paused;
    }
}
