<?php

if (! function_exists('localeUrl')) {
    /**
     * Build the current page URL with an explicit locale prefix.
     * Always generates /xx-xx/path so the switcher works correctly regardless
     * of which locale is the site default. The middleware reads the locale
     * from the route parameter (higher priority than cookie).
     *
     * Used by the language switcher. For hreflang/canonical use localeCanonicalUrl().
     */
    function localeUrl(string $locale): string
    {
        $locales = array_keys(config('localization.locales', []));

        $path     = trim(request()->path(), '/');
        $segments = explode('/', $path);
        if (in_array($segments[0] ?? '', $locales)) {
            array_shift($segments);
        }
        $cleanPath = implode('/', $segments);

        $query = request()->except(['lang', 'locale']);
        $qs    = $query ? '?' . http_build_query($query) : '';

        return url('/' . $locale . '/' . $cleanPath) . $qs;
    }
}

if (! function_exists('localeCanonicalUrl')) {
    /**
     * Build the canonical URL for a locale.
     * The default locale gets no prefix (for hreflang/x-default/sitemap use).
     */
    function localeCanonicalUrl(string $locale): string
    {
        $defaultLocale = app('view')->shared('defaultLocale', config('app.locale', 'en-us'));
        $locales       = array_keys(config('localization.locales', []));

        $path     = trim(request()->path(), '/');
        $segments = explode('/', $path);
        if (in_array($segments[0] ?? '', $locales)) {
            array_shift($segments);
        }
        $cleanPath = implode('/', $segments);

        $query = request()->except(['lang', 'locale']);
        $qs    = $query ? '?' . http_build_query($query) : '';

        if ($locale === $defaultLocale) {
            return url('/' . $cleanPath) . $qs;
        }

        return url('/' . $locale . '/' . $cleanPath) . $qs;
    }
}
