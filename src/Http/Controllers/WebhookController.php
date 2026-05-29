<?php
namespace DiscordPings\Http\Controllers;

use Illuminate\Routing\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Schema;
use DiscordPings\Models\DiscordWebhook;
use DiscordPings\Helpers\DiscordHelper;
use Seat\Web\Models\Acl\Role;

class WebhookController extends Controller
{
    /**
     * Display webhooks
     */
    public function index()
    {
        try {
            $webhooks = DiscordWebhook::with('roles')->get();
            return view('discordpings::webhooks.index', compact('webhooks'));
        } catch (\Exception $e) {
            Log::error('Discord Pings webhooks view error: ' . $e->getMessage());
            return redirect()->back()->with('error', 'Unable to load webhooks. Please check logs.');
        }
    }

    /**
     * Show create form
     */
    public function create()
    {
        try {
            $roles = Role::all();
            $corporations = $this->loadCorporations();
            return view('discordpings::webhooks.create', compact('roles', 'corporations'));
        } catch (\Exception $e) {
            Log::error('Discord Pings webhook create error: ' . $e->getMessage());
            return redirect()->back()->with('error', 'Unable to load create form. Please check logs.');
        }
    }

    /**
     * Load the list of corporations known to SeAT for the corp-scope dropdown
     * on the webhook create/edit forms. Used so an operator on a multi-corp
     * install can scope a webhook to a specific corp (NULL = any corp).
     * Defensive: returns an empty collection if corporation_infos is missing
     * (fresh install / non-standard schema).
     */
    private function loadCorporations()
    {
        try {
            if (! Schema::hasTable('corporation_infos')) {
                return collect();
            }

            return DB::table('corporation_infos')
                ->select('corporation_id', 'name', 'ticker')
                ->orderBy('name')
                ->get();
        } catch (\Throwable $e) {
            return collect();
        }
    }

    /**
     * Store new webhook
     */
    public function store(Request $request)
    {
        try {
            $validated = $request->validate([
                'name' => 'required|string|max:255',
                'webhook_url' => 'required|url|starts_with:https://discord.com/api/webhooks/',
                'channel_type' => 'nullable|string|max:50',
                'embed_color' => 'required|string|regex:/^#[0-9A-Fa-f]{6}$/',
                'receives_structure_alerts' => 'nullable|boolean',
                'receives_mining_alerts' => 'nullable|boolean',
                'corporation_id' => 'nullable|integer',
                'role_ids' => 'nullable|array',
                'role_ids.*' => 'exists:roles,id',
            ]);

            $webhook = DiscordWebhook::create([
                'name' => $validated['name'],
                'webhook_url' => $validated['webhook_url'],
                'channel_type' => $validated['channel_type'] ?? null,
                'embed_color' => $validated['embed_color'],
                'is_active' => true,
                'receives_structure_alerts' => $request->boolean('receives_structure_alerts'),
                'receives_mining_alerts' => $request->boolean('receives_mining_alerts'),
                'corporation_id' => $request->filled('corporation_id') ? (int) $request->input('corporation_id') : null,
                'created_by' => auth()->id(),
            ]);

            if (!empty($validated['role_ids'])) {
                $webhook->roles()->sync($validated['role_ids']);
            }

            return redirect()->route('discordpings.webhooks')
                ->with('success', 'Webhook created successfully!');
                
        } catch (\Exception $e) {
            Log::error('Discord Pings webhook store error: ' . $e->getMessage());
            return redirect()->back()->with('error', 'Failed to create webhook. Please check logs.');
        }
    }

    /**
     * Show edit form
     */
    public function edit($id)
    {
        try {
            $webhook = DiscordWebhook::with('roles')->findOrFail($id);
            $roles = Role::all();
            $corporations = $this->loadCorporations();
            return view('discordpings::webhooks.edit', compact('webhook', 'roles', 'corporations'));
        } catch (\Exception $e) {
            Log::error('Discord Pings webhook edit error: ' . $e->getMessage());
            return redirect()->back()->with('error', 'Unable to load webhook. Please check logs.');
        }
    }

