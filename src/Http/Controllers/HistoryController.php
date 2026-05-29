<?php
namespace DiscordPings\Http\Controllers;

use Illuminate\Routing\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use DiscordPings\Models\PingHistory;
use DiscordPings\Models\DiscordWebhook;
use DiscordPings\Jobs\SendPingJob;

class HistoryController extends Controller
{
    /**
     * Display ping history
     */
    public function index(Request $request)
    {
        try {
            $query = PingHistory::with('webhook');
            
            // Check if user can view all history
            if (!auth()->user()->can('discordpings.view_all_history')) {
                // Only show their own history
                $query->where('user_id', auth()->id());
            }
            
            // Add filtering
            if ($request->has('status')) {
                $query->where('status', $request->status);
            }
            
            if ($request->has('webhook_id')) {
                $query->where('webhook_id', $request->webhook_id);
            }
            
            if ($request->has('search')) {
                $query->where('message', 'like', '%' . $request->search . '%');
            }
            
            // Cap at 1000 most-recent rows: the view uses client-side DataTables,
            // which slows to a crawl past ~10k rows and OOMs on very large histories.
            $histories = $query->latest()->limit(1000)->get();
            $webhooks = DiscordWebhook::all();
            
            return view('discordpings::history.index', compact('histories', 'webhooks'));
            
        } catch (\Exception $e) {
            Log::error('Discord Pings history view error: ' . $e->getMessage());
            return redirect()->back()->with('error', 'Unable to load history. Please check logs.');
        }
    }
    
    /**
     * Show single ping details
     */
    public function show($id)
    {
        try {
            $history = PingHistory::with('webhook')->findOrFail($id);
            
            // Check permission
            if ($history->user_id != auth()->id() && !auth()->user()->can('discordpings.view_all_history')) {
                abort(403, 'Unauthorized');
            }
            
            return view('discordpings::history.show', compact('history'));
            
        } catch (\Exception $e) {
            Log::error('Discord Pings history show error: ' . $e->getMessage());
            return redirect()->back()->with('error', 'Unable to load ping details. Please check logs.');
        }
    }
    
    /**
     * Resend a ping
     */
    public function resend($id)
    {
        try {
            $history = PingHistory::with('webhook')->findOrFail($id);
            
            // Check if webhook still exists and is active
            if (!$history->webhook || !$history->webhook->is_active) {
                return redirect()->back()->with('error', 'Webhook is no longer available.');
            }
            
            // Prepare data for resend
            $data = [
                'message' => $history->message,
                'webhook_id' => $history->webhook_id,
            ];

            // Add fields if they exist
            if ($history->fields) {
                $data = array_merge($data, $history->fields);
            }

            SendPingJob::dispatch(
                $history->webhook->id,
                $data,
                auth()->id(),
                auth()->user()->name,
            );

            return redirect()->back()->with('success', 'Ping queued for resend.');
            
        } catch (\Exception $e) {
            Log::error('Discord Pings resend error: ' . $e->getMessage());
            return redirect()->back()->with('error', 'Failed to resend ping. Please check logs.');
        }
    }
}
