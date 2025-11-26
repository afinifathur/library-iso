<?php
// app/Console/Commands/ImportDocsFromImportsFolder.php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Str;
use Illuminate\Support\Facades\Storage;
use App\Models\Document;
use App\Models\DocumentVersion;
use App\Models\Department;
use Carbon\Carbon;

class ImportDocsFromImportsFolder extends Command
{
    protected $signature = 'import:bulk-docs 
        {--path=imports : relative path from project root to imports folder} 
        {--skip-existing : skip documents that already exist} 
        {--status=approved : baseline version status (approved|draft)}';

    protected $description = 'Bulk import doc/docx/pdf files from imports/* into storage and create baseline versions';

    public function handle()
    {
        $base = base_path($this->option('path'));
        if (! is_dir($base)) {
            $this->error("Imports folder not found: {$base}");
            return 1;
        }

        $disk = Storage::disk('documents');
        $countFiles = 0;
        $skipped = [];
        $imported = [];
        $failed = [];

        $this->info("Scanning folder: {$base}");

        $it = new \RecursiveIteratorIterator(new \RecursiveDirectoryIterator($base));
        foreach ($it as $file) {
            if ($file->isDir()) continue;
            $ext = strtolower(pathinfo($file->getFilename(), PATHINFO_EXTENSION));
            if (! in_array($ext, ['doc','docx','pdf'])) {
                // skip excel / other files
                continue;
            }

            $countFiles++;
            $originalName = $file->getFilename();
            $relativeDir = trim(str_replace($base, '', $file->getPath()), DIRECTORY_SEPARATOR);
            $folderName = $relativeDir ? basename($relativeDir) : null;

            // parse name: split by first space => "DOC_CODE rest_of_title"
            $nameOnly = pathinfo($originalName, PATHINFO_FILENAME);
            $firstSpacePos = strpos($nameOnly, ' ');
            if ($firstSpacePos !== false) {
                $docCodeRaw = substr($nameOnly, 0, $firstSpacePos);
                $titleRaw = trim(substr($nameOnly, $firstSpacePos + 1));
            } else {
                // fallback: no space — treat whole as title, create doc_code from slug
                $docCodeRaw = null;
                $titleRaw = $nameOnly;
            }

            $docCode = $docCodeRaw ? trim($docCodeRaw) : strtoupper(Str::slug($titleRaw, '.'));
            $title = trim($titleRaw) ?: $docCode;

            // skip existing if requested
            $document = Document::where('doc_code', $docCode)->first();
            if ($document && $this->option('skip-existing')) {
                $skipped[] = $originalName;
                continue;
            }

            try {
                // create document if missing
                if (! $document) {
                    $docData = [
                        'doc_code' => $docCode,
                        'title' => $title,
                    ];

                    // try map department by folder name (search code or name)
                    if ($folderName) {
                        $dept = Department::whereRaw('LOWER(code) = ?', [strtolower($folderName)])
                            ->orWhereRaw('LOWER(name) = ?', [strtolower($folderName)])
                            ->first();
                        if ($dept) {
                            $docData['department_id'] = $dept->id;
                        }
                    }

                    $document = Document::create($docData);
                }

                // prepare dest path
                $safeName = preg_replace('/[^\w\.\-]+/u', '_', $originalName);
                $destFolder = $document->doc_code . '/master';
                $destPath = $destFolder . '/' . time() . '_' . Str::random(6) . '_' . $safeName;

                // copy into storage disk
                $content = file_get_contents($file->getPathname());
                $disk->put($destPath, $content);

                // create version baseline
                $status = $this->option('status') === 'draft' ? 'draft' : 'approved';
                $approvalStage = $status === 'approved' ? 'DONE' : 'KABAG';

                $version = DocumentVersion::create([
                    'document_id' => $document->id,
                    'version_label' => 'v1',
                    'status' => $status,
                    'approval_stage' => $approvalStage,
                    'file_path' => $destPath,
                    'file_mime' => $this->mimeFromExt($ext),
                    'checksum' => hash('sha256', $content),
                    'change_note' => 'Imported baseline from imports folder',
                    'plain_text' => null,
                    'created_by' => null,
                    'approved_by' => $status === 'approved' ? null : null,
                    'approved_at' => $status === 'approved' ? Carbon::now() : null,
                ]);

                // update document current pointer
                $document->update([
                    'current_version_id' => $version->id,
                    'revision_number' => 1,
                    'revision_date' => Carbon::now(),
                ]);

                $imported[] = $originalName;
                $this->line("Imported: {$originalName} -> {$destPath}");
            } catch (\Throwable $e) {
                $failed[] = ['file' => $originalName, 'error' => $e->getMessage()];
                $this->error("Failed import {$originalName}: " . $e->getMessage());
            }
        }

        $this->info("Scan complete. Files considered: {$countFiles}");
        $this->info("Imported: " . count($imported));
        if ($skipped) $this->warn("Skipped (existing): " . count($skipped));
        if ($failed) $this->error("Failed: " . count($failed));
        $this->info("Done.");

        return 0;
    }

    protected function mimeFromExt($ext)
    {
        $map = [
            'pdf' => 'application/pdf',
            'doc' => 'application/msword',
            'docx'=> 'application/vnd.openxmlformats-officedocument.wordprocessingml.document',
        ];
        return $map[$ext] ?? 'application/octet-stream';
    }
}
