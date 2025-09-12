<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class CreateDiscordPingsTemplatesTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::create('discord_pings_templates', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->text('template');
            $table->json('fields')->nullable();
            $table->integer('created_by')->unsigned()->nullable();
            $table->boolean('is_global')->default(false);
            $table->timestamps();
            
            $table->index('is_global');
            $table->index('created_by');
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::dropIfExists('discord_pings_templates');
    }
}
