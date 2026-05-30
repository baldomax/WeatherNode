<?php

namespace App\Http\Middleware;

use App\Models\Setting;
use App\Support\UnitFormatter;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class LocaleUnitsMiddleware
{
    public function handle(Request $request, Closure $next): Response
    {
        $locales = array_keys(config('localization.locales', []));
        $defaults = config('localization.language_defaults', []);
        $unitAliases = config('localization.unit_aliases', []);
        $unitOptions = array_keys(config('localization.units', []));
        $cookieConfig = config('localization.cookies', []);
        $acceptLanguageEnabled = config('localization.accept_language.enabled', false);

        $cookieLang = $cookieConfig['lang'] ?? 'mu_lang';
        $cookieUnits = $cookieConfig['units'] ?? 'mu_units';
        $cookieMinutes = (int) ($cookieConfig['days'] ?? 90) * 24 * 60;
        $acceptLanguageLocale = $acceptLanguageEnabled ? $this->fromAcceptLanguage($request) : null;

        $defaultLang = $this->resolveLocale(
            Setting::getValue('display.language', config('app.locale', 'en')),
            $locales,
            $defaults
        );
        if (!$defaultLang) {
            $defaultLang = $this->resolveLocale(config('app.locale', 'en'), $locales, $defaults)
                ?? ($locales[0] ?? 'en-us');
        }

        $defaultUnits = $this->resolveUnits(
            Setting::getValue('display.unit_system', 'metric'),
            $unitOptions,
            $unitAliases
        ) ?? 'metric';

        // Redirect legacy ?lang= / ?locale= query params to path-prefix URLs (301)
        // Only for page requests — API calls use ?lang= as a legitimate NLG locale parameter.
        if (!$request->is('api/*') && ($request->has('lang') || $request->has('locale'))) {
            $legacyParam = $request->query('lang') ?? $request->query('locale');
            $resolved    = $this->resolveLocale($legacyParam, $locales, $defaults);
            if ($resolved) {
                $path     = trim($request->path(), '/');
                $segments = explode('/', $path);
                if (in_array($segments[0] ?? '', $locales)) {
                    array_shift($segments);
                }
                $cleanPath = implode('/', $segments);
                $query     = $request->except(['lang', 'locale']);
                $newUrl    = '/' . $resolved . '/' . ltrim($cleanPath, '/');
                if ($query) {
                    $newUrl .= '?' . http_build_query($query);
                }
                return redirect($newUrl, 301);
            }
        }

        // Read locale from route path parameter (highest priority)
        $lang = $this->resolveLocale($request->route('locale'), $locales, $defaults);
        if ($lang) {
            cookie()->queue($cookieLang, $lang, $cookieMinutes);
        } elseif ($request->is('api/*')) {
            // For API requests: ?lang= overrides cookie (used by JS for NLG locale — no redirect, no cookie update)
            $apiParam = $request->query('lang') ?? $request->query('locale');
            $lang = $this->resolveLocale($apiParam, $locales, $defaults)
                ?? $this->resolveLocale($request->cookie($cookieLang), $locales, $defaults);
        } else {
            $lang = $this->resolveLocale($request->cookie($cookieLang), $locales, $defaults);
        }

        if (!$lang) {
            $lang = $this->resolveLocale(Setting::getValue('display.language'), $locales, $defaults);
        }

        $langFromAcceptLanguage = false;
        if (!$lang && $acceptLanguageEnabled) {
            $lang = $this->resolveLocale($acceptLanguageLocale, $locales, $defaults);
            $langFromAcceptLanguage = (bool) $lang;
        }

        $units = $this->resolveUnits($request->query('units'), $unitOptions, $unitAliases);

        if ($units) {
            cookie()->queue($cookieUnits, $units, $cookieMinutes);
        } else {
            $units = $this->resolveUnits($request->cookie($cookieUnits), $unitOptions, $unitAliases);
        }

        if (!$units) {
            $units = $this->resolveUnits(Setting::getValue('display.unit_system'), $unitOptions, $unitAliases);
        }

        $unitsFromAcceptLanguage = false;
        if (!$units && $acceptLanguageEnabled) {
            $units = $this->unitsFromLocale($acceptLanguageLocale, $unitOptions, $unitAliases);
            $unitsFromAcceptLanguage = (bool) $units;
        }

        $lang = $lang ?: $defaultLang;
        $units = $units ?: $defaultUnits;

        if ($langFromAcceptLanguage && !$request->cookie($cookieLang)) {
            cookie()->queue($cookieLang, $lang, $cookieMinutes);
        }
        if ($unitsFromAcceptLanguage && !$request->cookie($cookieUnits)) {
            cookie()->queue($cookieUnits, $units, $cookieMinutes);
        }

        app()->setLocale($lang);

        view()->share([
            'activeLocale' => $lang,
            'activeUnits' => $units,
            'defaultLocale' => $defaultLang,
            'jsLocale' => $this->toJsLocale($lang),
            'stationTimezone' => Setting::timezone(),
            'localeOptions' => config('localization.locales', []),
            'unitOptions' => config('localization.units', []),
            'unit' => new UnitFormatter(),
        ]);

        return $next($request);
    }

    private function resolveLocale(?string $locale, array $allowed, array $defaults): ?string
    {
        $locale = $this->normalizeLocale($locale);
        if (!$locale) {
            return null;
        }

        if ($locale === 'auto') {
            return null;
        }

        if (in_array($locale, $allowed, true)) {
            return $locale;
        }

        $base = explode('-', $locale)[0] ?? $locale;
        $mapped = $defaults[$base] ?? null;

        return ($mapped && in_array($mapped, $allowed, true)) ? $mapped : null;
    }

    private function resolveUnits(?string $units, array $allowed, array $aliases): ?string
    {
        if (!$units) {
            return null;
        }

        $units = strtolower(trim($units));

        if ($units === 'auto') {
            return null;
        }

        $units = $aliases[$units] ?? $units;

        return in_array($units, $allowed, true) ? $units : null;
    }

    private function normalizeLocale(?string $locale): ?string
    {
        if (!$locale) {
            return null;
        }

        $locale = strtolower(str_replace('_', '-', trim($locale)));
        $parts = explode('-', $locale);

        if (count($parts) >= 2) {
            return $parts[0] . '-' . $parts[1];
        }

        return $parts[0] ?? null;
    }

    private function fromAcceptLanguage(Request $request): ?string
    {
        $header = $request->header('Accept-Language');
        if (!$header) {
            return null;
        }

        if (class_exists(\Locale::class)) {
            $locale = \Locale::acceptFromHttp($header);
            return $locale ?: null;
        }

        $chunks = explode(',', $header);
        return $chunks[0] ?? null;
    }

    private function unitsFromLocale(?string $locale, array $allowed, array $aliases): ?string
    {
        $locale = $this->normalizeLocale($locale);
        if (!$locale || !str_contains($locale, '-')) {
            return null;
        }

        [, $region] = explode('-', $locale, 2);
        $region = strtoupper($region);
        $units = config('localization.accept_language.units_by_region.' . $region);
        if (!$units) {
            return null;
        }

        $units = strtolower(trim((string) $units));
        $units = $aliases[$units] ?? $units;

        return in_array($units, $allowed, true) ? $units : null;
    }

    private function toJsLocale(string $locale): string
    {
        $locale = str_replace('_', '-', strtolower($locale));
        $parts = explode('-', $locale, 2);

        if (count($parts) === 2) {
            return $parts[0] . '-' . strtoupper($parts[1]);
        }

        return $parts[0];
    }
}