    /**
     * Update webhook
     */
    public function update(Request $request, $id)
    {
        try {
            $webhook = DiscordWebhook::findOrFail($id);

            $validated = $request->validate([
                'name' => 'required|string|max:255',
                'webhook_url' => 'required|url|starts_with:https://discord.com/api/webhooks/',
                'channel_type' => 'nullable|string|max:50',
                'embed_color' => 'required|string|regex:/^#[0-9A-Fa-f]{6}$/',
                'is_active' => 'boolean',
                'receives_structure_alerts' => 'nullable|boolean',
                'receives_mining_alerts' => 'nullable|boolean',
                'corporation_id' => 'nullable|integer',
                'role_ids' => 'nullable|array',
                'role_ids.*' => 'exists:roles,id',
            ]);

            $webhook->update([
                'name' => $validated['name'],
                'webhook_url' => $validated['webhook_url'],
                'channel_type' => $validated['channel_type'] ?? null,
                'embed_color' => $validated['embed_color'],
                'is_active' => $validated['is_active'] ?? true,
                'receives_structure_alerts' => $request->boolean('receives_structure_alerts'),
                'receives_mining_alerts' => $request->boolean('receives_mining_alerts'),
                'corporation_id' => $request->filled('corporation_id') ? (int) $request->input('corporation_id') : null,
            ]);

            $webhook->roles()->sync($validated['role_ids'] ?? []);

            return redirect()->route('discordpings.webhooks')
                ->with('success', 'Webhook updated successfully!');
                
        } catch (\Exception $e) {
            Log::error('Discord Pings webhook update error: ' . $e->getMessage());
            return redirect()->back()->with('error', 'Failed to update webhook. Please check logs.');
        }
    }

    /**
     * Delete webhook
     */
    public function destroy($id)
    {
        try {
            $webhook = DiscordWebhook::findOrFail($id);
            $webhook->delete();

            return redirect()->back()->with('success', 'Webhook deleted successfully!');
        } catch (\Exception $e) {
            Log::error('Discord Pings webhook delete error: ' . $e->getMessage());
            return redirect()->back()->with('error', 'Failed to delete webhook. Please check logs.');
        }
    }

    /**
     * Test webhook
     */
    public function test($id)
    {
        try {
            $webhook = DiscordWebhook::findOrFail($id);
            
            // Create test data
            $testData = [
                'message' => 'This is a test broadcast from SeAT Broadcast plugin.',
                'fc_name' => 'Test FC',
                'formup_location' => 'Test System',
                'pap_type' => 'Strategic',
                'comms' => 'Test Comms Channel',
                'doctrine' => 'Test Ships',
                'embed_color' => '#00FF00'
            ];
            
            $helper = new DiscordHelper();
            
            // Create a test user object
            $testUser = (object) [
                'id' => auth()->id() ?? 0,
                'name' => auth()->user()->name ?? 'System Test'
            ];
            
            $result = $helper->sendPing($webhook, $testData, $testUser);

            if ($result['success']) {
                return response()->json([
                    'success' => true, 
                    'message' => 'Test successful! Check your Discord channel.'
                ]);
            } else {
                Log::error('Discord webhook test failed', [
                    'webhook_id' => $id,
                    'error' => $result['error'] ?? 'Unknown error'
                ]);
                
                return response()->json([
                    'success' => false, 
                    'message' => 'Test failed: ' . ($result['error'] ?? 'Unknown error')
                ], 400);
            }
        } catch (\Exception $e) {
            Log::error('Discord Pings webhook test error', [
                'webhook_id' => $id,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);
            
            return response()->json([
                'success' => false, 
                'message' => 'Test failed: ' . $e->getMessage()
            ], 500);
        }
    }
}
