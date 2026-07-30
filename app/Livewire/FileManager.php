<?php

namespace App\Livewire;

use App\Models\File;
use App\Models\Folder;
use App\Services\ThreeMfParserService;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Livewire\Component;
use Livewire\WithFileUploads;
use App\Services\GcodeParserService;

class FileManager extends Component
{
    use WithFileUploads;

    /**
     * Reaguje na 'filesChanged' event z JS (files.blade.php), který se
     * spustí, jakmile SSE stream oznámí nový/změněný soubor. Metoda samotná
     * nic dělat nemusí - jen tím, že proběhne, Livewire komponentu
     * překreslí a getFilesProperty() se přepočítá s čerstvými daty z DB.
     */
    #[\Livewire\Attributes\On('filesChanged')]
    public function onFilesChanged(): void
    {
        //
    }

    public ?int $currentFolderId = null;
    public string $newFolderName = '';
    public bool $showNewFolderModal = false;
    public bool $showUploadModal = false;
    public $uploadedFiles = [];
    public string $renamingType = '';
    public ?int $renamingId = null;
    public string $renamingName = '';
    public bool $showRenameModal = false;
    public ?int $reparsingId = null;
    public ?int $confirmDeleteId = null;
    public string $confirmDeleteName = '';

    // View & řazení & hledání
    public string $viewMode = 'card';
    public string $sortBy   = 'created_at';
    public string $sortDir  = 'desc';
    public string $search   = '';

    // Přesun
    public ?int $movingFileId   = null;
    public ?int $movingFolderId = null;
    public string $movingName   = '';
    public bool $showMoveModal  = false;

    public ?int $confirmDeleteFolderId   = null;
    public string $confirmDeleteFolderName = '';

    public function getCurrentFolderProperty(): ?Folder
    {
        if (!$this->currentFolderId) return null;
        return Folder::find($this->currentFolderId);
    }

    public function getBreadcrumbsProperty(): array
    {
        $breadcrumbs = [];
        $folder = $this->currentFolder;
        while ($folder) {
            array_unshift($breadcrumbs, $folder);
            $folder = $folder->parent;
        }
        return $breadcrumbs;
    }

    public function getFoldersProperty()
    {
        $query = Folder::where('user_id', auth()->id())
            ->where('parent_id', $this->currentFolderId);
        if ($this->search) {
            $query->where('name', 'like', '%' . $this->search . '%');
        }
        return $query->orderBy('name')->get();
    }

    public function getFilesProperty()
    {
        $query = File::where('user_id', auth()->id())
            ->where('folder_id', $this->currentFolderId);
        if ($this->search) {
            $query->where('original_name', 'like', '%' . $this->search . '%');
        }
        return $query->orderBy($this->sortBy, $this->sortDir)->get();
    }

    public function getAllFoldersProperty()
    {
        return Folder::where('user_id', auth()->id())->orderBy('name')->get();
    }

    public function getStatsProperty(): array
    {
        $userId = auth()->id();
        $totalFiles   = File::where('user_id', $userId)->count();
        $totalFolders = Folder::where('user_id', $userId)->count();
        $totalBytes   = File::where('user_id', $userId)->sum('size_bytes');
        $gcodeFiles   = File::where('user_id', $userId)
            ->whereJsonContains('metadata->has_gcode', true)
            ->count();
        return [
            'total_files'   => $totalFiles,
            'total_folders' => $totalFolders,
            'total_size'    => $this->formatBytes($totalBytes),
            'gcode_files'   => $gcodeFiles,
        ];
    }

    private function formatBytes(int $bytes): string
    {
        if ($bytes >= 1073741824) return number_format($bytes / 1073741824, 2) . ' GB';
        if ($bytes >= 1048576)    return number_format($bytes / 1048576, 2) . ' MB';
        if ($bytes >= 1024)       return number_format($bytes / 1024, 2) . ' KB';
        return $bytes . ' B';
    }

    public function mount(): void
    {
        // Výchozí hodnoty – Alpine.js přepíše z localStorage po init
        $this->viewMode = 'card';
        $this->sortBy   = 'created_at';
        $this->sortDir  = 'desc';
    }

    private function savePrefs(): void
    {
        $this->dispatch('save-fm-prefs', [
            'viewMode' => $this->viewMode,
            'sortBy'   => $this->sortBy,
            'sortDir'  => $this->sortDir,
        ]);
    }

    public function setSort(string $column): void
    {
        if ($this->sortBy === $column) {
            $this->sortDir = $this->sortDir === 'asc' ? 'desc' : 'asc';
        } else {
            $this->sortBy  = $column;
            $this->sortDir = 'asc';
        }
        $this->savePrefs();
    }

    public function toggleView(): void
    {
        $this->viewMode = $this->viewMode === 'card' ? 'list' : 'card';
        $this->savePrefs();
    }

    public function openFolder(int $folderId): void
    {
        $this->currentFolderId = $folderId;
        $this->search = '';
    }

    public function goUp(): void
    {
        if ($this->currentFolder) {
            $this->currentFolderId = $this->currentFolder->parent_id;
        }
        $this->search = '';
    }

