<?php

namespace App\Actions\Content;

use App\Actions\Products\ProductAssets;
use App\Actions\Rebates\Finance;
use App\Models\Article;
use App\Models\ArticleImage;
use App\Models\GuideStep;
use App\Support\Audit;
use App\Support\SafeHtml;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

final class PublishContent
{
    private function time(?string $value): ?CarbonImmutable
    {
        return $value ? CarbonImmutable::createFromFormat('!Y-m-d\TH:i', $value, 'Asia/Jakarta')->utc() : null;
    }

    public function save(string $kind, ?int $id, array $input, ?UploadedFile $image = null, array $steps = [], array $stepUploads = []): Model
    {
        $admin = Finance::admin();
        $model = Types::model($kind);
        $table = (new $model)->getTable();
        $title = ['title' => 'required|string|max:191'];
        $slug = ['slug' => ['required', 'string', 'max:191', 'regex:/\A[a-z0-9]+(?:-[a-z0-9]+)*\z/', Rule::unique($table, 'slug')->ignore($id)]];
        $body = ['body' => 'required|string|max:100000'];
        $seo = ['seo_title' => 'nullable|string|max:191', 'seo_description' => 'nullable|string|max:320'];
        $product = ['product_id' => 'nullable|integer|exists:products,id'];
        $order = ['sort_order' => 'required|integer|min:0|max:100000'];
        $rules = match ($kind) {
            'edukasi' => [...$title, ...$slug, ...$body, ...$seo, 'category' => 'required|in:signal,cara_trading', 'visibility' => 'required|in:public,client', 'status' => 'required|in:draft,published', 'published_at' => 'nullable|date_format:Y-m-d\TH:i'],
            'faq' => [...$product, ...$order, 'question' => 'required|string|max:500', 'answer' => 'required|string|max:20000', 'is_active' => 'required|boolean'],
            'panduan' => [...$title, ...$slug, ...$product, 'summary' => 'nullable|string|max:10000', 'is_active' => 'required|boolean'],
            'halaman' => [...$title, ...$slug, ...$body, ...$seo, 'slug' => ['required', Rule::in(array_keys(Types::PAGES)), Rule::unique('pages', 'slug')->ignore($id)], 'is_published' => 'required|boolean'],
            'testimoni' => [...$product, ...$order, 'display_name' => 'required|string|max:120', 'content' => 'required|string|max:5000', 'rating' => 'nullable|integer|between:1,5', 'source' => 'required|string|max:255', 'consent_record' => 'required|string|max:500', 'consent_confirmed' => 'required|boolean', 'is_visible' => 'required|boolean', 'published_at' => 'nullable|date_format:Y-m-d\TH:i'],
            'pengumuman' => [...$title, ...$body, 'audience' => 'required|in:all,clients,ib_clients', 'starts_at' => 'nullable|date_format:Y-m-d\TH:i', 'ends_at' => 'nullable|date_format:Y-m-d\TH:i', 'is_active' => 'required|boolean'],
        };
        foreach (['product_id', 'rating', 'published_at', 'starts_at', 'ends_at', 'seo_title', 'seo_description'] as $key) {
            if (($input[$key] ?? null) === '') {
                $input[$key] = null;
            }
        }
        $data = Validator::make($input, $rules)->validate();
        if ($kind === 'testimoni' && $data['is_visible'] && ! $data['consent_confirmed']) {
            throw ValidationException::withMessages(['consent_confirmed' => 'Periksa izin publikasi dari sumber nyata sebelum menampilkan testimoni.']);
        }unset($data['consent_confirmed']);
        foreach (['body', 'answer', 'summary'] as $key) {
            if (isset($data[$key])) {
                $data[$key] = SafeHtml::clean($data[$key]);
                if (in_array($key, ['body', 'answer']) && trim(strip_tags($data[$key] ?? '')) === '') {
                    throw ValidationException::withMessages([$key => 'Isi tidak boleh kosong.']);
                }
            }
        }
        foreach (['published_at', 'starts_at', 'ends_at'] as $key) {
            if (array_key_exists($key, $data)) {
                $data[$key] = $this->time($data[$key]);
            }
        }
        if (in_array($kind, ['edukasi', 'testimoni'], true) && ($data['status'] ?? '') === 'published' && ! $data['published_at']) {
            $data['published_at'] = now();
        }
        if ($kind === 'testimoni' && $data['is_visible'] && ! $data['published_at']) {
            $data['published_at'] = now();
        }
        if ($kind === 'pengumuman' && $data['starts_at'] && $data['ends_at'] && $data['ends_at']->lte($data['starts_at'])) {
            throw ValidationException::withMessages(['ends_at' => 'Tanggal selesai harus setelah mulai.']);
        }
        if ($kind === 'panduan') {
            Validator::make(['steps' => $steps], ['steps' => 'array|max:50', 'steps.*.id' => 'nullable|integer', 'steps.*.body' => 'required|string|max:10000', 'steps.*.alt_text' => 'nullable|string|max:255'])->validate();
            if ($data['is_active'] && count($steps) === 0) {
                throw ValidationException::withMessages(['steps' => 'Tambahkan langkah sebelum mengaktifkan panduan.']);
            }
        }
        $paths = [];
        try {
            $path = $image ? app(ProductAssets::class)->image($image) : null;
            if ($path) {
                $paths[] = $path;
            }
            $stepPaths = [];
            foreach ($stepUploads as $index => $file) {
                if (! isset($steps[$index]) || ! $file instanceof UploadedFile) {
                    throw ValidationException::withMessages(['steps' => 'Unggahan langkah tidak valid.']);
                }$stepPaths[$index] = app(ProductAssets::class)->image($file);
                $paths[] = $stepPaths[$index];
            }

            return DB::transaction(function () use ($kind, $id, $model, $data, $path, $steps, $stepPaths, $admin) {
                $row = $id ? $model::whereKey($id)->lockForUpdate()->firstOrFail() : new $model;
                $before = hash('sha256', json_encode($row->getAttributes(), JSON_THROW_ON_ERROR));
                $row->forceFill($data);
                if ($kind === 'pengumuman' && ! $row->exists) {
                    $row->created_by = $admin->id;
                }
                if ($path) {
                    abort_unless(in_array($kind, ['edukasi', 'testimoni'], true), 422);
                    $row->{$kind === 'edukasi' ? 'cover_path' : 'photo_path'} = $path;
                }
                $row->save();
                if ($kind === 'panduan') {
                    $old = $row->steps()->lockForUpdate()->get()->keyBy('id');
                    $rebuilt = [];
                    foreach ($steps as $i => $step) {
                        $previous = isset($step['id']) ? $old->get($step['id']) : null;
                        if (isset($step['id']) && ! $previous) {
                            throw ValidationException::withMessages(['steps' => 'Langkah bukan milik panduan ini.']);
                        }
                        if (trim(strip_tags(SafeHtml::clean($step['body']) ?? '')) === '') {
                            throw ValidationException::withMessages(['steps' => 'Isi langkah tidak boleh kosong setelah sanitasi.']);
                        }
                        $rebuilt[] = ['step_no' => $i + 1, 'body' => SafeHtml::clean($step['body']), 'alt_text' => $step['alt_text'] ?? null, 'image_path' => $stepPaths[$i] ?? $previous?->image_path];
                    }
                    $row->steps()->delete();
                    foreach ($rebuilt as $step) {
                        (new GuideStep)->forceFill(['guide_id' => $row->id, ...$step])->save();
                    }
                }
                Audit::record($admin, 'content.saved', $row, ['content_changed' => true, 'before_sha256' => $before, 'after_sha256' => hash('sha256', json_encode($row->getAttributes(), JSON_THROW_ON_ERROR))]);

                return $row;
            }, 3);
        } catch (\Throwable $e) {
            foreach ($paths as $p) {
                Storage::disk('local')->delete($p);
            }throw $e;
        }
    }

    public function articleImage(int $id, UploadedFile $file, string $alt): ArticleImage
    {
        $admin = Finance::admin();
        Validator::make(['alt' => $alt], ['alt' => 'required|string|max:255'])->validate();
        $path = app(ProductAssets::class)->image($file);
        try {
            return DB::transaction(function () use ($id, $path, $alt, $admin) {
                $article = Article::whereKey($id)->lockForUpdate()->firstOrFail();
                $row = new ArticleImage;
                $row->forceFill(['article_id' => $id, 'path' => $path, 'alt_text' => $alt, 'bytes' => Storage::disk('local')->size($path)])->save();
                Audit::record($admin, 'content.image_added', $article, ['resource_id' => $row->id]);

                return $row;
            }, 3);
        } catch (\Throwable $e) {
            Storage::disk('local')->delete($path);
            throw $e;
        }
    }
}
