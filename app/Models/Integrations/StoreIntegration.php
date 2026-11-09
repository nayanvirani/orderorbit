<?php

namespace App\Models\Integrations;

use Illuminate\Database\Eloquent\Model;

/** A connected app for a store (phase 6). Credentials are encrypted at rest. */
class StoreIntegration extends Model
{
    protected $guarded = ['id'];

    protected $hidden = ['credentials'];

    protected function casts(): array
    {
        return ['credentials' => 'encrypted:array', 'settings' => 'array', 'log' => 'array', 'last_sync_at' => 'datetime'];
    }

    /** Adds a line to the activity log (the last 50 are kept). */
    public function note(string $text, bool $ok = true): void
    {
        $log = array_slice(array_merge([['at' => now()->toIso8601String(), 'text' => mb_substr($text, 0, 200), 'ok' => $ok]], (array) $this->log), 0, 50);
        $this->forceFill(['log' => $log] + ($ok ? ['status' => 'connected', 'last_error' => null] : ['status' => 'failing', 'last_error' => mb_substr($text, 0, 500)]))->save();
    }

    public function setting(string $key, mixed $default = null): mixed
    {
        return ($this->settings ?? [])[$key] ?? $default;
    }
}
