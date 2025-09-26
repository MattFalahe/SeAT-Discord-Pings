<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up()
    {
        Schema::create('discord_roles', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('role_id')->comment('Discord Role ID');
            $table->string('mention_format')->comment('Format for mentioning @role_id');
            $table->string('color', 7)->nullable();
            $table->text('description')->nullable();
            $table->boolean('is_active')->default(true);
            $table->unsignedInteger('created_by');
            $table->timestamps();
            
            $table->index('is_active');
            $table->unique('role_id');
        });
    }

    public function down()
    {
        Schema::dropIfExists('discord_roles');
    }
};
