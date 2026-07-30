<?php

namespace App\Livewire;

use App\Models\File;
use Livewire\Component;

class FileDetail extends Component
{
    public ?int $fileId = null;
    public bool $show = false;

    protected $listeners = ['showFileDetail' => 'open'];

    public function open(int $fileId): void
    {
        $this->fileId = $fileId;
        $this->show   = true;
    }

    public function close(): void
    {
        $this->show   = false;
        $this->fileId = null;
    }

    public function render()
    {
        $file   = null;
        $plates = [];

        if ($this->fileId) {
            $file   = File::where('id', $this->fileId)->where('user_id', auth()->id())->first();
            $plates = $file?->metadata['plates'] ?? [];
        }

        return view('livewire.file-detail', compact('file', 'plates'));
    }
}
