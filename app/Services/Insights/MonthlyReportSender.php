<?php

namespace App\Services\Insights;

use App\Models\Profit\ProfitSetting;
use App\Models\Store;
use App\Models\StoreUser;
use App\Services\Mail\EmailSender;
use App\Services\Mail\OutgoingEmail;
use App\Services\Profit\ProfitSync;
use App\Support\Features;
use App\Support\StaffAccess;
use Dompdf\Dompdf;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\URL;
use Throwable;

/**
 * The Monthly Business Report as a PDF and by email (G15): on the 1st (in the store's time zone)
 * every paid store with the report gets last month's, sent once, with the PDF attached, to the
 * people who can see profit and haven't turned summary emails off.
 */
class MonthlyReportSender
{
    public function __construct(private readonly MonthlyReport $reports, private readonly EmailSender $sender) {}

    /** Stores due for last month's report. Run daily; each month goes out once per store. */
    public function run(): int
    {
        $sent = 0;
        Store::whereNotNull('installed_at')->whereNull('uninstalled_at')->lazyById(50)->each(function (Store $store) use (&$sent) {
            // The first 3 days of the month in the store's time zone (a store that turns it on mid-month
            // waits for the next 1st instead of getting last month's report at a random time).
            if (now($store->timezone ?: 'UTC')->day <= 3 && self::available($store)
                && ProfitSetting::where('store_id', $store->id)->whereNotNull('backfilled_at')->exists()) {
                $sent += $this->sendMonth($store);
            }
        });

        return $sent;
    }

    public static function available(Store $store): bool
    {
        return Features::enabled('monthly_report', $store) && ProfitSync::enabled($store) && $store->planIncludes('sales_insights') && $store->hasPlanAccess();
    }

    /** Last month's report to the store's people, once. */
    public function sendMonth(Store $store): int
    {
        $report = $this->reports->build($store);
        try {
            $id = DB::table('lifecycle_emails')->insertGetId(['store_id' => $store->id, 'kind' => 'monthly:'.$report['month'], 'to_email' => '', 'status' => 'sending', 'created_at' => now(), 'updated_at' => now()]);
        } catch (QueryException) {
            return 0; // already sent
        }
        $people = $store->users()->whereNull('disabled_at')->whereNotNull('email')->where('weekly_email', true)->get()
            ->filter(fn (StoreUser $u) => StaffAccess::can($u, 'profit'));
        $count = 0;
        foreach ($people as $u) {
            $count += $this->sendTo($store, $u, $report) ? 1 : 0;
        }
        DB::table('lifecycle_emails')->where('id', $id)->update(['to_email' => mb_substr($people->pluck('email')->implode(', '), 0, 191), 'status' => $count ? 'sent' : 'failed', 'updated_at' => now()]);

        return $count;
    }

    /** One person, one report (also "Email to me" on the report page). */
    public function sendTo(Store $store, StoreUser $user, array $report): bool
    {
        $m = fn ($v) => $report['currency'].' '.number_format((float) $v, 2);
        $lines = [$report['summary'], ''];
        foreach ($report['kpis'] as $k) {
            $lines[] = $k['label'].': '.($k['value'] === null ? '—' : ($k['kind'] === 'money' ? $m($k['value']) : ($k['kind'] === 'percent' ? $k['value'].'%' : $k['value']))).($k['change'] ? " ({$k['change']})" : '');
        }
        if ($report['todo']) {
            $lines[] = '';
            $lines[] = "3 things to do in {$report['next']}:";
            foreach ($report['todo'] as $i => $t) {
                $lines[] = ($i + 1).". {$t['title']}";
            }
        }
        $app = $store->adminUrl('apps/'.(Cache::get('shopify.app_handle') ?: config('shopify.app_handle')).'/app/insights/report?month='.$report['month']);
        try {
            $result = $this->sender->send(OutgoingEmail::designed($user->email, "{$report['store']} · {$report['label']} business report", [
                'heading' => "Your {$report['label']} report", 'intro' => 'Hi'.($user->first_name ? " {$user->first_name}" : '').",\n\n".implode("\n", $lines),
                'cta' => ['Open the full report', $app], 'help' => 'The full report is attached as a PDF.',
                'footer' => "Sent on the 1st to people who can see profit for {$report['store']}.",
                'unsubscribe' => URL::signedRoute('weekly.off', ['user' => $user->id]), 'unsubscribe_label' => 'Turn off summary emails',
            ], ['category' => 'monthly_report', 'storeId' => $store->id, 'attachments' => [[
                'name' => 'growvia-report-'.$report['month'].'.pdf', 'mime' => 'application/pdf', 'data' => $this->pdf($report),
            ]]]));

            return $result->ok();
        } catch (Throwable $e) {
            report($e);

            return false;
        }
    }

