<?php
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up()
    {
        Schema::create('discord_webhook_roles', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('webhook_id');
            $table->unsignedInteger('role_id');
            $table->timestamps();
            
            $table->foreign('webhook_id')->references('id')->on('discord_webhooks')->onDelete('cascade');
            $table->unique(['webhook_id', 'role_id']);
            $table->index('role_id');
        });
    }

    public function down()
    {
        Schema::dropIfExists('discord_webhook_roles');
    }
};
