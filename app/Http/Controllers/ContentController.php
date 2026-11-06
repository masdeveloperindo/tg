<?php

namespace App\Http\Controllers;

use App\Actions\Content\Types;
use App\Actions\Content\Visibility;
use App\Actions\Orders\CheckoutSettings;
use App\Models\Faq;
use App\Models\Page;
use App\Security\Access;
use App\Support\PublicContent;
use Illuminate\Http\Request;

class ContentController
{
    public function __invoke(Request $request)
    {
        $kind = $request->route('kind');
        $slug = $request->route('slug');
        $kategori = $request->route('kategori');
        $record = null;
        $rows = null;
        $title = '';
        $description = 'Informasi dan panduan Trader Garuda.';
        $noindex = false;
        $images = [];
        $steps = [];
        $user = $request->user()?->fresh();
        $member = $user && Access::isOwner($user, $user->id);
        if ($kind === 'edukasi') {
            $query = $member ? Visibility::articles() : Visibility::publicArticles();
            if ($kategori) {
                $category = ['signal' => 'signal', 'cara-trading' => 'cara_trading'][$kategori] ?? abort(404);
                $query->where('category', $category);
            }
            if ($slug) {
                $record = Visibility::articles()->where('slug', $slug)->firstOrFail();
                if ($record->visibility->value === 'client' && ! $member) {
                    if (! $user) {
                        return redirect()->guest(route('login'));
                    }abort(403);
                }$noindex = $record->visibility->value === 'client';
                $title = $record->seo_title ?: $record->title;
                $description = $record->seo_description ?: PublicContent::text($record->body, 320);
                $images = $record->images()->get();
            } else {
                $rows = $query->latest('published_at')->paginate(20);
                $title = $kategori ? 'Edukasi — '.($kategori === 'signal' ? 'Signal' : 'Cara Trading') : 'Edukasi';
                $noindex = (bool) $member;
            }
        } elseif ($kind === 'panduan') {
            $title = 'Panduan instalasi';
            if ($slug) {
                $record = Visibility::guides()->where('slug', $slug)->firstOrFail();
                $title = $record->title;
                $description = PublicContent::text($record->summary, 320);
                $steps = $record->steps()->orderBy('step_no')->get();
            } else {
                $rows = Visibility::guides()->orderBy('title')->paginate(20);
            }
        } elseif ($kind === 'faq') {
            $title = 'Pertanyaan umum';
            $rows = Faq::whereNull('product_id')->where('is_active', true)->orderBy('sort_order')->paginate(30);
        } elseif ($kind === 'halaman') {
            abort_unless(isset(Types::PAGES[$slug]), 404);
            $record = Page::where('slug', $slug)->where('is_published', true)->first();
            $title = $record?->seo_title ?: $record?->title ?: Types::PAGES[$slug];
            $description = $record?->seo_description ?: $description;
            $noindex = ! $record;
        } else {
            abort(404);
        }
        $canonical = $noindex ? null : $request->getPathInfo();
        $socialImage = $kind === 'edukasi' && $record?->cover_path ? route('content.image', ['type' => 'cover', 'id' => $record->id]) : null;
        $telegram = $kind === 'halaman' && $slug === 'kontak' ? app(CheckoutSettings::class)->telegram() : null;
        $response = response()->view('content.public', compact('kind', 'record', 'rows', 'title', 'description', 'noindex', 'canonical', 'socialImage', 'images', 'steps', 'telegram'));
        if ($noindex) {
            $response->headers->set('X-Robots-Tag', 'noindex, nofollow');
            $response->headers->set('Cache-Control','no-store, private');
        }

        return $response;
    }
}
