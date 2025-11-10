<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\File; // <— tambahkan
use Illuminate\Support\Str;
use App\Models\Department;
use App\Models\Document;
use App\Models\DocumentVersion;
use Carbon\Carbon;

class ImportDocumentsCommand extends Command
{
    protected $signature = 'documents:import {path=imports} {--move-to= : move processed files here}';
    protected $description = 'Import documents from a folder structured by department code.';

    public function handle()
    {
        $base = base_path($this->argument('path'));
        if (!is_dir($base)) {
            $this->error("Folder not found: {$base}");
            return 1;
        }

        $files = new \RecursiveIteratorIterator(new \RecursiveDirectoryIterator($base));
        foreach ($files as $file) {
            if ($file->isDir()) continue;

            $filePath = $file->getRealPath();
            $relative = str_replace($base . DIRECTORY_SEPARATOR, '', $filePath);
            $parts = explode(DIRECTORY_SEPARATOR, $relative);
            $deptCode = $parts[0] ?? null;

            if (!$deptCode) {
                $this->warn("Skipping (no dept folder): {$relative}");
                continue;
            }

            $department = Department::firstOrCreate(
                ['code' => $deptCode],
                ['name' => $deptCode]
            );

            $filename = pathinfo($filePath, PATHINFO_BASENAME);
            preg_match('/(?P<doc>DOC[-_0-9A-Za-z]+)[_ -]+(?P<title>.+?)_v?(?P<ver>[0-9]+(?:\.[0-9]+)*)/i', $filename, $m);

            $docCode = $m['doc'] ?? Str::upper(Str::slug(pathinfo($filename, PATHINFO_FILENAME)));
            $versionLabel = isset($m['ver']) ? ('v' . $m['ver']) : 'v1';
            $title = isset($m['title']) ? str_replace('_', ' ', $m['title']) : pathinfo($filename, PATHINFO_FILENAME);

            // signature info (opsional)
            $signedBy = null;
            $signedAt = null;
            if (preg_match('/signed[_-]BY-?([A-Za-z0-9\-\s\.]+)[_\-]?(\d{4}-\d{2}-\d{2})?/i', $filename, $s)) {
                $signedBy = trim($s[1]);
                if (!empty($s[2])) {
                    try {
                        $signedAt = Carbon::createFromFormat('Y-m-d', $s[2]);
                    } catch (\Exception $e) {
                        $signedAt = null;
                    }
                }
            }

            $document = Document::firstOrCreate(
                ['doc_code' => $docCode],
                ['title' => ucfirst(str_replace(['_', '-'], ' ', $title)), 'department_id' => $department->id]
            );

            $disk = Storage::disk('documents');
            $storagePath = $document->doc_code . '/' . $versionLabel . '/' . Str::random(8) . '_' . $filename;
            $content = file_get_contents($filePath);
            $disk->put($storagePath, $content);

            $checksum = hash('sha256', $content);

            // Tentukan MIME (fallback ke tipe generik jika gagal)
            $mime = mime_content_type($filePath);
            if ($mime === false) {
                $mime = 'application/octet-stream';
            }

            // status otomatis berdasarkan nama file
            $status = 'draft';
            if (
                stripos($filename, 'signed') !== false ||
                stripos($filename, 'approved') !== false ||
                stripos($filename, 'published') !== false
            ) {
                $status = 'approved';
            }

            $dv = DocumentVersion::create([
                'document_id'   => $document->id,
                'version_label' => $versionLabel,
                'status'        => $status,
                'created_by'    => null,
                'file_path'     => $storagePath,
                'file_mime'     => $mime,
                'checksum'      => $checksum,
                'change_note'   => null,
                'signed_by'     => $signedBy,
                'signed_at'     => $signedAt,
            ]);

            if ($status === 'approved') {
                // handle null di revision_number
                $document->revision_number = max(1, (int)($document->revision_number ?? 0) + 1);
                $document->revision_date = now();
                $document->save();
            }

            $this->info("Imported: {$relative} => {$document->doc_code} / {$versionLabel} (status: {$status})");

            // —— Perbaikan bagian ini (hapus kode Python) ——
            if ($this->option('move-to')) {
                $destDir   = rtrim($this->option('move-to'), DIRECTORY_SEPARATOR);
                $destPath  = $destDir . DIRECTORY_SEPARATOR . $relative;
                $targetDir = dirname($destPath);

                // buat folder parent rekursif jika belum ada
                File::ensureDirectoryExists($targetDir);

                // pindahkan file fisik
                @rename($filePath, $destPath);
            }
            // ——————————————————————————————————————————————
        }

        $this->info("Import completed.");
        return 0;
    }
}
