<?php

namespace App\Livewire\Products;

use App\Actions\Orders\CalendarPricing;
use App\Actions\Orders\CheckoutSettings;
use App\Actions\Products\ManageProducts;
use App\Models\Product;
use Illuminate\Contracts\View\View;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\Gate;
use Livewire\Attributes\Locked;
use Livewire\Component;
use Livewire\WithFileUploads;

class Editor extends Component
{
    use WithFileUploads;

    #[Locked]
    public ?int $productId = null;

    public array $form = ['name' => '', 'slug' => '', 'type' => 'ea', 'description' => '', 'base_price_idr' => '', 'minimum_price_idr' => '0', 'seo_title' => '', 'seo_description' => ''];

    public array $mediaForm = ['section' => 'preview', 'caption' => '', 'alt_text' => '', 'sort_order' => 0, 'external_url' => ''];

    public array $versionForm = ['version' => '', 'changelog' => '', 'min_supported_version' => ''];

    public array $faqForm = ['question' => '', 'answer' => '', 'sort_order' => 0];

    public array $performanceForm = [];

    public array $steps = [];

    public array $calendarPrices = [];

    public $image;

    public $versionFile;

    public $reportFile;

    public $equityImage;

    public ?string $confirmation = null;

    public ?int $confirmationId = null;

    public const PERFORMANCE_FIELDS = ['id', 'version_id', 'test_type', 'account_environment', 'starts_on', 'ends_on', 'symbol', 'timeframe', 'broker_name', 'initial_capital', 'spread', 'commission', 'leverage', 'net_profit_pct', 'max_drawdown_pct', 'win_rate_pct', 'profit_factor', 'total_trades', 'methodology_note'];

    public function mount(?int $id = null): void
    {
        Gate::authorize('viewAny', Product::class);
        $this->productId = $id;
        if ($id !== null) {
            $product = $this->product();
            $this->form = Arr::only($product->getAttributes(), array_keys($this->form));
            $guide = $product->guides()->orderBy('id')->first();
            foreach ($guide?->steps()->orderBy('step_no')->get() ?? [] as $step) {
                $this->steps[] = ['body' => $step->body, 'media_id' => $product->media()->where('disk_path', $step->image_path)->value('id')];
            }
        }
    }

    public function simulateCalendar(): void
    {
        $product = $this->product();
        $rules = app(CheckoutSettings::class)->pricing();
        $prices = app(CalendarPricing::class)->simulate($product, $rules, now('Asia/Jakarta')->year);
        $this->calendarPrices = array_map(fn ($row) => $row['amount_idr'], $prices);
    }

    public function save(ManageProducts $products): void
    {
        $created = $this->productId === null;
        $product = $products->save($this->productId, $this->form);
        $this->productId = $product->id;
        session()->flash('status', 'Produk tersimpan. Penjualan tetap nonaktif.');
        if ($created) {
            $this->redirectRoute('admin.produk.id.edit', ['id' => $product->id]);
        }
    }

    public function uploadMedia(ManageProducts $products): void
    {
        if (isset($this->mediaForm['id'])) {
            $products->mediaDetails($this->product()->id, (int) $this->mediaForm['id'], $this->mediaForm);
        } else {
            $products->media($this->product()->id, $this->mediaForm, $this->image);
        }
        $this->reset('image', 'mediaForm');
        $this->dispatch('upload-validated', fields: ['image']);
        session()->flash('status', 'Media tersimpan.');
    }

    public function uploadVersion(ManageProducts $products): void
    {
        if (isset($this->versionForm['id'])) {
            $products->versionDetails($this->product()->id, (int) $this->versionForm['id'], $this->versionForm);
            $this->reset('versionForm');
            session()->flash('status', 'Keterangan versi tersimpan.');

            return;
        }
        $this->validate(['versionFile' => 'required|file|max:20480']);
        $products->version($this->product()->id, $this->versionFile, $this->versionForm);
        $this->reset('versionFile', 'versionForm');
        $this->dispatch('upload-validated', fields: ['versionFile']);
        session()->flash('status', 'File versi tersimpan privat. Aktifkan melalui konfirmasi.');
    }

