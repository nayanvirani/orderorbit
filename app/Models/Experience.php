<?php

namespace App\Models;

use App\Experiences\Registry;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Str;

/**
 * A CRO experience (section 15). Edits go to draft_config; publishing snapshots
 * it into an immutable ExperienceVersion that the storefront renders.
 */
class Experience extends Model
{
    protected $table = 'cro_experiences';

    public const STATUSES = ['draft', 'published', 'paused', 'archived'];

    protected $fillable = [
        'store_id', 'handle', 'type', 'name', 'description', 'cro_template_version_id', 'template_key', 'status',
        'draft_config', 'has_unpublished_changes', 'published_version_id', 'published_at', 'starts_at', 'ends_at',
        'placement_status', 'placement_checked_at', 'created_by', 'updated_by', 'archived_at', 'shopify_discount_id', 'bundle_product_id', 'bundle_variant_id',
    ];

    protected function casts(): array
    {
        return [
            'draft_config' => 'array',
            'has_unpublished_changes' => 'boolean',
            'published_at' => 'datetime',
            'starts_at' => 'datetime',
            'ends_at' => 'datetime',
            'placement_checked_at' => 'datetime',
            'archived_at' => 'datetime',
        ];
    }

    protected static function booted(): void
    {
        static::creating(function (Experience $experience) {
            $experience->handle ??= self::newHandle($experience->store_id);
        });
    }

    public static function newHandle(int $storeId): string
    {
        do {
            $handle = 'oo'.Str::lower(Str::random(8));
        } while (static::where('store_id', $storeId)->where('handle', $handle)->exists());

        return $handle;
    }

    public function store(): BelongsTo
    {
        return $this->belongsTo(Store::class);
    }

    public function versions(): HasMany
    {
        return $this->hasMany(ExperienceVersion::class, 'cro_experience_id');
    }

    public function publishedVersion(): BelongsTo
    {
        return $this->belongsTo(ExperienceVersion::class, 'published_version_id');
    }

    public function templateVersion(): BelongsTo
    {
        return $this->belongsTo(CroTemplateVersion::class, 'cro_template_version_id');
    }

    public function author(): BelongsTo
    {
        return $this->belongsTo(StoreUser::class, 'updated_by');
    }

    public function scopeActive(Builder $query): Builder
    {
        return $query->where('status', 'published');
    }

    public function scopeNotArchived(Builder $query): Builder
    {
        return $query->where('status', '!=', 'archived');
    }

    public function typeDefinition(): array
    {
        return Registry::type($this->type);
    }

    public function templateName(): string
    {
        return Registry::template($this->type, $this->template_key)['name'] ?? $this->template_key;
    }

    /**
     * Status shown to merchants (section D2 badges): Draft, Published, Paused,
     * Scheduled, Ended, Archived, plus "Not placed" for live but unplaced blocks.
     */
    public function displayStatus(): string
    {
        if ($this->status === 'published') {
            if ($this->starts_at?->isFuture()) {
                return 'scheduled';
            }
            if ($this->ends_at?->isPast()) {
                return 'ended';
            }
        }

        return $this->status;
    }

    public function isLive(): bool
    {
        return $this->displayStatus() === 'published';
    }
}
