<?php

namespace App\Services\Catalog;

use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Throwable;

class PublicCatalogCache
{
    public function remember(string $key, callable $resolver, int $seconds = 3600): mixed
    {
        try {
            $version = DB::table('catalog_cache_versions')->where('id', 1)->value('version');

            if ($version === null) {
                return $resolver();
            }

            return Cache::store('failover')->remember("catalog:v{$version}:{$key}", $seconds, $resolver);
        } catch (Throwable $exception) {
            Log::warning('Public catalog cache unavailable; resolving from the database.', [
                'exception' => $exception::class,
            ]);

            return $resolver();
        }
    }

    public function invalidate(): void
    {
        DB::table('catalog_cache_versions')->where('id', 1)->increment('version');
    }
}
