<?php

namespace App\Services\Catalog;

use App\Models\Product;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Validation\ValidationException;
use Symfony\Component\HttpKernel\Exception\HttpException;
use Throwable;

class ProductPublisher
{
    public function __construct(private readonly PublicCatalogCache $catalogCache) {}

    public function publish(Product $product, int $expectedVersion): Product
    {
        return DB::transaction(function () use ($product, $expectedVersion): Product {
            $locked = Product::query()->lockForUpdate()->findOrFail($product->getKey());

            if ((int) $locked->lock_version !== $expectedVersion) {
                if ($locked->status === Product::STATUS_PUBLISHED) {
                    return $locked;
                }

                throw new HttpException(409, 'This product changed since you opened it. Reload before publishing.');
            }

            if ($locked->status === Product::STATUS_PUBLISHED) {
                return $locked;
            }

            $this->assertPublishable($locked);
            $locked->forceFill([
                'status' => Product::STATUS_PUBLISHED,
                'lock_version' => ((int) $locked->lock_version) + 1,
            ])->save();
            $this->invalidateAfterCommit();

            return $locked->refresh();
        }, attempts: 3);
    }

    public function hide(Product $product, int $expectedVersion): Product
    {
        DB::transaction(function () use ($product, $expectedVersion): void {
            $locked = Product::query()->lockForUpdate()->findOrFail($product->getKey());

            if ((int) $locked->lock_version !== $expectedVersion) {
                if ($locked->status === Product::STATUS_HIDDEN) {
                    return;
                }

                throw new HttpException(409, 'This product changed since you opened it. Reload before hiding.');
            }

            if ($locked->status === Product::STATUS_HIDDEN) {
                return;
            }

            $locked->forceFill([
                'status' => Product::STATUS_HIDDEN,
                'lock_version' => ((int) $locked->lock_version) + 1,
            ])->save();
            $this->invalidateAfterCommit();
        }, attempts: 3);

        return $product->refresh();
    }

    public function assertPublishable(Product $product): void
    {
        $errors = [];

        foreach (['name_ar', 'name_en', 'description_ar', 'description_en', 'slug', 'affiliate_url'] as $field) {
            if (! is_string($product->{$field}) || trim($product->{$field}) === '') {
                $errors[$field] = 'This value is required before publishing.';
            }
        }

        $price = $product->price_minor;

        if ($price === null || $price < 0 || $price > 9_999_999_999_999) {
            $errors['price'] = 'A nonnegative price within DECIMAL(13,4) range is required.';
        }

        if (! is_string($product->currency)
            || ! preg_match('/\A[A-Z]{3}\z/', $product->currency)
            || ! in_array($product->currency, config('affiliate.currency_codes', []), true)) {
            $errors['currency'] = 'Choose an active ISO 4217 currency code.';
        }

        $url = is_string($product->affiliate_url) ? parse_url($product->affiliate_url) : false;

        if (! is_array($url)
            || ! in_array(strtolower($url['scheme'] ?? ''), ['http', 'https'], true)
            || empty($url['host'])
            || filter_var($product->affiliate_url, FILTER_VALIDATE_URL) === false) {
            $errors['affiliate_url'] = 'Enter an absolute HTTP or HTTPS URL.';
        }

        $category = $product->category;

        if (! $category || ! $category->is_active || ! $category->isLeaf()) {
            $errors['category_id'] = 'Choose an active category without children.';
        }

        $images = $product->images;

        if ($images->isEmpty()) {
            $errors['images'] = 'At least one product image is required.';
        }

        if ($images->count() > 10) {
            $errors['images'] = 'A product can contain up to 10 images.';
        }

        foreach ($images as $index => $image) {
            foreach (['alt_ar', 'alt_en'] as $field) {
                if (! is_string($image->{$field}) || trim($image->{$field}) === '') {
                    $errors["images.{$index}.{$field}"] = 'Image alternative text is required in both languages.';
                }
            }
        }

        if (! $product->slugReservations()->where('slug', $product->slug)->where('is_current', true)->exists()) {
            $errors['slug'] = 'The current product slug must be reserved.';
        }

        if ($errors !== []) {
            throw ValidationException::withMessages($errors);
        }
    }

    private function invalidateAfterCommit(): void
    {
        DB::afterCommit(function (): void {
            try {
                $this->catalogCache->invalidate();
            } catch (Throwable $exception) {
                Log::error('Product cache invalidation failed after a committed state change.', [
                    'exception' => $exception::class,
                ]);
            }
        });
    }
}
