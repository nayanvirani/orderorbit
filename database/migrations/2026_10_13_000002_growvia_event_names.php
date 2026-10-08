<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Analytics events are named growvia:* now (they were orderorbit:*). Renames the recorded events and
 * every saved reference to them: funnel steps, segment and personalization rules, workflows, experiments.
 */
return new class extends Migration
{
    public const TABLES = ['analytics_events', 'analytics_funnels', 'segments', 'personalization_rules', 'automation_workflows', 'automation_versions', 'experiments', 'experiment_variants'];

    public function up(): void
    {
        foreach (self::TABLES as $table) {
            if (! Schema::hasTable($table)) {
                continue;
            }
            foreach (Schema::getColumns($table) as $column) {
                if (! preg_match('/char|text|json/i', (string) ($column['type_name'] ?? $column['type'] ?? ''))) {
                    continue;
                }
                $col = DB::getQueryGrammar()->wrap($column['name']);
                // Postgres has no REPLACE or LIKE on json columns: go through text and back.
                $type = strtolower((string) ($column['type_name'] ?? ''));
                $json = DB::getDriverName() === 'pgsql' && in_array($type, ['json', 'jsonb'], true);
                $text = $json ? "{$col}::text" : $col;
                DB::table($table)->whereRaw("{$text} LIKE ?", ['%orderorbit:%'])
                    ->update([$column['name'] => DB::raw("REPLACE({$text}, 'orderorbit:', 'growvia:')".($json ? "::{$type}" : ''))]);
            }
        }
    }

    public function down(): void
    {
        // Not reversed.
    }
};
