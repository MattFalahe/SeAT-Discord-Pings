<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class AddSectionsToDiscordPingRequestButtons extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('discord_ping_request_buttons') || Schema::hasColumn('discord_ping_request_buttons', 'sections')) {
            return;
        }

        Schema::table('discord_ping_request_buttons', function (Blueprint $table) {
            // One entry per section: its name, its kind of box, and whether it's
            // asked after the press. A button nobody has saved since this arrived
            // keeps null here and is still read from starter_text.
            $table->json('sections')->nullable()->after('starter_text');
        });
    }

    public function down(): void
    {
        if (Schema::hasTable('discord_ping_request_buttons') && Schema::hasColumn('discord_ping_request_buttons', 'sections')) {
            Schema::table('discord_ping_request_buttons', function (Blueprint $table) {
                $table->dropColumn('sections');
            });
        }
    }
}
