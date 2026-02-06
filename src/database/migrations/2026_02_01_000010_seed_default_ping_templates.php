<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

class SeedDefaultPingTemplates extends Migration
{
    public function up()
    {
        // Only seed if no templates exist yet
        if (DB::table('discord_pings_templates')->count() > 0) {
            return;
        }

        $defaults = [
            [
                'name' => 'Standard CTA',
                'template' => 'Many hands make light work, the more people join the faster we\'re done!',
                'fields' => json_encode(['embed_type' => 'fleet', 'mention_type' => 'everyone']),
                'created_by' => null,
                'is_global' => true,
                'created_at' => now(),
                'updated_at' => now(),
            ],
            [
                'name' => 'Emergency',
                'template' => 'EMERGENCY! Hostiles in staging! All hands on deck NOW!',
                'fields' => json_encode(['embed_type' => 'fleet', 'mention_type' => 'everyone', 'embed_color' => '#FF0000']),
                'created_by' => null,
                'is_global' => true,
                'created_at' => now(),
                'updated_at' => now(),
            ],
            [
                'name' => 'Mining Op',
                'template' => 'Mining fleet forming! Boosts available. Join for some chill mining.',
                'fields' => json_encode(['embed_type' => 'fleet', 'mention_type' => 'here']),
                'created_by' => null,
                'is_global' => true,
                'created_at' => now(),
                'updated_at' => now(),
            ],
            [
                'name' => 'Roam Fleet',
                'template' => 'Roam fleet forming in 30 minutes. Kitchen sink doctrine, bring what you can fly!',
                'fields' => json_encode(['embed_type' => 'fleet', 'mention_type' => 'here']),
                'created_by' => null,
                'is_global' => true,
                'created_at' => now(),
                'updated_at' => now(),
            ],
            [
                'name' => 'Strategic Op',
                'template' => 'STRATOP! Maximum numbers needed. This is a critical timer. PAPs will be given.',
                'fields' => json_encode(['embed_type' => 'fleet', 'pap_type' => 'Strategic', 'mention_type' => 'everyone']),
                'created_by' => null,
                'is_global' => true,
                'created_at' => now(),
                'updated_at' => now(),
            ],
        ];

        DB::table('discord_pings_templates')->insert($defaults);
    }

    public function down()
    {
        DB::table('discord_pings_templates')
            ->whereNull('created_by')
            ->where('is_global', true)
            ->delete();
    }
}
