<?php

namespace App\Services\Catalog;

use App\Models\Category;
use App\Models\CategoryTreeLock;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class CategoryTreeManager
{
    public function __construct(
        private readonly SlugReservationManager $slugs,
        private readonly PublicCatalogCache $catalogCache,
    ) {}

    public function create(array $attributes): Category
    {
        return DB::transaction(function () use ($attributes): Category {
            $this->lockTree();
            $attributes = $this->validatedAttributes($attributes);
            $parent = $this->resolveParent($attributes['parent_id'] ?? null);

            if ($this->subtreeDepth(null) + $this->categoryDepth($parent) > 3) {
                throw ValidationException::withMessages(['parent_id' => 'Category trees may not exceed three levels.']);
            }

            if ($parent && Category::query()->whereKey($parent->getKey())->whereHas('products', fn ($query) => $query->where('status', 'published'))->exists()) {
                throw ValidationException::withMessages(['parent_id' => 'A category assigned to a published product cannot gain children.']);
            }

            $initialSlug = Str::slug($attributes['slug'] ?? $attributes['name_en']);

            if ($initialSlug === '') {
                throw ValidationException::withMessages(['slug' => 'An English name or slug is required.']);
            }

            $category = Category::query()->create([...$attributes, 'slug' => $initialSlug]);

            $this->slugs->reserveCategorySlug($category, $attributes['slug'] ?? null);
            $this->catalogCache->invalidate();

            return $category->refresh();
        }, attempts: 3);
    }

    public function update(Category $category, array $attributes): Category
    {
        return DB::transaction(function () use ($category, $attributes): Category {
            $this->lockTree();
            $category = Category::query()->lockForUpdate()->findOrFail($category->getKey());
            $attributes = $this->validatedAttributes($attributes, $category);
            $parentId = $attributes['parent_id'] ?? null;
            $parent = $this->resolveParent($parentId);

            if ($parent && $this->isDescendantOf($parent, $category)) {
                throw ValidationException::withMessages(['parent_id' => 'The category cannot be moved beneath itself or a descendant.']);
            }

            if ($this->categoryDepth($parent) + $this->subtreeDepth($category) > 3) {
                throw ValidationException::withMessages(['parent_id' => 'Category trees may not exceed three levels.']);
            }

            if ($parent && $parent->products()->where('status', 'published')->exists()) {
                throw ValidationException::withMessages(['parent_id' => 'A category assigned to a published product cannot gain children.']);
            }

            $hasPublishedProducts = $category->products()->where('status', 'published')->exists();
            $willHaveChildren = $category->children()->exists();

            if ($hasPublishedProducts && $willHaveChildren) {
                throw ValidationException::withMessages(['parent_id' => 'A category assigned to a published product must remain a leaf.']);
            }

            if (! ($attributes['is_active'] ?? $category->is_active) && ($hasPublishedProducts || $category->children()->where('is_active', true)->exists())) {
                throw ValidationException::withMessages(['is_active' => 'Move or hide affected records before deactivating this category.']);
            }

            $category->fill(collect($attributes)->except('slug')->all());
            $category->save();

            if (array_key_exists('slug', $attributes) || $category->slug === null) {
                $this->slugs->reserveCategorySlug($category, $attributes['slug'] ?? null);
            }

            $this->catalogCache->invalidate();

            return $category->refresh();
        }, attempts: 3);
    }

    public function delete(Category $category): void
    {
        DB::transaction(function () use ($category): void {
            $this->lockTree();
            $category = Category::query()->lockForUpdate()->findOrFail($category->getKey());

            if ($category->products()->exists() || $category->children()->exists()) {
                throw ValidationException::withMessages(['category' => 'Move related products and subcategories before deleting this category.']);
            }

            $category->slugReservations()->delete();
            $category->delete();
            $this->catalogCache->invalidate();
        }, attempts: 3);
    }

    /** @param list<int> $categoryIds */
    public function reorderSiblings(?int $parentId, array $categoryIds): void
    {
        DB::transaction(function () use ($parentId, $categoryIds): void {
            $this->lockTree();
            $siblings = Category::query()->where('parent_id', $parentId)->orderBy('id')->lockForUpdate()->get();
            $requested = array_map('intval', $categoryIds);
            $existing = $siblings->modelKeys();

            if (count($requested) !== count(array_unique($requested))
                || count($requested) !== count($existing)
                || array_diff($requested, $existing) !== []) {
                throw ValidationException::withMessages(['categories' => 'Provide every category at this level exactly once.']);
            }

            $offset = ((int) $siblings->max('sort_order')) + $siblings->count() + 1;

            foreach ($siblings as $sibling) {
                $sibling->forceFill(['sort_order' => $sibling->sort_order + $offset])->save();
            }

            foreach ($requested as $sortOrder => $categoryId) {
                Category::query()->whereKey($categoryId)->update(['sort_order' => $sortOrder]);
            }

            $this->catalogCache->invalidate();
        }, attempts: 3);
    }

    private function lockTree(): void
    {
        CategoryTreeLock::query()->whereKey(1)->increment('revision');
        CategoryTreeLock::query()->whereKey(1)->lockForUpdate()->firstOrFail();
    }

    private function validatedAttributes(array $attributes, ?Category $category = null): array
    {
        $validated = validator($attributes, [
            'name_ar' => ['required', 'string', 'max:255'],
            'name_en' => ['required', 'string', 'max:255'],
            'description_ar' => ['nullable', 'string'],
            'description_en' => ['nullable', 'string'],
            'slug' => ['nullable', 'string', 'max:255', 'regex:/\A[a-z0-9]+(?:-[a-z0-9]+)*\z/'],
            'parent_id' => ['nullable', 'integer', 'exists:categories,id'],
            'is_active' => ['sometimes', 'boolean'],
            'sort_order' => ['sometimes', 'integer', 'min:0', 'max:4294967295'],
        ])->validate();

        foreach (['name_ar', 'name_en'] as $field) {
            $validated[$field] = trim($validated[$field]);
        }

        if (($validated['name_ar'] ?? '') === '' || ($validated['name_en'] ?? '') === '') {
            throw ValidationException::withMessages(['name_en' => 'Category names cannot be blank.']);
        }

        if (isset($validated['slug']) && trim($validated['slug']) === '') {
            $validated['slug'] = Str::slug($validated['name_en']);
        }

        if ($category && ! array_key_exists('parent_id', $validated)) {
            $validated['parent_id'] = $category->parent_id;
        }

        return $validated;
    }

    private function resolveParent(?int $parentId): ?Category
    {
        if ($parentId === null) {
            return null;
        }

        $parent = Category::query()->active()->findOrFail($parentId);

        return $parent;
    }

    private function isDescendantOf(Category $candidate, Category $ancestor): bool
    {
        $visited = [];
        $current = $candidate;

        while ($current) {
            if ($current->is($ancestor)) {
                return true;
            }

            if (isset($visited[$current->getKey()])) {
                throw ValidationException::withMessages(['parent_id' => 'The existing category tree contains a cycle.']);
            }

            $visited[$current->getKey()] = true;
            $current = $current->parent;
        }

        return false;
    }

    private function categoryDepth(?Category $category): int
    {
        $depth = 0;

        while ($category !== null) {
            $depth++;
            $category = $category->parent;
        }

        return $depth;
    }

    private function subtreeDepth(?Category $category): int
    {
        if ($category === null) {
            return 1;
        }

        $maxChildDepth = 0;

        foreach ($category->children as $child) {
            $maxChildDepth = max($maxChildDepth, $this->subtreeDepth($child));
        }

        return 1 + $maxChildDepth;
    }
}
