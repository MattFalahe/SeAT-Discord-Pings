<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up()
    {
        Schema::create('discord_channels', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('channel_id')->comment('Discord Channel ID');
            $table->string('server_id')->comment('Discord Server/Guild ID');
            $table->string('channel_url')->comment('Full Discord channel URL');
            $table->string('channel_type')->default('text')->comment('text, voice, announcement, etc');
            $table->text('description')->nullable();
            $table->boolean('is_active')->default(true);
            $table->unsignedInteger('created_by');
            $table->timestamps();
            
            $table->index('is_active');
            $table->unique('channel_id');
        });
    }

    public function down()
    {
        Schema::dropIfExists('discord_channels');
    }
};
