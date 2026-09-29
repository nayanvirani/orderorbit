<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class CroSetting extends Model
{
    public const BRANDING_DEFAULTS = [
        'primary_color' => '#303030',
        'accent_color' => '#5b4bff',
        'text_color' => '#1d1b33',
        'background_color' => '#ffffff',
        'button_style' => 'filled',
        'radius' => 12,
        'font' => 'theme',
    ];

    protected $fillable = ['store_id', 'branding'];

    protected function casts(): array
    {
        return ['branding' => 'array'];
    }

    public static function brandingFor(Store $store): array
    {
        $saved = static::where('store_id', $store->id)->value('branding');

        return array_merge(self::BRANDING_DEFAULTS, is_array($saved) ? $saved : (json_decode((string) $saved, true) ?: []));
    }
}
