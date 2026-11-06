<?php

namespace App\Http\Controllers;

use App\Actions\Content\Types;
use App\Actions\Content\Visibility;
use App\Models\Page;
use App\Models\Product;
use App\Support\PublicContent;

final class SeoController
{
    public function sitemap()
    {
        $urls = array_map(fn ($p) => PublicContent::url($p), ['/', '/produk', '/broker', '/edukasi', '/faq', '/panduan']);
        foreach (Product::where('status', 'published')->pluck('slug') as $s) {
            $urls[] = PublicContent::url('/produk/'.$s);
        }
        foreach (Visibility::publicArticles()->pluck('slug') as $s) {
            $urls[] = PublicContent::url('/edukasi/'.$s);
        }
        foreach (Visibility::guides()->pluck('slug') as $s) {
            $urls[] = PublicContent::url('/panduan/'.$s);
        }
        foreach (Page::where('is_published', true)->whereIn('slug', array_keys(Types::PAGES))->pluck('slug') as $s) {
            $urls[] = PublicContent::url('/'.$s);
        }

        $document = new \DOMDocument('1.0', 'UTF-8');
        $root = $document->createElementNS('http://www.sitemaps.org/schemas/sitemap/0.9', 'urlset');
        $document->appendChild($root);
        foreach (array_unique($urls) as $url) {
            $entry = $root->appendChild($document->createElement('url'));
            $entry->appendChild($document->createElement('loc'))->appendChild($document->createTextNode($url));
        }

        return response($document->saveXML(), 200, ['Content-Type' => 'application/xml; charset=UTF-8']);
    }

    public function robots()
    {
        return response("User-agent: *\nDisallow: /admin\nDisallow: /member\nDisallow: /checkout\nDisallow: /pesanan\nDisallow: /download\nDisallow: /api\nDisallow: /health\nDisallow: /login\nDisallow: /register\nSitemap: ".PublicContent::url('/sitemap.xml')."\n")->header('Content-Type', 'text/plain; charset=UTF-8');
    }
}
