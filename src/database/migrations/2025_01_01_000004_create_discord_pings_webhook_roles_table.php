<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class CreateDiscordPingsWebhookRolesTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::create('discord_pings_webhook_roles', function (Blueprint $table) {
            $table->id();
            $table->foreignId('webhook_id')->constrained('discord_pings_webhooks')->onDelete('cascade');
            $table->integer('role_id')->unsigned();
            $table->timestamps();
            
            $table->unique(['webhook_id', 'role_id']);
            $table->index('role_id');
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::dropIfExists('discord_pings_webhook_roles');
    }
}
