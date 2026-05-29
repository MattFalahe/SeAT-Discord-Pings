<?php
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Additive column on the released discord_webhooks table. When set, the
 * webhook receives pre-timer reminder pings for structure timers ingested
 * from Manager Core's EventBus. hasColumn-guarded so re-runs are safe.
 */
return new class extends Migration {
    public function up()
    {
        if (Schema::hasColumn('discord_webhooks', 'receives_structure_alerts')) {
            return;
        }

        Schema::table('discord_webhooks', function (Blueprint $table) {
            $table->boolean('receives_structure_alerts')->default(false)->after('is_active');
        });
    }

    public function down()
    {
        if (! Schema::hasColumn('discord_webhooks', 'receives_structure_alerts')) {
            return;
        }

        Schema::table('discord_webhooks', function (Blueprint $table) {
            $table->dropColumn('receives_structure_alerts');
        });
    }
};
