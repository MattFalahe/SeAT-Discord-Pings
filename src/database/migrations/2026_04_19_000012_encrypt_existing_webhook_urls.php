<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up()
    {
        DB::table('discord_webhooks')
            ->select('id', 'webhook_url')
            ->orderBy('id')
            ->cursor()
            ->each(function ($row) {
                // Idempotent: skip rows that already decrypt successfully.
                try {
                    Crypt::decryptString($row->webhook_url);
                    return;
                } catch (\Throwable $e) {
                    // Not encrypted yet — fall through to encrypt.
                }

                DB::table('discord_webhooks')
                    ->where('id', $row->id)
                    ->update(['webhook_url' => Crypt::encryptString($row->webhook_url)]);
            });
    }

    public function down()
    {
        DB::table('discord_webhooks')
            ->select('id', 'webhook_url')
            ->orderBy('id')
            ->cursor()
            ->each(function ($row) {
                try {
                    $plain = Crypt::decryptString($row->webhook_url);
                } catch (\Throwable $e) {
                    return;
                }

                DB::table('discord_webhooks')
                    ->where('id', $row->id)
                    ->update(['webhook_url' => $plain]);
            });
    }
};
