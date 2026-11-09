<?php

namespace App\Models\Questions;

use Illuminate\Database\Eloquent\Model;

/** A store question added in Internal Admin, built from the library's building blocks (G18). */
class StoreQuestion extends Model
{
    protected $guarded = ['id'];

    protected function casts(): array
    {
        return ['spec' => 'array', 'active' => 'boolean'];
    }
}
