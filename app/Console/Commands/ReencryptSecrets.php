<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\DB;
use Throwable;

/**
 * After changing APP_KEY (old key kept in APP_PREVIOUS_KEYS): re-saves everything encrypted with
 * the app key using the new key, so APP_PREVIOUS_KEYS can then be removed. Covers store access and
 * refresh tokens, email provider credentials and the saved Railway token. Values no key can read
 * are reported and left as they are.
 */
class ReencryptSecrets extends Command
{
    protected $signature = 'orderorbit:reencrypt {--dry-run : Only check that every value can be read}';

    protected $description = 'Re-encrypt stored secrets with the current APP_KEY after a key rotation';

    private int $done = 0;

    private int $failed = 0;

    public function handle(): int
    {
        $this->done = $this->failed = 0;
        if (empty(config('app.previous_keys'))) {
            $this->warn('APP_PREVIOUS_KEYS is empty: values made with an old key can\'t be read. Set it to the old key first.');
        }

        DB::table('stores')->select(['id', 'access_token', 'refresh_token'])->orderBy('id')->lazyById(100)->each(function ($row) {
            $update = [];
            foreach (['access_token', 'refresh_token'] as $column) {
                if (($value = $this->rewrap($row->{$column}, "store {$row->id} {$column}")) !== null) {
                    $update[$column] = $value;
                }
            }
            if ($update && ! $this->option('dry-run')) {
                DB::table('stores')->where('id', $row->id)->update($update);
            }
        });

        DB::table('email_providers')->select(['id', 'credentials'])->orderBy('id')->lazyById(100)->each(function ($row) {
            if (($value = $this->rewrap($row->credentials, "email provider {$row->id}")) !== null && ! $this->option('dry-run')) {
                DB::table('email_providers')->where('id', $row->id)->update(['credentials' => $value]);
            }
        });

        $setting = DB::table('platform_settings')->where('key', 'railway')->first(); // App\Support\RailwayBilling's saved settings
        if ($setting) {
            $data = (array) json_decode((string) $setting->value, true);
            if (($value = $this->rewrap($data['token'] ?? null, 'Railway token')) !== null && ! $this->option('dry-run')) {
                $data['token'] = $value;
                DB::table('platform_settings')->where('key', $setting->key)->update(['value' => json_encode($data), 'updated_at' => now()]);
            }
        }

        $verb = $this->option('dry-run') ? 'Readable' : 'Re-encrypted';
        $this->info("{$verb}: {$this->done}. Unreadable: {$this->failed}.");
        if (! $this->option('dry-run') && $this->failed === 0) {
            $this->line('Every value now uses the current APP_KEY. You can remove APP_PREVIOUS_KEYS and redeploy.');
        }

        return $this->failed === 0 ? self::SUCCESS : self::FAILURE;
    }

    /** The value encrypted with the current key, or null when empty or unreadable. */
    private function rewrap(?string $encrypted, string $label): ?string
    {
        if ($encrypted === null || $encrypted === '') {
            return null;
        }
        try {
            $plain = Crypt::decryptString($encrypted); // tries APP_KEY, then APP_PREVIOUS_KEYS
        } catch (Throwable) {
            $this->failed++;
            $this->error("Can't read {$label} with APP_KEY or APP_PREVIOUS_KEYS; left unchanged.");

            return null;
        }
        $this->done++;

        return Crypt::encryptString($plain);
    }
}
