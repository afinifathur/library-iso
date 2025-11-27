<?php

namespace App\Console;

use Illuminate\Console\Scheduling\Schedule;
use Illuminate\Foundation\Console\Kernel as ConsoleKernel;

class Kernel extends ConsoleKernel
{
    /**
     * The Artisan commands provided by your application.
     *
     * Tambahkan command import di sini.
     *
     * @var array
     */
    protected $commands = [
        \App\Console\Commands\ImportDocumentsCommand::class,
        \App\Console\Commands\PopulateShortCodeCommand::class,
         \App\Console\Commands\CleanOldDrafts::class,
          \App\Console\Commands\ImportDocsFromImportsFolder::class,
          \App\Console\Commands\ExtractMissingText::class,
          \App\Console\Commands\ReextractDocumentTextCommand::class,
          
    ];

    protected function schedule(Schedule $schedule)
    {
        // $schedule->command('inspire')->hourly();
    }

    protected function commands()
    {
        $this->load(__DIR__.'/Commands');

        require base_path('routes/console.php');
    }
    
}
