<?php

namespace DiscordPings\Database\Seeders;

use Seat\Services\Seeding\AbstractScheduleSeeder;

class ScheduleSeeder extends AbstractScheduleSeeder
{
    public function getSchedules(): array
    {
        return [
            [
                'command'           => 'discordpings:process-scheduled',
                'expression'        => '* * * * *',
                'allow_overlap'     => false,
                'allow_maintenance' => false,
                'ping_before'       => null,
                'ping_after'        => null,
            ],
            [
                'command'           => 'discordpings:cleanup-history',
                'expression'        => '0 2 * * *',
                'allow_overlap'     => false,
                'allow_maintenance' => false,
                'ping_before'       => null,
                'ping_after'        => null,
            ],
        ];
    }

    public function getDeprecatedSchedules(): array
    {
        return [];
    }
}
