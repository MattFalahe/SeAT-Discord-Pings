<?php

namespace MattFalahe\Seat\DiscordPings\Console\Commands;

use Illuminate\Console\Command;
use MattFalahe\Seat\DiscordPings\Jobs\CleanupOldHistory;

class CleanupPingHistory extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'discordpings:cleanup-history';
    
    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Clean up old Discord ping history based on retention settings';

    /**
     * Execute the console command.
     *
     * @return int
     */
    public function handle()
    {
        $retentionDays = config('discordpings.history_retention_days', 90);
        $this->info("Cleaning up Discord ping history older than {$retentionDays} days...");
        
        // Dispatch the cleanup job
        dispatch(new CleanupOldHistory());
        
        $this->info('History cleanup job dispatched.');
        
        return 0;
    }
}
