<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Brand;
use App\Models\Category;
use App\Models\Product;
use App\Models\Tag;
use App\Models\User;
use App\Services\Catalog\ProductImageManager;
use App\Services\Catalog\ProductManager;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Arr;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Inertia\Response as InertiaResponse;

class ProductController extends Controller
{
    public function index(Request $request): InertiaResponse
    {
        $filters = $request->validate([
            'q' => ['nullable', 'string', 'max:255'],
            'status' => ['nullable', Rule::in([Product::STATUS_DRAFT, Product::STATUS_PUBLISHED, Product::STATUS_HIDDEN])],
            'page' => ['nullable', 'integer', 'min:1'],
        ]);

        $query = Product::query()->with('images')->orderByDesc('updated_at')->orderByDesc('id');

        if (isset($filters['q']) && trim($filters['q']) !== '') {
            $search = '%'.str_replace(['%', '_'], ['\\%', '\\_'], trim($filters['q'])).'%';
            $query->where(function ($builder) use ($search): void {
                $builder->where('name_ar', 'like', $search)->orWhere('name_en', 'like', $search);
            });
        }

        if (isset($filters['status'])) {
            $query->where('status', $filters['status']);
        }

        $products = $query->paginate(20)->withQueryString()->through(function (Product $product): array {
            return [
                'id' => $product->id,
                'name_ar' => $product->name_ar,
                'name_en' => $product->name_en,
                'status' => $product->status,
                'updated_at' => $product->updated_at?->toIso8601String(),
                'created_at' => $product->created_at?->toIso8601String(),
                'price' => $product->price,
                'currency' => $product->currency,
                'image_id' => $product->images->first()?->id,
            ];
        });

        return Inertia::render('Admin/Products/Index', [
            'products' => $products,
            'filters' => [
                'q' => $filters['q'] ?? '',
                'status' => $filters['status'] ?? '',
            ],
            'locale' => app()->getLocale(),
        ]);
    }

    public function create(): InertiaResponse
    {
        return Inertia::render('Admin/Products/Form', [
            'product' => null,
            'creationKey' => (string) Str::uuid(),
            ...$this->formOptions(),
        ]);
    }

    public function store(Request $request, ProductManager $products, ProductImageManager $images): RedirectResponse
    {
        $validated = $this->validateProduct($request, creating: true);
        $key = (string) $request->header('Idempotency-Key');

        if ($key === '') {
            throw \Illuminate\Validation\ValidationException::withMessages([
                'creation_idempotency_key' => 'Refresh the form and try again.',
            ]);
        }

        validator(['key' => $key], ['key' => ['required', 'uuid']])->validate();

        $staged = $images->stageBatch(
            $request->file('images', []),
            $request->input('image_alt_ar', []),
            $request->input('image_alt_en', []),
        );

        /** @var User $actor */
        $actor = $request->user();
        $product = $products->createDraft($validated, $actor, $key, $staged);

        return redirect()->route('admin.products.edit', [
            'product' => $product->getKey(),
            'locale' => app()->getLocale(),
        ])->with('success', 'تم حفظ المسودة. يمكنك مراجعتها ومعاينتها قبل النشر.');
    }

    public function edit(Product $product): InertiaResponse
    {
        $product->load(['images', 'category', 'brand', 'tags']);

        return Inertia::render('Admin/Products/Form', [
            'product' => [
                'id' => $product->id,
                'name_ar' => $product->name_ar ?? '',
                'name_en' => $product->name_en ?? '',
                'description_ar' => $product->description_ar ?? '',
                'description_en' => $product->description_en ?? '',
                'slug' => $product->slug ?? '',
                'affiliate_url' => $product->affiliate_url ?? '',
                'price' => $product->price ?? '',
                'currency' => $product->currency ?? '',
                'category_id' => $product->category_id,
                'brand_id' => $product->brand_id,
                'tag_ids' => $product->tags->modelKeys(),
                'status' => $product->status,
                'lock_version' => (int) $product->lock_version,
                'images' => $product->images->map(fn ($image): array => [
                    'id' => $image->id,
                    'alt_ar' => $image->alt_ar ?? '',
                    'alt_en' => $image->alt_en ?? '',
                    'url' => route('products.media', ['image' => $image->id, 'locale' => app()->getLocale()]),
                ])->values(),
            ],
            'creationKey' => null,
            ...$this->formOptions(),
        ]);
    }

    public function update(Request $request, Product $product, ProductManager $products, ProductImageManager $images): RedirectResponse
    {
        $validated = $this->validateProduct($request, creating: false);
        $staged = $images->stageBatch(
            $request->file('images', []),
            $request->input('image_alt_ar', []),
            $request->input('image_alt_en', []),
        );

        $data = Arr::only($validated, [
            'name_ar', 'name_en', 'description_ar', 'description_en', 'slug', 'affiliate_url',
            'price', 'currency', 'category_id', 'brand_id', 'tag_ids', 'lock_version',
        ]);

        $product = $products->update(
            $product,
            $data,
            $staged,
            $validated['image_sequence'] ?? null,
            $validated['delete_image_ids'] ?? [],
            $validated['image_alt_existing'] ?? [],
        );

        return redirect()->route('admin.products.edit', [
            'product' => $product->getKey(),
            'locale' => app()->getLocale(),
        ])->with('success', 'تم حفظ التعديلات.');
    }

