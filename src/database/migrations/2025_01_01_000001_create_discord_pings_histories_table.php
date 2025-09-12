<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class CreateDiscordPingsHistoriesTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::create('discord_pings_histories', function (Blueprint $table) {
            $table->id();
            $table->foreignId('webhook_id')->constrained('discord_pings_webhooks')->onDelete('cascade');
            $table->integer('user_id')->unsigned();
            $table->string('user_name');
            $table->text('message');
            $table->json('fields')->nullable();
            $table->json('webhook_response')->nullable();
            $table->string('status')->default('sent');
            $table->text('error_message')->nullable();
            $table->string('discord_message_id')->nullable();
            $table->timestamps();
            
            $table->index(['user_id', 'created_at']);
            $table->index('status');
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::dropIfExists('discord_pings_histories');
    }
}
