<?php

namespace App\Http\Controllers\Catalog;

use App\Http\Controllers\Controller;
use App\Models\Product;
use App\Models\ProductImage;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;

class ProductImageController extends Controller
{
    public function show(Request $request, ProductImage $image): \Symfony\Component\HttpFoundation\StreamedResponse
    {
        $image->load('product');
        $published = $image->product?->status === Product::STATUS_PUBLISHED;

        if (! $published) {
            abort_unless($request->user()?->can('manage-products'), 403);
        }

        abort_unless(Storage::disk('product-private')->exists($image->storage_path), 404);

        $headers = $published
            ? ['Cache-Control' => 'public, max-age=3600', 'X-Content-Type-Options' => 'nosniff']
            : ['Cache-Control' => 'private, no-store', 'X-Robots-Tag' => 'noindex, nofollow', 'X-Content-Type-Options' => 'nosniff'];

        return Storage::disk('product-private')->response($image->storage_path, basename($image->storage_path), $headers);
    }
}
