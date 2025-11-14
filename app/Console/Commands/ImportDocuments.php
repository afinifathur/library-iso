<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use PhpOffice\PhpWord\IOFactory as WordIO;
use Smalot\PdfParser\Parser as PdfParser;
use App\Models\Document;
use App\Models\DocumentVersion;
use App\Models\Category;
use App\Models\Department;
use Carbon\Carbon;
use DB;

class ImportDocuments extends Command
{
    protected $signature = 'import:documents
                            {--path=imports : relative path from project root to import folder}
                            {--approve : mark imported versions as approved}
                            {--dry : dry-run (do not write DB / files)}
                            {--batch=10 : files per batch (throttle)}';

    protected $description = 'Import documents from local folders (docx/pdf) into system';

    public function handle()
    {
        $path = base_path($this->option('path'));
        if (!is_dir($path)) {
            $this->error("Path not found: $path");
            return 1;
        }

        $files = $this->gatherFiles($path);
        $total = count($files);
        $this->info("Found $total file(s) to process.");

        $dry = $this->option('dry');
        $approve = $this->option('approve');
        $batch = (int)$this->option('batch');

        $counter = 0;
        foreach ($files as $row) {
            $counter++;
            [$filePath, $deptCode] = $row;
            $this->line("[$counter/$total] Processing: $filePath (folder dept: $deptCode)");

            try {
                // attempt parse file name to get doc_code and title
                $basename = pathinfo($filePath, PATHINFO_BASENAME);
                // Expected patterns:
                // IK.QA-FL.01 SPEKTROMETER.docx
                // or SPEKTROMETER.docx (then rely on parent folder for dept)
                $nameOnly = pathinfo($filePath, PATHINFO_FILENAME);
                // split by first space => left could be doc_code
                $parts = preg_split('/\s+/', $nameOnly, 2);
                $maybeCode = strtoupper($parts[0]);
                $title = isset($parts[1]) ? $parts[1] : $nameOnly;

                $doc_code = null;
                if (preg_match('/^[A-Z]{1,5}\./', $maybeCode) || strpos($maybeCode, '.') !== false) {
                    // looks like code
                    $doc_code = $maybeCode;
                }

                // guess category code from doc_code (prefix before dot)
                $category = null;
                if ($doc_code) {
                    $catPrefix = strtoupper(explode('.', $doc_code)[0]);
                    $category = Category::where('code', $catPrefix)->first();
                }

                // guess department: either given by parent folder or from doc_code 2nd segment
                $department = null;
                if ($doc_code) {
                    $seg = explode('.', $doc_code);
                    if (isset($seg[1])) {
                        $deptGuess = strtoupper($seg[1]); // e.g. QA-FL
                        $department = Department::where('code', $deptGuess)->first();
                    }
                }
                if (!$department && $deptCode) {
                    $department = Department::where('code', $deptCode)->first();
                }

                // fallback: first department
                if (!$department) {
                    $department = Department::first();
                }

                // set category id (nullable)
                $category_id = $category ? $category->id : null;

                // read text depending on extension
                $ext = strtolower(pathinfo($filePath, PATHINFO_EXTENSION));
                $plain_text = null;
                if (in_array($ext, ['docx','doc'])) {
                    $plain_text = $this->extractTextFromDocx($filePath);
                } elseif ($ext === 'pdf') {
                    $plain_text = $this->extractTextFromPdf($filePath);
                } else {
                    $plain_text = '';
                }

                // in dry-run mode, just print summary
                if ($dry) {
                    $this->info("[DRY] would create Document: code={$doc_code} title={$title} dept={$department->code} cat={$category?->code}");
                    continue;
                }

                DB::beginTransaction();

                // create or find document by doc_code (or title)
                if ($doc_code) {
                    $document = Document::firstOrCreate(
                        ['doc_code' => $doc_code],
                        ['title' => $title,
                         'department_id' => $department->id ?? null,
                         'category_id' => $category_id]
                    );
                } else {
                    // try by title
                    $document = Document::firstOrCreate(
                        ['title' => $title],
                        ['doc_code' => null,
                         'department_id' => $department->id ?? null,
                         'category_id' => $category_id]
                    );
                }

                // store master file to storage/app/documents/{document_id}/filename
                $storagePath = "documents/{$document->id}";
                $fileName = preg_replace('/[^A-Za-z0-9\-\._ ]/','_', $basename);
                $diskPath = $storagePath . '/' . $fileName;
                // ensure directory
                Storage::disk('local')->putFileAs($storagePath, $filePath, $fileName);

                // create version record
                $versionLabel = 'v1';
                // find last version number
                $last = $document->versions()->orderBy('id','desc')->first();
                if ($last && preg_match('/v(\d+)/i', $last->version_label, $m)) {
                    $versionLabel = 'v' . (intval($m[1]) + 1);
                }

                $status = $approve ? 'approved' : 'draft';
                $approval_stage = $approve ? 'DONE' : 'KABAG';

                $version = DocumentVersion::create([
                    'document_id' => $document->id,
                    'version_label' => $versionLabel,
                    'status' => $status,
                    'created_by' => 1, // you may change default uploader id
                    'file_path' => $diskPath,
                    'file_mime' => mime_content_type($filePath) ?: null,
                    'checksum' => substr(hash('sha256', file_get_contents($filePath)),0,40),
                    'change_note' => 'Imported via batch',
                    'signed_by' => null,
                    'signed_at' => null,
                    'plain_text' => $plain_text,
                    'pasted_text' => $plain_text,
                    'created_at' => Carbon::now(),
                    'updated_at' => Carbon::now(),
                    'approval_stage' => $approval_stage,
                ]);

                DB::commit();

                $this->info("Imported -> Document#{$document->id} Version#{$version->id} ({$versionLabel})");

            } catch (\Exception $e) {
                DB::rollBack();
                $this->error("Error importing $filePath : " . $e->getMessage());
            }

            // throttle per batch
            if ($counter % $batch === 0) {
                $this->info("Processed $counter items, sleeping 2s...");
                sleep(2);
            }
        }

        $this->info("Done. Processed $counter files.");
        return 0;
    }

