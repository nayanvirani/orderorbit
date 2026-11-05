<?php

namespace App\Models\Mail;

use Illuminate\Database\Eloquent\Model;

class EmailDelivery extends Model
{
    public const UPDATED_AT = null;

    protected $guarded = ['id'];

    protected static function booted(): void
    {
        // The address is encrypted; its hash lets support find a delivery by exact recipient.
        static::saving(function (self $d) {
            if ($d->isDirty('to_email')) {
                $d->to_hash = $d->to_email ? self::hash($d->to_email) : null;
            }
        });
    }

    public static function hash(string $email): string
    {
        return hash('sha256', strtolower(trim($email)));
    }

    /** Recipients and subjects can be customers' personal data: encrypted at rest. */
    protected function casts(): array
    {
        return ['to_email' => 'encrypted', 'subject' => 'encrypted'];
    }
}
