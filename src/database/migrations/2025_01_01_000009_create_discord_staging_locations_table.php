<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    /**
     * Run the migrations.
     */
    public function up()
    {
        Schema::create('discord_staging_locations', function (Blueprint $table) {
            $table->id();
            $table->string('name')->comment('Friendly name like Home Staging');
            $table->string('system_name')->comment('System name like Jita');
            $table->string('structure_name')->nullable()->comment('Structure name if applicable');
            $table->text('description')->nullable();
            $table->boolean('is_active')->default(true);
            $table->boolean('is_default')->default(false);
            $table->unsignedInteger('created_by');
            $table->timestamps();
            
            $table->index('is_active');
            $table->index('is_default');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down()
    {
        Schema::dropIfExists('discord_staging_locations');
    }
};
