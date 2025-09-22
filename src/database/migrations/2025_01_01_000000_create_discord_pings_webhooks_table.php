<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class CreateDiscordPingsWebhooksTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::create('discord_pings_webhooks', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->text('webhook_url');
            $table->string('channel_type')->nullable();
            $table->string('embed_color', 7)->default('#5865F2');
            $table->boolean('enable_mentions')->default(false);
            $table->string('default_mention')->nullable();
            $table->boolean('is_active')->default(true);
            $table->integer('created_by')->unsigned();
            $table->timestamps();
            
            $table->index('is_active');
            $table->index('channel_type');
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::dropIfExists('discord_pings_webhooks');
    }
}
