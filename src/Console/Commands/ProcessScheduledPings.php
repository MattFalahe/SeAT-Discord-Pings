<?php

namespace MattFalahe\Seat\DiscordPings\Console\Commands;

use Illuminate\Console\Command;
use MattFalahe\Seat\DiscordPings\Jobs\SendScheduledPing;

class ProcessScheduledPings extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'discordpings:process-scheduled';
    
    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Process scheduled Discord pings that are due';

    /**
     * Execute the console command.
     *
     * @return int
     */
    public function handle()
    {
        $this->info('Processing scheduled Discord pings...');
        
        // Dispatch the job to process scheduled pings
        dispatch(new SendScheduledPing());
        
        $this->info('Scheduled pings processing job dispatched.');
        
        return 0;
    }
}