    public function goToFolder(?int $folderId): void
    {
        $this->currentFolderId = $folderId;
        $this->search = '';
    }

    public function startMove(string $type, int $id, string $name): void
    {
        if ($type === 'file') {
            $this->movingFileId   = $id;
            $this->movingFolderId = null;
        } else {
            $this->movingFolderId = $id;
            $this->movingFileId   = null;
        }
        $this->movingName    = $name;
        $this->showMoveModal = true;
    }

    public function moveTo(?int $folderId): void
    {
        if ($this->movingFileId) {
            $file = File::where('id', $this->movingFileId)
                ->where('user_id', auth()->id())
                ->first();
            if ($file) {
                $file->update(['folder_id' => $folderId]);
                $this->dispatch('toast', type: 'success', message: 'Soubor přesunut');
            }
        } elseif ($this->movingFolderId) {
            $folder = Folder::where('id', $this->movingFolderId)
                ->where('user_id', auth()->id())
                ->first();
            if ($folder && $folder->id !== $folderId) {
                $folder->update(['parent_id' => $folderId]);
                $this->dispatch('toast', type: 'success', message: 'Složka přesunuta');
            }
        }
        $this->showMoveModal  = false;
        $this->movingFileId   = null;
        $this->movingFolderId = null;
        $this->movingName     = '';
    }

    public function cancelMove(): void
    {
        $this->showMoveModal  = false;
        $this->movingFileId   = null;
        $this->movingFolderId = null;
        $this->movingName     = '';
    }

    public function createFolder(): void
    {
        $this->validate(['newFolderName' => 'required|string|max:255']);
        $parentPath = $this->currentFolder ? $this->currentFolder->path : '/';
        $path = rtrim($parentPath, '/') . '/' . Str::slug($this->newFolderName);
        Folder::create([
            'name'      => $this->newFolderName,
            'parent_id' => $this->currentFolderId,
            'path'      => $path,
            'user_id'   => auth()->id(),
        ]);
        $this->newFolderName    = '';
        $this->showNewFolderModal = false;
    }

    public function uploadFiles(): void
    {
        $this->validate(['uploadedFiles.*' => 'required|file|max:512000']);
        $parser = new ThreeMfParserService();

        foreach ($this->uploadedFiles as $file) {
            $originalName = $file->getClientOriginalName();
	    $extension    = strtolower(pathinfo($file->getClientOriginalName(), PATHINFO_EXTENSION));
            $storedName   = Str::uuid() . '.' . $extension;
            $diskPath     = 'prints/' . $storedName;
	    $fullPath = Storage::disk('local')->path($diskPath);
            Storage::disk('local')->put($diskPath, file_get_contents($file->getRealPath()));
            if (in_array($extension, ['3mf'])) {
                $metadata = $parser->parse($fullPath);
            } else {
                $metadata = (new GcodeParserService())->parse($fullPath);
            }

            $thumbnailPath = null;
            if (!empty($metadata['thumbnail_data'])) {
                $thumbName     = Str::uuid() . '.png';
                $thumbnailPath = 'thumbnails/' . $thumbName;
                Storage::disk('local')->put($thumbnailPath, base64_decode($metadata['thumbnail_data']));
                unset($metadata['thumbnail_data']);
            }

	    if (!empty($metadata['plates'])) {
                foreach ($metadata['plates'] as $i => $plate) {
		\Log::info('Plate ' . $i . ' thumbnail_data: ' . (isset($plate['thumbnail_data']) ? 'EXISTS len=' . strlen($plate['thumbnail_data']) : 'NULL'));
            if (isset($plate['thumbnail_data']) && $plate['thumbnail_data'] !== '') {
                        $plateThumbName = Str::uuid() . '.png';
                        $plateThumbPath = 'thumbnails/' . $plateThumbName;
                        $decoded = base64_decode($plate['thumbnail_data']);
                        if ($decoded !== false && strlen($decoded) > 0) {
                            Storage::disk('local')->put($plateThumbPath, $decoded);
                            $metadata['plates'][$i]['thumbnail_path'] = $plateThumbPath;
                        }
                        unset($metadata['plates'][$i]['thumbnail_data']);
                    }
                }
            }

            File::create([
                'folder_id'      => $this->currentFolderId,
                'user_id'        => auth()->id(),
                'original_name'  => $originalName,
                'stored_name'    => $storedName,
                'disk_path'      => $diskPath,
                'size_bytes'     => Storage::disk('local')->size($diskPath),
                'metadata'       => $metadata,
                'thumbnail_path' => $thumbnailPath,
            ]);
        }

        $this->uploadedFiles   = [];
        $this->showUploadModal = false;
    }

