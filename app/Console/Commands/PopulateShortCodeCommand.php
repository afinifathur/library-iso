<?php
namespace App\Console\Commands;

use Illuminate\Console\Command;
use App\Models\Document;

class PopulateShortCodeCommand extends Command
{
    protected $signature = 'documents:populate-shortcode';
    protected $description = 'Populate documents.short_code from doc_code prefix (e.g. IK.QC.01 -> IK)';

    public function handle()
    {
        $this->info('Scanning documents...');
        $count = 0;
        foreach (Document::cursor() as $doc) {
            if ($doc->doc_code) {
                // Extract prefix letters before first dot or dash
                $code = strtoupper(trim($doc->doc_code));
                // split by non-alphanumeric if needed
                if (preg_match('/^([A-Z0-9]{1,6})[\\.\\-_]/i', $code, $m)) {
                    $prefix = strtoupper($m[1]);
                } else {
                    // fallback: split by dot
                    $parts = preg_split('/[\\.\\-_]/', $code);
                    $prefix = strtoupper($parts[0] ?? '');
                }
                $prefix = $prefix ?: null;
                if ($prefix !== $doc->short_code) {
                    $doc->short_code = $prefix;
                    $doc->save();
                    $count++;
                }
            }
        }
        $this->info("Done. Updated {$count} documents.");
        return 0;
    }
}
