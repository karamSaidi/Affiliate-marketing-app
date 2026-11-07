<?php

namespace App\Services\Catalog;

use App\Models\Product;
use App\Models\ProductImage;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\ValidationException;
use Throwable;

class ProductImageManager
{
    public function __construct(
        private readonly ProductPublisher $publisher,
        private readonly PublicCatalogCache $catalogCache,
    ) {}

    public function attach(Product $product, string $storagePath, ?string $altAr, ?string $altEn): ProductImage
    {
        $this->assertSafePath($storagePath);

        try {
            return DB::transaction(function () use ($product, $storagePath, $altAr, $altEn): ProductImage {
                $locked = Product::query()->lockForUpdate()->findOrFail($product->getKey());

                if (! Storage::disk('public')->exists($storagePath)) {
                    throw ValidationException::withMessages(['image' => 'The uploaded image file is missing.']);
                }

                $image = $locked->images()->create([
                    'storage_path' => $storagePath,
                    'sort_order' => $locked->images()->count(),
                    'alt_ar' => $this->normalizeAlt($altAr),
                    'alt_en' => $this->normalizeAlt($altEn),
                ]);

                if ($locked->status === Product::STATUS_PUBLISHED) {
                    $this->publisher->assertPublishable($locked->refresh());
                }

                $this->catalogCache->invalidate();

                return $image->refresh();
            }, attempts: 3);
        } catch (Throwable $exception) {
            if (! ProductImage::query()->where('storage_path', $storagePath)->exists()) {
                Storage::disk('public')->delete($storagePath);
            }

            throw $exception;
        }
    }

    /** @param list<int> $imageIds */
    public function reorder(Product $product, array $imageIds): void
    {
        DB::transaction(function () use ($product, $imageIds): void {
            $locked = Product::query()->lockForUpdate()->findOrFail($product->getKey());
            $images = $locked->images()->get();
            $requestedIds = array_map('intval', $imageIds);
            $existingIds = $images->modelKeys();

            if (count($requestedIds) !== count(array_unique($requestedIds))
                || count($requestedIds) !== count($existingIds)
                || array_diff($requestedIds, $existingIds) !== []) {
                throw ValidationException::withMessages(['images' => 'Provide each image for this product exactly once.']);
            }

            $offset = ((int) $images->max('sort_order')) + $images->count() + 1;

            foreach ($images as $index => $image) {
                $image->forceFill(['sort_order' => $image->sort_order + $offset])->save();
            }

            foreach ($requestedIds as $sortOrder => $imageId) {
                $images->firstWhere('id', $imageId)->forceFill(['sort_order' => $sortOrder])->save();
            }

            if ($locked->status === Product::STATUS_PUBLISHED) {
                $this->publisher->assertPublishable($locked->refresh());
            }

            $this->catalogCache->invalidate();
        }, attempts: 3);
    }

    public function updateAltText(ProductImage $image, ?string $altAr, ?string $altEn): ProductImage
    {
        return DB::transaction(function () use ($image, $altAr, $altEn): ProductImage {
            $imageProductId = ProductImage::query()->whereKey($image->getKey())->value('product_id');
            $product = Product::query()->lockForUpdate()->findOrFail($imageProductId);
            $lockedImage = ProductImage::query()->lockForUpdate()->findOrFail($image->getKey());

            if ((int) $lockedImage->product_id !== (int) $product->getKey()) {
                throw ValidationException::withMessages(['image' => 'The image changed while it was being edited.']);
            }
            $lockedImage->forceFill([
                'alt_ar' => $this->normalizeAlt($altAr),
                'alt_en' => $this->normalizeAlt($altEn),
            ])->save();

            if ($product->status === Product::STATUS_PUBLISHED) {
                $this->publisher->assertPublishable($product->refresh());
            }

            $this->catalogCache->invalidate();

            return $lockedImage->refresh();
        }, attempts: 3);
    }

    public function delete(ProductImage $image): void
    {
        $storagePath = null;

        DB::transaction(function () use ($image, &$storagePath): void {
            $imageProductId = ProductImage::query()->whereKey($image->getKey())->value('product_id');
            $product = Product::query()->lockForUpdate()->findOrFail($imageProductId);
            $lockedImage = ProductImage::query()->lockForUpdate()->findOrFail($image->getKey());

            if ((int) $lockedImage->product_id !== (int) $product->getKey()) {
                throw ValidationException::withMessages(['image' => 'The image changed while it was being removed.']);
            }
            $images = $product->images()->get();

            if ($product->status === Product::STATUS_PUBLISHED && $images->count() <= 1) {
                throw ValidationException::withMessages(['images' => 'A published product must keep at least one image.']);
            }

            $storagePath = $lockedImage->storage_path;
            $lockedImage->delete();

            $remainingIds = $images->where('id', '<>', $lockedImage->getKey())->pluck('id')->all();
            $this->reorder($product, $remainingIds);

            if ($product->status === Product::STATUS_PUBLISHED) {
                $this->publisher->assertPublishable($product->refresh());
            }

            $this->catalogCache->invalidate();

            DB::afterCommit(function () use (&$storagePath): void {
                if ($storagePath !== null) {
                    Storage::disk('public')->delete($storagePath);
                }
            });
        }, attempts: 3);
    }

    private function assertSafePath(string $path): void
    {
        if ($path === '' || mb_strlen($path) > 512 || str_starts_with($path, '/') || str_contains($path, '\\') || in_array('..', explode('/', $path), true)) {
            throw ValidationException::withMessages(['image' => 'The image path is invalid.']);
        }
    }

    private function normalizeAlt(?string $value): ?string
    {
        $value = $value === null ? null : trim($value);

        if ($value !== null && mb_strlen($value) > 255) {
            throw ValidationException::withMessages(['alt' => 'Alternative text must not exceed 255 characters.']);
        }

        return $value === '' ? null : $value;
    }
}
