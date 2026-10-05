<?php

namespace App\Automation;

use App\Models\AuditLog;
use App\Models\Automation\Workflow;
use App\Models\Automation\WorkflowRun;
use App\Models\Automation\WorkflowVersion;
use App\Models\Store;
use App\Models\StoreUser;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use RuntimeException;

/**
 * Workflow lifecycle: create (blank or from a template), save draft, publish (a numbered version
 * that runs keep following), enable/disable, test with a sample order, and restore a version.
 */
class WorkflowManager
{
    public function __construct(private readonly Engine $engine) {}

    public function create(Store $store, ?string $template, ?StoreUser $user, ?string $name = null): Workflow
    {
        $templates = Definition::templates();
        $definition = $template && isset($templates[$template])
            ? Definition::normalize($templates[$template] + ['trigger_config' => []])[0]
            : ['trigger' => 'order_paid', 'trigger_config' => [], 'steps' => []];

        $workflow = Workflow::create([
            'store_id' => $store->id,
            'handle' => 'wf'.Str::lower(Str::random(8)),
            'name' => $name ?: ($templates[$template]['name'] ?? 'New workflow'),
            'template' => $template && isset($templates[$template]) ? $template : null,
            'status' => 'draft',
            'trigger' => $definition['trigger'],
            'draft' => $definition,
            'has_unpublished_changes' => true,
        ]);
        AuditLog::record('workflow.created', $store, ['template' => $template], $workflow);

        return $workflow;
    }

    /** @return array<string, string> errors (the draft is saved either way) */
    public function saveDraft(Workflow $workflow, array $input, ?string $name): array
    {
        [$definition, $errors] = Definition::normalize($input);
        $workflow->forceFill([
            'name' => trim((string) $name) !== '' ? mb_substr(trim((string) $name), 0, 120) : $workflow->name,
            'draft' => $definition,
            'trigger' => $definition['trigger'],
            'has_unpublished_changes' => true,
        ])->save();

        return $errors;
    }

    public function publish(Workflow $workflow, ?StoreUser $user): WorkflowVersion
    {
        [$definition, $errors] = Definition::normalize($workflow->draft);
        if ($errors) {
            throw new RuntimeException('Fix the highlighted steps before publishing.');
        }
        if ($why = $this->engine->blocked($workflow->store, Definition::compile($definition['steps']))) {
            throw new RuntimeException($why.' Upgrade your plan to publish it.');
        }
        $this->assertWithinLimit($workflow);

        return DB::transaction(function () use ($workflow, $definition, $user) {
            $version = WorkflowVersion::create([
                'workflow_id' => $workflow->id,
                'version' => (int) $workflow->versions()->max('version') + 1,
                'definition' => $definition,
                'program' => Definition::compile($definition['steps']),
                'created_by' => $user?->id,
                'published_at' => now(),
            ]);
            $workflow->forceFill([
                'published_version_id' => $version->id,
                'trigger' => $definition['trigger'],
                'status' => 'enabled',
                'has_unpublished_changes' => false,
            ])->save();
            AuditLog::record('workflow.published', $workflow->store, ['version' => $version->version], $workflow);

            return $version;
        });
    }

    public function setEnabled(Workflow $workflow, bool $enabled): void
    {
        if ($enabled && ! $workflow->published_version_id) {
            throw new RuntimeException('Publish the workflow first.');
        }
        if ($enabled) {
            if ($why = $this->engine->blocked($workflow->store, $workflow->publishedVersion?->program ?? [])) {
                throw new RuntimeException($why.' Upgrade your plan to switch it on.');
            }
            $this->assertWithinLimit($workflow);
        }
        $workflow->forceFill(['status' => $enabled ? 'enabled' : 'disabled'])->save();
        AuditLog::record($enabled ? 'workflow.enabled' : 'workflow.disabled', $workflow->store, [], $workflow);
    }

    /**
     * Runs the current draft against a sample order (or the given context) without changing
     * anything: conditions are evaluated, waits are skipped and actions say what they would do.
     */
    public function test(Workflow $workflow, ?array $context = null): WorkflowRun
    {
        [$definition, $errors] = Definition::normalize($workflow->draft);
        if ($errors) {
            throw new RuntimeException('Fix the highlighted steps before testing.');
        }
        // A throwaway version 0 so the test follows the draft, not the published workflow. The
        // previous test (and its run) is replaced.
        WorkflowVersion::where('workflow_id', $workflow->id)->where('version', 0)->delete();
        $version = WorkflowVersion::create([
            'workflow_id' => $workflow->id, 'version' => 0, 'definition' => $definition,
            'program' => Definition::compile($definition['steps']), 'published_at' => now(),
        ]);

        $test = $workflow->replicate()->forceFill(['published_version_id' => $version->id]);
        $test->id = $workflow->id;

        return $this->engine->start($workflow->store, $test, $context ?? Context::sample(), '', 0, true);
    }

    public function restore(Workflow $workflow, WorkflowVersion $version): void
    {
        $workflow->forceFill(['draft' => $version->definition, 'trigger' => $version->definition['trigger'], 'has_unpublished_changes' => true])->save();
    }

    /** A workflow that isn't on yet needs room under the plan's workflow limit. */
    private function assertWithinLimit(Workflow $workflow): void
    {
        if ($workflow->status !== 'enabled' && ! app(\App\Services\Usage::class)->allows($workflow->store, 'workflows')) {
            $limit = (int) $workflow->store->planLimit('workflows');

            throw new RuntimeException("Your plan includes {$limit} active ".\Illuminate\Support\Str::plural('workflow', $limit).'. Switch one off or upgrade for more.');
        }
    }
}
