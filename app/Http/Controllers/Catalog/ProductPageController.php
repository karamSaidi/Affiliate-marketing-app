<?php

namespace App\Http\Controllers\Catalog;

use App\Http\Controllers\Controller;
use App\Models\Product;
use App\Services\Catalog\ProductEventRecorder;
use App\Services\Catalog\ProductPriceFormatter;
use App\Services\Catalog\PublicCatalogCache;
use App\Services\Catalog\SlugReservationManager;
use Illuminate\Http\Request;
use Illuminate\View\View;

class ProductPageController extends Controller
{
    public function show(
        Request $request,
        string $locale,
        string $slug,
        SlugReservationManager $slugs,
        PublicCatalogCache $cache,
        ProductEventRecorder $events,
        ProductPriceFormatter $prices,
    ): View|\Illuminate\Http\RedirectResponse {
        $resolved = $slugs->resolveProductSlug($slug);

        if (! $resolved) {
            abort(404);
        }

        if ($resolved['redirect']) {
            return redirect()->route('products.show', [
                'locale' => $locale,
                'slug' => $resolved['canonical_slug'],
            ], 301);
        }

        $product = $cache->remember('product:'.$resolved['product']->getKey().':'.$locale, function () use ($resolved): Product {
            return Product::query()->published()->with(['images', 'category', 'brand', 'tags'])->findOrFail($resolved['product']->getKey());
        });

        if ($product->status !== Product::STATUS_PUBLISHED) {
            abort(404);
        }

        $events->record((int) $product->getKey(), 'view', $request->userAgent());

        $name = $locale === 'ar' ? $product->name_ar : $product->name_en;
        $description = $locale === 'ar' ? $product->description_ar : $product->description_en;

        return view('products.show', [
            'product' => $product,
            'locale' => $locale,
            'name' => $name,
            'description' => $description,
            'seoDescription' => mb_substr((string) $description, 0, 160),
            'priceLabel' => $prices->format((int) $product->price_minor, $product->currency, $locale),
            'preview' => false,
        ]);
    }

    public function redirect(
        Request $request,
        string $locale,
        string $slug,
        SlugReservationManager $slugs,
        ProductEventRecorder $events,
    ): \Illuminate\Http\RedirectResponse {
        $resolved = $slugs->resolveProductSlug($slug);

        if (! $resolved) {
            abort(404);
        }

        if ($resolved['redirect']) {
            return redirect()->route('products.go', [
                'locale' => $locale,
                'slug' => $resolved['canonical_slug'],
            ], 301);
        }

        $events->record((int) $resolved['product']->getKey(), 'click', $request->userAgent());

        return redirect()->away($resolved['product']->affiliate_url, 302)
            ->header('Cache-Control', 'no-store');
    }
}
