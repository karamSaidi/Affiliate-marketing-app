<?php

namespace App\Services\Catalog;

use App\Models\Product;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Symfony\Component\HttpKernel\Exception\HttpException;
use Throwable;

class ProductManager
{
    public function __construct(
        private readonly ProductPublisher $publisher,
        private readonly SlugReservationManager $slugs,
        private readonly PublicCatalogCache $catalogCache,
        private readonly ProductImageManager $images,
    ) {}

    /** @param list<array{storage_path: string, alt_ar: ?string, alt_en: ?string}> $stagedImages */
    public function createDraft(array $input, User $actor, string $idempotencyKey, array $stagedImages = []): Product
    {
        $created = false;

        try {
            $product = DB::transaction(function () use ($input, $actor, $idempotencyKey, $stagedImages, &$created): Product {
                $existing = Product::query()->where('creation_idempotency_key', $idempotencyKey)->first();

                if ($existing) {
                    if ((int) $existing->created_by !== (int) $actor->getKey()) {
                        throw new HttpException(409, 'This creation key belongs to another administrator.');
                    }

                    return $existing;
                }

                $data = $this->validated($input);
                $tags = $data['tag_ids'] ?? [];
                unset($data['tag_ids']);

                if (array_key_exists('price', $data)) {
                    $data['price_minor'] = $this->toMinorUnits($data['price']);
                    unset($data['price']);
                }

                $priceMinor = $data['price_minor'] ?? null;
                unset($data['price_minor']);
                $this->assertPriceCurrencyPair($priceMinor, $data['currency'] ?? null);
                $requestedSlug = $data['slug'] ?? null;
                unset($data['slug']);

                $product = new Product;
                $product->fill($data);
                $product->forceFill([
                    'status' => Product::STATUS_DRAFT,
                    'created_by' => $actor->getKey(),
                    'price_minor' => $priceMinor,
                    'creation_idempotency_key' => $idempotencyKey,
                ])->save();

                $slug = $requestedSlug ?: ($product->name_en ? Str::slug($product->name_en) : null);

                if ($slug !== null && $slug !== '') {
                    $this->slugs->reserveProductSlug($product, $slug);
                }

                if ($tags !== []) {
                    $product->tags()->sync($tags);
                }

                $this->images->createWithinTransaction($product, $stagedImages);
                $this->invalidateAfterCommit();
                $created = true;

                return $product->refresh();
            }, attempts: 3);

            if (! $created) {
                $this->images->cleanupStaged($stagedImages);
            }

            return $product;
        } catch (Throwable $exception) {
            $existing = Product::query()->where('creation_idempotency_key', $idempotencyKey)->first();
            $this->images->cleanupStaged($stagedImages);

            if ($existing && (int) $existing->created_by === (int) $actor->getKey()) {
                return $existing;
            }

            throw $exception;
        }
    }

    /**
     * @param list<array{storage_path: string, alt_ar: ?string, alt_en: ?string}> $stagedImages
     * @param list<string>|null $imageSequence Values are `existing:<id>` and `new:<index>`.
     * @param list<int> $deleteImageIds
     * @param array<int, array{ar?: ?string, en?: ?string}> $imageAltText
     */
    public function update(Product $product, array $input, array $stagedImages = [], ?array $imageSequence = null, array $deleteImageIds = [], array $imageAltText = []): Product
    {
        try {
            return DB::transaction(function () use ($product, $input, $stagedImages, $imageSequence, $deleteImageIds, $imageAltText): Product {
                $locked = Product::query()->lockForUpdate()->findOrFail($product->getKey());
                $expectedVersion = filter_var($input['lock_version'] ?? null, FILTER_VALIDATE_INT);

                if ($expectedVersion === false || $expectedVersion === null || $expectedVersion !== (int) $locked->lock_version) {
                    throw new HttpException(409, 'This product changed since you opened it. Reload before saving.');
                }

                unset($input['lock_version']);
                $data = $this->validated($input, partial: true);
                $tags = $data['tag_ids'] ?? null;
                unset($data['tag_ids']);

                $requestedSlug = array_key_exists('slug', $data) ? $data['slug'] : null;
                unset($data['slug']);

                if (array_key_exists('price', $data)) {
                    $data['price_minor'] = $this->toMinorUnits($data['price']);
                    unset($data['price']);
                }

                $priceMinor = array_key_exists('price_minor', $data) ? $data['price_minor'] : null;
                $hasPriceChange = array_key_exists('price_minor', $data);
                unset($data['price_minor']);
                $locked->fill($data);

                if ($hasPriceChange) {
                    $locked->forceFill(['price_minor' => $priceMinor]);
                }

                $locked->forceFill(['lock_version' => ((int) $locked->lock_version) + 1])->save();

                if (($requestedSlug === null || $requestedSlug === '')
                    && $locked->slug === null
                    && is_string($locked->name_en)
                    && trim($locked->name_en) !== '') {
                    $requestedSlug = Str::slug($locked->name_en);
                }

                if ($requestedSlug !== null && $requestedSlug !== '') {
                    $this->slugs->reserveProductSlug($locked, $requestedSlug);
                }

                if ($tags !== null) {
                    $locked->tags()->sync($tags);
                }

                if ($imageSequence !== null || $stagedImages !== [] || $deleteImageIds !== []) {
                    $this->images->syncWithinTransaction($locked, $stagedImages, $imageSequence ?? [], $deleteImageIds, $imageAltText);
                }

                $locked->refresh();
                $this->assertPriceCurrencyPair($locked->price_minor, $locked->currency);

                if ($locked->status === Product::STATUS_PUBLISHED) {
                    $this->publisher->assertPublishable($locked);
                }

                $this->invalidateAfterCommit();

                return $locked->refresh();
            }, attempts: 3);
        } catch (Throwable $exception) {
            $this->images->cleanupStaged($stagedImages);

            throw $exception;
        }
    }

