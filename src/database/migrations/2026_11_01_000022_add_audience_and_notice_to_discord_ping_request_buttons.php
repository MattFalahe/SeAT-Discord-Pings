<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class AddAudienceAndNoticeToDiscordPingRequestButtons extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('discord_ping_request_buttons')) {
            return;
        }

        Schema::table('discord_ping_request_buttons', function (Blueprint $table) {
            // Told to members on the button. It's never posted and doesn't decide who gets pinged.
            if (! Schema::hasColumn('discord_ping_request_buttons', 'ping_notice')) {
                $table->string('ping_notice', 100)->nullable()->after('mention_role_id');
            }

            if (! Schema::hasColumn('discord_ping_request_buttons', 'alliance_id')) {
                $table->unsignedBigInteger('alliance_id')->nullable()->after('corporation_id');
            }

            // Full permission names, and holding any one of them is enough. Null means no limit.
            if (! Schema::hasColumn('discord_ping_request_buttons', 'audience_permissions')) {
                $table->json('audience_permissions')->nullable()->after('alliance_id');
            }
        });

        // A SeAT role could point at people who can't open the plugin at all.
        // This column never got further than dev, so it can just go.
        if (Schema::hasColumn('discord_ping_request_buttons', 'role_id')) {
            Schema::table('discord_ping_request_buttons', function (Blueprint $table) {
                $table->dropColumn('role_id');
            });
        }
    }

    public function down(): void
    {
        if (! Schema::hasTable('discord_ping_request_buttons')) {
            return;
        }

        foreach (['ping_notice', 'alliance_id', 'audience_permissions'] as $column) {
            if (Schema::hasColumn('discord_ping_request_buttons', $column)) {
                Schema::table('discord_ping_request_buttons', function (Blueprint $table) use ($column) {
                    $table->dropColumn($column);
                });
            }
        }

        if (! Schema::hasColumn('discord_ping_request_buttons', 'role_id')) {
            Schema::table('discord_ping_request_buttons', function (Blueprint $table) {
                $table->unsignedBigInteger('role_id')->nullable()->after('corporation_id');
            });
        }
    }
}
