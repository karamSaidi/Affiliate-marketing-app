<?php

namespace App\Services\Catalog;

use Carbon\CarbonImmutable;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class ProductAnalytics
{
    /** @return array{products: Collection, totals: array{views: int, clicks: int}} */
    public function summarize(?string $from = null, ?string $until = null, ?int $productId = null): array
    {
        $timezone = (string) config('app.timezone', 'UTC');
        $query = DB::table('product_events')
            ->select('product_id')
            ->selectRaw("SUM(CASE WHEN event_type = 'view' THEN 1 ELSE 0 END) AS views")
            ->selectRaw("SUM(CASE WHEN event_type = 'click' THEN 1 ELSE 0 END) AS clicks");

        if ($from !== null) {
            $query->where('occurred_at', '>=', $this->toUtc($from, $timezone, 'from'));
        }

        if ($until !== null) {
            $query->where('occurred_at', '<', $this->toUtc($until, $timezone, 'until'));
        }

        if ($productId !== null) {
            $query->where('product_id', $productId);
        }

        $products = $query->groupBy('product_id')->orderBy('product_id')->get();

        return [
            'products' => $products,
            'totals' => [
                'views' => (int) $products->sum('views'),
                'clicks' => (int) $products->sum('clicks'),
            ],
        ];
    }

    private function toUtc(string $value, string $timezone, string $field): string
    {
        try {
            return CarbonImmutable::parse($value, $timezone)->utc()->format('Y-m-d H:i:s.u');
        } catch (\Throwable) {
            throw ValidationException::withMessages([$field => 'Enter a valid report boundary.']);
        }
    }
}