    protected function gatherFiles($path)
    {
        $rows = [];
        // iterate department folders (first-level)
        $entries = array_filter(scandir($path), function($n){ return $n !== '.' && $n !== '..'; });
        foreach ($entries as $entry) {
            $full = rtrim($path, DIRECTORY_SEPARATOR) . DIRECTORY_SEPARATOR . $entry;
            if (is_dir($full)) {
                $files = glob($full . DIRECTORY_SEPARATOR . '*.{docx,doc,pdf}', GLOB_BRACE);
                foreach ($files as $f) {
                    $rows[] = [$f, $entry]; // pass dept folder name
                }
            } else {
                // files in root imports folder
                if (preg_match('/\.(docx|doc|pdf)$/i', $full)) {
                    $rows[] = [$full, null];
                }
            }
        }
        return $rows;
    }

    protected function extractTextFromDocx($file)
    {
        try {
            // phpword can read docx, but for plain extraction we attempt to parse sections
            $phpWord = WordIO::load($file);
            $text = '';
            foreach ($phpWord->getSections() as $section) {
                $elements = $section->getElements();
                foreach ($elements as $el) {
                    if (method_exists($el, 'getText')) {
                        $text .= $el->getText() . "\n";
                    } elseif (property_exists($el, 'text')) {
                        $text .= $el->text . "\n";
                    }
                }
            }
            if (trim($text) === '') {
                // fallback: try open as zip and extract document.xml
                $text = $this->extractDocxViaXml($file);
            }
            return trim($text);
        } catch (\Throwable $e) {
            return $this->extractDocxViaXml($file);
        }
    }

    protected function extractDocxViaXml($file)
    {
        try {
            $zip = new \ZipArchive();
            $res = $zip->open($file);
            if ($res === true) {
                $index = $zip->locateName('word/document.xml');
                if ($index !== false) {
                    $xml = $zip->getFromIndex($index);
                    $zip->close();
                    $xml = preg_replace('/<w:.*?>|<\/w:.*?>/','',$xml);
                    $xml = strip_tags($xml);
                    return trim(preg_replace("/\s+/", ' ', $xml));
                }
            }
            return '';
        } catch (\Throwable $e) {
            return '';
        }
    }

    protected function extractTextFromPdf($file)
    {
        try {
            $parser = new PdfParser();
            $pdf    = $parser->parseFile($file);
            $text = $pdf->getText();
            return trim($text);
        } catch (\Throwable $e) {
            return '';
        }
    }
}
