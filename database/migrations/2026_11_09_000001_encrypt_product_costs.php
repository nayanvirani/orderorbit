<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Product costs (Growvia's and Shopify's "Cost per item") encrypted at rest, like access tokens:
 * columns become text and stored costs are encrypted in place (App\Casts\EncryptedFloat reads them).
 */
return new class extends Migration
{
    private const COLUMNS = ['cost', 'shopify_cost'];

    public function up(): void
    {
        Schema::table('product_costs', function (Blueprint $table) {
            foreach (self::COLUMNS as $c) {
                $table->text($c)->nullable()->change();
            }
        });
        DB::table('product_costs')->orderBy('id')->chunkById(1000, function ($rows) {
            foreach ($rows as $row) {
                $update = [];
                foreach (self::COLUMNS as $c) {
                    if ($row->{$c} !== null && $row->{$c} !== '' && is_numeric($row->{$c})) {
                        $update[$c] = Crypt::encryptString((string) (float) $row->{$c});
                    }
                }
                if ($update) {
                    DB::table('product_costs')->where('id', $row->id)->update($update);
                }
            }
        });
    }

    public function down(): void
    {
        DB::table('product_costs')->orderBy('id')->chunkById(1000, function ($rows) {
            foreach ($rows as $row) {
                $update = [];
                foreach (self::COLUMNS as $c) {
                    if ($row->{$c} !== null && ! is_numeric($row->{$c})) {
                        $update[$c] = (float) Crypt::decryptString($row->{$c});
                    }
                }
                if ($update) {
                    DB::table('product_costs')->where('id', $row->id)->update($update);
                }
            }
        });
        Schema::table('product_costs', function (Blueprint $table) {
            foreach (self::COLUMNS as $c) {
                $table->decimal($c, 14, 2)->nullable()->change();
            }
        });
    }
};
