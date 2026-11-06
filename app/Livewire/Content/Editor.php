<?php

namespace App\Livewire\Content;

use App\Actions\Content\PublishContent;
use App\Actions\Content\Types;
use App\Actions\Rebates\Finance;
use App\Models\ArticleImage;
use Livewire\Attributes\Locked;
use Livewire\Component;
use Livewire\WithFileUploads;
use Livewire\WithPagination;

class Editor extends Component
{
    use WithFileUploads,WithPagination;

    #[Locked]
    public string $kind;

    #[Locked]
    public ?int $recordId = null;

    #[Locked]
    public bool $editing = false;

    #[Locked]
    public bool $confirming = false;

    public function preview(): void
    {
        Finance::admin(false);
        $this->confirming = true;
    }

    public function cancelConfirmation(): void
    {
        Finance::admin(false);
        $this->confirming = false;
    }

    public array $form = [];

    public array $steps = [];

    public $image;

    public $inlineImage;

    public string $alt = '';

    public array $stepUploads = [];

    public function mount(string $kind, string $mode = 'index', ?int $id = null): void
    {
        Finance::admin(false);
        Types::model($kind);
        $this->kind = $kind;
        if ($mode !== 'index') {
            $this->open($id);
        }
    }

    public function open(?int $id = null): void
    {
        Finance::admin(false);
        $this->resetValidation();
        $this->reset('image', 'inlineImage', 'stepUploads', 'steps');
        $this->recordId = $id;
        $this->form = Types::defaults($this->kind);
        if ($id) {
            $model = Types::model($this->kind);
            $row = $model::findOrFail($id);
            foreach ($this->form as $key => $value) {
                if ($key === 'consent_confirmed') {
                    continue;
                }$v = $row->getAttribute($key);
                $this->form[$key] = $v instanceof \BackedEnum ? $v->value : ($v instanceof \DateTimeInterface ? $v->timezone('Asia/Jakarta')->format('Y-m-d\TH:i') : ($v ?? ''));
            }if ($this->kind === 'panduan') {
                $this->steps = $row->steps()->orderBy('step_no')->get()->map(fn ($s) => ['id' => $s->id, 'body' => $s->body, 'alt_text' => $s->alt_text ?? ''])->all();
            }
        }$this->editing = true;
    }

    public function addStep(): void
    {
        Finance::admin(false);
        abort_unless($this->kind === 'panduan' && count($this->steps) < 50, 422);
        $this->steps[] = ['body' => '', 'alt_text' => ''];
    }

    public function removeStep(int $index): void
    {
        Finance::admin(false);
        unset($this->steps[$index],$this->stepUploads[$index]);
        $this->steps = array_values($this->steps);
        $this->stepUploads = [];
    }

    public function save(PublishContent $service): void
    {
        $row = $service->save($this->kind, $this->recordId, $this->form, $this->image, $this->steps, $this->stepUploads);
        $this->confirming = false;
        $this->recordId = $row->id;
        $this->reset('image', 'stepUploads');
        $this->resetValidation();
        session()->flash('status', 'Konten tersimpan. Publikasi mengikuti status, izin dan jadwal.');
        $this->open($row->id);
    }

    public function uploadInline(PublishContent $service): void
    {
        abort_unless($this->kind === 'edukasi' && $this->recordId && $this->inlineImage, 422);
        $service->articleImage($this->recordId, $this->inlineImage, $this->alt);
        $this->reset('inlineImage', 'alt');
        $this->dispatch('upload-validated', fields: ['inlineImage']);
        session()->flash('status', 'Gambar isi tersimpan.');
    }

    public function close(): void
    {
        Finance::admin(false);
        $this->editing = false;
        $this->resetValidation();
    }

    public function render()
    {
        Finance::admin(false);
        $model = Types::model($this->kind);

        return view('livewire.content.editor', ['fields' => Types::fields($this->kind), 'records' => $model::latest('id')->paginate(20), 'images' => $this->kind === 'edukasi' && $this->recordId ? ArticleImage::where('article_id', $this->recordId)->get() : []])->layout('components.layouts.portal', ['area' => 'admin', 'title' => 'Kelola '.ucfirst($this->kind)]);
    }
}