    public function reparseFile(int $id): void
    {
        $file = File::where('id', $id)->where('user_id', auth()->id())->firstOrFail();
        $this->reparsingId = $id;
        $fullPath = Storage::disk('local')->path($file->disk_path);

        if (!file_exists($fullPath)) {
            $this->reparsingId = null;
            $this->dispatch('toast', type: 'error', message: 'Soubor nenalezen na disku.');
            return;
        }

        $extension = strtolower(pathinfo($file->disk_path, PATHINFO_EXTENSION));
        if ($extension === '3mf') {
            $parser   = new ThreeMfParserService();
            $metadata = $parser->parse($fullPath);
        } else {
            $metadata = (new GcodeParserService())->parse($fullPath);
        }

        if (!empty($metadata['thumbnail_data'])) {
            if ($file->thumbnail_path) Storage::disk('local')->delete($file->thumbnail_path);
            $thumbName     = Str::uuid() . '.png';
            $thumbnailPath = 'thumbnails/' . $thumbName;
            Storage::disk('local')->put($thumbnailPath, base64_decode($metadata['thumbnail_data']));
            unset($metadata['thumbnail_data']);
            $file->thumbnail_path = $thumbnailPath;
        }

        if (!empty($metadata['plates'])) {
            if (!empty($file->metadata['plates'])) {
                foreach ($file->metadata['plates'] as $oldPlate) {
                    if (!empty($oldPlate['thumbnail_path'])) {
                        Storage::disk('local')->delete($oldPlate['thumbnail_path']);
                    }
                }
            }
            foreach ($metadata['plates'] as $i => $plate) {
                if (!empty($plate['thumbnail_data'])) {
                    $plateThumbName = Str::uuid() . '.png';
                    $plateThumbPath = 'thumbnails/' . $plateThumbName;
                    Storage::disk('local')->put($plateThumbPath, base64_decode($plate['thumbnail_data']));
                    $metadata['plates'][$i]['thumbnail_path'] = $plateThumbPath;
                    unset($metadata['plates'][$i]['thumbnail_data']);
                }
            }
        }

        $file->metadata = $metadata;
        $file->save();
        $this->reparsingId = null;
        $this->dispatch('toast', type: 'success', message: 'Parsování dokončeno: ' . $file->original_name);
    }

    public function startRename(string $type, int $id, string $name): void
    {
        $this->renamingType    = $type;
        $this->renamingId      = $id;
        $this->renamingName    = $name;
        $this->showRenameModal = true;
    }

    public function rename(): void
    {
        $this->validate(['renamingName' => 'required|string|max:255']);
        if ($this->renamingType === 'folder') {
            Folder::where('id', $this->renamingId)->where('user_id', auth()->id())->update(['name' => $this->renamingName]);
        } elseif ($this->renamingType === 'file') {
            File::where('id', $this->renamingId)->where('user_id', auth()->id())->update(['original_name' => $this->renamingName]);
        }
        $this->showRenameModal = false;
    }

    public function confirmDeleteFolder(int $id): void
    {
        $folder = Folder::where('id', $id)->where('user_id', auth()->id())->first();
        if ($folder) {
            $this->confirmDeleteFolderId   = $id;
            $this->confirmDeleteFolderName = $folder->name;
        }
    }

    public function deleteFolder(): void
    {
        if (!$this->confirmDeleteFolderId) return;
        $folder = Folder::where('id', $this->confirmDeleteFolderId)
            ->where('user_id', auth()->id())
            ->first();
        if ($folder) {
            // Zkontrolujeme jestli je složka prázdná
            $hasFiles   = File::where('folder_id', $folder->id)->exists();
            $hasFolders = Folder::where('parent_id', $folder->id)->exists();
            if ($hasFiles || $hasFolders) {
                $this->dispatch('toast', type: 'error',
                    message: 'Složka není prázdná. Nejdříve přesuňte nebo smažte obsah.');
                $this->confirmDeleteFolderId   = null;
                $this->confirmDeleteFolderName = '';
                return;
            }
            $folder->delete();
            $this->dispatch('toast', type: 'success', message: 'Složka smazána.');
        }
        $this->confirmDeleteFolderId   = null;
        $this->confirmDeleteFolderName = '';
    }

    public function cancelDeleteFolder(): void
    {
        $this->confirmDeleteFolderId   = null;
        $this->confirmDeleteFolderName = '';
    }

    public function confirmDelete(int $id): void
    {
        $file = File::where('id', $id)->where('user_id', auth()->id())->first();
        if ($file) {
            $this->confirmDeleteId   = $id;
            $this->confirmDeleteName = $file->original_name;
        }
    }

    public function deleteFile(): void
    {
        if (!$this->confirmDeleteId) return;
        $file = File::where('id', $this->confirmDeleteId)->where('user_id', auth()->id())->first();
        if ($file) {
            if ($file->thumbnail_path) Storage::disk('local')->delete($file->thumbnail_path);
            if (!empty($file->metadata['plates'])) {
                foreach ($file->metadata['plates'] as $plate) {
                    if (!empty($plate['thumbnail_path'])) Storage::disk('local')->delete($plate['thumbnail_path']);
                }
            }
            if ($file->disk_path) Storage::disk('local')->delete($file->disk_path);
            $file->delete();
            $this->dispatch('toast', type: 'success', message: 'Soubor smazán.');
        }
        $this->confirmDeleteId   = null;
        $this->confirmDeleteName = '';
    }

    public function cancelDelete(): void
    {
        $this->confirmDeleteId   = null;
        $this->confirmDeleteName = '';
    }

    public function render()
    {
        return view('livewire.file-manager');
    }
}