    /** The report as an A4 PDF. */
    public function pdf(array $r): string
    {
        $e = fn ($v) => e((string) $v);
        $m = fn ($v) => $e($r['currency'].' '.number_format((float) $v, 2));
        $kpis = '';
        foreach ($r['kpis'] as $k) {
            $value = $k['value'] === null ? '—' : ($k['kind'] === 'money' ? $m($k['value']) : ($k['kind'] === 'percent' ? $e($k['value']).'%' : $e(number_format((float) $k['value']))));
            $kpis .= '<td class="kpi"><small>'.$e($k['label']).'</small><b>'.$value.'</b><span>'.$e($k['change'] ?? '').'</span></td>';
        }
        $rows = fn (array $items, callable $right) => implode('', array_map(fn ($i) => '<tr><td>'.$e($i['name']).'</td><td class="r">'.$right($i).'</td></tr>', $items));
        $widgets = $r['widgets'] ? $rows($r['widgets'], fn ($w) => $m($w['value']))
            .'<tr class="total"><td>Total from offers</td><td class="r">'.$m($r['widget_total']).($r['widget_share'] !== null ? ' · '.$e($r['widget_share']).'% of sales' : '').'</td></tr>'
            : '<tr><td class="muted">No offer sales recorded this month.</td></tr>';
        $top = $r['top'] ? $rows($r['top'], fn ($t) => $m($t['value']).($t['margin'] !== null ? ' · '.$e($t['margin']).'% margin' : '')) : '<tr><td class="muted">Add product costs to see profit by product.</td></tr>';
        $todo = $r['todo'] ? implode('', array_map(fn ($t, $i) => '<div class="todo"><b>'.($i + 1).'. '.$e($t['title']).'</b><br><span>'.$e($t['detail']).'</span></div>', $r['todo'], array_keys($r['todo'])))
            : '<p class="muted">Nothing open right now.</p>';
        $html = '<!doctype html><html><head><meta charset="utf-8"><style>
            body{font-family:"DejaVu Sans",sans-serif;font-size:10pt;color:#1b1830;margin:28pt}
            h1{font-size:18pt;margin:0}h2{font-size:12pt;margin:18pt 0 6pt}.sub{color:#6b6880;margin:2pt 0 12pt}
            .summary{background:#f4f4f8;padding:10pt;border-radius:6pt;line-height:1.5}
            table{width:100%;border-collapse:collapse}.kpis td{width:25%;padding:8pt;border:1pt solid #e6e4ef}
            .kpi small{display:block;color:#6b6880;font-size:8pt}.kpi b{display:block;font-size:14pt;margin:2pt 0}.kpi span{font-size:8pt;color:#4b4862}
            .list td{padding:5pt 0;border-bottom:1pt solid #f0eef5}.r{text-align:right}.total td{font-weight:bold;border-top:1pt solid #1b1830}
            .muted{color:#6b6880}.todo{margin:0 0 8pt}.todo span{color:#4b4862}.foot{margin-top:18pt;font-size:8pt;color:#6b6880}
        </style></head><body>'
            .'<h1>'.$e($r['store']).' · Monthly Business Report</h1><p class="sub">'.$e($r['label']).'</p>'
            .'<p class="summary">'.$e($r['summary']).'</p>'
            .'<table class="kpis"><tr>'.$kpis.'</tr></table>'
            .'<h2>What Growvia\'s offers earned</h2><table class="list">'.$widgets.'</table>'
            .'<h2>Top products by profit</h2><table class="list">'.$top.'</table>'
            .'<h2>'.$e($r['problems']).' open '.($r['problems'] === 1 ? 'problem' : 'problems').' · 3 things to do in '.$e($r['next']).'</h2>'.$todo
            .'<p class="foot">Numbers match the Profit dashboard for '.$e($r['from']).' to '.$e($r['to']).'.'
            .($r['missing_share'] > 0 ? ' '.$e($r['missing_share']).'% of sales had no product cost and is left out of profit.' : '')
            .' Offer sales are the order lines Growvia\'s offers added.</p></body></html>';

        $pdf = new Dompdf(['isRemoteEnabled' => false, 'isPhpEnabled' => false, 'defaultFont' => 'DejaVu Sans']);
        $pdf->loadHtml($html, 'UTF-8');
        $pdf->setPaper('A4');
        $pdf->render();

        return (string) $pdf->output();
    }
}
