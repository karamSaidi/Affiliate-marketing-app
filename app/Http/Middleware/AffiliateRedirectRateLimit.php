<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\RateLimiter;
use Symfony\Component\HttpFoundation\Response;
use Throwable;

class AffiliateRedirectRateLimit
{
    public function handle(Request $request, Closure $next): Response
    {
        $clientIp = $request->ip() ?? 'unknown';
        $key = 'affiliate-go:'.hash('sha256', $clientIp);

        try {
            $allowed = RateLimiter::attempt($key, 120, static fn (): bool => true, 60);

            if (! $allowed) {
                return response('Too many redirect requests.', 429)
                    ->header('Retry-After', (string) max(1, RateLimiter::availableIn($key)));
            }
        } catch (Throwable $exception) {
            Log::critical('Affiliate redirect limiter unavailable; request denied.', [
                'exception' => $exception::class,
                'route' => $request->route()?->getName(),
            ]);

            return response('Too many redirect requests.', 429)->header('Retry-After', '60');
        }

        return $next($request);
    }
}
