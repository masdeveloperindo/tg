<?php

namespace App\Actions\Products;

use App\Models\Faq;
use App\Models\Guide;
use App\Models\GuideStep;
use App\Models\Product;
use App\Models\ProductMedia;
use App\Models\ProductPerformance;
use App\Models\ProductVersion;
use App\Models\User;
use App\Support\Audit;
use App\Support\SafeHtml;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

final class ManageProducts
{
    public function save(?int $id, array $input): Product
    {
        Gate::authorize($id ? 'update' : 'create', $id ? Product::findOrFail($id) : Product::class);
        $data = Validator::make($this->normalized($input), [
            'name' => 'required|string|max:180', 'slug' => ['required', 'string', 'max:191', 'regex:/^[a-z0-9]+(?:-[a-z0-9]+)*$/', Rule::unique('products', 'slug')->ignore($id)],
            'type' => ['required', Rule::in(['ea', 'indicator'])], 'description' => 'nullable|string|max:100000',
            'base_price_idr' => ['required', 'string', 'regex:/^\d{1,18}(?:\.\d{1,2})?$/'], 'minimum_price_idr' => ['required', 'string', 'regex:/^\d{1,18}(?:\.\d{1,2})?$/'],
            'seo_title' => 'nullable|string|max:191', 'seo_description' => 'nullable|string|max:320',
        ])->validate();
        if (bccomp($data['minimum_price_idr'], $data['base_price_idr'], 2) > 0) {
            throw ValidationException::withMessages(['minimum_price_idr' => 'Harga minimum tidak boleh melebihi harga awal.']);
        }
        $data['description'] = SafeHtml::clean($data['description'] ?? null);

        return DB::transaction(function () use ($id, $data): Product {
            $product = $id ? Product::query()->lockForUpdate()->findOrFail($id) : new Product;
            Gate::authorize($id ? 'update' : 'create', $id ? $product : Product::class);
            if ($id && $product->status->value === 'published' && $product->slug !== $data['slug']) {
                throw ValidationException::withMessages(['slug' => 'Slug produk yang telah dipublikasikan tidak dapat diubah.']);
            }
            $product->forceFill([...$data, 'sale_enabled' => false]);
            if (! $id) {
                $product->forceFill(['status' => 'draft']);
            }
            $product->save();
            $this->audit($product, $id ? 'product.updated' : 'product.created', ['content_changed' => true]);

            return $product;
        });
    }

    public function status(int $id, string $status): void
    {
        abort_unless(in_array($status, ['draft', 'published', 'archived'], true), 422);
        DB::transaction(function () use ($id, $status): void {
            $product = $this->locked($id);
            if ($status === 'published' && (! $product->description || ! trim(strip_tags($product->description)))) {
                throw ValidationException::withMessages(['product' => 'Isi deskripsi sebelum mempublikasikan produk.']);
            }
            $product->forceFill(['status' => $status, 'sale_enabled' => false])->save();
            $this->audit($product, 'product.status_changed', ['status' => $status]);
        });
    }

    public function version(int $id, UploadedFile $file, array $input): void
    {
        Gate::authorize('update', Product::findOrFail($id));
        $data = Validator::make($this->normalized($input), [
            'version' => ['required', 'string', 'max:40', 'regex:/^[A-Za-z0-9][A-Za-z0-9._-]*$/', Rule::unique('product_versions', 'version')->where('product_id', $id)],
            'changelog' => 'nullable|string|max:10000', 'min_supported_version' => ['nullable', 'string', 'max:40', 'regex:/^[A-Za-z0-9][A-Za-z0-9._-]*$/'],
        ])->validate();
        $asset = app(ProductAssets::class)->ex5($file);
        $this->withAsset($asset['path'], function () use ($id, $asset, $data): void {
            $product = $this->locked($id);
            (new ProductVersion)->forceFill([...$data, ...$asset, 'product_id' => $id, 'is_active' => false])->save();
            $this->audit($product, 'product.version_uploaded', ['version_count' => $product->versions()->count()]);
        });
    }

