<?php

namespace Tests\Feature;

use Illuminate\Encryption\Encrypter;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\DB;
use Tests\Concerns\InteractsWithShopify;
use Tests\TestCase;

/** Rotating APP_KEY: everything encrypted with the old key is re-saved with the new one. */
class ReencryptSecretsTest extends TestCase
{
    use InteractsWithShopify, RefreshDatabase;

    private function useKeys(string $key, array $previous = []): void
    {
        config(['app.key' => 'base64:'.base64_encode($key), 'app.previous_keys' => array_map(fn ($k) => 'base64:'.base64_encode($k), $previous)]);
        $this->app->forgetInstance('encrypter');
        Crypt::clearResolvedInstance('encrypter');
    }

    public function test_secrets_move_to_the_new_key_and_the_old_key_can_be_dropped(): void
    {
        $old = Encrypter::generateKey('aes-256-cbc');
        $new = Encrypter::generateKey('aes-256-cbc');
        $this->setUpShopify();

        $this->useKeys($old);
        $store = $this->installedStore();
        $store->forceFill(['access_token' => 'shpat_old_access', 'refresh_token' => 'shprt_old_refresh'])->save();
        DB::table('platform_settings')->insert(['key' => 'railway', 'value' => json_encode(['token' => Crypt::encryptString('rw-token'), 'project_id' => null]), 'created_at' => now(), 'updated_at' => now()]);

        // New key, old one kept as previous: dry run reads everything and changes nothing.
        $this->useKeys($new, [$old]);
        $before = DB::table('stores')->where('id', $store->id)->value('access_token');
        $this->artisan('orderorbit:reencrypt --dry-run')->expectsOutputToContain('Readable: 3. Unreadable: 0.')->assertSuccessful();
        $this->assertSame($before, DB::table('stores')->where('id', $store->id)->value('access_token'));

        $this->artisan('orderorbit:reencrypt')->expectsOutputToContain('Re-encrypted: 3. Unreadable: 0.')->assertSuccessful();

        // Without the old key everything still reads.
        $this->useKeys($new);
        $fresh = $store->fresh();
        $this->assertSame(['shpat_old_access', 'shprt_old_refresh'], [$fresh->access_token, $fresh->refresh_token]);
        $this->assertSame('rw-token', Crypt::decryptString(json_decode(DB::table('platform_settings')->where('key', 'railway')->value('value'), true)['token']));
    }
}
