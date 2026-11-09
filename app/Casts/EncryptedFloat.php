<?php

namespace App\Casts;

use Illuminate\Contracts\Database\Eloquent\CastsAttributes;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Crypt;

/**
 * A number stored encrypted (AES-256, the app key), read back as a float. Used for product costs:
 * a merchant's margins are commercially sensitive, so they're unreadable in the database and in
 * backups. Values can't be compared or summed in SQL; everything reads them through the model.
 */
class EncryptedFloat implements CastsAttributes
{
    public function get(Model $model, string $key, mixed $value, array $attributes): ?float
    {
        if ($value === null || $value === '') {
            return null;
        }

        // A plain number (written before encryption, or by a direct update) still reads correctly.
        return is_numeric($value) ? (float) $value : (float) Crypt::decryptString($value);
    }

    public function set(Model $model, string $key, mixed $value, array $attributes): ?string
    {
        return $value === null || $value === '' ? null : Crypt::encryptString((string) round((float) $value, 4));
    }
}
