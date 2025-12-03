<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use App\Models\Document;
use App\Models\DocumentVersion;

class BuildDocumentRelationsCommand extends Command
{
    protected $signature = 'documents:build-relations';

    protected $description = 'Rebuild previous/next relations for document versions.';


    public function handle()
    {
        $this->info("Rebuilding version relations...");

        $docs = Document::with('versions')->get();
        $total = 0;

        foreach ($docs as $doc) {
            $versions = $doc->versions
                ->sortBy('created_at')
                ->values();

            $prevId = null;

            foreach ($versions as $v) {
                $v->prev_version_id = $prevId;
                $v->save();

                $prevId = $v->id;
                $total++;
            }
        }

        $this->info("✅ Completed. Processed {$total} versions.");
        return Command::SUCCESS;
    }
}
