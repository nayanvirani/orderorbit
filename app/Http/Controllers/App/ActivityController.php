<?php

namespace App\Http\Controllers\App;

use App\Http\Controllers\Controller;
use App\Models\AuditLog;
use App\Models\Store;
use Illuminate\View\View;

class ActivityController extends Controller
{
    public function index(Store $store): View
    {
        $logs = AuditLog::with('actor')
            ->where('store_id', $store->id)
            ->latest('id')
            ->paginate(25);

        return view('app.settings.activity', ['store' => $store, 'logs' => $logs]);
    }
}
