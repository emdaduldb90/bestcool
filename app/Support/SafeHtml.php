<?php

namespace App\Support;

class SafeHtml
{
    public static function clean(?string $html): string
    {
        $html = strip_tags((string) $html, '<p><h2><h3><h4><ul><ol><li><strong><b><em><i><br><hr><a>');
        $html = preg_replace('/<(?!\/?a\b)([a-z0-9]+)\b[^>]*>/i', '<$1>', $html) ?? '';

        return preg_replace_callback('/<a\b([^>]*)>/i', function (array $match): string {
            if (! preg_match('/\bhref\s*=\s*(["\'])(.*?)\1/i', $match[1], $hrefMatch)) {
                return '<a>';
            }

            $href = trim(html_entity_decode($hrefMatch[2], ENT_QUOTES | ENT_HTML5, 'UTF-8'));
            $scheme = parse_url($href, PHP_URL_SCHEME);
            if ($scheme && ! in_array(strtolower($scheme), ['http', 'https', 'mailto', 'tel'], true)) {
                return '<a>';
            }

            return '<a href="'.e($href).'" rel="nofollow noopener">';
        }, $html) ?? '';
    }
}