    public function versionDetails(int $id, int $versionId, array $input): void
    {
        Gate::authorize('update', Product::findOrFail($id));
        $data = Validator::make($this->normalized($input), ['changelog' => 'nullable|string|max:10000', 'min_supported_version' => ['nullable', 'string', 'max:40', 'regex:/^[A-Za-z0-9][A-Za-z0-9._-]*$/']])->validate();
        DB::transaction(function () use ($id, $versionId, $data): void {
            $product = $this->locked($id);
            $version = $product->versions()->whereKey($versionId)->firstOrFail();
            $version->forceFill($data)->save();
            $this->audit($product, 'product.version_updated', ['resource_id' => $versionId]);
        });
    }

    public function mediaDetails(int $id, int $mediaId, array $input): void
    {
        Gate::authorize('update', Product::findOrFail($id));
        $data = Validator::make($this->normalized($input), [
            'section' => ['required', Rule::in(['preview', 'tutorial'])], 'caption' => 'nullable|string|max:255',
            'alt_text' => 'required|string|max:255', 'sort_order' => 'required|integer|min:0|max:10000',
        ])->validate();
        DB::transaction(function () use ($id, $mediaId, $data): void {
            $product = $this->locked($id);
            $media = $product->media()->whereKey($mediaId)->firstOrFail();
            $media->forceFill($data)->save();
            $this->audit($product, 'product.media_changed', ['media_count' => $product->media()->count(), 'resource_id' => $mediaId]);
        });
    }

    public function activateVersion(int $id, int $versionId): void
    {
        DB::transaction(function () use ($id, $versionId): void {
            $product = $this->locked($id);
            $version = $product->versions()->whereKey($versionId)->firstOrFail();
            abort_unless(Storage::disk('local')->exists($version->path), 409);
            $product->versions()->where('is_active', true)->update(['is_active' => false]);
            $version->forceFill(['is_active' => true, 'released_at' => $version->released_at ?? now()])->save();
            $this->audit($product, 'product.version_activated', ['version_count' => $product->versions()->count()]);
        });
    }

    public function media(int $id, array $input, ?UploadedFile $image): void
    {
        Gate::authorize('update', Product::findOrFail($id));
        $data = Validator::make($this->normalized($input), [
            'section' => ['required', Rule::in(['preview', 'tutorial'])], 'caption' => 'nullable|string|max:255',
            'alt_text' => 'required|string|max:255', 'sort_order' => 'required|integer|min:0|max:10000',
            'external_url' => 'nullable|url:https|max:1000',
        ])->validate();
        $url = $data['external_url'] ?? null;
        if (($image === null) === blank($url)) {
            throw ValidationException::withMessages(['image' => 'Pilih satu sumber: gambar atau tautan video.']);
        }
        if ($url && (! in_array(strtolower((string) parse_url($url, PHP_URL_HOST)), ['www.youtube.com', 'youtube.com', 'youtu.be', 'vimeo.com', 'www.vimeo.com'], true)
            || parse_url($url, PHP_URL_USER) || parse_url($url, PHP_URL_PASS) || parse_url($url, PHP_URL_PORT))) {
            throw ValidationException::withMessages(['external_url' => 'Gunakan HTTPS YouTube atau Vimeo tanpa credential/port.']);
        }
        $path = $image ? app(ProductAssets::class)->image($image) : null;
        $this->withAsset($path, function () use ($id, $data, $path, $url): void {
            $product = $this->locked($id);
            (new ProductMedia)->forceFill([...$data, 'external_url' => $url ?: null, 'disk_path' => $path, 'media_type' => $path ? 'image' : 'video_link', 'product_id' => $id])->save();
            $this->audit($product, 'product.media_changed', ['media_count' => $product->media()->count()]);
        });
    }

