<?php

namespace App\Models;

use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ProductEvent extends Model
{
    public const TYPE_VIEW = 'view';

    public const TYPE_CLICK = 'click';

    public $timestamps = false;

    protected $fillable = ['product_id', 'event_type', 'occurred_at'];

    protected function occurredAt(): Attribute
    {
        return Attribute::make(
            get: fn (?string $value): ?CarbonImmutable => $value === null
                ? null
                : CarbonImmutable::parse($value, 'UTC'),
            set: fn (CarbonImmutable|string|null $value): ?string => $value === null
                ? null
                : CarbonImmutable::parse($value, 'UTC')->utc()->format('Y-m-d H:i:s.u'),
        );
    }

    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class);
    }
}
