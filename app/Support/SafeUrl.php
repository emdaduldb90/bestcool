<?php

namespace App\Support;

class SafeUrl
{
    public static function image(string $url): bool
    {
        return self::uploadPath($url) || self::https($url);
    }

    public static function uploadPath(string $url): bool
    {
        if (! str_starts_with($url, '/uploads/')) {
            return false;
        }

        $path = rawurldecode((string) parse_url($url, PHP_URL_PATH));

        return str_starts_with($path, '/uploads/')
            && ! str_contains($path, '%')
            && ! preg_match('#(^|/)\.\.?(/|$)#', $path)
            && ! str_contains($path, "\\")
            && ! preg_match('/[\x00-\x1F\x7F]/', $path);
    }

    public static function https(string $url): bool
    {
        return filter_var($url, FILTER_VALIDATE_URL) !== false
            && strtolower((string) parse_url($url, PHP_URL_SCHEME)) === 'https';
    }
}
