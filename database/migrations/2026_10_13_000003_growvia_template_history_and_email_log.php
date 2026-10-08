<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * The last OrderOrbit mentions in the database: help text in older template versions and the
 * subjects of logged emails. Same rules as the first rename (email addresses stay).
 */
return new class extends Migration
{
    public function up(): void
    {
        $rename = require __DIR__.'/2026_10_13_000001_rename_to_growvia.php';
        foreach (['cro_template_versions' => 'schema', 'cro_templates' => 'schema', 'email_deliveries' => 'subject'] as $table => $column) {
            if (! Schema::hasTable($table) || ! Schema::hasColumn($table, $column)) {
                continue;
            }
            // Postgres can't LIKE a json column directly.
            $col = DB::getQueryGrammar()->wrap($column);
            $text = DB::getDriverName() === 'pgsql' ? "{$col}::text" : $col;
            DB::table($table)->whereRaw("{$text} LIKE ?", ['%OrderOrbit%'])->orderBy('id')->each(function ($row) use ($table, $column, $rename) {
                $new = $rename::rename($row->{$column});
                if ($new !== $row->{$column}) {
                    DB::table($table)->where('id', $row->id)->update([$column => $new]);
                }
            });
        }
    }

    public function down(): void
    {
        // Not reversed.
    }
};
