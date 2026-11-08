<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class CreateDiscordPingRequestPresetsTable extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('discord_ping_request_presets')) {
            return;
        }

        Schema::create('discord_ping_request_presets', function (Blueprint $table) {
            $table->id();
            $table->unsignedInteger('user_id');
            $table->unsignedBigInteger('button_id');
            $table->string('name', 64);
            $table->json('answers')->nullable();
            $table->timestamps();

            $table->unique(['user_id', 'button_id', 'name'], 'dp_presets_user_button_name');
            $table->foreign('button_id', 'dp_presets_button_fk')
                ->references('id')
                ->on('discord_ping_request_buttons')
                ->onDelete('cascade');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('discord_ping_request_presets');
    }
}
