<?php

namespace App\Automation;

use App\Models\Automation\Workflow;
use App\Models\Automation\WorkflowLog;
use App\Models\Automation\WorkflowRun;
use App\Models\Store;
use App\Services\Usage;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Support\Facades\Log;
use Throwable;

/**
 * Starts and runs workflows. A trigger starts one run per enabled workflow (idempotent: the same
 * Shopify event never starts a workflow twice). A run walks its version's program until it ends,
 * waits (resumed by the worker's tick), or fails; failed actions are retried twice (after 5 and
 * 30 minutes) before the run is marked failed. Every step is logged.
 */
class Engine
{
    private const RETRY_MINUTES = [5, 30];

    public function __construct(private readonly Conditions $conditions, private readonly Actions $actions, private readonly Usage $usage) {}

    /**
     * @param  string  $event  a key unique to the Shopify event (e.g. "order_paid:5551")
     * @param  array  $facts  extra trigger facts: products, added_tags, event
     */
    public function trigger(Store $store, string $trigger, array $context, string $event, array $facts = []): int
    {
        if (! $this->available($store)) {
            return 0;
        }

        $started = 0;
        Workflow::with('publishedVersion')->where('store_id', $store->id)->where('status', 'enabled')->where('trigger', $trigger)->get()
            ->filter(fn (Workflow $w) => $w->publishedVersion && $this->matches($w->publishedVersion->definition, $context, $facts))
            ->each(function (Workflow $w) use ($store, $context, $event, &$started) {
                if ($this->start($store, $w, $context, $event.':'.$w->id)) {
                    $started++;
                }
            });

        return $started;
    }

    public function start(Store $store, Workflow $workflow, array $context, string $key, int $depth = 0, bool $test = false): ?WorkflowRun
    {
        if (! $test) {
            if (! $this->available($store) || ! $this->usage->allows($store, 'automation_executions')) {
                return null;
            }
        }

        try {
            $run = WorkflowRun::create([
                'store_id' => $store->id, 'workflow_id' => $workflow->id, 'version_id' => $workflow->published_version_id,
                'trigger' => $workflow->trigger, 'idempotency_key' => $test ? 'test:'.uniqid('', true) : $key,
                'subject' => Context::subject($context), 'status' => 'running', 'step' => 0, 'attempts' => 0, 'depth' => $depth, 'context' => $context, 'results' => [], 'test' => $test,
            ]);
        } catch (UniqueConstraintViolationException) {
            return null; // already started for this event
        }

        if (! $test) {
            $this->usage->increment($store, 'automation_executions');
            $workflow->forceFill(['last_run_at' => now()])->save();
        }

        return $this->execute($run);
    }

    /** Resumes runs whose wait (or retry delay) is over. */
    public function tick(int $limit = 500): int
    {
        $due = WorkflowRun::where('status', 'waiting')->where('resume_at', '<=', now())->orderBy('resume_at')->limit($limit)->pluck('id');
        foreach ($due as $id) {
            // Claim the run so two workers never resume it twice.
            if (WorkflowRun::whereKey($id)->where('status', 'waiting')->update(['status' => 'running']) === 1) {
                $this->execute(WorkflowRun::find($id));
            }
        }

        return $due->count();
    }

