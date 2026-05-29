<?php
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Per-webhook opt-in flag for pre-expiry mining-extraction alerts published
 * by Mining Manager v2.0.1+ via Manager Core's EventBus.
 *
 * Distinct from receives_structure_alerts because the audiences typically
 * differ: defense fleet alerts (structure timers) go to a tactical channel,
 * while mining alerts (T-2h to extraction expiry) go to the industry
 * channel. Operators with a single combined channel can simply flag both.
 *
 * Default false: existing webhooks opt in on the Webhook edit page.
 * Standalone-safe: with neither Manager Core nor Mining Manager installed,
 * the column exists but nothing ever publishes the source events.
 */
return new class extends Migration {
    public function up()
    {
        if (Schema::hasColumn('discord_webhooks', 'receives_mining_alerts')) {
            return;
        }

        Schema::table('discord_webhooks', function (Blueprint $table) {
            $table->boolean('receives_mining_alerts')->default(false)->after('corporation_id');
        });
    }

    public function down()
    {
        if (! Schema::hasColumn('discord_webhooks', 'receives_mining_alerts')) {
            return;
        }

        Schema::table('discord_webhooks', function (Blueprint $table) {
            $table->dropColumn('receives_mining_alerts');
        });
    }
};
