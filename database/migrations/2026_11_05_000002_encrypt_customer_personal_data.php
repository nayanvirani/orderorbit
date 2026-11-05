<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Protected customer data (Shopify level 2): customers' names, emails and order facts are
 * encrypted at rest with the app key (AES-256), so they are unreadable in the database and in
 * every backup. Columns become text (ciphertext is longer than the plain value); rows already
 * stored are encrypted in place. The delivery log keeps a SHA-256 of the address so support can
 * still find an email by its exact recipient.
 */
return new class extends Migration
{
    /** table => [column => array (json) or string] */
    private const COLUMNS = [
        'automation_runs' => ['context' => 'array'],
        'automation_emails' => ['to_email' => 'string', 'subject' => 'string', 'body' => 'string'],
        'automation_inbox' => ['title' => 'string', 'body' => 'string'],
        'issued_documents' => ['customer_name' => 'string'],
        'email_deliveries' => ['to_email' => 'string', 'subject' => 'string'],
    ];

    public function up(): void
    {
        Schema::table('email_deliveries', function (Blueprint $table) {
            $table->string('to_hash', 64)->nullable()->index();
        });
        foreach (self::COLUMNS as $table => $columns) {
            Schema::table($table, function (Blueprint $t) use ($columns, $table) {
                foreach (array_keys($columns) as $column) {
                    // context and the delivery log's address stay required, as before.
                    $t->text($column)->nullable(! in_array("{$table}.{$column}", ['automation_runs.context', 'email_deliveries.to_email', 'automation_emails.subject', 'automation_inbox.title'], true))->change();
                }
            });
        }
        foreach (self::COLUMNS as $table => $columns) {
            DB::table($table)->orderBy('id')->chunkById(500, function ($rows) use ($table, $columns) {
                foreach ($rows as $row) {
                    $update = [];
                    foreach ($columns as $column => $kind) {
                        $value = $row->{$column};
                        if ($value === null || $value === '' || $this->encrypted($value)) {
                            continue;
                        }
                        // Arrays are stored as JSON already: encrypting that JSON matches Laravel's encrypted:array cast.
                        $update[$column] = Crypt::encryptString($value);
                    }
                    if ($table === 'email_deliveries' && $row->to_email && ! $this->encrypted($row->to_email)) {
                        $update['to_hash'] = hash('sha256', strtolower(trim($row->to_email)));
                    }
                    if ($update) {
                        DB::table($table)->where('id', $row->id)->update($update);
                    }
                }
            });
        }
    }

    public function down(): void
    {
        foreach (self::COLUMNS as $table => $columns) {
            DB::table($table)->orderBy('id')->chunkById(500, function ($rows) use ($table, $columns) {
                foreach ($rows as $row) {
                    $update = [];
                    foreach (array_keys($columns) as $column) {
                        if ($row->{$column} !== null && $this->encrypted($row->{$column})) {
                            $update[$column] = Crypt::decryptString($row->{$column});
                        }
                    }
                    if ($update) {
                        DB::table($table)->where('id', $row->id)->update($update);
                    }
                }
            });
        }
        Schema::table('email_deliveries', fn (Blueprint $table) => $table->dropColumn('to_hash'));
    }

    private function encrypted(string $value): bool
    {
        try {
            Crypt::decryptString($value);

            return true;
        } catch (Throwable) {
            return false;
        }
    }
};
