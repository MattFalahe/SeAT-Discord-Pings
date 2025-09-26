<?php
namespace MattFalahe\Seat\DiscordPings\Http\Controllers;

use Illuminate\Routing\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use MattFalahe\Seat\DiscordPings\Models\ScheduledPing;
use MattFalahe\Seat\DiscordPings\Models\DiscordWebhook;
use Carbon\Carbon;

class ScheduledController extends Controller
{
    /**
     * Display scheduled pings
     */
    public function index()
    {
        try {
            $query = ScheduledPing::with('webhook');
            
            // Only show user's own scheduled pings
            $query->where('user_id', auth()->id());
            
            $scheduledPings = $query->orderBy('scheduled_at')->paginate(20);
            
            return view('discordpings::scheduled.index', compact('scheduledPings'));
            
        } catch (\Exception $e) {
            Log::error('Discord Pings scheduled view error: ' . $e->getMessage());
            return redirect()->back()->with('error', 'Unable to load scheduled pings. Please check logs.');
        }
    }
    
    /**
     * Show create form
     */
    public function create()
    {
        try {
            $webhooks = DiscordWebhook::active()->get();
            
            return view('discordpings::scheduled.create', compact('webhooks'));
            
        } catch (\Exception $e) {
            Log::error('Discord Pings scheduled create error: ' . $e->getMessage());
            return redirect()->back()->with('error', 'Unable to load create form. Please check logs.');
        }
    }
    
    /**
     * Store scheduled ping
     */
    public function store(Request $request)
    {
        try {
            $validated = $request->validate([
                'webhook_id' => 'required|exists:discord_webhooks,id',
                'message' => 'required|string|max:2000',
                'scheduled_at' => 'required|date|after:now',
                'repeat_interval' => 'nullable|in:hourly,daily,weekly,monthly',
                'repeat_until' => 'nullable|date|after:scheduled_at',
                // Optional fields
                'fc_name' => 'nullable|string|max:100',
                'formup_location' => 'nullable|string|max:100',
                'pap_type' => 'nullable|string|in:Strategic,Peacetime,CTA',
                'comms' => 'nullable|string|max:200',
                'doctrine' => 'nullable|string|max:200',
                'mention_type' => 'nullable|string|in:none,everyone,here,custom',
                'custom_mention' => 'nullable|string|max:100',
                'embed_color' => 'nullable|string|regex:/^#[0-9A-Fa-f]{6}$/',
            ]);
            
            // Check webhook exists and is active
            $webhook = DiscordWebhook::find($validated['webhook_id']);
            if (!$webhook || !$webhook->is_active) {
                return redirect()->back()->with('error', 'Invalid or inactive webhook.');
            }
            
            // Check user's scheduled ping limit
            $userScheduledCount = ScheduledPing::where('user_id', auth()->id())
                ->where('is_active', true)
                ->count();
                
            if ($userScheduledCount >= config('discordpings.max_scheduled_per_user', 50)) {
                return redirect()->back()->with('error', 'You have reached the maximum number of scheduled pings.');
            }
            
            // Build fields array
            $fields = [];
            $fieldKeys = ['fc_name', 'formup_location', 'pap_type', 'comms', 'doctrine', 
                         'mention_type', 'custom_mention', 'embed_color'];
            
            foreach ($fieldKeys as $key) {
                if (isset($validated[$key]) && !empty($validated[$key])) {
                    $fields[$key] = $validated[$key];
                }
            }
            
            // Create scheduled ping
            ScheduledPing::create([
                'webhook_id' => $validated['webhook_id'],
                'user_id' => auth()->id(),
                'message' => $validated['message'],
                'fields' => $fields,
                'scheduled_at' => Carbon::parse($validated['scheduled_at']),
                'repeat_interval' => $validated['repeat_interval'] ?? null,
                'repeat_until' => isset($validated['repeat_until']) ? Carbon::parse($validated['repeat_until']) : null,
                'is_active' => true,
            ]);
            
            return redirect()->route('discordpings.scheduled')
                ->with('success', 'Ping scheduled successfully!');
                
        } catch (\Exception $e) {
            Log::error('Discord Pings scheduled store error: ' . $e->getMessage());
            return redirect()->back()->with('error', 'Failed to schedule ping. Please check logs.');
        }
    }
    
    /**
     * Delete scheduled ping
     */
    public function destroy($id)
    {
        try {
            $scheduledPing = ScheduledPing::findOrFail($id);
            
            // Check ownership
            if ($scheduledPing->user_id != auth()->id()) {
                abort(403, 'Unauthorized');
            }
            
            $scheduledPing->delete();
            
            return redirect()->back()->with('success', 'Scheduled ping deleted successfully!');
            
        } catch (\Exception $e) {
            Log::error('Discord Pings scheduled delete error: ' . $e->getMessage());
            return redirect()->back()->with('error', 'Failed to delete scheduled ping. Please check logs.');
        }
    }
}
