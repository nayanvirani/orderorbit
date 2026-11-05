<?php

namespace App\Models\Protect;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

/** A storefront page the URL monitor checks (G10). */
class MonitoredUrl extends Model
{
    protected $guarded = ['id'];

    protected function casts(): array
    {
        return ['removed' => 'boolean', 'down_since' => 'datetime', 'checked_at' => 'datetime'];
    }

    public function checks(): HasMany
    {
        return $this->hasMany(UrlCheck::class);
    }

    /** The path shown to merchants ("/" for the home page). */
    public function path(): string
    {
        $p = parse_url($this->url);

        return ($p['path'] ?? '/') . (isset($p['query']) ? '?'.$p['query'] : '');
    }
}
