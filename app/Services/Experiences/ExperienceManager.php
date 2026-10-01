<?php

namespace App\Services\Experiences;

use App\Experiences\Registry;
use App\Experiences\Schema;
use App\Models\AuditLog;
use App\Models\CroSetting;
use App\Models\Experience;
use App\Models\ExperienceVersion;
use App\Models\Store;
use App\Models\StoreUser;
use App\Services\Usage;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;

/**
 * Experience lifecycle: create → save draft → publish (versioned) → pause /
 * resume → archive, plus duplicate, restore version and discard changes.
 * Every storefront-visible change republishes the store payload.
 */
class ExperienceManager
{
    public function __construct(
        private readonly StorefrontPublisher $publisher,
        private readonly Usage $usage,
    ) {}

    public function create(Store $store, string $type, string $templateKey, ?StoreUser $user, ?string $name = null): Experience
    {
        $templateKey = Registry::template($type, $templateKey) ? $templateKey : Registry::defaultTemplate($type);
        $templateVersion = TemplateLibrary::currentVersion($type, $templateKey);

        $experience = Experience::create([
            'store_id' => $store->id,
            'type' => $type,
            'name' => $name ?: Registry::type($type)['singular'].' · '.Registry::template($type, $templateKey)['name'],
            'template_key' => $templateKey,
            'cro_template_version_id' => $templateVersion?->id,
            'status' => 'draft',
            'draft_config' => TemplateLibrary::defaults($type, $templateKey, CroSetting::brandingFor($store)),
            'has_unpublished_changes' => true,
            'created_by' => $user?->id,
            'updated_by' => $user?->id,
        ]);

        AuditLog::record('experience.created', $store, ['type' => $type, 'template' => $templateKey], $experience);

        return $experience;
    }

    public function saveDraft(Experience $experience, array $config, array $meta, ?StoreUser $user): Experience
    {
        $templateChanged = isset($meta['template_key']) && $meta['template_key'] !== $experience->template_key;

        $experience->fill([
            'name' => $meta['name'] ?? $experience->name,
            'description' => $meta['description'] ?? $experience->description,
            'template_key' => $meta['template_key'] ?? $experience->template_key,
            'draft_config' => $config,
            'has_unpublished_changes' => true,
            'updated_by' => $user?->id,
        ]);

        if ($templateChanged) {
            $experience->cro_template_version_id = TemplateLibrary::currentVersion($experience->type, $experience->template_key)?->id;
        }

        $experience->save();
        AuditLog::record('experience.saved', $experience->store, [], $experience);

        return $experience;
    }

    /**
     * Snapshots the draft into a new version and makes it live.
     *
     * @throws PublishException
     */
    public function publish(Experience $experience, ?StoreUser $user, ?string $changeNote = null): ExperienceVersion
    {
        $store = $experience->store;
        $this->assertPublishable($experience);

        return DB::transaction(function () use ($experience, $user, $changeNote, $store) {
            $version = ExperienceVersion::create([
                'cro_experience_id' => $experience->id,
                'version' => (int) $experience->versions()->max('version') + 1,
                'template_key' => $experience->template_key,
                'config' => $experience->draft_config,
                'change_note' => $changeNote ?: null,
                'created_by' => $user?->id,
                'published_at' => now(),
            ]);

            $schedule = $experience->draft_config['schedule'] ?? [];
            $experience->forceFill([
                'status' => 'published',
                'published_version_id' => $version->id,
                'published_at' => now(),
                'starts_at' => ! empty($schedule['starts_at']) ? Carbon::parse($schedule['starts_at']) : null,
                'ends_at' => ! empty($schedule['ends_at']) ? Carbon::parse($schedule['ends_at']) : null,
                'has_unpublished_changes' => false,
                'archived_at' => null,
                'updated_by' => $user?->id,
            ])->save();

            $this->publisher->sync($store);
            AuditLog::record('experience.published', $store, ['version' => $version->version], $experience);

            return $version;
        });
    }

    public function pause(Experience $experience): void
    {
        $this->transition($experience, 'paused', 'experience.paused');
    }

