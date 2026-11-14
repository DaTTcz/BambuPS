<?php

namespace App\Console\Commands;

use App\Models\File;
use App\Services\FileReparseService;
use App\Services\ThreeMfParserService;
use Illuminate\Console\Command;

class ReparseFilesCommand extends Command
{
    protected $signature = 'bambups:reparse {--all : Načíst znovu všechny soubory, nejen ty ze starší verze parseru}';

    protected $description = 'Znovu načte metadata souborů (teploty, desky, náhledy) aktuálním parserem';

    public function handle(FileReparseService $service): int
    {
        $done = $missing = $failed = 0;

        File::query()->orderBy('id')->each(function (File $file) use ($service, &$done, &$missing, &$failed) {
            $version = (int) ($file->metadata['parser_version'] ?? 0);
            if (!$this->option('all') && $version >= ThreeMfParserService::VERSION) {
                return;
            }
            try {
                $service->reparse($file) ? $done++ : $missing++;
            } catch (\Throwable $e) {
                $failed++;
                $this->warn("#{$file->id} {$file->original_name}: " . $e->getMessage());
            }
        });

        $this->info("Znovu načteno: {$done}, chybí na disku: {$missing}, chyba: {$failed}");
        return self::SUCCESS;
    }
}
