<?php

namespace App\Models\Documents;

use App\Models\Store;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/** One document made for one order (a certificate per item, line or order), with its private link. */
class IssuedDocument extends Model
{
    public const LINK_DAYS = 30;

    protected $fillable = ['store_id', 'product_document_id', 'order_id', 'order_name', 'ordered_at', 'line_key', 'unit', 'serial', 'customer_name',
        'product_title', 'variant_title', 'token', 'verify_code', 'expires_at', 'downloads'];

    protected function casts(): array
    {
        return ['ordered_at' => 'datetime', 'expires_at' => 'datetime'];
    }

    public function document(): BelongsTo
    {
        return $this->belongsTo(ProductDocument::class, 'product_document_id');
    }

    public function store(): BelongsTo
    {
        return $this->belongsTo(Store::class);
    }

    public function url(): string
    {
        return route('documents.download', ['token' => $this->token]);
    }

    public function verifyUrl(): string
    {
        return route('documents.verify', ['code' => $this->verify_code]);
    }

    public function fileName(): string
    {
        $base = trim(preg_replace('/[^A-Za-z0-9]+/', '-', $this->document?->name ?? 'document'), '-') ?: 'document';

        return $base.($this->serial ? '-'.preg_replace('/[^A-Za-z0-9-]+/', '', $this->serial) : '').'.pdf';
    }
}
