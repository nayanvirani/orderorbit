<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * The app is now Growvia, on growvia.orderorbit.space. Renames it in the text saved in the database
 * (website content, settings such as email sender names, plan copy, legal pages and their drafts).
 * Email addresses and code names (window.OrderOrbit…) stay; past legal versions stay as published.
 */
return new class extends Migration
{
    public const COLUMNS = [
        'site_contents' => ['data'],
        'platform_settings' => ['value'],
        'plans' => ['description', 'features'],
        'legal_pages' => ['title', 'summary', 'body', 'draft_title', 'draft_body'],
    ];

    public static function rename(?string $text): ?string
    {
        if ($text === null) {
            return null;
        }
        $text = str_replace('OrderOrbit Space', 'Growvia', $text);
        $text = preg_replace('/(?<![\w.])OrderOrbit(?![\w.])/', 'Growvia', $text);
        $text = preg_replace('/(?<![@\w.-])orderorbit\.space/', 'growvia.orderorbit.space', $text);

        return preg_replace('/\b([Aa])n Growvia/', '$1 Growvia', $text);
    }

    public function up(): void
    {
        foreach (self::COLUMNS as $table => $columns) {
            if (! Schema::hasTable($table)) {
                continue;
            }
            DB::table($table)->orderBy('id')->each(function ($row) use ($table, $columns) {
                $changes = [];
                foreach ($columns as $column) {
                    $new = self::rename($row->{$column} ?? null);
                    if ($new !== ($row->{$column} ?? null)) {
                        $changes[$column] = $new;
                    }
                }
                if ($changes) {
                    DB::table($table)->where('id', $row->id)->update($changes);
                }
            });
        }
    }

    public function down(): void
    {
        // A brand rename isn't reversed automatically.
    }
};
