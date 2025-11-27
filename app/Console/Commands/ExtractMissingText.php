<?php
// app/Console/Commands/ExtractMissingText.php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use App\Models\DocumentVersion;
use Illuminate\Support\Facades\Storage;
use Carbon\Carbon;

class ExtractMissingText extends Command
{
    protected $signature = 'extract:missing-text 
        {--limit=0 : optional limit to number of versions to process} 
        {--pdftotext= : optional full path to pdftotext binary}';

    protected $description = 'Extract plain text for document versions that have no plain_text yet (docx -> xml, pdf -> pdftotext, doc -> libreoffice)';

    public function handle()
    {
        $limit = (int) $this->option('limit');
        $pdftotextOpt = $this->option('pdftotext') ?: env('PDFTOTEXT_PATH', 'pdftotext');

        $query = DocumentVersion::query()
            ->where(function($q) {
                $q->whereNull('plain_text')->orWhere('plain_text', '');
            })
            ->whereNotNull('file_path');

        if ($limit > 0) $query->limit($limit);

        $versions = $query->get();
        $count = $versions->count();

        if ($count === 0) {
            $this->info("No versions found without plain_text.");
            return 0;
        }

        $this->info("Found {$count} version(s) to process.");

        $disk = Storage::disk('documents');

        foreach ($versions as $v) {
            $this->line("Processing version id={$v->id}, doc_id={$v->document_id}, path={$v->file_path} ...");
            try {
                if (! $v->file_path || ! $disk->exists($v->file_path)) {
                    $this->warn(" - file not found on disk, skipping.");
                    continue;
                }

                $content = $disk->get($v->file_path);
                $lower = strtolower($v->file_path);
                $text = null;

                // DOCX
                if (str_ends_with($lower, '.docx')) {
                    $tmp = sys_get_temp_dir().DIRECTORY_SEPARATOR.'docx_'.uniqid().'.docx';
                    file_put_contents($tmp, $content);
                    $zip = new \ZipArchive();
                    if ($zip->open($tmp) === true) {
                        $idx = $zip->locateName('word/document.xml');
                        if ($idx !== false) {
                            $xml = $zip->getFromIndex($idx);
                            $zip->close();
                            $text = strip_tags($xml);
                        } else {
                            $zip->close();
                        }
                    }
                    @unlink($tmp);
                }

                // PDF
                if (!$text && str_ends_with($lower, '.pdf')) {
                    // write temp pdf
                    $tmpPdf = sys_get_temp_dir().DIRECTORY_SEPARATOR.'pdf_'.uniqid().'.pdf';
                    $tmpTxt = sys_get_temp_dir().DIRECTORY_SEPARATOR.'pdf_'.uniqid().'.txt';
                    file_put_contents($tmpPdf, $content);

                    $cmd = escapeshellcmd($pdftotextOpt) . ' -enc UTF-8 ' . escapeshellarg($tmpPdf) . ' ' . escapeshellarg($tmpTxt) . ' 2>&1';
                    $out = null;
                    $ret = null;
                    exec($cmd, $out, $ret);

                    if ($ret === 0 && file_exists($tmpTxt)) {
                        $text = file_get_contents($tmpTxt);
                        @unlink($tmpTxt);
                    } else {
                        // try without output file (stdout)
                        $cmd2 = escapeshellcmd($pdftotextOpt) . ' -enc UTF-8 -q ' . escapeshellarg($tmpPdf) . ' - 2>&1';
                        $out2 = null;
                        $ret2 = null;
                        exec($cmd2, $out2, $ret2);
                        if ($ret2 === 0 && is_array($out2)) {
                            $text = implode("\n", $out2);
                        }
                    }

                    @unlink($tmpPdf);
                }

                /**
                 * DOC via LibreOffice (best and stable)
                 */
                if (!$text && str_ends_with($lower, '.doc')) {
                    $this->info(" - converting .doc using LibreOffice...");

                    $tmpDoc = sys_get_temp_dir().DIRECTORY_SEPARATOR.'doc_'.uniqid().'.doc';
                    file_put_contents($tmpDoc, $content);

                    $lo = env('LIBREOFFICE_PATH', 'soffice');
                    $outputDir = sys_get_temp_dir();

                    // LibreOffice will create a file with same basename but .txt in $outputDir
                    $cmd = "\"{$lo}\" --headless --convert-to txt --outdir " . escapeshellarg($outputDir) . " " . escapeshellarg($tmpDoc) . " 2>&1";

                    $out = null; $ret = null;
                    exec($cmd, $out, $ret);

                    $convertedTxt = $outputDir . DIRECTORY_SEPARATOR . pathinfo($tmpDoc, PATHINFO_FILENAME) . '.txt';
                    if (file_exists($convertedTxt)) {
                        $text = file_get_contents($convertedTxt);
                        @unlink($convertedTxt);
                    } else {
                        // fallback: try reading any .txt created with pattern
                        $pattern = $outputDir . DIRECTORY_SEPARATOR . pathinfo($tmpDoc, PATHINFO_FILENAME) . '.*.txt';
                        foreach (glob($outputDir . DIRECTORY_SEPARATOR . pathinfo($tmpDoc, PATHINFO_FILENAME) . '*.txt') as $candidate) {
                            if (file_exists($candidate)) {
                                $text = file_get_contents($candidate);
                                @unlink($candidate);
                                break;
                            }
                        }
                    }

                    @unlink($tmpDoc);
                }

                if ($text) {
                    // normalize similar to controller
                    $text = str_replace(["\r\n", "\r"], "\n", $text);
                    $text = preg_replace('/[^\PC\n\t]/u', ' ', $text);
                    $text = preg_replace('/[ \t]{2,}/', ' ', $text);
                    $text = preg_replace("/\n{3,}/", "\n\n", $text);
                    $text = trim($text);

                    $v->plain_text = mb_substr($text, 0, 500000); // guard
                    $v->summary_changed = 'Text extracted automatically via batch command';
                    $v->save();

                    $this->info(" - extracted (len=" . strlen($v->plain_text) . ")");
                } else {
                    $v->summary_changed = $v->summary_changed ? $v->summary_changed . '; extractor-failed' : 'extractor-failed';
                    $v->save();
                    $this->warn(" - no text extracted for this file.");
                }
            } catch (\Throwable $e) {
                $this->error(" - error: " . $e->getMessage());
                try { $v->summary_changed = ($v->summary_changed ?? '') . '; extract-error'; $v->save(); } catch (\Throwable $e2) {}
            }
        }

        $this->info("Done.");
        return 0;
    }
}
