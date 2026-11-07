<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        $driver = DB::connection()->getDriverName();

        Schema::create('categories', function (Blueprint $table) use ($driver): void {
            $table->id();
            $nameAr = $table->string('name_ar');
            $nameEn = $table->string('name_en');

            if ($driver === 'mysql') {
                $nameAr->collation('utf8mb4_0900_bin');
                $nameEn->collation('utf8mb4_0900_bin');
            }

            $table->text('description_ar')->nullable();
            $table->text('description_en')->nullable();
            $table->string('slug')->unique();
            $table->foreignId('parent_id')->nullable()->constrained('categories')->restrictOnDelete();
            $table->boolean('is_active')->default(true);
            $table->unsignedInteger('sort_order')->default(0);
            $table->timestamps();

            $table->unique('name_ar');
            $table->unique('name_en');
            $table->index(['parent_id', 'is_active', 'sort_order']);
        });

        Schema::create('brands', function (Blueprint $table) use ($driver): void {
            $table->id();
            $nameAr = $table->string('name_ar');
            $nameEn = $table->string('name_en');

            if ($driver === 'mysql') {
                $nameAr->collation('utf8mb4_0900_bin');
                $nameEn->collation('utf8mb4_0900_bin');
            }

            $table->timestamps();
            $table->unique('name_ar');
            $table->unique('name_en');
        });

        Schema::create('tags', function (Blueprint $table) use ($driver): void {
            $table->id();
            $nameAr = $table->string('name_ar');
            $nameEn = $table->string('name_en');

            if ($driver === 'mysql') {
                $nameAr->collation('utf8mb4_0900_bin');
                $nameEn->collation('utf8mb4_0900_bin');
            }

            $table->timestamps();
            $table->unique('name_ar');
            $table->unique('name_en');
        });

        Schema::create('products', function (Blueprint $table) use ($driver): void {
            $table->id();
            $table->string('name_ar')->nullable();
            $table->string('name_en')->nullable();
            $table->text('description_ar')->nullable();
            $table->text('description_en')->nullable();
            $table->string('slug')->nullable()->unique();
            $table->string('affiliate_url', 2048)->nullable();
            $table->bigInteger('price_minor')->nullable();
            $table->char('currency', 3)->nullable();
            $table->foreignId('category_id')->nullable()->constrained()->restrictOnDelete();
            $table->foreignId('brand_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->string('status', 16)->default('draft');
            $table->timestamps();

            $table->index(['status', 'created_at']);
            $table->index(['category_id', 'status']);
            $table->index(['brand_id', 'status']);
        });

        Schema::create('product_slug_reservations', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('product_id')->constrained()->restrictOnDelete();
            $table->string('slug')->unique();
            $table->boolean('is_current')->default(false);
            $table->timestamp('created_at')->useCurrent();

            $table->unique(['product_id', 'slug']);
            $table->index(['product_id', 'is_current']);
        });

        Schema::create('category_slug_reservations', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('category_id')->constrained()->restrictOnDelete();
            $table->string('slug')->unique();
            $table->boolean('is_current')->default(false);
            $table->timestamp('created_at')->useCurrent();

            $table->unique(['category_id', 'slug']);
            $table->index(['category_id', 'is_current']);
        });

        Schema::create('product_tag', function (Blueprint $table): void {
            $table->foreignId('product_id')->constrained()->cascadeOnDelete();
            $table->foreignId('tag_id')->constrained()->cascadeOnDelete();
            $table->primary(['product_id', 'tag_id']);
        });

        Schema::create('product_images', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('product_id')->constrained()->cascadeOnDelete();
            $table->string('storage_path', 512);
            $table->unsignedInteger('sort_order')->default(0);
            $table->string('alt_ar')->nullable();
            $table->string('alt_en')->nullable();
            $table->timestamps();

            $table->unique(['product_id', 'sort_order']);
            $table->unique(['product_id', 'storage_path']);
        });

        Schema::create('product_events', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('product_id')->constrained()->restrictOnDelete();
            $table->string('event_type', 8);
            $table->dateTime('occurred_at', precision: 6);

            $table->index(['product_id', 'event_type', 'occurred_at']);
            $table->index(['event_type', 'occurred_at', 'product_id']);
        });

        Schema::create('category_tree_lock', function (Blueprint $table): void {
            $table->unsignedTinyInteger('id')->primary();
            $table->unsignedBigInteger('revision')->default(0);
        });

        DB::table('category_tree_lock')->insert(['id' => 1, 'revision' => 0]);

        $this->addPortableChecks($driver);
    }

    public function down(): void
    {
        $driver = DB::connection()->getDriverName();

        if ($driver === 'sqlite') {
            foreach (['products', 'categories', 'product_images', 'product_events'] as $table) {
                DB::unprepared("DROP TRIGGER IF EXISTS {$table}_domain_insert");
                DB::unprepared("DROP TRIGGER IF EXISTS {$table}_domain_update");
            }
        }

        Schema::dropIfExists('category_tree_lock');
        Schema::dropIfExists('product_events');
        Schema::dropIfExists('product_images');
        Schema::dropIfExists('product_tag');
        Schema::dropIfExists('category_slug_reservations');
        Schema::dropIfExists('product_slug_reservations');
        Schema::dropIfExists('products');
        Schema::dropIfExists('tags');
        Schema::dropIfExists('brands');
        Schema::dropIfExists('categories');
    }

    private function addPortableChecks(string $driver): void
    {
        if ($driver === 'mysql') {
            DB::statement("ALTER TABLE products ADD CONSTRAINT products_status_check CHECK (status IN ('draft', 'published', 'hidden'))");
            DB::statement('ALTER TABLE products ADD CONSTRAINT products_price_check CHECK (price_minor IS NULL OR price_minor >= 0)');
            DB::statement('ALTER TABLE products ADD CONSTRAINT products_price_currency_pair_check CHECK ((price_minor IS NULL AND currency IS NULL) OR (price_minor IS NOT NULL AND currency IS NOT NULL))');
            DB::statement('ALTER TABLE categories ADD CONSTRAINT categories_parent_check CHECK (parent_id IS NULL OR parent_id <> id)');
            DB::statement('ALTER TABLE product_images ADD CONSTRAINT product_images_sort_check CHECK (sort_order >= 0)');
            DB::statement("ALTER TABLE product_events ADD CONSTRAINT product_events_type_check CHECK (event_type IN ('view', 'click'))");

            return;
        }

        if ($driver !== 'sqlite') {
            return;
        }

        $checks = [
            'products' => "NEW.status NOT IN ('draft', 'published', 'hidden') OR (NEW.price_minor IS NOT NULL AND NEW.price_minor < 0) OR ((NEW.price_minor IS NULL) <> (NEW.currency IS NULL))",
            'categories' => 'NEW.parent_id IS NOT NULL AND NEW.parent_id = NEW.id',
            'product_images' => 'NEW.sort_order < 0',
            'product_events' => "NEW.event_type NOT IN ('view', 'click')",
        ];

        foreach ($checks as $table => $condition) {
            foreach (['INSERT', 'UPDATE'] as $operation) {
                $trigger = "{$table}_domain_".strtolower($operation);
                DB::unprepared("CREATE TRIGGER {$trigger} BEFORE {$operation} ON {$table} FOR EACH ROW WHEN ({$condition}) BEGIN SELECT RAISE(ABORT, 'Invalid {$table} domain value'); END");
            }
        }
    }
};
