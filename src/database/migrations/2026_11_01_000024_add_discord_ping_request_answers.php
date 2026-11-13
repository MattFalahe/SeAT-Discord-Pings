<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

class AddDiscordPingRequestAnswers extends Migration
{
    private const UNIQUE = 'dp_request_answers_request_user';
    private const FOREIGN = 'dp_request_answers_request_fk';

    public function up(): void
    {
        if (Schema::hasTable('discord_ping_request_buttons') && ! Schema::hasColumn('discord_ping_request_buttons', 'answer_defaults')) {
            Schema::table('discord_ping_request_buttons', function (Blueprint $table) {
                // What the Send form starts from when an FC answers one of this
                // button's requests. Null leaves it to the Send defaults.
                $table->json('answer_defaults')->nullable()->after('sections');
            });
        }

        if (! Schema::hasTable('discord_ping_request_answers')) {
            Schema::create('discord_ping_request_answers', function (Blueprint $table) {
                $table->id();

                // The request's own row in discord_ping_histories.
                $table->unsignedBigInteger('request_history_id');
                $table->unsignedInteger('user_id');
                $table->string('fc_name', 100);

                // The broadcast that answered it. No key, because History clears
                // its rows by age and this one can go before the request does.
                $table->unsignedBigInteger('broadcast_history_id')->nullable();
                $table->timestamps();
            });
        }

        // Apart from the create, so an install left half way by a failed run still gets them.
        // One row per FC, so a broadcast sent to several webhooks answers once.
        if (! $this->hasIndex('discord_ping_request_answers', self::UNIQUE)) {
            Schema::table('discord_ping_request_answers', function (Blueprint $table) {
                $table->unique(['request_history_id', 'user_id'], self::UNIQUE);
            });
        }

        // History's cleanup takes the answers with the request.
        if (Schema::hasTable('discord_ping_histories') && ! $this->hasForeignKey('discord_ping_request_answers', self::FOREIGN)) {
            Schema::table('discord_ping_request_answers', function (Blueprint $table) {
                $table->foreign('request_history_id', self::FOREIGN)
                    ->references('id')
                    ->on('discord_ping_histories')
                    ->onDelete('cascade');
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('discord_ping_request_answers');

        if (Schema::hasTable('discord_ping_request_buttons') && Schema::hasColumn('discord_ping_request_buttons', 'answer_defaults')) {
            Schema::table('discord_ping_request_buttons', function (Blueprint $table) {
                $table->dropColumn('answer_defaults');
            });
        }
    }

    private function hasIndex(string $table, string $index): bool
    {
        try {
            return DB::table('information_schema.STATISTICS')
                ->where('TABLE_SCHEMA', DB::getDatabaseName())
                ->where('TABLE_NAME', $table)
                ->where('INDEX_NAME', $index)
                ->exists();
        } catch (\Throwable $e) {
            return false;
        }
    }

    private function hasForeignKey(string $table, string $name): bool
    {
        try {
            return DB::table('information_schema.TABLE_CONSTRAINTS')
                ->where('TABLE_SCHEMA', DB::getDatabaseName())
                ->where('TABLE_NAME', $table)
                ->where('CONSTRAINT_NAME', $name)
                ->where('CONSTRAINT_TYPE', 'FOREIGN KEY')
                ->exists();
        } catch (\Throwable $e) {
            return false;
        }
    }
}
