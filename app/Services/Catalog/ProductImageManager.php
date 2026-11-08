<?php

namespace App\Services\Catalog;

use App\Models\Product;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Throwable;

class ProductImageManager
{
    private const DISK = 'product-private';

    /** @return list<array{storage_path: string, alt_ar: ?string, alt_en: ?string}> */
    public function stageBatch(array $files, array $altAr, array $altEn): array
    {
        if (count($files) > 10) {
            throw ValidationException::withMessages(['images' => 'A product can contain up to 10 images.']);
        }

        $staged = [];

        try {
            foreach ($files as $index => $file) {
                if (! $file instanceof UploadedFile || ! $file->isValid()) {
                    throw ValidationException::withMessages(['images' => 'One of the uploaded images could not be read.']);
                }

                $extension = $file->guessExtension();

                if (! in_array(strtolower((string) $extension), ['jpg', 'jpeg', 'png', 'webp'], true)) {
                    throw ValidationException::withMessages(["images.{$index}" => 'Use a JPEG, PNG, or WebP image.']);
                }

                $path = 'products/'.Str::uuid().'.'.$extension;
                $storedPath = $file->storeAs('products', basename($path), self::DISK);

                if (! is_string($storedPath) || $storedPath === '') {
                    throw ValidationException::withMessages(["images.{$index}" => 'The image could not be stored.']);
                }

                $staged[] = [
                    'storage_path' => $storedPath,
                    'alt_ar' => $this->normalizeAlt($altAr[$index] ?? null),
                    'alt_en' => $this->normalizeAlt($altEn[$index] ?? null),
                ];
            }

            return $staged;
        } catch (Throwable $exception) {
            $this->cleanupStaged($staged);

            throw $exception;
        }
    }

    /** @param list<array{storage_path: string, alt_ar: ?string, alt_en: ?string}> $staged */
    public function cleanupStaged(array $staged): void
    {
        foreach ($staged as $image) {
            try {
                Storage::disk(self::DISK)->delete($image['storage_path']);
            } catch (Throwable $exception) {
                Log::error('A staged product image could not be removed.', [
                    'storage_path' => $image['storage_path'],
                    'exception' => $exception::class,
                ]);
            }
        }
    }

    /** @param list<array{storage_path: string, alt_ar: ?string, alt_en: ?string}> $staged */
    public function createWithinTransaction(Product $product, array $staged): void
    {
        foreach ($staged as $sortOrder => $image) {
            if (! Storage::disk(self::DISK)->exists($image['storage_path'])) {
                throw ValidationException::withMessages(['images' => 'An uploaded image file is missing.']);
            }

            $product->images()->create([
                'storage_path' => $image['storage_path'],
                'sort_order' => $sortOrder,
                'alt_ar' => $image['alt_ar'],
                'alt_en' => $image['alt_en'],
            ]);
        }
    }

    /**
     * @param list<array{storage_path: string, alt_ar: ?string, alt_en: ?string}> $staged
     * @param list<string> $sequence Values are `existing:<id>` and `new:<index>`.
     * @param list<int> $deleteIds
     * @param array<int, array{ar?: ?string, en?: ?string}> $altById
     */
    public function syncWithinTransaction(Product $product, array $staged, array $sequence, array $deleteIds, array $altById = []): void
    {
        $existing = $product->images()->get()->keyBy('id');
        $deleteIds = array_map('intval', $deleteIds);

        if (count($deleteIds) !== count(array_unique($deleteIds)) || array_diff($deleteIds, $existing->keys()->all()) !== []) {
            throw ValidationException::withMessages(['delete_image_ids' => 'Choose only images belonging to this product.']);
        }

        $remainingIds = $existing->keys()->map(fn ($id) => (int) $id)->diff($deleteIds)->values()->all();
        $sequenceExisting = [];
        $sequenceNew = [];

        foreach ($sequence as $item) {
            if (preg_match('/\Aexisting:(\d+)\z/', (string) $item, $matches) === 1) {
                $sequenceExisting[] = (int) $matches[1];
            } elseif (preg_match('/\Anew:(\d+)\z/', (string) $item, $matches) === 1) {
                $sequenceNew[] = (int) $matches[1];
            } else {
                throw ValidationException::withMessages(['image_sequence' => 'The image order is invalid.']);
            }
        }

        $expectedNew = $staged === [] ? [] : range(0, count($staged) - 1);

        if (count($sequenceExisting) !== count(array_unique($sequenceExisting))
            || count($sequenceNew) !== count(array_unique($sequenceNew))
            || array_diff($sequenceExisting, $remainingIds) !== []
            || array_diff($remainingIds, $sequenceExisting) !== []
            || array_diff($sequenceNew, $expectedNew) !== []
            || array_diff($expectedNew, $sequenceNew) !== []) {
            throw ValidationException::withMessages(['image_sequence' => 'Place every retained and newly uploaded image exactly once.']);
        }

        if (count($sequence) > 10) {
            throw ValidationException::withMessages(['images' => 'A product can contain up to 10 images.']);
        }

        foreach ($staged as $image) {
            if (! Storage::disk(self::DISK)->exists($image['storage_path'])) {
                throw ValidationException::withMessages(['images' => 'An uploaded image file is missing.']);
            }
        }

        $oldPaths = $existing->only($deleteIds)->pluck('storage_path')->all();
        $maxOrder = (int) ($existing->max('sort_order') ?? -1);
        $offset = $maxOrder + count($existing) + count($sequence) + 1;

        foreach ($existing as $image) {
            $image->forceFill(['sort_order' => (int) $image->sort_order + $offset])->save();
        }

        if ($deleteIds !== []) {
            $product->images()->whereIn('id', $deleteIds)->delete();
        }

        foreach ($sequence as $sortOrder => $item) {
            if (str_starts_with($item, 'existing:')) {
                $imageId = (int) substr($item, strlen('existing:'));
                $image = $existing->get($imageId);
                $alt = $altById[$imageId] ?? [];
                $image->forceFill([
                    'sort_order' => $sortOrder,
                    'alt_ar' => array_key_exists('ar', $alt) ? $this->normalizeAlt($alt['ar']) : $image->alt_ar,
                    'alt_en' => array_key_exists('en', $alt) ? $this->normalizeAlt($alt['en']) : $image->alt_en,
                ])->save();

                continue;
            }

            $imageIndex = (int) substr($item, strlen('new:'));
            $product->images()->create([
                ...$staged[$imageIndex],
                'sort_order' => $sortOrder,
            ]);
        }

        if ($oldPaths !== []) {
            DB::afterCommit(function () use ($oldPaths): void {
                try {
                    Storage::disk(self::DISK)->delete($oldPaths);
                } catch (Throwable $exception) {
                    Log::error('Replaced product images could not be removed.', [
                        'storage_paths' => $oldPaths,
                        'exception' => $exception::class,
                    ]);
                }
            });
        }
    }

    private function normalizeAlt(mixed $value): ?string
    {
        if ($value !== null && ! is_string($value)) {
            throw ValidationException::withMessages(['alt' => 'Alternative text must be plain text.']);
        }

        $value = $value === null ? null : trim($value);

        if ($value !== null && mb_strlen($value) > 255) {
            throw ValidationException::withMessages(['alt' => 'Alternative text must not exceed 255 characters.']);
        }

        return $value === '' ? null : $value;
    }
}
