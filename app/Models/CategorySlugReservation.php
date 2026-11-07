<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class CategorySlugReservation extends Model
{
    public $timestamps = false;

    protected $fillable = ['category_id', 'slug', 'is_current'];

    protected function casts(): array
    {
        return ['is_current' => 'boolean', 'created_at' => 'immutable_datetime'];
    }

    public function category(): BelongsTo
    {
        return $this->belongsTo(Category::class);
    }
}
