<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\Storage;
use App\Models\DocumentVersion;

class ExtractDocumentTextCommand extends Command
{
    protected $signature = 'documents:extract-text 
                            {version_id? : Extract only this version}
                            {--rebuild : Re-extract all even if already has text}';

    protected $description = 'Extract plain text from PDF for document versions.';

    public function handle()
    {
        if ($id = $this->argument('version_id')) {
            return $this->extractSpecific((int)$id);
        }
        return $this->option('rebuild') ? $this->extractAll(true) : $this->extractAll(false);
    }

    protected function extractSpecific(int $id): int
    {
        $v = DocumentVersion::find($id);
        if (!$v) { $this->error("Version {$id} not found."); return self::FAILURE; }

        $ok = $this->extractOne($v, true);
        $this->line($ok ? "✅ Done." : "⚠ Skipped.");
        return self::SUCCESS;
    }

    protected function extractAll(bool $rebuild): int
    {
        $this->info("Rebuilding text extraction for document versions...");
        $disk = Storage::disk('documents');
        $count = 0;

        DocumentVersion::query()
            ->orderBy('id')
            ->chunkById(200, function ($versions) use (&$count, $disk, $rebuild) {
                foreach ($versions as $v) {
                    // skip jika sudah punya text dan bukan --rebuild
                    if (!$rebuild && !empty($v->plain_text)) { continue; }
                    // hanya proses PDF valid
                    if (empty($v->file_path) || !$disk->exists($v->file_path)) { continue; }
                    if (strtolower(pathinfo($v->file_path, PATHINFO_EXTENSION)) !== 'pdf') { continue; }
                    if ($this->extractOne($v, true)) { $count++; }
                }
            });

        $this->info("✅ Completed. Extracted text for {$count} versions.");
        return self::SUCCESS;
    }

    public function extractTextForVersion(DocumentVersion $v, $pdftotext = 'pdftotext'): ?string
    {
        $disk = Storage::disk('documents');
        if (empty($v->file_path) || !$disk->exists($v->file_path)) { return null; }
        if (strtolower(pathinfo($v->file_path, PATHINFO_EXTENSION)) !== 'pdf') { return null; }

        $tmpPdf = tempnam(sys_get_temp_dir(), 'pdf_');
        $tmpTxt = $tmpPdf . '.txt';
        file_put_contents($tmpPdf, $disk->get($v->file_path));

        // -layout menjaga tata Letak lebih stabil untuk diff
        $cmd = escapeshellcmd($pdftotext) . ' -layout ' . escapeshellarg($tmpPdf) . ' ' . escapeshellarg($tmpTxt);
        @shell_exec($cmd);

        $text = file_exists($tmpTxt) ? file_get_contents($tmpTxt) : null;
        @unlink($tmpPdf);
        @unlink($tmpTxt);
        return $text ? $this->clean($text) : null;
    }

    protected function extractOne(DocumentVersion $v, bool $save): bool
    {
        $text = $this->extractTextForVersion($v, env('PDFTOTEXT_PATH', 'pdftotext'));
        if (!$text) { return false; }
        if ($save) {
            $v->plain_text = $text;
            $v->summary_changed = trim(($v->summary_changed ?? '').' Extracted from PDF.');
            $v->save();
        }
        return true;
    }

    protected function clean(string $t): string
    {
        $t = str_replace(["\r\n","\r"], "\n", $t);
        $t = preg_replace('/[^\PC\n\t]/u', ' ', $t);
        $t = preg_replace('/[ \t]{2,}/', ' ', $t);
        $t = preg_replace("/\n{3,}/", "\n\n", $t);
        return trim($t);
    }
}
