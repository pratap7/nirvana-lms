<?php

declare(strict_types=1);

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\App;
use Illuminate\Support\Facades\Cookie;
use Illuminate\Support\Facades\Session;
use Symfony\Component\HttpFoundation\Response;

class SetLocaleMiddleware
{
    /**
     * Supported application locales.
     *
     * @var list<string>
     */
    protected const SUPPORTED_LOCALES = ['en', 'hi'];

    /**
     * Handle an incoming request.
     *
     * @param  Closure(Request): (Response)  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        $locale = null;

        // 1. Check query parameter '?lang=hi'
        if ($request->has('lang')) {
            $queryLang = (string) $request->query('lang');
            if (in_array($queryLang, self::SUPPORTED_LOCALES, true)) {
                $locale = $queryLang;
            }
        }

        // 2. Check route parameter '{locale}'
        if (! $locale && $request->route('locale')) {
            $routeLocale = (string) $request->route('locale');
            if (in_array($routeLocale, self::SUPPORTED_LOCALES, true)) {
                $locale = $routeLocale;
            }
        }

        // 3. Check Session or Cookie
        if (! $locale) {
            $sessionLocale = (string) Session::get('locale');
            if (in_array($sessionLocale, self::SUPPORTED_LOCALES, true)) {
                $locale = $sessionLocale;
            } else {
                $cookieLocale = (string) $request->cookie('pramana_locale');
                if (in_array($cookieLocale, self::SUPPORTED_LOCALES, true)) {
                    $locale = $cookieLocale;
                }
            }
        }

        // Default fallback to 'en'
        $resolvedLocale = $locale ?? 'en';

        App::setLocale($resolvedLocale);
        Session::put('locale', $resolvedLocale);

        $response = $next($request);

        // Attach cookie if not already set or changed
        if ($request->cookie('pramana_locale') !== $resolvedLocale) {
            Cookie::queue('pramana_locale', $resolvedLocale, 60 * 24 * 365); // 1 year
        }

        return $response;
    }
}
