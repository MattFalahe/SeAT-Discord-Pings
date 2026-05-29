<?php
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Link a scheduled ping to the tactical event (structure timer) it was
 * formed up for. Set when an FC clicks "Schedule formup ping" from the
 * tactical event modal or the FC Opportunities board.
 *
 * Pure informational correlation: no foreign key constraint, because
 * tactical events are pruned by the cleanup job after retention_days
 * and we want the historical scheduled-ping rows to survive that.
 */
return new class extends Migration {
    public function up()
    {
        if (Schema::hasColumn('discord_scheduled_pings', 'tactical_event_id')) {
            return;
        }

        Schema::table('discord_scheduled_pings', function (Blueprint $table) {
            $table->unsignedBigInteger('tactical_event_id')->nullable()->after('user_id');
            $table->index('tactical_event_id');
        });
    }

    public function down()
    {
        if (! Schema::hasColumn('discord_scheduled_pings', 'tactical_event_id')) {
            return;
        }

        Schema::table('discord_scheduled_pings', function (Blueprint $table) {
            $table->dropIndex(['tactical_event_id']);
            $table->dropColumn('tactical_event_id');
        });
    }
};