    public function preview(Product $product): \Illuminate\Http\Response
    {
        $product->load(['images', 'category', 'brand', 'tags']);

        return response()
            ->view('products.show', $this->publicViewData($product, preview: true))
            ->header('Cache-Control', 'private, no-store')
            ->header('X-Robots-Tag', 'noindex, nofollow');
    }

    public function publish(Request $request, Product $product, ProductManager $products): RedirectResponse
    {
        $validated = $request->validate(['lock_version' => ['required', 'integer', 'min:0']]);
        $product = $products->publish($product, (int) $validated['lock_version']);

        return redirect()->route('admin.products.edit', [
            'product' => $product->getKey(),
            'locale' => app()->getLocale(),
        ])->with('success', 'تم نشر المنتج.');
    }

    public function hide(Request $request, Product $product, ProductManager $products): RedirectResponse
    {
        $validated = $request->validate([
            'lock_version' => ['required', 'integer', 'min:0'],
            'confirmed' => ['accepted'],
        ]);
        $product = $products->hide($product, (int) $validated['lock_version']);

        return redirect()->route('admin.products.edit', [
            'product' => $product->getKey(),
            'locale' => app()->getLocale(),
        ])->with('success', 'تم إخفاء المنتج.');
    }

    private function validateProduct(Request $request, bool $creating): array
    {
        $sometimes = $creating ? '' : 'sometimes|';

        return $request->validate([
            'name_ar' => [$sometimes.'nullable', 'string', 'max:255'],
            'name_en' => [$sometimes.'nullable', 'string', 'max:255'],
            'description_ar' => [$sometimes.'nullable', 'string', 'max:10000'],
            'description_en' => [$sometimes.'nullable', 'string', 'max:10000'],
            'slug' => [$sometimes.'nullable', 'string', 'max:255', 'regex:/\A[a-z0-9]+(?:-[a-z0-9]+)*\z/'],
            'affiliate_url' => [$sometimes.'nullable', 'string', 'max:2048'],
            'price' => [$sometimes.'nullable', 'string', 'regex:/\A(?:0|[1-9][0-9]{0,8})(?:\.[0-9]{1,4})?\z/'],
            'currency' => [$sometimes.'nullable', 'string', 'size:3', 'regex:/\A[A-Z]{3}\z/', Rule::in(config('affiliate.currency_codes', []))],
            'category_id' => [$sometimes.'nullable', 'integer', 'exists:categories,id'],
            'brand_id' => [$sometimes.'nullable', 'integer', 'exists:brands,id'],
            'tag_ids' => [$sometimes.'nullable', 'array'],
            'tag_ids.*' => ['integer', 'distinct', 'exists:tags,id'],
            'lock_version' => [$creating ? 'nullable' : 'required', 'integer', 'min:0'],
            'images' => ['nullable', 'array', 'max:10'],
            'images.*' => ['file', 'image', 'mimes:jpg,jpeg,png,webp', 'max:5120'],
            'image_alt_ar' => ['nullable', 'array'],
            'image_alt_ar.*' => ['nullable', 'string', 'max:255'],
            'image_alt_en' => ['nullable', 'array'],
            'image_alt_en.*' => ['nullable', 'string', 'max:255'],
            'image_sequence' => ['nullable', 'array'],
            'image_sequence.*' => ['string', 'max:32'],
            'delete_image_ids' => ['nullable', 'array'],
            'delete_image_ids.*' => ['integer', 'distinct'],
            'image_alt_existing' => ['nullable', 'array'],
            'image_alt_existing.*.ar' => ['nullable', 'string', 'max:255'],
            'image_alt_existing.*.en' => ['nullable', 'string', 'max:255'],
        ]);
    }

    private function formOptions(): array
    {
        $categories = Category::query()->where('is_active', true)->with('children')->orderBy('sort_order')->get()
            ->filter(fn (Category $category): bool => $category->children->isEmpty())
            ->map(fn (Category $category): array => ['id' => $category->id, 'name_ar' => $category->name_ar, 'name_en' => $category->name_en])
            ->values();

        return [
            'categories' => $categories,
            'brands' => Brand::query()->orderBy('name_en')->get(['id', 'name_ar', 'name_en']),
            'tags' => Tag::query()->orderBy('name_en')->get(['id', 'name_ar', 'name_en']),
            'currencies' => config('affiliate.currency_codes', []),
            'locale' => app()->getLocale(),
        ];
    }

    private function publicViewData(Product $product, bool $preview = false): array
    {
        $locale = app()->getLocale();
        $name = $locale === 'ar' ? $product->name_ar : $product->name_en;
        $description = $locale === 'ar' ? $product->description_ar : $product->description_en;

        return [
            'product' => $product,
            'locale' => $locale,
            'name' => $name,
            'description' => $description,
            'seoDescription' => mb_substr((string) $description, 0, 160),
            'preview' => $preview,
        ];
    }
}