    public function faq(int $id, array $input): void
    {
        Gate::authorize('update', Product::findOrFail($id));
        $data = Validator::make($this->normalized($input), ['id' => ['nullable', 'integer', Rule::exists('faqs', 'id')->where('product_id', $id)], 'question' => 'required|string|max:500', 'answer' => 'required|string|max:20000', 'sort_order' => 'required|integer|min:0|max:10000'])->validate();
        $faqId = $data['id'] ?? null;
        unset($data['id']);
        $data['answer'] = SafeHtml::clean($data['answer']);
        if (blank(strip_tags($data['answer'] ?? ''))) {
            throw ValidationException::withMessages(['answer' => 'Isi jawaban yang dapat dibaca.']);
        }
        DB::transaction(function () use ($id, $data, $faqId): void {
            $product = $this->locked($id);
            $faq = $faqId ? $product->faqs()->whereKey($faqId)->firstOrFail() : new Faq;
            $product->faqs()->save($faq->forceFill([...$data, 'is_active' => true]));
            $this->audit($product, 'product.faq_changed', ['faq_count' => $product->faqs()->count()]);
        });
    }

    public function performance(int $id, array $input, ?UploadedFile $report = null, ?UploadedFile $equity = null): void
    {
        Gate::authorize('update', Product::findOrFail($id));
        $rules = [
            'id' => ['nullable', 'integer', Rule::exists('product_performances', 'id')->where('product_id', $id)],
            'version_id' => ['nullable', 'integer', Rule::exists('product_versions', 'id')->where('product_id', $id)],
            'test_type' => ['required', Rule::in(['backtest', 'forward_test'])], 'account_environment' => ['required', Rule::in(['demo', 'real'])],
            'starts_on' => 'required|date_format:Y-m-d', 'ends_on' => 'required|date_format:Y-m-d|after_or_equal:starts_on',
            'symbol' => 'required|string|max:30', 'timeframe' => 'required|string|max:12', 'broker_name' => 'nullable|string|max:120',
            'initial_capital' => ['nullable', 'string', 'regex:/^\d{1,18}(?:\.\d{1,2})?$/'],
            'spread' => 'nullable|string|max:90', 'commission' => 'nullable|string|max:90', 'leverage' => 'nullable|string|max:20',
            'total_trades' => 'nullable|integer|min:0|max:4294967295', 'methodology_note' => 'required|string|max:20000',
            'net_profit_pct' => ['nullable', 'string', 'regex:/^-?\d{1,8}(?:\.\d{1,4})?$/'],
            'max_drawdown_pct' => ['nullable', 'string', 'regex:/^\d{1,3}(?:\.\d{1,4})?$/', 'numeric', 'between:0,100'],
            'win_rate_pct' => ['nullable', 'string', 'regex:/^\d{1,3}(?:\.\d{1,4})?$/', 'numeric', 'between:0,100'],
            'profit_factor' => ['nullable', 'string', 'regex:/^\d{1,10}(?:\.\d{1,6})?$/'],
        ];
        $data = Validator::make($this->normalized($input), $rules)->validate();
        $performanceId = $data['id'] ?? null;
        unset($data['id']);
        $reportPath = null;
        $equityPath = null;
        try {
            if ($report) {
                if (! $report->isValid() || $report->getSize() > 5242880 || strtolower($report->getClientOriginalExtension()) !== 'pdf' || $report->getMimeType() !== 'application/pdf' || ! str_starts_with(file_get_contents($report->getRealPath(), false, null, 0, 5), '%PDF-')) {
                    throw ValidationException::withMessages(['reportFile' => 'Laporan asli harus PDF maksimal 5 MiB.']);
                }
                $reportPath = $report->storeAs('products/reports', Str::uuid().'.pdf', 'local');
                if (! $reportPath) {
                    throw new \RuntimeException('Private performance report write failed.');
                }
            }
            $equityPath = $equity ? app(ProductAssets::class)->image($equity) : null;
            DB::transaction(function () use ($id, $data, $reportPath, $equityPath, $performanceId): void {
                $product = $this->locked($id);
                $performance = $performanceId ? $product->performances()->whereKey($performanceId)->firstOrFail() : new ProductPerformance;
                $beforeHash = hash('sha256', json_encode($performance->getAttributes(), JSON_THROW_ON_ERROR));
                $performance->forceFill([...$data, 'product_id' => $id, 'updated_by' => Auth::id(), 'report_path' => $reportPath ?: $performance->report_path, 'equity_image_path' => $equityPath ?: $performance->equity_image_path])->save();
                $this->audit($product, 'product.performance_changed', ['performance_count' => $product->performances()->count(), 'resource_id' => $performance->id, 'before_sha256' => $beforeHash, 'after_sha256' => hash('sha256', json_encode($performance->getAttributes(), JSON_THROW_ON_ERROR))]);
            });
        } catch (\Throwable $error) {
            foreach ([$reportPath, $equityPath] as $path) {
                if ($path) {
                    Storage::disk('local')->delete($path);
                }
            }
            throw $error;
        }
    }

