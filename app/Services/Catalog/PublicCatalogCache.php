<?php

namespace App\Services\Catalog;

use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Throwable;

class PublicCatalogCache
{
    public function remember(string $key, callable $resolver, int $seconds = 3600): mixed
    {
        if ($this->isBypassing()) {
            if (! $this->recoverInvalidation()) {
                return $resolver();
            }
        }

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
        try {
            $updated = DB::table('catalog_cache_versions')->where('id', 1)->increment('version');

            if ($updated === 0) {
                throw new \RuntimeException('Catalog cache version row is missing.');
            }

            Storage::disk('local')->delete('catalog-cache-bypass');
        } catch (Throwable $exception) {
            try {
                Storage::disk('local')->put('catalog-cache-bypass', '1');
            } catch (Throwable $flagException) {
                Log::critical('Catalog cache bypass flag could not be saved.', [
                    'exception' => $flagException::class,
                ]);
            }

            throw $exception;
        }
    }

    private function isBypassing(): bool
    {
        try {
            return Storage::disk('local')->exists('catalog-cache-bypass');
        } catch (Throwable $exception) {
            Log::warning('Catalog cache bypass state could not be read; resolving from the database.', [
                'exception' => $exception::class,
            ]);

            return true;
        }
    }

    private function recoverInvalidation(): bool
    {
        try {
            $updated = DB::table('catalog_cache_versions')->where('id', 1)->increment('version');

            if ($updated === 0) {
                return false;
            }

            Storage::disk('local')->delete('catalog-cache-bypass');

            return ! $this->isBypassing();
        } catch (Throwable $exception) {
            Log::warning('Catalog cache remains bypassed until invalidation can complete.', [
                'exception' => $exception::class,
            ]);

            return false;
        }
    }
}
