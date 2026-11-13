<?php

namespace DiscordPings\Http\Controllers;

use Illuminate\Routing\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use DiscordPings\Models\DiscordRole;
use DiscordPings\Models\DiscordChannel;
use DiscordPings\Models\DiscordWebhook;
use DiscordPings\Models\StagingLocation;
use DiscordPings\Models\PapType;
use DiscordPings\Models\PingRequestButton;
use DiscordPings\Models\PluginSetting;
use DiscordPings\Helpers\DiscordHelper;
use DiscordPings\Services\BroadcastForm;
use DiscordPings\Services\DiscordRoleResolver;

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
        $stagings = StagingLocation::all();
        $papTypes = PapType::ordered()->get();

        // Structure Timers tab: current opt-out state + integration detection.
        $structureAlertsEnabled = PluginSetting::getBool('structure_alerts_enabled', true);
        $miningAlertsEnabled = PluginSetting::getBool('mining_alerts_enabled', true);
        $managerCoreInstalled = class_exists('\ManagerCore\Services\EventBus');
        $structureManagerInstalled = class_exists('\StructureManager\Services\TimerEventPublisher');
        $miningManagerInstalled = class_exists('\MiningManager\Services\Events\MoonExtractionEventPublisher');
        $formupOffsetMinutes = (int) PluginSetting::getValue(
            'formup_offset_minutes',
            (int) config('discordpings.structure_events.formup_offset_minutes', 30)
        );

        // Routing Map tab: which webhooks fire for each event the plugin
        // reacts to, with corp scope and active/inactive status. Read-only
        // snapshot — operators verify "what fires where" at a glance
        // without clicking through every webhook.
        $alertWebhooks = DiscordWebhook::where('receives_structure_alerts', true)
            ->orderBy('name')
            ->get();
        $miningAlertWebhooks = DiscordWebhook::where('receives_mining_alerts', true)
            ->orderBy('name')
            ->get();
        $routingCorpNames = $this->resolveCorporationNames(
            $alertWebhooks->pluck('corporation_id')
                ->merge($miningAlertWebhooks->pluck('corporation_id'))
                ->filter()->unique()->all()
        );

        // Discord Roles tab: connector-sourced picker for the Add Role modal.
        // Empty array when no connector plugin is detected — the picker
        // button is hidden and operators fall back to manual snowflake entry.
        // Already-added roles are filtered out by the resolver so we don't
        // offer duplicates.
        $connectorRoles      = DiscordRoleResolver::listAvailableRoles();
        $connectorAvailable  = DiscordRoleResolver::isAvailable();
        $connectorProviderLabel = DiscordRoleResolver::providerLabel();

        // Ping Requests tab.
        $requestButtons = PingRequestButton::with('webhook')->orderBy('sort_order')->orderBy('name')->get();
        $requestCorporations = $this->requestCorporations($requestButtons);
        $requestAlliances = $this->requestAlliances($requestButtons);
        $audienceOptions = PingRequestButton::audienceOptions();
        $pickerRoles = DiscordRoleResolver::listRoles();

        // Send Form tab.
        $sendFormMode = BroadcastForm::mode();
        $sendFormDefaults = BroadcastForm::saved();
        $doctrineNames = collect(DiscordHelper::listFittingDoctrines())->pluck('name')->filter()->unique()->values()->all();

        return view('discordpings::config.index', compact(
            'webhooks', 'roles', 'channels', 'stagings', 'papTypes',
            'structureAlertsEnabled', 'miningAlertsEnabled',
            'managerCoreInstalled', 'structureManagerInstalled', 'miningManagerInstalled',
            'formupOffsetMinutes',
            'alertWebhooks', 'miningAlertWebhooks', 'routingCorpNames',
            'connectorRoles', 'connectorAvailable', 'connectorProviderLabel',
            'requestButtons', 'requestCorporations', 'requestAlliances', 'audienceOptions', 'pickerRoles',
            'sendFormMode', 'sendFormDefaults', 'doctrineNames'
        ));
    }

    /**
     * Settings > Send Form: whether the Send and Schedule forms start empty or
     * from these defaults. Saved either way, so the defaults can be got ready
     * before they're switched on.
     */
    public function updateSendDefaults(Request $request)
    {
        $validated = $request->validate([
            'send_form_mode' => 'required|in:' . BroadcastForm::EMPTY . ',' . BroadcastForm::DEFAULTS,
            'webhook_id' => 'nullable|integer|exists:discord_webhooks,id',
            'embed_type' => 'nullable|in:' . implode(',', BroadcastForm::EMBED_TYPES),
            'mention_type' => 'nullable|in:' . implode(',', BroadcastForm::MENTION_TYPES),
            'role_mention' => 'nullable|integer|exists:discord_roles,id',
            'custom_mention' => 'nullable|string|max:100',
            'channel_link' => 'nullable|integer|exists:discord_channels,id',
            'embed_color' => ['nullable', 'regex:/^#[0-9A-Fa-f]{6}$/'],
            'fc_main' => 'nullable|boolean',
            'formup_location' => 'nullable|string|max:100',
            'pap_type' => 'nullable|string|max:100',
            'comms' => 'nullable|string|max:200',
            'doctrine' => 'nullable|string|max:200',
        ]);

        try {
            BroadcastForm::save($validated['send_form_mode'], $validated);

            return redirect()->back()->with('success', 'Send form settings saved.');
        } catch (\Exception $e) {
            Log::error('Discord Pings send form settings error: ' . $e->getMessage());
            return redirect()->back()->with('error', 'Failed to save the send form settings.');
        }
    }

    /**
     * Corporations a button can be limited to: the ones with members on this
     * SeAT, plus any a button already names, so that editing a button can't
     * quietly drop its limit.
     *
     * @return array<int, string> names by corporation id
     */
    private function requestCorporations($buttons): array
    {
        try {
            $names = DB::table('refresh_tokens')
                ->join('character_affiliations', 'refresh_tokens.character_id', '=', 'character_affiliations.character_id')
                ->join('corporation_infos', 'character_affiliations.corporation_id', '=', 'corporation_infos.corporation_id')
                ->whereNull('refresh_tokens.deleted_at')
                ->distinct()
                ->pluck('corporation_infos.name', 'corporation_infos.corporation_id')
                ->toArray();
        } catch (\Throwable $e) {
            Log::warning('[DiscordPings] Could not list corporations for Ping Requests: ' . $e->getMessage());
            $names = [];
        }

        $used = $buttons->pluck('corporation_id')->filter()->unique()->all();
        $names += $this->resolveCorporationNames(array_values(array_diff($used, array_keys($names))));

        foreach ($used as $corporationId) {
            $names[$corporationId] = $names[$corporationId] ?? 'Corporation ' . $corporationId;
        }

        asort($names, SORT_NATURAL | SORT_FLAG_CASE);

        return $names;
    }

    /**
     * Alliances a button can be limited to, picked the same way as the
     * corporations above.
     *
     * @return array<int, string> names by alliance id
     */
    private function requestAlliances($buttons): array
    {
        $used = $buttons->pluck('alliance_id')->filter()->unique()->all();

        try {
            $names = DB::table('refresh_tokens')
                ->join('character_affiliations', 'refresh_tokens.character_id', '=', 'character_affiliations.character_id')
                ->join('alliances', 'character_affiliations.alliance_id', '=', 'alliances.alliance_id')
                ->whereNull('refresh_tokens.deleted_at')
                ->distinct()
                ->pluck('alliances.name', 'alliances.alliance_id')
                ->toArray();

            $names += DB::table('alliances')->whereIn('alliance_id', $used)->pluck('name', 'alliance_id')->toArray();
        } catch (\Throwable $e) {
            Log::warning('[DiscordPings] Could not list alliances for Ping Requests: ' . $e->getMessage());
            $names = [];
        }

        $names = array_filter($names);

        foreach ($used as $allianceId) {
            $names[$allianceId] = $names[$allianceId] ?? 'Alliance ' . $allianceId;
        }

        asort($names, SORT_NATURAL | SORT_FLAG_CASE);

        return $names;
    }

    /**
     * Look up corporation names for a set of IDs (for the Routing Map tab).
     * Defensive: returns an empty array when corporation_infos is missing
     * or the query fails. Caller falls back to displaying raw IDs.
     */
    private function resolveCorporationNames(array $corpIds): array
    {
        if (empty($corpIds)) {
            return [];
        }

        try {
            if (! \Illuminate\Support\Facades\Schema::hasTable('corporation_infos')) {
                return [];
            }

            return \Illuminate\Support\Facades\DB::table('corporation_infos')
                ->whereIn('corporation_id', $corpIds)
                ->pluck('name', 'corporation_id')
                ->toArray();
        } catch (\Throwable $e) {
            return [];
        }
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
     * Store new staging location
     */
    public function storeStaging(Request $request)
    {
        try {
            $validated = $request->validate([
                'name' => 'required|string|max:255',
                'system_name' => 'required|string|max:255',
                'structure_name' => 'nullable|string|max:255',
                'description' => 'nullable|string|max:500',
                'is_default' => 'boolean',
            ]);
            
            // If this is set as default, unset any existing defaults
            if ($request->boolean('is_default')) {
                StagingLocation::where('is_default', true)->update(['is_default' => false]);
            }
            
            StagingLocation::create([
                'name' => $validated['name'],
                'system_name' => $validated['system_name'],
                'structure_name' => $validated['structure_name'] ?? null,
                'description' => $validated['description'] ?? null,
                'is_default' => $request->boolean('is_default'),
                'is_active' => true,
                'created_by' => auth()->id(),
            ]);
            
            return redirect()->back()->with('success', 'Staging location added successfully!');
            
        } catch (\Exception $e) {
            Log::error('Staging location creation error: ' . $e->getMessage());
            return redirect()->back()->with('error', 'Failed to add staging location.');
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
     * Delete staging location
     */
    public function destroyStaging($id)
    {
        try {
            $staging = StagingLocation::findOrFail($id);
            $staging->delete();
            
            return redirect()->back()->with('success', 'Staging location removed successfully!');
        } catch (\Exception $e) {
            Log::error('Staging location deletion error: ' . $e->getMessage());
            return redirect()->back()->with('error', 'Failed to remove staging location.');
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
    
    /**
     * Toggle staging location active status
     */
    public function toggleStaging($id)
    {
        try {
            $staging = StagingLocation::findOrFail($id);
            $staging->is_active = !$staging->is_active;
            $staging->save();
            
            $status = $staging->is_active ? 'activated' : 'deactivated';
            return response()->json(['success' => true, 'message' => "Staging location {$status} successfully"]);
        } catch (\Exception $e) {
            return response()->json(['success' => false, 'message' => 'Failed to toggle staging status'], 500);
        }
    }
    
    /**
     * Store new PAP type
     */
    public function storePapType(Request $request)
    {
        try {
            $validated = $request->validate([
                'name' => 'required|string|max:100|unique:discord_pap_types,name',
                'sort_order' => 'nullable|integer|min:0',
            ]);

            PapType::create([
                'name'       => $validated['name'],
                'sort_order' => $validated['sort_order'] ?? 0,
                'is_active'  => true,
            ]);

            return redirect()->back()->with('success', 'PAP type added successfully!');

        } catch (\Exception $e) {
            Log::error('PAP type creation error: ' . $e->getMessage());
            return redirect()->back()->with('error', 'Failed to add PAP type: ' . $e->getMessage());
        }
    }

    /**
     * Delete PAP type
     */
    public function destroyPapType($id)
    {
        try {
            PapType::findOrFail($id)->delete();
            return redirect()->back()->with('success', 'PAP type removed successfully!');
        } catch (\Exception $e) {
            Log::error('PAP type deletion error: ' . $e->getMessage());
            return redirect()->back()->with('error', 'Failed to remove PAP type.');
        }
    }

    /**
     * Toggle PAP type active status
     */
    public function togglePapType($id)
    {
        try {
            $papType = PapType::findOrFail($id);
            $papType->is_active = !$papType->is_active;
            $papType->save();

            $status = $papType->is_active ? 'activated' : 'deactivated';
            return response()->json(['success' => true, 'message' => "PAP type {$status} successfully"]);
        } catch (\Exception $e) {
            return response()->json(['success' => false, 'message' => 'Failed to toggle PAP type'], 500);
        }
    }

    /**
     * Set default staging location
     */
    public function setDefaultStaging($id)
    {
        try {
            // Unset all defaults
            StagingLocation::where('is_default', true)->update(['is_default' => false]);
            
            // Set new default
            $staging = StagingLocation::findOrFail($id);
            $staging->is_default = true;
            $staging->save();
            
            return response()->json(['success' => true, 'message' => 'Default staging location set']);
        } catch (\Exception $e) {
            return response()->json(['success' => false, 'message' => 'Failed to set default'], 500);
        }
    }

    /**
     * Save Structure Timer integration settings — the master opt-out toggle
     * for pre-timer reminder pings.
     */
    public function updateStructureTimerSettings(Request $request)
    {
        try {
            PluginSetting::setValue(
                'structure_alerts_enabled',
                $request->boolean('structure_alerts_enabled') ? '1' : '0'
            );

            // Mining extraction alerts share the same opt-out surface as
            // structure timer alerts so operators have one place to manage
            // every EventBus-driven alert.
            PluginSetting::setValue(
                'mining_alerts_enabled',
                $request->boolean('mining_alerts_enabled') ? '1' : '0'
            );

            // Clamp form-up offset to a sensible range (5 minutes to 12 hours).
            $formupOffset = (int) $request->input(
                'formup_offset_minutes',
                (int) config('discordpings.structure_events.formup_offset_minutes', 30)
            );
            $formupOffset = max(5, min(720, $formupOffset));
            PluginSetting::setValue('formup_offset_minutes', (string) $formupOffset);

            return redirect()->back()->with('success', 'Structure timer settings saved.');
        } catch (\Exception $e) {
            Log::error('Discord Pings structure timer settings error: ' . $e->getMessage());
            return redirect()->back()->with('error', 'Failed to save structure timer settings.');
        }
    }
}
