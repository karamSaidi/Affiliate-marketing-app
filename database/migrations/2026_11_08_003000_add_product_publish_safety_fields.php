<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('products', function (Blueprint $table): void {
            $table->unsignedInteger('lock_version')->default(0);
            $table->uuid('creation_idempotency_key')->nullable()->unique();
        });
    }

    public function down(): void
    {
        Schema::table('products', function (Blueprint $table): void {
            $table->dropUnique(['creation_idempotency_key']);
            $table->dropColumn(['creation_idempotency_key', 'lock_version']);
        });
    }
};