    public function resume(Experience $experience): void
    {
        if ($experience->published_version_id === null) {
            throw new PublishException('Publish this experience first.');
        }
        $this->assertPublishable($experience);
        $this->transition($experience, 'published', 'experience.resumed');
    }

    public function archive(Experience $experience): void
    {
        $this->transition($experience, 'archived', 'experience.archived', ['archived_at' => now()]);
    }

    public function unarchive(Experience $experience): void
    {
        $this->transition($experience, $experience->published_version_id ? 'paused' : 'draft', 'experience.unarchived', ['archived_at' => null]);
    }

    public function duplicate(Experience $experience, ?StoreUser $user): Experience
    {
        $copy = Experience::create([
            'store_id' => $experience->store_id,
            'type' => $experience->type,
            'name' => 'Copy of '.$experience->name,
            'description' => $experience->description,
            'template_key' => $experience->template_key,
            'cro_template_version_id' => $experience->cro_template_version_id,
            'status' => 'draft',
            'draft_config' => $experience->draft_config,
            'has_unpublished_changes' => true,
            'created_by' => $user?->id,
            'updated_by' => $user?->id,
        ]);

        AuditLog::record('experience.duplicated', $experience->store, ['from' => $experience->handle], $copy);

        return $copy;
    }

    /**
     * Loads an old version into the draft; publishing it makes it live again.
     */
    public function restoreVersion(Experience $experience, ExperienceVersion $version, ?StoreUser $user): void
    {
        $experience->forceFill([
            'draft_config' => $version->config,
            'template_key' => $version->template_key,
            'has_unpublished_changes' => true,
            'updated_by' => $user?->id,
        ])->save();

        AuditLog::record('experience.version_restored', $experience->store, ['version' => $version->version], $experience);
    }

    public function discardChanges(Experience $experience): void
    {
        $published = $experience->publishedVersion;
        if ($published === null) {
            return;
        }

        $experience->forceFill([
            'draft_config' => $published->config,
            'template_key' => $published->template_key,
            'has_unpublished_changes' => false,
        ])->save();

        AuditLog::record('experience.changes_discarded', $experience->store, [], $experience);
    }

    private function transition(Experience $experience, string $status, string $action, array $extra = []): void
    {
        DB::transaction(function () use ($experience, $status, $action, $extra) {
            $wasLive = $experience->status === 'published';
            $experience->forceFill(['status' => $status] + $extra)->save();

            if ($wasLive || $status === 'published') {
                $this->publisher->sync($experience->store);
            }

            AuditLog::record($action, $experience->store, [], $experience);
        });
    }

    private function assertPublishable(Experience $experience): void
    {
        $type = Registry::type($experience->type);
        $store = $experience->store;

        if (! $store->hasPlanAccess()) {
            throw new PublishException('Choose a plan to start publishing.', 'plan');
        }

        if ($store->offersSuspended()) {
            throw new PublishException('Your store has passed its plan\'s sales limit. Upgrade your plan to publish.', 'plan');
        }

        // Only a newly live experience adds to the plan's counts.
        if ($experience->status !== 'published') {
            if (! $this->usage->allows($store, 'active_experiences')) {
                throw new PublishException('You\'ve reached your current OrderOrbit plan limit.', 'plan');
            }
            if (($meter = $type['meter'] ?? null) && ! $this->usage->allows($store, $meter)) {
                $limit = (int) $store->planLimit($meter);
                $plan = config('shopify.billing.plans.'.$store->effectivePlan().'.name');

                throw new PublishException("The {$plan} plan includes {$limit} live ".lower_label(\Illuminate\Support\Str::plural($type['singular'], $limit)).'. Pause the one that\'s live or upgrade for unlimited.', 'plan');
            }
        }

        $errors = Schema::normalize($experience->type, $experience->draft_config, $store->timezone ?? 'UTC')[1];
        if ($errors !== []) {
            throw new PublishException('Fix the highlighted fields before publishing.', 'invalid');
        }
    }
}
