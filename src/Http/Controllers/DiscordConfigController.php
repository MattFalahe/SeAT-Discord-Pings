<?php

namespace MattFalahe\Seat\DiscordPings\Http\Controllers;

use Illuminate\Routing\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use MattFalahe\Seat\DiscordPings\Models\DiscordRole;
use MattFalahe\Seat\DiscordPings\Models\DiscordChannel;
use MattFalahe\Seat\DiscordPings\Models\DiscordWebhook;

class DiscordConfigController extends Controller
{
    /**
     * Show Discord configuration page
     */
    public function index()
    {
        $webhooks = DiscordWebhook::all();
        $roles = DiscordRole::all();
        $channels = DiscordChannel::all();
        
        return view('discordpings::config.index', compact('webhooks', 'roles', 'channels'));
    }
    
    /**
     * Store new Discord role
     */
    public function storeRole(Request $request)
    {
        try {
            $validated = $request->validate([
                'name' => 'required|string|max:255',
                'role_id' => 'required|string|max:255',
                'color' => 'nullable|string|regex:/^#[0-9A-Fa-f]{6}$/',
                'description' => 'nullable|string|max:500',
            ]);
            
            // Parse role ID if it's a mention format
            $roleId = DiscordRole::parseRoleId($validated['role_id']) ?? $validated['role_id'];
            
            // Check if role already exists
            if (DiscordRole::where('role_id', $roleId)->exists()) {
                return redirect()->back()->with('error', 'This Discord role is already configured.');
            }
            
            DiscordRole::create([
                'name' => $validated['name'],
                'role_id' => $roleId,
                'mention_format' => "<@&{$roleId}>",
                'color' => $validated['color'] ?? null,
                'description' => $validated['description'] ?? null,
                'is_active' => true,
                'created_by' => auth()->id(),
            ]);
            
            return redirect()->back()->with('success', 'Discord role added successfully!');
            
        } catch (\Exception $e) {
            Log::error('Discord role creation error: ' . $e->getMessage());
            return redirect()->back()->with('error', 'Failed to add Discord role.');
        }
    }
    
    /**
     * Store new Discord channel
     */
    public function storeChannel(Request $request)
    {
        try {
            $validated = $request->validate([
                'name' => 'required|string|max:255',
                'channel_url' => 'required|url|regex:/discord\.com\/channels\/\d+\/\d+/',
                'channel_type' => 'nullable|string|in:text,voice,announcement,forum,stage',
                'description' => 'nullable|string|max:500',
            ]);
            
            // Parse channel URL
            $parsed = DiscordChannel::parseChannelUrl($validated['channel_url']);
            
            if (!$parsed) {
                return redirect()->back()->with('error', 'Invalid Discord channel URL format.');
            }
            
            // Check if channel already exists
            if (DiscordChannel::where('channel_id', $parsed['channel_id'])->exists()) {
                return redirect()->back()->with('error', 'This Discord channel is already configured.');
            }
            
            DiscordChannel::create([
                'name' => $validated['name'],
                'channel_id' => $parsed['channel_id'],
                'server_id' => $parsed['server_id'],
                'channel_url' => $validated['channel_url'],
                'channel_type' => $validated['channel_type'] ?? 'text',
                'description' => $validated['description'] ?? null,
                'is_active' => true,
                'created_by' => auth()->id(),
            ]);
            
            return redirect()->back()->with('success', 'Discord channel added successfully!');
            
        } catch (\Exception $e) {
            Log::error('Discord channel creation error: ' . $e->getMessage());
            return redirect()->back()->with('error', 'Failed to add Discord channel.');
        }
    }
    
    /**
     * Delete Discord role
     */
    public function destroyRole($id)
    {
        try {
            $role = DiscordRole::findOrFail($id);
            $role->delete();
            
            return redirect()->back()->with('success', 'Discord role removed successfully!');
        } catch (\Exception $e) {
            Log::error('Discord role deletion error: ' . $e->getMessage());
            return redirect()->back()->with('error', 'Failed to remove Discord role.');
        }
    }
    
    /**
     * Delete Discord channel
     */
    public function destroyChannel($id)
    {
        try {
            $channel = DiscordChannel::findOrFail($id);
            $channel->delete();
            
            return redirect()->back()->with('success', 'Discord channel removed successfully!');
        } catch (\Exception $e) {
            Log::error('Discord channel deletion error: ' . $e->getMessage());
            return redirect()->back()->with('error', 'Failed to remove Discord channel.');
        }
    }
    
    /**
     * Toggle role active status
     */
    public function toggleRole($id)
    {
        try {
            $role = DiscordRole::findOrFail($id);
            $role->is_active = !$role->is_active;
            $role->save();
            
            $status = $role->is_active ? 'activated' : 'deactivated';
            return response()->json(['success' => true, 'message' => "Role {$status} successfully"]);
        } catch (\Exception $e) {
            return response()->json(['success' => false, 'message' => 'Failed to toggle role status'], 500);
        }
    }
    
    /**
     * Toggle channel active status
     */
    public function toggleChannel($id)
    {
        try {
            $channel = DiscordChannel::findOrFail($id);
            $channel->is_active = !$channel->is_active;
            $channel->save();
            
            $status = $channel->is_active ? 'activated' : 'deactivated';
            return response()->json(['success' => true, 'message' => "Channel {$status} successfully"]);
        } catch (\Exception $e) {
            return response()->json(['success' => false, 'message' => 'Failed to toggle channel status'], 500);
        }
    }
}