    public function tutorial(int $id, array $steps): void
    {
        Gate::authorize('update', Product::findOrFail($id));
        $data = Validator::make(['steps' => $steps], [
            'steps' => 'present|array|max:30', 'steps.*.body' => 'required|string|max:10000',
            'steps.*.media_id' => ['nullable', 'integer', Rule::exists('product_media', 'id')->where('product_id', $id)->where('media_type', 'image')],
        ])->validate();
        DB::transaction(function () use ($id, $data): void {
            $product = $this->locked($id);
            $guide = $product->guides()->orderBy('id')->first() ?? new Guide;
            $guide->forceFill(['product_id' => $id, 'title' => mb_substr('Cara menggunakan '.$product->name, 0, 191), 'slug' => 'produk-'.$id.'-panduan', 'is_active' => true])->save();
            $guide->steps()->delete();
            foreach ($data['steps'] as $index => $step) {
                $body = SafeHtml::clean($step['body']);
                if (blank(strip_tags($body ?? ''))) {
                    throw ValidationException::withMessages(['steps' => 'Setiap langkah memerlukan petunjuk yang dapat dibaca.']);
                }
                $media = ! empty($step['media_id']) ? $product->media()->whereKey($step['media_id'])->firstOrFail() : null;
                $guide->steps()->save((new GuideStep)->forceFill(['step_no' => $index + 1, 'body' => $body, 'image_path' => $media?->disk_path, 'alt_text' => $media?->alt_text]));
            }
            $this->audit($product, 'product.tutorial_changed', ['step_count' => count($data['steps'])]);
        });
    }

    public function remove(int $id, string $kind, int $childId): void
    {
        DB::transaction(function () use ($id, $kind, $childId): void {
            $product = $this->locked($id);
            abort_unless(in_array($kind, ['media', 'faqs', 'performances'], true), 422);
            $child = $product->{$kind}()->whereKey($childId)->firstOrFail();
            // Keep original proof files for audit; only detach the editable presentation record.
            $child->delete();
            $this->audit($product, 'product.content_removed', ['content_changed' => true, 'resource_id' => $childId, 'before_sha256' => hash('sha256', json_encode($child->getAttributes(), JSON_THROW_ON_ERROR))]);
        });
    }

    private function normalized(array $input): array
    {
        return array_map(fn ($value) => is_string($value) && trim($value) === '' ? null : $value, $input);
    }

    private function locked(int $id): Product
    {
        $product = Product::query()->lockForUpdate()->findOrFail($id);
        Gate::authorize('update', $product);

        return $product;
    }

    private function withAsset(?string $path, \Closure $work): void
    {
        try {
            DB::transaction($work);
        } catch (\Throwable $error) {
            if ($path) {
                Storage::disk('local')->delete($path);
            } throw $error;
        }
    }

    private function audit(Product $product, string $action, array $summary): void
    {
        $actor = Auth::user();
        abort_unless($actor instanceof User, 403);
        Audit::record($actor, $action, $product, $summary);
    }
}
