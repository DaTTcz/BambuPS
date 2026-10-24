<?php

namespace App\Livewire;

use App\Models\File;
use App\Models\PrintJob;
use App\Models\Printer;
use Illuminate\Support\Facades\Storage;
use Livewire\Attributes\Url;
use Livewire\Component;
use Livewire\WithPagination;

class PrintHistory extends Component
{
    use WithPagination;

    #[Url(as: 'tiskarna')]
    public string $printerFilter = '';

    #[Url(as: 'stav')]
    public string $statusFilter = '';

    #[Url(as: 'obdobi')]
    public string $period = '30';   // dny, 'all' = vše

    #[Url(as: 'q')]
    public string $search = '';

    #[Url(as: 'soubor')]
    public string $fileFilter = '';

    public ?int $confirmDeleteId = null;

    public function updating($name): void
    {
        if (in_array($name, ['printerFilter', 'statusFilter', 'period', 'search', 'fileFilter'], true)) {
            $this->resetPage();
        }
    }

    /** Základní dotaz se všemi filtry (bez "queued" - ty ještě nejsou tisk) */
    private function filteredQuery()
    {
        return PrintJob::query()
            ->where('status', '!=', PrintJob::STATUS_QUEUED)
            ->when($this->printerFilter !== '', fn ($q) => $q->where('printer_id', (int) $this->printerFilter))
            ->when($this->statusFilter !== '', fn ($q) => $q->where('status', $this->statusFilter))
            ->when(!in_array($this->period, ['', 'all'], true), fn ($q) => $q->where('started_at', '>=', now()->subDays((int) $this->period)))
            ->when($this->search !== '', fn ($q) => $q->where('name', 'like', '%' . $this->search . '%'))
            ->when($this->fileFilter !== '', fn ($q) => $q->where('file_id', (int) $this->fileFilter));
    }

    /** Soubor, podle kterého se právě filtruje (odkaz z detailu souboru) */
    public function getFilteredFileProperty(): ?File
    {
        return $this->fileFilter !== ''
            ? File::where('id', (int) $this->fileFilter)->where('user_id', auth()->id())->first()
            : null;
    }

    public function getPrintersProperty()
    {
        return Printer::orderBy('name')->get(['id', 'name']);
    }

    public function getStatsProperty(): array
    {
        $jobs = $this->filteredQuery()->get(['status', 'duration_seconds', 'started_at', 'filament_g', 'progress']);

        $closed   = $jobs->whereIn('status', [PrintJob::STATUS_FINISHED, PrintJob::STATUS_FAILED, PrintJob::STATUS_CANCELLED]);
        $finished = $jobs->where('status', PrintJob::STATUS_FINISHED)->count();

        $seconds = $jobs->sum(fn ($j) => $j->duration ?? 0);
        $grams   = $jobs->sum(fn ($j) => $j->filament_used_g ?? 0);

        return [
            'count'        => $jobs->count(),
            'finished'     => $finished,
            'failed'       => $jobs->where('status', PrintJob::STATUS_FAILED)->count(),
            'cancelled'    => $jobs->where('status', PrintJob::STATUS_CANCELLED)->count(),
            'success_rate' => $closed->count() > 0 ? round($finished / $closed->count() * 100) : null,
            'hours'        => round($seconds / 3600, 1),
            'filament_kg'  => round($grams / 1000, 2),
        ];
    }

    public function confirmDelete(int $id): void
    {
        $this->confirmDeleteId = $id;
    }

    public function cancelDelete(): void
    {
        $this->confirmDeleteId = null;
    }

    public function deleteJob(): void
    {
        $job = PrintJob::find($this->confirmDeleteId);
        if ($job) {
            if ($job->snapshot_path) {
                Storage::disk('local')->delete($job->snapshot_path);
            }
            $job->delete();
            $this->dispatch('toast', type: 'success', message: 'Záznam smazán z historie.');
        }
        $this->confirmDeleteId = null;
    }

    public function render()
    {
        return view('livewire.print-history', [
            'jobs' => $this->filteredQuery()
                ->with(['file', 'printer'])
                ->orderByDesc('started_at')
                ->orderByDesc('id')
                ->paginate(25),
        ]);
    }
}
