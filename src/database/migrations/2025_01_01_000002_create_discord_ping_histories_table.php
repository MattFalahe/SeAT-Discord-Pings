<?php
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up()
    {
        Schema::create('discord_ping_histories', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('webhook_id');
            $table->unsignedInteger('user_id');
            $table->string('user_name');
            $table->text('message');
            $table->json('fields')->nullable();
            $table->json('webhook_response')->nullable();
            $table->string('status')->default('sent');
            $table->text('error_message')->nullable();
            $table->string('discord_message_id')->nullable();
            $table->timestamps();
            
            $table->foreign('webhook_id')->references('id')->on('discord_webhooks')->onDelete('cascade');
            $table->index(['user_id', 'created_at']);
            $table->index('status');
        });
    }

    public function down()
    {
        Schema::dropIfExists('discord_ping_histories');
    }
};
