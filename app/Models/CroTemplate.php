<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;

class CroTemplate extends Model
{
    protected $fillable = ['type', 'key', 'name', 'surface', 'status', 'current_version'];

    public function versions(): HasMany
    {
        return $this->hasMany(CroTemplateVersion::class);
    }

    public function currentVersion(): HasOne
    {
        return $this->hasOne(CroTemplateVersion::class)->ofMany('version', 'max');
    }
}
