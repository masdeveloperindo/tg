<?php

namespace App\Support;

use Illuminate\Support\Facades\Storage;

final class PublicContent
{
    public static function url(string $path): string
    {
        return rtrim(config('app.url'), '/').'/'.ltrim($path, '/');
    }

    public static function text(?string $html, int $length = 320): string
    {
        return mb_substr(trim(preg_replace('/\s+/u', ' ', html_entity_decode(strip_tags(SafeHtml::clean($html) ?? ''), ENT_QUOTES | ENT_HTML5, 'UTF-8'))), 0, $length);
    }

    public static function image(?string $path, string $route): ?array
    {
        if (! $path || ! str_starts_with($path, 'products/') || ! Storage::disk('local')->exists($path)) {
            return null;
        }
        $size = @getimagesize(Storage::disk('local')->path($path));
        if (! $size || ($size['mime'] ?? '') !== 'image/webp') {
            return null;
        }

        return ['url' => self::url($route), 'width' => $size[0], 'height' => $size[1]];
    }

    public static function videoLink(?string $url): ?string
    {
        if (! $url || ! filter_var($url, FILTER_VALIDATE_URL) || strtolower((string) parse_url($url, PHP_URL_SCHEME)) !== 'https'
            || parse_url($url, PHP_URL_USER) || parse_url($url, PHP_URL_PASS) || parse_url($url, PHP_URL_PORT)) {
            return null;
        }

        return in_array(strtolower((string) parse_url($url, PHP_URL_HOST)), ['youtube.com', 'www.youtube.com', 'youtu.be', 'vimeo.com', 'www.vimeo.com'], true) ? $url : null;
    }

    public static function brokerLink(string $url): ?string
    {
        if (! filter_var($url, FILTER_VALIDATE_URL) || strtolower((string) parse_url($url, PHP_URL_SCHEME)) !== 'https'
            || parse_url($url, PHP_URL_USER) || parse_url($url, PHP_URL_PASS) || parse_url($url, PHP_URL_PORT)) {
            return null;
        }
        $host = strtolower((string) parse_url($url, PHP_URL_HOST));
        if (filter_var($host, FILTER_VALIDATE_IP) || ! str_contains($host, '.') || preg_match('/\.(?:local|internal|localhost)$/i', $host)) {
            return null;
        }

        return in_array($host, config('tradergaruda.broker_referral_hosts', []), true) ? $url : null;
    }
}
