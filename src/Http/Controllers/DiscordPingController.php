<?php

namespace MattFalahe\Seat\DiscordPings\Http\Controllers;

use Seat\Web\Http\Controllers\Controller;
use Illuminate\Http\Request;
use MattFalahe\Seat\DiscordPings\Models\DiscordWebhook;
use MattFalahe\Seat\DiscordPings\Models\PingTemplate;
use MattFalahe\Seat\DiscordPings\Models\PingHistory;
use MattFalahe\Seat\DiscordPings\Helpers\DiscordHelper;

class DiscordPingController extends Controller
{
    /**
     * Show the ping form
     */
    public function showPingForm()
    {
        $webhooks = DiscordWebhook::active()
            ->forUser(auth()->user())
            ->get();
            
        $templates = PingTemplate::forUser(auth()->id())->get();
        
        $recentPings = PingHistory::where('user_id', auth()->id())
            ->with('webhook')
            ->latest()
            ->take(5)
            ->get();
        
        return view('discord-pings::ping', compact('webhooks', 'templates', 'recentPings'));
    }

    /**
     * Send a ping
     */
    public function sendPing(Request $request)
    {
        $validated = $request->validate([
            'webhook_id' => 'required|exists:discord_pings_webhooks,id',
            'message' => 'required|string|max:2000',
            'fc_name' => 'nullable|string|max:100',
            'formup_location' => 'nullable|string|max:100',
            'pap_type' => 'nullable|string|in:Strategic,Peacetime,CTA',
            'comms' => 'nullable|string|max:200',
            'doctrine' => 'nullable|string|max:200',
            'mention_type' => 'nullable|string|in:none,everyone,here,custom',
            'custom_mention' => 'nullable|string|max:100',
            'embed_color' => 'nullable|string|regex:/^#[0-9A-Fa-f]{6}$/',
        ]);

        $webhook = DiscordWebhook::find($validated['webhook_id']);
        
        // Check permissions
        if (!$webhook->canBeUsedBy(auth()->user())) {
            return redirect()->back()->with('error', 'You do not have permission to use this webhook.');
        }

        // Build and send
        $helper = new DiscordHelper();
        $result = $helper->sendPing($webhook, $validated, auth()->user());

        if ($result['success']) {
            return redirect()->back()->with('success', 'Ping sent successfully!');
        } else {
            return redirect()->back()->with('error', 'Failed to send ping: ' . $result['error']);
        }
    }

    /**
     * Send to multiple webhooks
     */
    public function sendMultiplePing(Request $request)
    {
        $validated = $request->validate([
            'webhook_ids' => 'required|array',
            'webhook_ids.*' => 'exists:discord_pings_webhooks,id',
            'message' => 'required|string|max:2000',
            'fc_name' => 'nullable|string|max:100',
            'formup_location' => 'nullable|string|max:100',
            'pap_type' => 'nullable|string|in:Strategic,Peacetime,CTA',
            'comms' => 'nullable|string|max:200',
            'doctrine' => 'nullable|string|max:200',
            'mention_type' => 'nullable|string|in:none,everyone,here,custom',
            'custom_mention' => 'nullable|string|max:100',
            'embed_color' => 'nullable|string|regex:/^#[0-9A-Fa-f]{6}$/',
        ]);

        $successCount = 0;
        $failCount = 0;
        $helper = new DiscordHelper();

        foreach ($validated['webhook_ids'] as $webhookId) {
            $webhook = DiscordWebhook::find($webhookId);
            
            if (!$webhook->canBeUsedBy(auth()->user())) {
                $failCount++;
                continue;
            }

            $data = $validated;
            $data['webhook_id'] = $webhookId;
            
            $result = $helper->sendPing($webhook, $data, auth()->user());
            
            if ($result['success']) {
                $successCount++;
            } else {
                $failCount++;
            }
        }

        $message = "Sent to {$successCount} webhook(s)";
        if ($failCount > 0) {
            $message .= ", {$failCount} failed";
        }

        return redirect()->back()->with('success', $message);
    }
}
