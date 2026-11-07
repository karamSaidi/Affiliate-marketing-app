<?php

namespace App\Services\Catalog;

use App\Models\Product;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class ProductManager
{
    public function __construct(
        private readonly ProductPublisher $publisher,
        private readonly SlugReservationManager $slugs,
        private readonly PublicCatalogCache $catalogCache,
    ) {}

    public function createDraft(array $input, User $actor): Product
    {
        return DB::transaction(function () use ($input, $actor): Product {
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
            ])->save();

            $slug = $requestedSlug ?: ($product->name_en ? Str::slug($product->name_en) : null);

            if ($slug !== null && $slug !== '') {
                $this->slugs->reserveProductSlug($product, $slug);
            }

            if ($tags !== []) {
                $product->tags()->sync($tags);
            }

            $this->catalogCache->invalidate();

            return $product->refresh();
        }, attempts: 3);
    }

    public function update(Product $product, array $input): Product
    {
        return DB::transaction(function () use ($product, $input): Product {
            $locked = Product::query()->lockForUpdate()->findOrFail($product->getKey());
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

            $locked->save();

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

            $locked->refresh();
            $this->assertPriceCurrencyPair($locked->price_minor, $locked->currency);

            if ($locked->status === Product::STATUS_PUBLISHED) {
                $this->publisher->assertPublishable($locked);
            }

            $this->catalogCache->invalidate();

            return $locked->refresh();
        }, attempts: 3);
    }

    public function publish(Product $product): Product
    {
        return $this->publisher->publish($product);
    }

    public function hide(Product $product): Product
    {
        return $this->publisher->hide($product);
    }

    private function validated(array $input, bool $partial = false): array
    {
        $sometimes = $partial ? 'sometimes|' : '';

        $data = validator($input, [
            'name_ar' => [$sometimes.'nullable', 'string', 'max:255'],
            'name_en' => [$sometimes.'nullable', 'string', 'max:255'],
            'description_ar' => [$sometimes.'nullable', 'string'],
            'description_en' => [$sometimes.'nullable', 'string'],
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
}
