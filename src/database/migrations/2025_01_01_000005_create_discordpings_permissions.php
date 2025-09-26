<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Seat\Web\Models\Acl\Permission;

return new class extends Migration
{
    /**
     * The permissions to insert
     */
    private $permissions = [
        'discordpings.view',
        'discordpings.send',
        'discordpings.send_multiple',
        'discordpings.manage_webhooks',
        'discordpings.view_history',
        'discordpings.view_all_history',
        'discordpings.manage_scheduled',
    ];

    /**
     * Run the migrations.
     */
    public function up()
    {
        // Create permissions in the database
        foreach ($this->permissions as $permission) {
            Permission::firstOrCreate([
                'title' => $permission
            ]);
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down()
    {
        // Remove our permissions
        Permission::whereIn('title', $this->permissions)->delete();
    }
};
