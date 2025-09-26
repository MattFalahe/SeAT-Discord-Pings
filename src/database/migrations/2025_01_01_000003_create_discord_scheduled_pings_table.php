<?php
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up()
    {
        Schema::create('discord_scheduled_pings', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('webhook_id');
            $table->unsignedInteger('user_id');
            $table->text('message');
            $table->json('fields')->nullable();
            $table->datetime('scheduled_at');
            $table->string('repeat_interval')->nullable();
            $table->datetime('repeat_until')->nullable();
            $table->boolean('is_active')->default(true);
            $table->datetime('last_sent_at')->nullable();
            $table->integer('times_sent')->default(0);
            $table->timestamps();
            
            $table->foreign('webhook_id')->references('id')->on('discord_webhooks')->onDelete('cascade');
            $table->index(['scheduled_at', 'is_active']);
            $table->index('user_id');
        });
    }

    public function down()
    {
        Schema::dropIfExists('discord_scheduled_pings');
    }
};
