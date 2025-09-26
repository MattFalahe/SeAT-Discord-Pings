<?php

use Illuminate\Database\Migrations\Migration;
use Seat\Services\Models\Schedule;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up()
    {
        // Add scheduled ping processor
        Schedule::firstOrCreate(
            ['command' => 'discordpings:process-scheduled'],
            [
                'expression'        => '* * * * *', // Every minute
                'allow_overlap'     => false,
                'allow_maintenance' => false,
            ]
        );

        // Add history cleanup
        Schedule::firstOrCreate(
            ['command' => 'discordpings:cleanup-history'],
            [
                'expression'        => '0 2 * * *', // Daily at 2 AM
                'allow_overlap'     => false,
                'allow_maintenance' => false,
            ]
        );
    }

    /**
     * Reverse the migrations.
     */
    public function down()
    {
        Schedule::where('command', 'discordpings:process-scheduled')->delete();
        Schedule::where('command', 'discordpings:cleanup-history')->delete();
    }
};
