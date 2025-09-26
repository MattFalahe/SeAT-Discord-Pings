<?php

namespace MattFalahe\Seat\DiscordPings\Database\Seeders;

use Illuminate\Database\Seeder;
use Seat\Services\Models\Schedule;

class ScheduleSeeder extends Seeder
{
    /**
     * List of schedules to seed
     *
     * @var array
     */
    protected $schedules = [
        [
            'command'           => 'discordpings:process-scheduled',
            'expression'        => '* * * * *', // Run every minute
            'allow_overlap'     => false,
            'allow_maintenance' => false,
            'ping_before'       => null,
            'ping_after'        => null,
        ],
        [
            'command'           => 'discordpings:cleanup-history',
            'expression'        => '0 2 * * *', // Run daily at 2 AM
            'allow_overlap'     => false,
            'allow_maintenance' => false,
            'ping_before'       => null,
            'ping_after'        => null,
        ],
    ];

    /**
     * Run the database seeds.
     *
     * @return void
     */
    public function run()
    {
        foreach ($this->schedules as $schedule) {
            $existing = Schedule::where('command', $schedule['command'])->first();
            
            if (!$existing) {
                Schedule::create($schedule);
                $this->command->info('Seeded schedule for: ' . $schedule['command']);
            } else {
                $this->command->info('Schedule already exists for: ' . $schedule['command']);
            }
        }
    }
}
