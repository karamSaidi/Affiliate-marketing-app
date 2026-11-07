<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('catalog_cache_versions', function (Blueprint $table): void {
            $table->unsignedTinyInteger('id')->primary();
            $table->unsignedBigInteger('version')->default(1);
        });

        DB::table('catalog_cache_versions')->insert(['id' => 1, 'version' => 1]);
    }

    public function down(): void
    {
        Schema::dropIfExists('catalog_cache_versions');
    }
};
