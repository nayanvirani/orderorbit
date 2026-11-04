<?php

namespace App\Models\Documents;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

/** A certificate made for each order, or the same PDF for everyone, sent with automation emails (G42). */
class ProductDocument extends Model
{
    public const MAX_FILE = 10 * 1024 * 1024;

    protected $fillable = ['store_id', 'name', 'kind', 'file_name', 'file_size', 'file_data', 'match', 'match_items', 'match_tag', 'per', 'certificate', 'logo', 'active'];

    // The file itself is never sent to the admin pages.
    protected $hidden = ['file_data', 'logo'];

    protected function casts(): array
    {
        return ['match_items' => 'array', 'certificate' => 'array', 'active' => 'boolean'];
    }

    public function issued(): HasMany
    {
        return $this->hasMany(IssuedDocument::class);
    }

    /** The certificate's wording and options, with defaults. */
    public function certificateSettings(): array
    {
        return array_merge([
            'heading' => 'Certificate of Authenticity', 'intro' => 'This certifies that', 'owns' => 'owns',
            'show_name' => true, 'serial_prefix' => '', 'signature' => '', 'footer' => '', 'show_qr' => true, 'accent' => '#1f2328',
        ], (array) $this->certificate);
    }
}