    public function publish(Product $product, int $expectedVersion): Product
    {
        return $this->publisher->publish($product, $expectedVersion);
    }

    public function hide(Product $product, int $expectedVersion): Product
    {
        return $this->publisher->hide($product, $expectedVersion);
    }

    private function validated(array $input, bool $partial = false): array
    {
        $sometimes = $partial ? 'sometimes|' : '';

        $data = validator($input, [
            'name_ar' => [$sometimes.'nullable', 'string', 'max:255'],
            'name_en' => [$sometimes.'nullable', 'string', 'max:255'],
            'description_ar' => [$sometimes.'nullable', 'string', 'max:10000'],
            'description_en' => [$sometimes.'nullable', 'string', 'max:10000'],
            'slug' => [$sometimes.'nullable', 'string', 'max:255', 'regex:/\A[a-z0-9]+(?:-[a-z0-9]+)*\z/'],
            'affiliate_url' => [$sometimes.'nullable', 'string', 'max:2048'],
            'price' => [$sometimes.'nullable', 'string', 'regex:/\A(?:0|[1-9][0-9]{0,8})(?:\.[0-9]{1,4})?\z/'],
            'currency' => [$sometimes.'nullable', 'string', 'size:3', 'regex:/\A[A-Z]{3}\z/'],
            'category_id' => [$sometimes.'nullable', 'integer', 'exists:categories,id'],
            'brand_id' => [$sometimes.'nullable', 'integer', 'exists:brands,id'],
            'tag_ids' => [$sometimes.'array'],
            'tag_ids.*' => ['integer', 'distinct', 'exists:tags,id'],
        ])->validate();

        foreach (['name_ar', 'name_en', 'description_ar', 'description_en'] as $field) {
            if (array_key_exists($field, $data) && is_string($data[$field])) {
                $data[$field] = trim($data[$field]);
            }
        }

        if (array_key_exists('affiliate_url', $data) && $data['affiliate_url'] !== null) {
            $this->assertAffiliateUrl($data['affiliate_url']);
        }

        if (isset($data['currency']) && ! in_array($data['currency'], config('affiliate.currency_codes', []), true)) {
            throw ValidationException::withMessages(['currency' => 'Choose an active ISO 4217 currency code.']);
        }

        if (array_key_exists('price', $data)) {
            $data['price_minor'] = $this->toMinorUnits($data['price']);
        }

        return $data;
    }

    private function toMinorUnits(?string $price): ?int
    {
        if ($price === null) {
            return null;
        }

        if (! preg_match('/\A(0|[1-9][0-9]{0,8})(?:\.([0-9]{1,4}))?\z/', $price, $matches)) {
            throw ValidationException::withMessages(['price' => 'Enter a nonnegative price with up to four decimal places.']);
        }

        $whole = (int) $matches[1];
        $fraction = (int) str_pad($matches[2] ?? '', 4, '0');

        return ($whole * 10000) + $fraction;
    }

    private function assertPriceCurrencyPair(?int $priceMinor, ?string $currency): void
    {
        if (($priceMinor === null) !== ($currency === null)) {
            throw ValidationException::withMessages(['price' => 'Price and currency must both be set or both be empty.']);
        }
    }

    private function assertAffiliateUrl(string $url): void
    {
        $parts = parse_url($url);

        if (! is_array($parts)
            || ! in_array(strtolower($parts['scheme'] ?? ''), ['http', 'https'], true)
            || empty($parts['host'])
            || filter_var($url, FILTER_VALIDATE_URL) === false) {
            throw ValidationException::withMessages(['affiliate_url' => 'Enter an absolute HTTP or HTTPS URL.']);
        }
    }

    private function invalidateAfterCommit(): void
    {
        DB::afterCommit(function (): void {
            try {
                $this->catalogCache->invalidate();
            } catch (Throwable $exception) {
                Log::error('Product cache invalidation failed after a committed change.', [
                    'exception' => $exception::class,
                ]);
            }
        });
    }
}
