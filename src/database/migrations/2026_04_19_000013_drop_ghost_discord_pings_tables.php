<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Ghost tables left over from an earlier naming pass. The live code uses
     * discord_webhooks, discord_ping_histories, discord_scheduled_pings, and
     * discord_webhook_roles. These four tables are created by migrations
     * 000000..000004 but never written to by any model, controller, or job.
     *
     * Ordered dependents-first so FK constraints drop cleanly.
     */
    private array $ghosts = [
        'discord_pings_histories',
        'discord_pings_scheduled',
        'discord_pings_webhook_roles',
        'discord_pings_webhooks',
    ];

    public function up()
    {
        foreach ($this->ghosts as $table) {
            if (Schema::hasTable($table) && DB::table($table)->count() > 0) {
                throw new \RuntimeException(
                    "Refusing to drop ghost table '{$table}': it contains rows. " .
                    "No current code path writes to it — investigate the source " .
                    "before allowing this migration to proceed."
                );
            }
        }

        foreach ($this->ghosts as $table) {
            if (Schema::hasTable($table)) {
                Schema::drop($table);
            }
        }
    }

    public function down()
    {
        // No-op: these tables were dead code and should not be recreated.
    }
};
