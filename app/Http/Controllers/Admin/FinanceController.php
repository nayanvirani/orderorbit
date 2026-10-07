<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\AuditLog;
use App\Models\FinanceEntry;
use App\Support\Finance;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

/**
 * Finance: monthly earnings, expenses (fixed costs, percentage fees, manual entries) and profit.
 */
class FinanceController extends Controller
{
    public function show(Request $request): View
    {
        $month = preg_match('/^\d{4}-(0[1-9]|1[0-2])$/', (string) $request->query('month')) ? $request->query('month') : now()->format('Y-m');

        return view('admin.finance', [
            'month' => Finance::month($month),
            'history' => Finance::months(12),
            'settings' => Finance::settings(),
            'categories' => Finance::CATEGORIES,
            'currency' => config('shopify.billing.currency', 'USD'),
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'kind' => ['required', 'in:expense,income'],
            'date' => ['required', 'date', 'after:2000-01-01', 'before:2100-01-01'],
            'description' => ['required', 'string', 'max:200'],
            'category' => ['nullable', 'string', 'max:60'],
            'amount' => ['required', 'numeric', 'min:0.01', 'max:9999999'],
        ]);
        $entry = FinanceEntry::create($data + ['created_by' => $request->user()->id]);
        AuditLog::record('admin.finance_entry_added', null, ['by' => $request->user()->email, 'kind' => $entry->kind, 'amount' => $entry->amount]);

        return redirect()->route('admin.finance', ['month' => $entry->date->format('Y-m')])->with('status', ucfirst($entry->kind).' added.');
    }

    public function update(Request $request, FinanceEntry $entry): RedirectResponse
    {
        $data = $request->validate([
            'kind' => ['required', 'in:expense,income'],
            'date' => ['required', 'date', 'after:2000-01-01', 'before:2100-01-01'],
            'description' => ['required', 'string', 'max:200'],
            'category' => ['nullable', 'string', 'max:60'],
            'amount' => ['required', 'numeric', 'min:0.01', 'max:9999999'],
        ]);
        $entry->update($data);

        return redirect()->route('admin.finance', ['month' => $entry->date->format('Y-m')])->with('status', 'Entry updated.');
    }

    public function destroy(Request $request, FinanceEntry $entry): RedirectResponse
    {
        $month = $entry->date->format('Y-m');
        AuditLog::record('admin.finance_entry_deleted', null, ['by' => $request->user()->email, 'description' => $entry->description, 'amount' => $entry->amount]);
        $entry->delete();

        return redirect()->route('admin.finance', ['month' => $month])->with('status', 'Entry deleted.');
    }

    public function settings(Request $request): RedirectResponse
    {
        Finance::save((array) $request->input('recurring', []), (array) $request->input('fees', []));
        AuditLog::record('admin.finance_settings_saved', null, ['by' => $request->user()->email]);

        return back()->with('status', 'Monthly costs and fees saved.');
    }
}
