<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class SetRequestLocale
{
    public function handle(Request $request, Closure $next): Response
    {
        $locale = $request->route('locale')
            ?? $request->input('locale')
            ?? $request->query('locale')
            ?? $request->session()->get('locale')
            ?? config('app.locale', 'en');

        if (in_array($locale, ['ar', 'en'], true)) {
            app()->setLocale($locale);
            $request->session()->put('locale', $locale);
        }

        return $next($request);
    }
}
