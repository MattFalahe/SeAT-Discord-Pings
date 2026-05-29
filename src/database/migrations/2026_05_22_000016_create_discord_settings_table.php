<?php
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Generic key-value store for UI-editable plugin settings (as opposed to the
 * install defaults in discordpings.config.php). First consumer is the
 * structure-timer pre-timer-ping master toggle on the Settings page.
 */
return new class extends Migration {
    public function up()
    {
        if (Schema::hasTable('discord_settings')) {
            return;
        }

        Schema::create('discord_settings', function (Blueprint $table) {
            $table->id();
            $table->string('key')->unique();
            $table->text('value')->nullable();
            $table->timestamps();
        });
    }

    public function down()
    {
        Schema::dropIfExists('discord_settings');
    }
};
