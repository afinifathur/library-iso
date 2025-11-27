<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Symfony\Component\Finder\Finder;
use Illuminate\Support\Str;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

class ReExtractText extends Command
{
    protected $signature = 'extract:redo {--only=} {--dry-run}';
    protected $description = 'Re-extract text for candidate models that contain extracted_text column (defensive search).';

    public function handle()
    {
        $this->info("Starting defensive re-extraction routine...");

        $only = $this->option('only'); // optional FQCN to limit
        $dry = $this->option('dry-run');

        // If user passed explicit class name, use it directly
        if ($only) {
            $this->info("Running only for: {$only}");
            $this->processCandidateClass($only, $dry);
            $this->info("Finished.");
            return 0;
        }

        $finder = new Finder();
        $finder->files()->in(app_path())->name('*.php')->contains('extracted_text'); // prioritize files referencing extracted_text

        // if none found, broaden to files referencing pdf_path OR file_path
        if (! $finder->hasResults()) {
            $this->info('No files referencing extracted_text found. Broadening search to pdf_path or file_path...');
            $finder = new Finder();
            $finder->files()->in(app_path())->name('*.php')
                ->filter(function (\SplFileInfo $file) {
                    $content = $file->getContents();
                    return Str::contains($content, 'pdf_path') || Str::contains($content, 'file_path') || Str::contains($content, 'extracted_text');
                });
        }

        if (! $finder->hasResults()) {
            $this->warn('No candidate files found in app/ referencing extracted_text/pdf_path/file_path. Please inspect your models manually.');
            return 1;
        }

        $candidates = [];
        foreach ($finder as $file) {
            $path = $file->getRealPath();
            $this->line("Scanning file: {$path}");
            $fqcn = $this->getClassFullNameFromFile($path);
            if ($fqcn) {
                $candidates[$fqcn] = $path;
            }
        }

        if (empty($candidates)) {
            $this->warn('No PHP classes could be parsed from candidate files.');
            return 1;
        }

        $this->info('Candidate classes found:');
        foreach ($candidates as $fqcn => $path) {
            $this->line(" - {$fqcn} (from {$path})");
        }

        foreach ($candidates as $fqcn => $path) {
            $this->processCandidateClass($fqcn, $dry);
        }

        $this->info("All done.");
        return 0;
    }

    protected function processCandidateClass(string $fqcn, bool $dry)
    {
        $this->line("== Processing {$fqcn} == ");

        if (! class_exists($fqcn)) {
            $this->warn("Class {$fqcn} does not exist (autoload). Try running `composer dump-autoload` and retry.");
            return;
        }

        try {
            $model = new $fqcn;
        } catch (\Throwable $e) {
            $this->error("Could not instantiate {$fqcn}: " . $e->getMessage());
            return;
        }

        if (! method_exists($model, 'getTable')) {
            $this->warn("Class {$fqcn} is not an Eloquent model (no getTable()). Skipping.");
            return;
        }

        $table = $model->getTable();
        $this->line(" -> model table: {$table}");

        // Check if extracted_text column exists
        if (! Schema::hasColumn($table, 'extracted_text')) {
            $this->warn(" -> Table {$table} does NOT have column extracted_text. Skipping.");
            return;
        }

        $this->info(" -> Found extracted_text column in {$table}. Will re-extract rows.");

        // Chunk through rows
        $count = DB::table($table)->count();
        $this->info(" -> Total rows: {$count}");

        if ($dry) {
            $this->info("Dry-run enabled: not performing extraction. Skipping actual work.");
            return;
        }

        $processed = 0;
        $errors = 0;

        // We will use the model's query to chunk
        $query = $fqcn::query();

        $query->chunkById(50, function ($rows) use (&$processed, &$errors, $fqcn) {
            foreach ($rows as $row) {
                try {
                    // Prefer calling model method extractText() if it exists
                    if (method_exists($fqcn, 'extractText')) {
                        // load full model
                        $m = $fqcn::find($row->id);
                        $this->line("Extracting via model->extractText() for id {$m->id}...");
                        $m->extractText();
                    } else {
                        // fallback: look for file path columns to re-run extraction job (best-effort)
                        $this->line("No extractText() method on {$fqcn}. Please implement or run extraction manually for record id {$row->id}.");
                    }

                    $processed++;
                } catch (\Throwable $e) {
                    $this->error("Error processing id {$row->id}: " . $e->getMessage());
                    $errors++;
                }
            }
        });

        $this->info(" -> Finished processing {$fqcn}. Processed: {$processed}, Errors: {$errors}");
    }

    /**
     * Parse PHP file and return fully qualified class name (namespace + class)
     */
    protected function getClassFullNameFromFile(string $filePath): ?string
    {
        $contents = file_get_contents($filePath);
        if (! $contents) return null;

        $namespace = null;
        $class = null;

        // find namespace
        if (preg_match('/^namespace\s+(.+?);/m', $contents, $m)) {
            $namespace = trim($m[1]);
        }

        // find class name (class, abstract class, trait)
        if (preg_match('/class\s+([A-Za-z0-9_]+)/m', $contents, $m2)) {
            $class = trim($m2[1]);
        } elseif (preg_match('/trait\s+([A-Za-z0-9_]+)/m', $contents, $m3)) {
            $class = trim($m3[1]);
        }

        if ($class) {
            return $namespace ? "{$namespace}\\{$class}" : $class;
        }

        return null;
    }
}
