<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ProductSlugReservation extends Model
{
    public $timestamps = false;

    protected $fillable = ['product_id', 'slug', 'is_current'];

    protected function casts(): array
    {
        return ['is_current' => 'boolean', 'created_at' => 'immutable_datetime'];
    }

    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class);
    }
}
