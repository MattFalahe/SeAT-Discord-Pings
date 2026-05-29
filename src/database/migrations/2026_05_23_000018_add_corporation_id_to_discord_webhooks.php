<?php
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Per-webhook corporation scope for structure-timer alert routing.
 *
 * NULL  = webhook receives alerts for ANY corporation (backwards-
 *         compatible default; existing webhooks behave as before).
 * Set   = webhook only receives alerts whose event corporation_id
 *         matches this value. Combined with a NULL-corp webhook the
 *         operator can fan out across multiple corp channels OR keep
 *         a single org-wide channel.
 *
 * No FK constraint: SeAT's corporation_infos can be pruned without
 * needing to cascade-delete webhooks; the webhook just stops matching
 * any incoming events until the operator either deletes it or repoints
 * it.
 */
return new class extends Migration {
    public function up()
    {
        if (Schema::hasColumn('discord_webhooks', 'corporation_id')) {
            return;
        }

        Schema::table('discord_webhooks', function (Blueprint $table) {
            $table->unsignedBigInteger('corporation_id')->nullable()->after('receives_structure_alerts');
            $table->index('corporation_id');
        });
    }

    public function down()
    {
        if (! Schema::hasColumn('discord_webhooks', 'corporation_id')) {
            return;
        }

        Schema::table('discord_webhooks', function (Blueprint $table) {
            $table->dropIndex(['corporation_id']);
            $table->dropColumn('corporation_id');
        });
    }
};
