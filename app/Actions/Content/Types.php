<?php

namespace App\Actions\Content;

use App\Models\Announcement;
use App\Models\Article;
use App\Models\Faq;
use App\Models\Guide;
use App\Models\Page;
use App\Models\Testimonial;

final class Types
{
    public const MODELS = ['edukasi' => Article::class, 'faq' => Faq::class, 'panduan' => Guide::class, 'halaman' => Page::class, 'testimoni' => Testimonial::class, 'pengumuman' => Announcement::class];

    public const PAGES = ['tentang' => 'Tentang', 'kontak' => 'Kontak', 'kebijakan-privasi' => 'Kebijakan privasi', 'syarat-layanan' => 'Syarat layanan', 'disclaimer-risiko' => 'Disclaimer risiko'];

    public static function model(string $kind): string
    {
        abort_unless(isset(self::MODELS[$kind]), 404);

        return self::MODELS[$kind];
    }

    public static function fields(string $kind): array
    {
        $common = ['title' => ['Judul', 'text'], 'slug' => ['Slug', 'text'], 'body' => ['Isi', 'rich'], 'seo_title' => ['Judul SEO', 'text'], 'seo_description' => ['Deskripsi SEO', 'textarea']];

        return match ($kind) {
            'edukasi' => [...$common, 'category' => ['Kategori', ['signal' => 'Signal', 'cara_trading' => 'Cara Trading']], 'visibility' => ['Visibilitas', ['public' => 'Publik', 'client' => 'Client login']], 'status' => ['Status', ['draft' => 'Draf', 'published' => 'Terbit']], 'published_at' => ['Tanggal terbit (WIB)', 'datetime-local']],
            'faq' => ['product_id' => ['ID produk (opsional)', 'number'], 'question' => ['Pertanyaan', 'text'], 'answer' => ['Jawaban', 'rich'], 'sort_order' => ['Urutan', 'number'], 'is_active' => ['Aktif', 'checkbox']],
            'panduan' => ['product_id' => ['ID produk (opsional)', 'number'], 'title' => ['Judul', 'text'], 'slug' => ['Slug', 'text'], 'summary' => ['Ringkasan', 'rich'], 'is_active' => ['Aktif', 'checkbox']],
            'halaman' => [...$common, 'slug' => ['Halaman', self::PAGES], 'is_published' => ['Publikasikan', 'checkbox']],
            'testimoni' => ['product_id' => ['ID produk (opsional)', 'number'], 'display_name' => ['Nama tampil', 'text'], 'content' => ['Testimoni asli', 'textarea'], 'rating' => ['Rating 1–5 (opsional)', 'number'], 'source' => ['Referensi sumber asli', 'text'], 'consent_record' => ['Referensi izin publikasi', 'textarea'], 'consent_confirmed' => ['Izin publikasi masih berlaku dan sudah diperiksa', 'checkbox'], 'sort_order' => ['Urutan', 'number'], 'published_at' => ['Tanggal tampil (WIB)', 'datetime-local'], 'is_visible' => ['Tampilkan', 'checkbox']],
            'pengumuman' => ['title' => ['Judul', 'text'], 'body' => ['Isi', 'rich'], 'audience' => ['Audiens', ['all' => 'Semua client', 'clients' => 'Client', 'ib_clients' => 'Client IB terverifikasi']], 'starts_at' => ['Mulai (WIB)', 'datetime-local'], 'ends_at' => ['Selesai (WIB)', 'datetime-local'], 'is_active' => ['Aktif', 'checkbox']],
            default => abort(404),
        };
    }

    public static function defaults(string $kind): array
    {
        $data = [];
        foreach (self::fields($kind) as $key => [$label,$type]) {
            $data[$key] = $type === 'checkbox' ? false : ($type === 'number' && $key === 'sort_order' ? '0' : '');
        }
        if ($kind === 'edukasi') {
            $data['category'] = 'cara_trading';
            $data['visibility'] = 'public';
            $data['status'] = 'draft';
        }
        if ($kind === 'pengumuman') {
            $data['audience'] = 'clients';
        }

        return $data;
    }
}
