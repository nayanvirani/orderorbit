<?php

namespace App\Support;

use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Schema;
use Throwable;

/**
 * Railway hosting costs for Finance, from Railway's public API: the workspace's invoices (what was
 * actually charged, after credits) and the running billing period's usage, which becomes an
 * estimate for the next invoice (the plan's minimum, or the usage when higher).
 *
 * Token: an account token from railway.com/account/tokens, saved (encrypted) in the Internal Admin
 * or set as the RAILWAY_API_TOKEN variable.
 */
class RailwayBilling
{
    public const ENDPOINT = 'https://backboard.railway.com/graphql/v2';

    private const KEY = 'railway';

    private const CACHE = 'railway-billing:v1';

    /** What each plan charges at least per period (it includes that much usage). */
    public const PLAN_MINIMUM = ['FREE' => 0.0, 'HOBBY' => 5.0, 'PRO' => 20.0];

    /** @return array{token: ?string, workspace_id: ?string, from_env: bool} */
    public static function settings(): array
    {
        $saved = [];
        try {
            $row = Schema::hasTable('platform_settings') ? DB::table('platform_settings')->where('key', self::KEY)->value('value') : null;
            $saved = $row ? (array) json_decode($row, true) : [];
        } catch (Throwable) {
            // No database yet.
        }
        $token = null;
        if (! empty($saved['token'])) {
            try {
                $token = Crypt::decryptString($saved['token']);
            } catch (Throwable) {
                $token = null;
            }
        }

        $env = config('services.railway.token') ?: null;

        return ['token' => $token ?: $env, 'workspace_id' => $saved['workspace_id'] ?? null, 'from_env' => ! $token && $env];
    }

    public static function connected(): bool
    {
        return (bool) self::settings()['token'];
    }

    /** Saves the token (encrypted; empty keeps the current one) and the optional workspace. */
    public static function save(?string $token, ?string $workspaceId, bool $forget = false): void
    {
        $row = DB::table('platform_settings')->where('key', self::KEY)->value('value');
        $current = $row ? (array) json_decode($row, true) : [];
        $value = [
            'token' => $forget ? null : (trim((string) $token) !== '' ? Crypt::encryptString(trim($token)) : ($current['token'] ?? null)),
            'workspace_id' => preg_match('/^[a-f0-9-]{36}$/', trim((string) $workspaceId)) ? trim($workspaceId) : null,
        ];
        DB::table('platform_settings')->updateOrInsert(['key' => self::KEY], ['value' => json_encode($value), 'updated_at' => now(), 'created_at' => now()]);
        Cache::forget(self::CACHE);
    }

    public static function refresh(): void
    {
        Cache::forget(self::CACHE);
    }

    /**
     * The workspace's billing: plan, running period, usage so far, credit and invoices (newest first).
     * Cached for an hour.
     *
     * @return array{ok: bool, error?: string, workspace?: string, plan?: string, period?: array{start: string, end: string}, usage?: float, credit?: float, invoices?: list<array>, fetched_at?: string}
     */
    public static function data(): array
    {
        $s = self::settings();
        if (! $s['token']) {
            return ['ok' => false, 'error' => 'not_connected'];
        }

        return Cache::remember(self::CACHE, 3600, function () use ($s) {
            $fields = 'id name plan customer { currentUsage creditBalance billingPeriod { start end } invoices { invoiceId periodStart periodEnd total amountPaid amountDue status hostedURL } }';
            try {
                $response = Http::timeout(15)->withToken($s['token'])->acceptJson()->post(self::ENDPOINT, [
                    'query' => $s['workspace_id'] ? "query(\$id: String!) { workspace(workspaceId: \$id) { {$fields} } }" : "{ me { workspaces { {$fields} } } }",
                    'variables' => $s['workspace_id'] ? ['id' => $s['workspace_id']] : (object) [],
                ]);
            } catch (Throwable $e) {
                return ['ok' => false, 'error' => 'Railway couldn\'t be reached: '.$e->getMessage()];
            }
            $body = $response->json() ?? [];
            if (! $response->successful() || ! empty($body['errors'])) {
                $message = $body['errors'][0]['message'] ?? ('HTTP '.$response->status());

                return ['ok' => false, 'error' => 'Railway refused the request: '.$message.'. Use an account token from railway.com/account/tokens.'];
            }
            $workspace = $s['workspace_id'] ? ($body['data']['workspace'] ?? null) : (($body['data']['me']['workspaces'] ?? [])[0] ?? null);
            if (! $workspace || empty($workspace['customer'])) {
                return ['ok' => false, 'error' => 'No Railway workspace with billing was found for this token.'];
            }
            $c = $workspace['customer'];
            $invoices = collect($c['invoices'] ?? [])
                ->reject(fn ($i) => in_array($i['status'] ?? '', ['void', 'draft', 'uncollectible'], true))
                ->map(fn ($i) => [
                    'id' => $i['invoiceId'] ?? null,
                    'date' => CarbonImmutable::parse($i['periodEnd'] ?: $i['periodStart'])->toDateString(),
                    'total' => round(((int) ($i['total'] ?? 0)) / 100, 2),
                    // What was actually charged, after credits.
                    'charged' => round(((int) (($i['status'] ?? '') === 'paid' ? ($i['amountPaid'] ?? 0) : ($i['amountDue'] ?? 0))) / 100, 2),
                    'status' => $i['status'] ?? '',
                    'url' => $i['hostedURL'] ?? null,
                ])
                ->sortByDesc('date')->values()->all();

            return [
                'ok' => true,
                'workspace' => (string) $workspace['name'],
                'plan' => (string) ($workspace['plan'] ?? ''),
                'period' => ['start' => CarbonImmutable::parse($c['billingPeriod']['start'])->toDateString(), 'end' => CarbonImmutable::parse($c['billingPeriod']['end'])->toDateString()],
                'usage' => round((float) ($c['currentUsage'] ?? 0), 2),
                'credit' => round((float) ($c['creditBalance'] ?? 0), 2),
                'invoices' => $invoices,
                'fetched_at' => now()->toIso8601String(),
            ];
        });
    }

    /**
     * Railway's cost for a month: invoices issued that month, plus an estimate for the running period
     * when its invoice is due that month and hasn't been issued yet.
     *
     * @return array{connected: bool, amount: float, invoices: list<array>, estimate: ?array}
     */
    public static function forMonth(string $month): array
    {
        $data = self::data();
        if (! $data['ok']) {
            return ['connected' => false, 'amount' => 0.0, 'invoices' => [], 'estimate' => null, 'error' => $data['error']];
        }
        $invoices = array_values(array_filter($data['invoices'], fn ($i) => str_starts_with($i['date'], $month)));
        $estimate = null;
        if (str_starts_with($data['period']['end'], $month) && ! collect($data['invoices'])->contains(fn ($i) => $i['date'] >= $data['period']['end'])) {
            $min = self::PLAN_MINIMUM[$data['plan']] ?? 0.0;
            $amount = round(max(0, max($min, $data['usage']) - $data['credit']), 2);
            $estimate = ['amount' => $amount, 'usage' => $data['usage'], 'minimum' => $min, 'due' => $data['period']['end']];
        }

        return [
            'connected' => true,
            'amount' => round(array_sum(array_column($invoices, 'charged')) + ($estimate['amount'] ?? 0), 2),
            'invoices' => $invoices,
            'estimate' => $estimate,
        ];
    }
}
