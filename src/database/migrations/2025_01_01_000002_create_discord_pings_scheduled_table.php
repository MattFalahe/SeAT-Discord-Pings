<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class CreateDiscordPingsScheduledTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::create('discord_pings_scheduled', function (Blueprint $table) {
            $table->id();
            $table->foreignId('webhook_id')->constrained('discord_pings_webhooks')->onDelete('cascade');
            $table->integer('user_id')->unsigned();
            $table->text('message');
            $table->json('fields')->nullable();
            $table->datetime('scheduled_at');
            $table->string('repeat_interval')->nullable();
            $table->datetime('repeat_until')->nullable();
            $table->boolean('is_active')->default(true);
            $table->datetime('last_sent_at')->nullable();
            $table->integer('times_sent')->default(0);
            $table->timestamps();
            
            $table->index(['scheduled_at', 'is_active']);
            $table->index('repeat_interval');
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::dropIfExists('discord_pings_scheduled');
    }
}
