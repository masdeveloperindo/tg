<?php

namespace App\Actions\Content;

use App\Models\Announcement;
use App\Models\Article;
use App\Models\Guide;
use App\Models\Testimonial;
use App\Models\TradingAccount;
use App\Security\Access;

final class Visibility
{
    public static function articles()
    {
        return Article::where('status', 'published')->whereNotNull('published_at')->where('published_at', '<=', now()->format('Y-m-d H:i:s.u'));
    }

    public static function publicArticles()
    {
        return self::articles()->where('visibility', 'public');
    }

    public static function guides()
    {
        return Guide::where('is_active', true)->where(fn ($q) => $q->whereNull('product_id')->orWhereHas('product', fn ($p) => $p->where('status', 'published')));
    }

    public static function testimonials()
    {
        return Testimonial::where('is_visible', true)->whereNotNull('published_at')->where('published_at', '<=', now()->format('Y-m-d H:i:s.u'))->where('consent_record', '<>', '')->where('source', '<>', '')->where(fn ($q) => $q->whereNull('product_id')->orWhereHas('product', fn ($p) => $p->where('status', 'published')));
    }

    public static function canRead(Article $article): bool
    {
        $user = auth()->user()?->fresh();

        return $article->visibility->value === 'public' || ($user && Access::isOwner($user, $user->id));
    }

    public static function announcements(int $userId)
    {
        $ib = TradingAccount::where('user_id', $userId)->where('status', 'approved')->exists();

        return Announcement::where('is_active', true)->where(fn ($q) => $q->whereNull('starts_at')->orWhere('starts_at', '<=', now()->format('Y-m-d H:i:s.u')))->where(fn ($q) => $q->whereNull('ends_at')->orWhere('ends_at', '>', now()->format('Y-m-d H:i:s.u')))->whereIn('audience', $ib ? ['all', 'clients', 'ib_clients'] : ['all', 'clients']);
    }
}
