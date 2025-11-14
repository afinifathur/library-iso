<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use App\Models\DocumentVersion;
use Smalot\PdfParser\Parser as PdfParser;
use PhpOffice\PhpWord\IOFactory as WordIO;
use Illuminate\Support\Facades\Storage;

class ExtractDocumentText extends Command
{
    // signature yang akan dipakai: php artisan documents:extract-text
    protected $signature = 'documents:extract-text
                            {--rebuild : re-extract even if plain_text exists}
                            {--limit=0 : limit number of files (0 = all)}';

    protected $description = 'Extract plain_text and pasted_text from stored document files (docx/pdf)';

    public function handle()
    {
        $rebuild = $this->option('rebuild');
        $limit = (int) $this->option('limit');

        $q = DocumentVersion::query();
        if (!$rebuild) {
            $q->whereNull('plain_text')->orWhere('plain_text', '');
        }
        $q->orderBy('id', 'asc');
        if ($limit > 0) $q->limit($limit);

        $total = $q->count();
        $this->info("Found $total versions to process");

        $parserPdf = null;
        $processed = 0;

        foreach ($q->cursor() as $version) {
            $processed++;
            $this->line("[$processed/$total] v#{$version->id} doc#{$version->document_id} file: {$version->file_path}");

            if (!$version->file_path) {
                $this->warn(" - skipped: no file_path");
                continue;
            }

            $full = storage_path('app/' . ltrim($version->file_path, '/'));
            if (!file_exists($full)) {
                $this->warn(" - skipped: file not found ($full)");
                continue;
            }

            $ext = strtolower(pathinfo($full, PATHINFO_EXTENSION));
            $text = '';

            try {
                if (in_array($ext, ['docx','doc'])) {
                    try {
                        $phpWord = WordIO::load($full);
                        $txt = '';
                        foreach ($phpWord->getSections() as $section) {
                            $elements = $section->getElements();
                            foreach ($elements as $el) {
                                if (method_exists($el, 'getText')) {
                                    $txt .= $el->getText() . "\n";
                                } elseif (property_exists($el, 'text')) {
                                    $txt .= $el->text . "\n";
                                }
                            }
                        }
                        $text = trim($txt);
                        if ($text === '') {
                            // fallback via document.xml
                            $zip = new \ZipArchive();
                            if ($zip->open($full) === true) {
                                $idx = $zip->locateName('word/document.xml');
                                if ($idx !== false) {
                                    $xml = $zip->getFromIndex($idx);
                                    $zip->close();
                                    $xml = strip_tags(preg_replace('/<w:[^>]+>/', '', $xml));
                                    $text = trim(preg_replace('/\s+/', ' ', $xml));
                                }
                            }
                        }
                    } catch (\Throwable $e) {
                        $this->warn(" - phpword failed: " . $e->getMessage());
                    }
                } elseif ($ext === 'pdf') {
                    if ($parserPdf === null) $parserPdf = new PdfParser();
                    try {
                        $pdf = $parserPdf->parseFile($full);
                        $text = trim($pdf->getText());
                    } catch (\Throwable $e) {
                        $this->warn(" - pdfparser failed: " . $e->getMessage());
                        $text = '';
                    }
                } else {
                    $text = trim(strip_tags(file_get_contents($full)));
                }

                $version->plain_text = $text;
                if (empty($version->pasted_text)) {
                    $version->pasted_text = $text;
                }
                $version->save();

                $this->info(" - saved (len: " . strlen($text) . ")");
            } catch (\Throwable $e) {
                $this->error(" - error: " . $e->getMessage());
            }
        }

        $this->info("Done. Processed $processed items.");
        return 0;
    }
}
