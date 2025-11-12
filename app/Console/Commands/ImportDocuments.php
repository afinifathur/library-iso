<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use League\Csv\Reader;
use App\Models\Document;
use App\Models\DocumentVersion;
use App\Models\Department;
use App\Models\Category;
use App\Models\User;
use Carbon\Carbon;

class ImportDocuments extends Command
{
    protected $signature = 'import:documents {manifest=storage/app/imports/import_manifest.csv} {--dry-run}';
    protected $description = 'Import documents and versions from CSV manifest + files in storage/app/imports';

    public function handle()
    {
        $manifestPath = $this->argument('manifest');
        $dryRun = $this->option('dry-run');

        if (!file_exists($manifestPath)) {
            $this->error("Manifest not found: {$manifestPath}");
            return 1;
        }

        $this->info("Reading manifest: {$manifestPath}");
        $csv = Reader::createFromPath($manifestPath, 'r');
        $csv->setHeaderOffset(0);
        $records = $csv->getRecords();

        $row = 0;
        foreach ($records as $rec) {
            $row++;
            DB::beginTransaction();
            try {
                $docCode = trim($rec['doc_code'] ?? '');
                $title   = trim($rec['title'] ?? 'No title');
                $deptCode = trim($rec['department_code'] ?? '');
                $catCode = trim($rec['category_code'] ?? '');
                $masterFn = trim($rec['master_filename'] ?? '');
                $pdfFn = trim($rec['signed_pdf_filename'] ?? '');
                $pastedText = $rec['pasted_text'] ?? null;
                $versionLabel = trim($rec['version_label'] ?? 'v1');
                $creatorEmail = trim($rec['created_by_email'] ?? null);
                $status = trim($rec['status'] ?? 'draft');
                $signedAt = trim($rec['signed_at'] ?? null);

                // Department
                $department = null;
                if ($deptCode) {
                    $department = Department::where('code', $deptCode)->first();
                    if (!$department) {
                        $this->warn("Row {$row}: Department {$deptCode} not found — creating placeholder.");
                        if (!$dryRun) {
                            $department = Department::create(['code'=>$deptCode,'name'=>$deptCode]);
                        }
                    }
                }

                // Category (optional)
                $category = null;
                if ($catCode) {
                    $category = Category::where('code', $catCode)->first();
                    if (!$category) {
                        $this->warn("Row {$row}: Category {$catCode} not found — creating placeholder.");
                        if (!$dryRun) {
                            $category = Category::create(['code'=>$catCode,'name'=>$catCode]);
                        }
                    }
                }

                // Creator
                $creator = null;
                if ($creatorEmail) {
                    $creator = User::where('email', $creatorEmail)->first();
                    if (!$creator) {
                        $this->warn("Row {$row}: user {$creatorEmail} not found — using admin (or null).");
                        $creator = User::first(); // fallback
                    }
                } else {
                    $creator = User::first();
                }

                // Create or find Document
                $document = Document::where('doc_code', $docCode)->first();
                if (!$document) {
                    $this->info("Row {$row}: creating Document {$docCode}");
                    if (!$dryRun) {
                        $document = Document::create([
                            'doc_code' => $docCode,
                            'title' => $title,
                            'department_id' => $department ? $department->id : null,
                            'category_id' => $category ? $category->id : null,
                        ]);
                    }
                } else {
                    $this->info("Row {$row}: found existing Document {$docCode} (id {$document->id})");
                    // optional: update title/props
                }

                // Prepare file paths
                $signedFilePath = null;
                if ($pdfFn) {
                    $signedFull = storage_path("app/imports/signed/{$pdfFn}");
                    if (!file_exists($signedFull)) {
                        $this->warn("Row {$row}: signed PDF {$pdfFn} not found at imports/signed/ — skipping PDF.");
                    } else {
                        // copy to public storage (or keep in storage and link)
                        $dest = "documents/".date('Y/m')."/".basename($pdfFn);
                        if (!$dryRun) {
                            Storage::disk('public')->putFileAs(dirname($dest), new \Illuminate\Http\File($signedFull), basename($dest));
                        }
                        $signedFilePath = 'storage/'.$dest; // or use Storage::url(...)
                    }
                }

                // checksum if file present
                $checksum = null;
                if ($signedFilePath && !$dryRun) {
                    $realPath = public_path($signedFilePath);
                    if (file_exists($realPath)) {
                        $checksum = substr(hash_file('sha256', $realPath), 0, 40);
                    }
                }

                // create version
                $this->info("Row {$row}: creating version {$versionLabel} for document {$docCode}");
                if (!$dryRun) {
                    $vdata = [
                        'document_id' => $document->id,
                        'version_label' => $versionLabel,
                        'status' => $status,
                        'created_by' => $creator->id ?? null,
                        'file_path' => $signedFilePath ? $signedFilePath : null,
                        'file_mime' => $signedFilePath ? mime_content_type(public_path($signedFilePath)) : null,
                        'checksum' => $checksum,
                        'change_note' => $rec['change_note'] ?? null,
                        'signed_by' => $rec['signed_by'] ?? null,
                        'signed_at' => $signedAt ? Carbon::parse($signedAt) : null,
                        'plain_text' => $pastedText ? null : null, // keep null if we want extraction later
                        'pasted_text' => $pastedText ? $pastedText : null,
                    ];
                    $version = DocumentVersion::create($vdata);
                }

                DB::commit();
            } catch (\Exception $e) {
                DB::rollBack();
                $this->error("Row {$row} FAILED: ".$e->getMessage());
                // optionally continue or stop
                continue;
            }
        }

        $this->info("Import completed.");
        return 0;
    }
}