    public function editMedia(int $id): void
    {
        $media = $this->product()->media()->whereKey($id)->firstOrFail();
        $this->reset('image');
        $this->mediaForm = Arr::only($media->getAttributes(), ['id', 'section', 'caption', 'alt_text', 'sort_order']);
    }

    public function editVersion(int $id): void
    {
        $version = $this->product()->versions()->whereKey($id)->firstOrFail();
        $this->reset('versionFile');
        $this->versionForm = Arr::only($version->getAttributes(), ['id', 'version', 'changelog', 'min_supported_version']);
    }

    public function cancelContentEdit(): void
    {
        $this->product();
        $this->reset('mediaForm', 'versionForm', 'faqForm', 'performanceForm', 'image', 'versionFile', 'reportFile', 'equityImage');
    }

    public function saveFaq(ManageProducts $products): void
    {
        $products->faq($this->product()->id, $this->faqForm);
        $this->reset('faqForm');
        session()->flash('status', 'FAQ tersimpan.');
    }

    public function editFaq(int $id): void
    {
        $faq = $this->product()->faqs()->whereKey($id)->firstOrFail();
        $this->faqForm = Arr::only($faq->getAttributes(), ['id', 'question', 'answer', 'sort_order']);
    }

    public function savePerformance(ManageProducts $products): void
    {
        $products->performance($this->product()->id, $this->performanceForm, $this->reportFile, $this->equityImage);
        $this->reset('performanceForm', 'reportFile', 'equityImage');
        $this->dispatch('upload-validated', fields: ['reportFile', 'equityImage']);
        session()->flash('status', 'Data pengujian tersimpan dengan waktu dan editor.');
    }

    public function editPerformance(int $id): void
    {
        $performance = $this->product()->performances()->whereKey($id)->firstOrFail();
        $this->performanceForm = Arr::only($performance->getAttributes(), self::PERFORMANCE_FIELDS);
    }

    public function saveTutorial(ManageProducts $products): void
    {
        $products->tutorial($this->product()->id, $this->steps);
        session()->flash('status', 'Langkah panduan tersimpan.');
    }

    public function addStep(): void
    {
        $this->product();
        if (count($this->steps) < 30) {
            $this->steps[] = ['body' => '', 'media_id' => null];
        }
    }

    public function removeStep(int $index): void
    {
        $this->product();
        unset($this->steps[$index]);
        $this->steps = array_values($this->steps);
    }

    public function confirm(string $action, ?int $id = null): void
    {
        $product = $this->product();
        abort_unless(in_array($action, ['published', 'draft', 'archived', 'activate', 'media', 'faqs', 'performances'], true), 422);
        if (in_array($action, ['media', 'faqs', 'performances'], true)) {
            $product->{$action}()->whereKey($id)->firstOrFail();
        }
        if ($action === 'activate') {
            $product->versions()->whereKey($id)->firstOrFail();
        }
        $this->confirmation = $action;
        $this->confirmationId = $id;
    }

    public function cancelConfirmation(): void
    {
        $this->product();
        $this->reset('confirmation', 'confirmationId');
    }

    public function execute(ManageProducts $products): void
    {
        $product = $this->product();
        if (in_array($this->confirmation, ['published', 'draft', 'archived'], true)) {
            $products->status($product->id, $this->confirmation);
        } elseif ($this->confirmation === 'activate' && $this->confirmationId !== null) {
            $products->activateVersion($product->id, $this->confirmationId);
        } elseif (in_array($this->confirmation, ['media', 'faqs', 'performances'], true) && $this->confirmationId !== null) {
            $products->remove($product->id, $this->confirmation, $this->confirmationId);
        } else {
            abort(422);
        }
        $this->reset('confirmation', 'confirmationId');
        session()->flash('status', 'Perubahan dikonfirmasi dan tersimpan.');
    }

    private function product(): Product
    {
        abort_if($this->productId === null, 404);
        $product = Product::findOrFail($this->productId);
        Gate::authorize('update', $product);

        return $product;
    }

    public function render(): View
    {
        Gate::authorize('viewAny', Product::class);
        $product = $this->productId ? $this->product()->load(['media', 'versions', 'performances', 'faqs']) : null;

        return view('livewire.products.editor', compact('product'))->layout('components.layouts.portal', ['area' => 'admin', 'title' => $product ? 'Edit produk' : 'Tambah produk']);
    }
}
