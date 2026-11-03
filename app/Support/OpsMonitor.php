<?php

namespace App\Support;

use App\Models\Automation\AutomationEmail;
use App\Models\Automation\WorkflowRun;
use App\Models\AuditLog;
use App\Models\ContactSubmission;
use App\Models\Mail\EmailDelivery;
use App\Models\OpsAlert;
use App\Models\Store;
use App\Models\Support\Ticket;
use App\Models\WebhookReceipt;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Throwable;

/**
 * Ops alerts (G26). Every few minutes the worker checks the things that break quietly. A problem
 * opens one alert, which is emailed to the team at most once an hour while it lasts and closes
 * by itself when the check passes again. A daily summary goes out every morning.
 */
class OpsMonitor
{
    public const EMAIL_EVERY_MINUTES = 60;

    /** @return array<string, array{0: string, 1: string}|null> key => [title, details] when failing, null when fine */
    public function checks(): array
    {
        $hour = now()->subHour();
        $stuckWebhooks = WebhookReceipt::whereNull('processed_at')->whereBetween('created_at', [$hour, now()->subMinutes(5)])->count();
        $failedRuns = WorkflowRun::where('status', 'failed')->where('updated_at', '>=', $hour)->count();
        $failedSends = EmailDelivery::where('status', 'failed')->where('created_at', '>=', $hour)->count();
        $waitingEmails = AutomationEmail::where('status', 'waiting_for_provider')->where('created_at', '<=', now()->subMinutes(30))->count();
        $rejectedTokens = AuditLog::where('action', 'store.token_refresh_rejected')->where('created_at', '>=', $hour)->count();

        return [
            'site_down' => $this->siteDown() ? ['The Growvia website or app is not responding', 'Two checks in a row of '.url('/up').' failed. Merchants may not be able to open the app.'] : null,
            'webhooks' => $stuckWebhooks >= 3 ? ["{$stuckWebhooks} Shopify webhooks failed in the last hour", 'They were received but not processed. Check the web service logs for errors in WebhookController.'] : null,
            'automation' => $failedRuns >= 5 ? ["{$failedRuns} workflow runs failed in the last hour", 'Open Internal Admin › Workflow failures to see which stores and steps.'] : null,
            'email_failing' => $failedSends >= 5 ? ["{$failedSends} emails failed to send in the last hour", 'Check Internal Admin › Email providers for limits or errors.'] : null,
            'email_waiting' => $waitingEmails >= 10 ? ["{$waitingEmails} workflow emails waiting over 30 minutes", 'No email provider is available to send them (all at their limit, resting after errors, or sending switched off).'] : null,
            'tokens' => $rejectedTokens >= 3 ? ["Shopify rejected {$rejectedTokens} token refreshes in the last hour", 'Stores may be disconnected until they open the app. Check the Shopify client secret on both services.'] : null,
        ];
    }

    /** Runs the checks, opens, emails and closes alerts. Returns the keys that are failing. */
    public function run(): array
    {
        Cache::put('ops-monitor:last-run', now()->toIso8601String(), now()->addDay());
        $failing = [];
        foreach ($this->checks() as $key => $problem) {
            $open = OpsAlert::where('key', $key)->whereNull('resolved_at')->first();
            if ($problem === null) {
                $open?->forceFill(['resolved_at' => now()])->save();

                continue;
            }
            $failing[] = $key;
            [$title, $details] = $problem;
            $open ??= new OpsAlert(['key' => $key, 'first_seen_at' => now(), 'seen' => 0]);
            $open->fill(['title' => $title, 'details' => $details, 'last_seen_at' => now(), 'seen' => $open->seen + 1])->save();
            if ($open->last_emailed_at === null || $open->last_emailed_at->lte(now()->subMinutes(self::EMAIL_EVERY_MINUTES))) {
                if (TeamAlert::send('Growvia alert: '.$title, $details."\n\nFirst seen ".$open->first_seen_at->toDayDateTimeString()." UTC.\nAll alerts: ".route('admin.ops'))) {
                    $open->forceFill(['last_emailed_at' => now()])->save();
                }
            }
        }

        return $failing;
    }

    /** The daily summary for the team: what happened in the last 24 hours. */
    public function digest(): bool
    {
        $day = now()->subDay();
        $lines = [
            'Stores installed: '.Store::where('installed_at', '>=', $day)->count(),
            'Stores uninstalled: '.Store::where('uninstalled_at', '>=', $day)->count(),
            'Stores active: '.Store::whereNotNull('installed_at')->whereNull('uninstalled_at')->count(),
            'New support tickets: '.Ticket::where('created_at', '>=', $day)->count().' (open now: '.Ticket::where('status', 'open')->count().')',
            'New contact messages: '.ContactSubmission::where('created_at', '>=', $day)->count().' (unread: '.ContactSubmission::where('status', 'new')->count().')',
            'Workflow runs failed: '.WorkflowRun::where('status', 'failed')->where('updated_at', '>=', $day)->count(),
            'Emails failed: '.EmailDelivery::where('status', 'failed')->where('created_at', '>=', $day)->count(),
            'Open alerts: '.OpsAlert::whereNull('resolved_at')->count(),
        ];

        return TeamAlert::send('Growvia daily summary · '.now()->toFormattedDateString(), implode("\n", $lines)."\n\nInternal Admin: ".route('admin.home'));
    }

    /** Down only after two failed checks in a row, so one slow response doesn't page anyone. */
    private function siteDown(): bool
    {
        try {
            $ok = Http::timeout(10)->connectTimeout(5)->get(url('/up'))->successful();
        } catch (Throwable) {
            $ok = false;
        }
        if ($ok) {
            Cache::forget('ops-monitor:up-failures');

            return false;
        }

        return Cache::increment('ops-monitor:up-failures') >= 2;
    }
}