    public function execute(WorkflowRun $run): WorkflowRun
    {
        $program = $run->version->program;
        $results = $run->results ?? [];

        for ($guard = 0; $guard < 500 && $run->step < count($program); $guard++) {
            $i = $run->step;
            $node = $program[$i];

            if ($node['t'] === 'jump') {
                $run->step = $node['to'];

                continue;
            }

            if ($node['t'] === 'cond') {
                try {
                    $pass = $this->conditions->passes($node, $run);
                } catch (Throwable $e) {
                    return $this->failure($run, $i, 'condition', $e);
                }
                $this->log($run, $i, 'condition', $pass ? 'ok' : 'skipped', Definition::describe($node).($pass ? ' → yes' : ' → no'));
                $run->step = $pass ? $i + 1 : $node['else'];

                continue;
            }

            if ($node['t'] === 'wait') {
                $run->step = $i + 1;
                if ($run->test) {
                    $this->log($run, $i, 'wait', 'skipped', 'Would wait '.$node['amount'].' '.$node['unit'].' (skipped in a test).');

                    continue;
                }
                $resume = now()->add($node['unit'], $node['amount']);
                $run->forceFill(['status' => 'waiting', 'resume_at' => $resume, 'results' => $results])->save();
                $this->log($run, $i, 'wait', 'waiting', 'Waiting '.$node['amount'].' '.$node['unit'].', until '.$resume->toDayDateTimeString().' UTC.');

                return $run;
            }

            // Action. Already done before a retry or crash: don't repeat it.
            if (array_key_exists((string) $i, $results)) {
                $run->step = $i + 1;

                continue;
            }
            if ($run->test) {
                $this->log($run, $i, $node['action'], 'skipped', $this->actions->describe($node, $run));
                $run->step = $i + 1;

                continue;
            }
            try {
                [$status, $message, $data] = $this->actions->run($node, $run, $i);
            } catch (Throwable $e) {
                $run->results = $results;

                return $this->failure($run, $i, $node['action'], $e);
            }
            $results[(string) $i] = $data;
            $run->forceFill(['results' => $results, 'attempts' => 0, 'error' => null]);
            $this->log($run, $i, $node['action'], $status, $message, $data ?: null);
            $run->step = $i + 1;
            $run->save();
        }

        $run->forceFill(['status' => 'completed', 'results' => $results, 'resume_at' => null, 'finished_at' => now()])->save();
        $this->log($run, $run->step, 'end', 'ok', $run->test ? 'Test finished. Nothing was changed.' : 'Workflow finished.');

        return $run;
    }

    /** Whether the store's plan runs automation right now. */
    public function available(Store $store): bool
    {
        return $store->hasPlanAccess() && $store->planIncludes('automation') && ! $store->offersSuspended();
    }

    private function matches(array $definition, array $context, array $facts): bool
    {
        $config = $definition['trigger_config'] ?? [];

        return match ($definition['trigger']) {
            'product_purchased' => (bool) array_intersect(
                array_map(fn ($p) => preg_replace('/\D/', '', (string) ($p['id'] ?? '')), $config['products'] ?? []),
                $context['order']['products'] ?? [],
            ),
            'customer_tag_added' => in_array(strtolower(trim((string) ($config['tag'] ?? ''))), array_map('strtolower', $facts['added_tags'] ?? []), true),
            'custom_event' => ($config['event'] ?? '') === ($context['event']['name'] ?? null),
            default => true,
        };
    }

    private function failure(WorkflowRun $run, int $step, string $kind, Throwable $e): WorkflowRun
    {
        $attempts = $run->attempts + 1;
        $message = mb_substr($e->getMessage(), 0, 400);
        if ($attempts <= count(self::RETRY_MINUTES)) {
            $resume = now()->addMinutes(self::RETRY_MINUTES[$attempts - 1]);
            $run->forceFill(['status' => 'waiting', 'attempts' => $attempts, 'resume_at' => $resume, 'error' => $message])->save();
            $this->log($run, $step, $kind, 'failed', "Failed: {$message} Retrying at ".$resume->format('H:i').' UTC.');
        } else {
            $run->forceFill(['status' => 'failed', 'attempts' => $attempts, 'resume_at' => null, 'error' => $message, 'finished_at' => now()])->save();
            $this->log($run, $step, $kind, 'failed', "Failed after {$attempts} attempts: {$message}");
            Log::info('Automation run failed', ['run' => $run->id, 'error' => $message]);
        }

        return $run;
    }

    private function log(WorkflowRun $run, int $step, string $kind, string $status, string $message, ?array $data = null): void
    {
        WorkflowLog::create(['run_id' => $run->id, 'step' => $step, 'kind' => $kind, 'status' => $status, 'message' => mb_substr($message, 0, 500), 'data' => $data, 'created_at' => now()]);
    }
}
