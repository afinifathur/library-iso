<?php
namespace App\Console\Commands;

use Illuminate\Console\Command;
use App\Models\DocumentVersion;
use Carbon\Carbon;

class CleanOldDrafts extends Command
{
    protected $signature = 'documents:clean-drafts {days=90}';
    protected $description = 'Delete draft versions older than days (default 90)';

    public function handle()
    {
        $days = (int)$this->argument('days');
        $cut = Carbon::now()->subDays($days);
        $old = DocumentVersion::whereIn('status',['draft','rejected'])->where('created_at','<',$cut)->get();
        $count = $old->count();
        foreach($old as $v) $v->delete();
        $this->info("Deleted {$count} draft versions older than {$days} days.");
    }
}
