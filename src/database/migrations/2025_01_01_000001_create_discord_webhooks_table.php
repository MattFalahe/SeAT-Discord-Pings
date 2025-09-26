<?php
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up()
    {
        Schema::create('discord_webhooks', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->text('webhook_url');
            $table->string('channel_type')->nullable();
            $table->string('embed_color', 7)->default('#5865F2');
            $table->boolean('enable_mentions')->default(false);
            $table->string('default_mention')->nullable();
            $table->boolean('is_active')->default(true);
            $table->unsignedInteger('created_by');
            $table->timestamps();
            
            $table->index('is_active');
            $table->index('channel_type');
            $table->index('created_by');
        });
    }

    public function down()
    {
        Schema::dropIfExists('discord_webhooks');
    }
};
