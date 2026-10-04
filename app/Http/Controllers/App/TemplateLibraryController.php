<?php

namespace App\Http\Controllers\App;

use App\Experiences\Registry;
use App\Http\Controllers\Controller;
use App\Models\CroSetting;
use App\Models\CroTemplate;
use App\Models\Experience;
use App\Models\Store;
use App\Services\Experiences\TemplateLibrary;
use Illuminate\Http\Request;
use Illuminate\View\View;

class TemplateLibraryController extends Controller
{
    public function index(Request $request, Store $store): View
    {
        $type = Registry::has($request->query('type')) ? $request->query('type') : null;
        $branding = CroSetting::brandingFor($store);
        // Keyed in PHP so the query stays portable (Postgres in production, SQLite in tests).
        $versions = CroTemplate::get(['type', 'key', 'current_version'])->mapWithKeys(fn ($t) => ["{$t->type}:{$t->key}" => $t->current_version]);
        $usedBy = Experience::where('store_id', $store->id)->notArchived()
            ->selectRaw('type, template_key, count(*) as total')->groupBy('type', 'template_key')->get()
            ->mapWithKeys(fn ($row) => ["{$row->type}:{$row->template_key}" => (int) $row->total]);

        $templates = [];
        foreach (Registry::creatable() as $typeKey => $definition) {
            if ($type && $type !== $typeKey) {
                continue;
            }
            foreach (Registry::offered($typeKey) as $key => $template) {
                $templates[] = [
                    'type' => $typeKey,
                    'type_label' => $definition['label'],
                    'key' => $key,
                    'name' => $template['name'],
                    'surface' => $definition['surface'],
                    'version' => $versions["{$typeKey}:{$key}"] ?? 1,
                    'used_by' => $usedBy["{$typeKey}:{$key}"] ?? 0,
                    'preview' => TemplateLibrary::preview($typeKey, $key, $branding),
                ];
            }
        }

        return view('app.templates.index', ['store' => $store, 'type' => $type, 'templates' => $templates]);
    }
}
