<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class CreateDiscordPingRequestButtonsTable extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('discord_ping_request_buttons')) {
            return;
        }

        Schema::create('discord_ping_request_buttons', function (Blueprint $table) {
            $table->id();
            $table->string('name', 100);

            // No foreign key on purpose. A button has to outlive its webhook so
            // Settings can show which ones lost theirs, and the example buttons
            // start with none at all.
            $table->unsignedBigInteger('webhook_id')->nullable();

            // The Discord role that gets pinged. Null posts without a ping.
            $table->string('mention_role_id', 32)->nullable();
            $table->text('starter_text')->nullable();

            // emergency | request
            $table->string('type', 16)->default('request');
            $table->unsignedSmallInteger('cooldown_minutes')->default(15);

            // Who sees the button, same two gates as discord_tactical_events.
            // Null means no gate. role_id is a SeAT role, not a Discord one.
            $table->unsignedBigInteger('corporation_id')->nullable();
            $table->unsignedBigInteger('role_id')->nullable();

            $table->boolean('is_active')->default(true);
            $table->unsignedInteger('sort_order')->default(0);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('discord_ping_request_buttons');
    }
}
