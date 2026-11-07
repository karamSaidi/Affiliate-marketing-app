<?php

namespace App\Services\Catalog;

use App\Models\Category;
use App\Models\CategorySlugReservation;
use App\Models\Product;
use App\Models\ProductSlugReservation;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class SlugReservationManager
{
    /** @return array{product: Product, redirect: bool, canonical_slug: string}|null */
    public function resolveProductSlug(string $slug): ?array
    {
        $reservation = ProductSlugReservation::query()->with('product')->where('slug', $slug)->first();
        $product = $reservation?->product;

        if (! $product || $product->status !== Product::STATUS_PUBLISHED) {
            return null;
        }

        return [
            'product' => $product,
            'redirect' => ! $reservation->is_current,
            'canonical_slug' => $product->slug,
        ];
    }

    /** @return array{category: Category, redirect: bool, canonical_slug: string}|null */
    public function resolveCategorySlug(string $slug): ?array
    {
        $reservation = CategorySlugReservation::query()->with('category')->where('slug', $slug)->first();
        $category = $reservation?->category;

        if (! $category || ! $category->is_active) {
            return null;
        }

        return [
            'category' => $category,
            'redirect' => ! $reservation->is_current,
            'canonical_slug' => $category->slug,
        ];
    }

    public function reserveProductSlug(Product $product, ?string $requestedSlug = null): string
    {
        return DB::transaction(function () use ($product, $requestedSlug): string {
            $locked = Product::query()->lockForUpdate()->findOrFail($product->getKey());
            $slug = $this->normalize($requestedSlug ?: $locked->slug ?: $locked->name_en);

            if ($slug === '') {
                throw ValidationException::withMessages(['slug' => 'An English name or slug is required.']);
            }

            $this->assertAvailable(ProductSlugReservation::class, 'product_id', $locked->getKey(), $slug);

            if ($locked->slug === $slug && $locked->slugReservations()->where('is_current', true)->where('slug', $slug)->exists()) {
                return $slug;
            }

            $locked->slugReservations()->where('is_current', true)->update(['is_current' => false]);
            $reservation = $locked->slugReservations()->where('slug', $slug)->first();

            if ($reservation) {
                $reservation->forceFill(['is_current' => true])->save();
            } else {
                $locked->slugReservations()->create(['slug' => $slug, 'is_current' => true]);
            }

            $locked->forceFill(['slug' => $slug])->save();

            return $slug;
        }, attempts: 3);
    }

    public function reserveCategorySlug(Category $category, ?string $requestedSlug = null): string
    {
        return DB::transaction(function () use ($category, $requestedSlug): string {
            $locked = Category::query()->lockForUpdate()->findOrFail($category->getKey());
            $slug = $this->normalize($requestedSlug ?: $locked->slug ?: $locked->name_en);

            if ($slug === '') {
                throw ValidationException::withMessages(['slug' => 'An English name or slug is required.']);
            }

            $this->assertAvailable(CategorySlugReservation::class, 'category_id', $locked->getKey(), $slug);

            if ($locked->slug === $slug && $locked->slugReservations()->where('is_current', true)->where('slug', $slug)->exists()) {
                return $slug;
            }

            $locked->slugReservations()->where('is_current', true)->update(['is_current' => false]);
            $reservation = $locked->slugReservations()->where('slug', $slug)->first();

            if ($reservation) {
                $reservation->forceFill(['is_current' => true])->save();
            } else {
                $locked->slugReservations()->create(['slug' => $slug, 'is_current' => true]);
            }

            $locked->forceFill(['slug' => $slug])->save();

            return $slug;
        }, attempts: 3);
    }

    private function normalize(string $value): string
    {
        $slug = Str::slug(trim($value));

        if ($slug !== '' && ! preg_match('/\A[a-z0-9]+(?:-[a-z0-9]+)*\z/', $slug)) {
            throw ValidationException::withMessages(['slug' => 'The slug format is invalid.']);
        }

        return $slug;
    }

    /** @param class-string<ProductSlugReservation|CategorySlugReservation> $reservationClass */
    private function assertAvailable(string $reservationClass, string $foreignKey, int $ownerId, string $slug): void
    {
        $conflict = $reservationClass::query()
            ->where('slug', $slug)
            ->where($foreignKey, '<>', $ownerId)
            ->exists();

        if ($conflict) {
            throw ValidationException::withMessages(['slug' => 'This slug is already reserved.']);
        }
    }
}
