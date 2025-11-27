<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use App\Models\DocumentVersion;
use Illuminate\Support\Str;
use Symfony\Component\Process\Process;

class ReextractDocumentTextCommand extends Command
{
    protected $signature = 'reextract:versions {--limit=0}';
    protected $description = 'Re-extract plain_text for document_versions where plain_text is empty or short (fixed path resolution).';

    public function handle()
    {
        $limit = (int) $this->option('limit');

        $this->info("Starting fixed re-extraction...");

        $query = DocumentVersion::query()
            ->whereRaw('plain_text IS NULL OR LENGTH(IFNULL(plain_text,"")) < 200')
            ->orderBy('id');

        if ($limit > 0) {
            $query->limit($limit);
        }

        $versions = $query->get();

        if ($versions->isEmpty()) {
            $this->info("No versions to process.");
            return 0;
        }

        foreach ($versions as $version) {

            $this->line("Processing id={$version->id} path={$version->file_path}");

            $candidate = $version->file_path ?: ($version->master_path ?? null);
            $fullPath = storage_path('app/documents/' . $candidate);

            if (! $candidate || ! file_exists($fullPath)) {
                $this->warn(" - file not found: $fullPath");
                continue;
            }

            // Prepare temp dir & copy file
            $tmpDir = sys_get_temp_dir() . DIRECTORY_SEPARATOR . 'extract_' . uniqid();
            @mkdir($tmpDir);
            $tmpFile = $tmpDir . '/' . basename($fullPath);
            copy($fullPath, $tmpFile);

            $ext = strtolower(pathinfo($tmpFile, PATHINFO_EXTENSION));
            $text = null;

            // Handle .doc by converting to .docx
            if ($ext === 'doc') {
                $this->line(" - converting DOC → DOCX");

                $soffice = env('LIBREOFFICE_PATH', '"C:\\Program Files\\LibreOffice\\program\\soffice.exe"');

                $cmd = "\"$soffice\" --headless --convert-to docx --outdir " .
                       escapeshellarg($tmpDir) . ' ' .
                       escapeshellarg($tmpFile) . " 2>&1";

                exec($cmd, $output, $ret);

                $converted = glob($tmpDir . '/*.docx');

                if ($converted) {
                    $tmpFile = $converted[0];
                    $ext = 'docx';
                } else {
                    $this->warn(" - DOC conversion failed.");
                    continue;
                }
            }

            // Extract DOCX
            if ($ext === 'docx') {
                $text = $this->extractDocx($tmpFile);
            }

            // Extract PDF
            if ($ext === 'pdf') {
                $text = $this->extractPdf($tmpFile);
            }

            // Save or warn
            if ($text) {
                $clean = $this->normalize($text);
                $version->plain_text = $clean;
                $version->save();
                $this->info(" - extracted OK (len=" . strlen($clean) . ")");
            } else {
                $this->warn(" - failed to extract.");
            }

            // Cleanup
            foreach (glob($tmpDir.'/*') as $f) unlink($f);
            @rmdir($tmpDir);
        }

        return 0;
    }

    private function extractDocx($file)
    {
        try {
            $zip = new \ZipArchive();
            if ($zip->open($file) === true) {
                $idx = $zip->locateName('word/document.xml');
                if ($idx !== false) {
                    $xml = $zip->getFromIndex($idx);
                    $zip->close();
                    return strip_tags($xml);
                }
                $zip->close();
            }
        } catch (\Throwable $e) {
            return null;
        }
        return null;
    }

    private function extractPdf($file)
    {
        $txt = $file . '.txt';
        $pdftotext = env('PDFTOTEXT_PATH', 'pdftotext');

        $process = new Process([$pdftotext, $file, $txt]);
        $process->setTimeout(90);
        $process->run();

        if ($process->isSuccessful() && file_exists($txt)) {
            return file_get_contents($txt);
        }

        return null;
    }

    private function normalize($t)
    {
        $t = str_replace(["\r\n","\r"], "\n", $t);
        $t = preg_replace('/[^\PC\n\t]/u', ' ', $t);
        $t = preg_replace('/[ \t]{2,}/', ' ', $t);
        return trim($t);
    }
}
