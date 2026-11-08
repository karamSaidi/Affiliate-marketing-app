<?php

namespace App\Services\Catalog;

use App\Models\Product;
use App\Models\ProductEvent;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\Log;
use Throwable;

class ProductEventRecorder
{
    public function record(int $productId, string $type, ?string $userAgent): bool
    {
        if (! in_array($type, [ProductEvent::TYPE_VIEW, ProductEvent::TYPE_CLICK], true)) {
            return false;
        }

        if ($this->isKnownBot($userAgent)) {
            return false;
        }

        try {
            $publishedProduct = Product::query()->published()->find($productId);

            if (! $publishedProduct) {
                return false;
            }

            ProductEvent::query()->create([
                'product_id' => $publishedProduct->getKey(),
                'event_type' => $type,
                'occurred_at' => CarbonImmutable::now('UTC'),
            ]);

            return true;
        } catch (Throwable $exception) {
            Log::error('Product event could not be recorded.', [
                'product_id' => $productId,
                'event_type' => $type,
                'route' => request()->route()?->getName(),
                'request_id' => request()->attributes->get('request_id'),
                'exception' => $exception::class,
            ]);

            return false;
        }
    }

    private function isKnownBot(?string $userAgent): bool
    {
        if ($userAgent === null || $userAgent === '') {
            return false;
        }

        try {
            foreach (config('affiliate.known_bot_user_agent_patterns', []) as $pattern) {
                $matched = preg_match($pattern, $userAgent);

                if ($matched === false) {
                    throw new \RuntimeException('Invalid configured bot matcher.');
                }

                if ($matched === 1) {
                    return true;
                }
            }
        } catch (Throwable $exception) {
            Log::warning('Bot user agent matching failed; event recording remains enabled.', [
                'exception' => $exception::class,
            ]);
        }

        return false;
    }
}
