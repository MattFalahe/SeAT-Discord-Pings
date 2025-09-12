<?php

namespace MattFalahe\Seat\DiscordPings\Http\Controllers;

use Seat\Web\Http\Controllers\Controller;
use Illuminate\Http\Request;
use MattFalahe\Seat\DiscordPings\Models\DiscordWebhook;
use MattFalahe\Seat\DiscordPings\Helpers\DiscordHelper;
use Seat\Web\Models\Acl\Role;

class WebhookController extends Controller
{
    /**
     * Display webhooks
     */
    public function index()
    {
        $webhooks = DiscordWebhook::with('roles', 'histories')->get();
        return view('discord-pings::webhooks.index', compact('webhooks'));
    }

    /**
     * Show create form
     */
    public function create()
    {
        $roles = Role::all();
        return view('discord-pings::webhooks.create', compact('roles'));
    }

    /**
     * Store new webhook
     */
    public function store(Request $request)
    {
        $validated = $request->validate([
            'name' => 'required|string|max:255',
            'webhook_url' => 'required|url|starts_with:https://discord.com/api/webhooks/',
            'channel_type' => 'nullable|string|max:50',
            'embed_color' => 'required|string|regex:/^#[0-9A-Fa-f]{6}$/',
            'enable_mentions' => 'boolean',
            'default_mention' => 'nullable|string|max:100',
            'role_ids' => 'nullable|array',
            'role_ids.*' => 'exists:roles,id',
        ]);

        $webhook = DiscordWebhook::create([
            'name' => $validated['name'],
            'webhook_url' => $validated['webhook_url'],
            'channel_type' => $validated['channel_type'],
            'embed_color' => $validated['embed_color'],
            'enable_mentions' => $validated['enable_mentions'] ?? false,
            'default_mention' => $validated['default_mention'],
            'is_active' => true,
            'created_by' => auth()->id(),
        ]);

        if (!empty($validated['role_ids'])) {
            $webhook->roles()->sync($validated['role_ids']);
        }

        return redirect()->route('discord.pings.webhooks.index')
            ->with('success', 'Webhook created successfully!');
    }

    /**
     * Show edit form
     */
    public function edit($id)
    {
        $webhook = DiscordWebhook::with('roles')->findOrFail($id);
        $roles = Role::all();
        return view('discord-pings::webhooks.edit', compact('webhook', 'roles'));
    }

    /**
     * Update webhook
     */
    public function update(Request $request, $id)
    {
        $webhook = DiscordWebhook::findOrFail($id);

        $validated = $request->validate([
            'name' => 'required|string|max:255',
            'webhook_url' => 'required|url|starts_with:https://discord.com/api/webhooks/',
            'channel_type' => 'nullable|string|max:50',
            'embed_color' => 'required|string|regex:/^#[0-9A-Fa-f]{6}$/',
            'enable_mentions' => 'boolean',
            'default_mention' => 'nullable|string|max:100',
            'is_active' => 'boolean',
            'role_ids' => 'nullable|array',
            'role_ids.*' => 'exists:roles,id',
        ]);

        $webhook->update([
            'name' => $validated['name'],
            'webhook_url' => $validated['webhook_url'],
            'channel_type' => $validated['channel_type'],
            'embed_color' => $validated['embed_color'],
            'enable_mentions' => $validated['enable_mentions'] ?? false,
            'default_mention' => $validated['default_mention'],
            'is_active' => $validated['is_active'] ?? true,
        ]);

        $webhook->roles()->sync($validated['role_ids'] ?? []);

        return redirect()->route('discord.pings.webhooks.index')
            ->with('success', 'Webhook updated successfully!');
    }

    /**
     * Delete webhook
     */
    public function destroy($id)
    {
        $webhook = DiscordWebhook::findOrFail($id);
        $webhook->delete();

        return redirect()->back()->with('success', 'Webhook deleted successfully!');
    }

    /**
     * Test webhook
     */
    public function test($id)
    {
        $webhook = DiscordWebhook::findOrFail($id);
        $helper = new DiscordHelper();
        
        $result = $helper->testWebhook($webhook);

        if ($result['success']) {
            return response()->json(['success' => true, 'message' => 'Test successful!']);
        } else {
            return response()->json(['success' => false, 'message' => $result['error']], 400);
        }
    }
}
