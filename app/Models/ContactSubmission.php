<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class ContactSubmission extends Model
{
    public const STATUSES = ['new' => 'New', 'read' => 'Read', 'replied' => 'Replied', 'closed' => 'Closed'];

    protected $fillable = ['name', 'email', 'company', 'store_url', 'topic', 'message', 'ip', 'status', 'read_at', 'replied_at', 'replied_by', 'reply', 'store_id'];

    protected function casts(): array
    {
        return ['read_at' => 'datetime', 'replied_at' => 'datetime'];
    }

    public function store(): \Illuminate\Database\Eloquent\Relations\BelongsTo
    {
        return $this->belongsTo(Store::class);
    }

    public function replier(): \Illuminate\Database\Eloquent\Relations\BelongsTo
    {
        return $this->belongsTo(User::class, 'replied_by');
    }

    /** The installed store the sender named, matched by its myshopify domain or store URL. */
    public static function matchStore(?string $url): ?int
    {
        $host = strtolower((string) (parse_url(str_contains((string) $url, '://') ? (string) $url : 'https://'.trim((string) $url), PHP_URL_HOST) ?? ''));
        if ($host === '') {
            return null;
        }
        $host = preg_replace('/^www\./', '', $host);

        return Store::where('shop_domain', $host)->orWhere('shop_domain', str_replace('.myshopify.com', '', $host).'.myshopify.com')->value('id');
    }
}
