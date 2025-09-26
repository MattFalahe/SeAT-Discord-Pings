<?php

namespace MattFalahe\Seat\DiscordPings\Console\Commands;

use Illuminate\Console\Command;
use Seat\Web\Models\Acl\Permission;
use Seat\Web\Models\Acl\Role;

class SetupPermissionsCommand extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'discordpings:setup 
                            {--reset : Reset all Discord Pings permissions}
                            {--grant-admin : Grant all permissions to the Admin role}';
    
    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Setup Discord Pings permissions in database';

    /**
     * The permissions to create
     *
     * @var array
     */
    protected $permissions = [
        'discordpings.view' => 'View Discord Pings',
        'discordpings.send' => 'Send Discord Pings',
        'discordpings.send_multiple' => 'Send to Multiple Webhooks',
        'discordpings.manage_webhooks' => 'Manage Webhooks',
        'discordpings.view_history' => 'View Ping History',
        'discordpings.view_all_history' => 'View All Users History',
        'discordpings.manage_scheduled' => 'Manage Scheduled Pings',
    ];

    /**
     * Execute the console command.
     *
     * @return int
     */
    public function handle()
    {
        $this->info('Setting up Discord Pings permissions...');
        
        // Handle reset option
        if ($this->option('reset')) {
            $this->resetPermissions();
        }
        
        // Create or update permissions
        $created = 0;
        $existing = 0;
        
        foreach ($this->permissions as $name => $description) {
            $permission = Permission::firstOrCreate(
                ['title' => $name],
                ['description' => $description]
            );
            
            if ($permission->wasRecentlyCreated) {
                $created++;
                $this->info("✓ Created permission: {$name}");
            } else {
                $existing++;
                $this->comment("• Permission already exists: {$name}");
            }
        }
        
        $this->newLine();
        $this->info("Summary: {$created} created, {$existing} already existed");
        
        // Handle grant-admin option
        if ($this->option('grant-admin')) {
            $this->grantAdminPermissions();
        }
        
        $this->newLine();
        $this->info('Setup complete! You can now assign these permissions in SeAT\'s Access Management.');
        $this->info('Don\'t forget to assign "discordpings.view" permission to see the sidebar menu.');
        
        return 0;
    }

    /**
     * Reset all Discord Pings permissions
     *
     * @return void
     */
    protected function resetPermissions()
    {
        $this->warn('Resetting all Discord Pings permissions...');
        
        $count = Permission::whereIn('title', array_keys($this->permissions))->delete();
        
        $this->info("Removed {$count} existing permissions.");
        $this->newLine();
    }

    /**
     * Grant all permissions to the Admin role
     *
     * @return void
     */
    protected function grantAdminPermissions()
    {
        $this->info('Granting permissions to Admin role...');
        
        // Find the admin role (usually ID 1, but let's be safe)
        $adminRole = Role::where('title', 'admin')
            ->orWhere('title', 'Administrator')
            ->orWhere('id', 1)
            ->first();
        
        if (!$adminRole) {
            $this->error('Could not find Admin role!');
            return;
        }
        
        // Get all our permission IDs
        $permissionIds = Permission::whereIn('title', array_keys($this->permissions))
            ->pluck('id')
            ->toArray();
        
        // Sync permissions to admin role
        $adminRole->permissions()->syncWithoutDetaching($permissionIds);
        
        $this->info("✓ Granted all Discord Pings permissions to {$adminRole->title} role");
    }
}
