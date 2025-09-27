<?php
namespace MattFalahe\Seat\DiscordPings\Http\Controllers;

use Illuminate\Routing\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use MattFalahe\Seat\DiscordPings\Models\DiscordWebhook;
use MattFalahe\Seat\DiscordPings\Models\DiscordRole;
use MattFalahe\Seat\DiscordPings\Models\DiscordChannel;
use MattFalahe\Seat\DiscordPings\Models\PingHistory;
use MattFalahe\Seat\DiscordPings\Models\StagingLocation;
use MattFalahe\Seat\DiscordPings\Helpers\DiscordHelper;

class PingController extends Controller
{
    /**
     * Show the send ping page
     */
    public function index()
    {
        try {
            $webhooks = DiscordWebhook::active()->get();
            $roles = DiscordRole::active()->get();
            $channels = DiscordChannel::active()->get();
            $stagings = StagingLocation::active()->get();
            
            $templates = config('discordpings.default_templates', []);
            
            $recentPings = PingHistory::where('user_id', auth()->id())
                ->with('webhook')
                ->latest()
                ->take(5)
                ->get();
            
            // Check if seat-fitting plugin is installed and get doctrines
            $doctrines = [];
            $hasFittingPlugin = false;
            
            // Try CryptaTech namespace first (based on your database tables)
            if (class_exists('CryptaTech\Seat\Fitting\Models\Doctrine')) {
                $hasFittingPlugin = true;
                try {
                    $doctrines = \CryptaTech\Seat\Fitting\Models\Doctrine::all();
                } catch (\Exception $e) {
                    Log::info('CryptaTech seat-fitting plugin found but could not load doctrines: ' . $e->getMessage());
                }
            } 
            // Fall back to Denngarr namespace
            elseif (class_exists('Denngarr\Seat\Fitting\Models\Doctrine')) {
                $hasFittingPlugin = true;
                try {
                    $doctrines = \Denngarr\Seat\Fitting\Models\Doctrine::all();
                } catch (\Exception $e) {
                    Log::info('Denngarr seat-fitting plugin found but could not load doctrines: ' . $e->getMessage());
                }
            }
            
            return view('discordpings::send', compact(
                'webhooks', 
                'roles', 
                'channels', 
                'stagings',
                'templates', 
                'recentPings', 
                'doctrines', 
                'hasFittingPlugin'
            ));
            
        } catch (\Exception $e) {
            Log::error('Discord Pings view error: ' . $e->getMessage());
            return redirect()->back()->with('error', 'Unable to load ping form. Please check logs.');
        }
    }
    
    /**
     * Send a ping
     */
    public function send(Request $request)
    {
        try {
            $validated = $request->validate([
                'webhook_id' => 'required|exists:discord_webhooks,id',
                'message' => 'required|string|max:2000',
                'fc_name' => 'nullable|string|max:100',
                'formup_location' => 'nullable|string|max:100',
                'pap_type' => 'nullable|string|in:Strategic,Peacetime,CTA',
                'comms' => 'nullable|string|max:200',
                'doctrine' => 'nullable|string|max:200',
                'doctrine_id' => 'nullable|integer',
                'mention_type' => 'nullable|string|in:none,everyone,here,role,custom',
                'role_mention' => 'nullable|exists:discord_roles,id',
                'channel_link' => 'nullable|exists:discord_channels,id',
                'custom_mention' => 'nullable|string|max:100',
                'embed_color' => 'nullable|string|regex:/^#[0-9A-Fa-f]{6}$/',
            ]);
            
            $webhook = DiscordWebhook::find($validated['webhook_id']);
            
            if (!$webhook || !$webhook->is_active) {
                return redirect()->back()->with('error', 'Invalid or inactive webhook.');
            }
            
            // Handle doctrine from seat-fitting if selected
            if (!empty($validated['doctrine_id'])) {
                $doctrine = null;
                
                // Try CryptaTech namespace first
                if (class_exists('CryptaTech\Seat\Fitting\Models\Doctrine')) {
                    try {
                        $doctrine = \CryptaTech\Seat\Fitting\Models\Doctrine::find($validated['doctrine_id']);
                    } catch (\Exception $e) {
                        Log::info('Could not load doctrine from CryptaTech seat-fitting: ' . $e->getMessage());
                    }
                }
                // Fall back to Denngarr namespace
                elseif (class_exists('Denngarr\Seat\Fitting\Models\Doctrine')) {
                    try {
                        $doctrine = \Denngarr\Seat\Fitting\Models\Doctrine::find($validated['doctrine_id']);
                    } catch (\Exception $e) {
                        Log::info('Could not load doctrine from Denngarr seat-fitting: ' . $e->getMessage());
                    }
                }
                
                if ($doctrine) {
                    // Build doctrine link - we know the correct route
                    try {
                        $doctrineUrl = route('fitting.doctrineviewdetails', ['doctrine_id' => $doctrine->id]);
                        $validated['doctrine'] = "[{$doctrine->name}]({$doctrineUrl})";
                        $validated['doctrine_url'] = $doctrineUrl;
                    } catch (\Exception $e) {
                        // If route fails for any reason, just use the name
                        Log::info('Could not generate doctrine URL: ' . $e->getMessage());
                        $validated['doctrine'] = $doctrine->name;
                    }
                    $validated['doctrine_name'] = $doctrine->name;
                }
            }
            
            // Handle role mention
            if ($validated['mention_type'] === 'role' && !empty($validated['role_mention'])) {
                $role = DiscordRole::find($validated['role_mention']);
                if ($role) {
                    $validated['custom_mention'] = $role->getMentionString();
                }
            }
            
            // Handle channel link
            if (!empty($validated['channel_link'])) {
                $channel = DiscordChannel::find($validated['channel_link']);
                if ($channel) {
                    $validated['channel_url'] = $channel->getChannelLink();
                    $validated['channel_mention'] = $channel->getMentionString();
                }
            }
            
            $helper = new DiscordHelper();
            $result = $helper->sendPing($webhook, $validated, auth()->user());
            
            if ($result['success']) {
                return redirect()->back()->with('success', 'Ping sent successfully!');
            } else {
                return redirect()->back()->with('error', 'Failed to send ping: ' . $result['error']);
            }
            
        } catch (\Exception $e) {
            Log::error('Discord ping send error', [
                'error' => $e->getMessage(),
                'user' => auth()->id()
            ]);
            
            return redirect()->back()->with('error', 'Failed to send ping. Please check logs.');
        }
    }
    
    /**
     * Send to multiple webhooks
     */
    public function sendMultiple(Request $request)
    {
        try {
            $validated = $request->validate([
                'webhook_ids' => 'required|array',
                'webhook_ids.*' => 'exists:discord_webhooks,id',
                'message' => 'required|string|max:2000',
                'fc_name' => 'nullable|string|max:100',
                'formup_location' => 'nullable|string|max:100',
                'pap_type' => 'nullable|string|in:Strategic,Peacetime,CTA',
                'comms' => 'nullable|string|max:200',
                'doctrine' => 'nullable|string|max:200',
                'doctrine_id' => 'nullable|integer',
                'mention_type' => 'nullable|string|in:none,everyone,here,role,custom',
                'role_mention' => 'nullable|exists:discord_roles,id',
                'channel_link' => 'nullable|exists:discord_channels,id',
                'custom_mention' => 'nullable|string|max:100',
                'embed_color' => 'nullable|string|regex:/^#[0-9A-Fa-f]{6}$/',
            ]);
            
            // Handle doctrine from seat-fitting if selected
            if (!empty($validated['doctrine_id'])) {
                $doctrine = null;
                
                // Try CryptaTech namespace first
                if (class_exists('CryptaTech\Seat\Fitting\Models\Doctrine')) {
                    try {
                        $doctrine = \CryptaTech\Seat\Fitting\Models\Doctrine::find($validated['doctrine_id']);
                    } catch (\Exception $e) {
                        Log::info('Could not load doctrine from CryptaTech seat-fitting: ' . $e->getMessage());
                    }
                }
                // Fall back to Denngarr namespace
                elseif (class_exists('Denngarr\Seat\Fitting\Models\Doctrine')) {
                    try {
                        $doctrine = \Denngarr\Seat\Fitting\Models\Doctrine::find($validated['doctrine_id']);
                    } catch (\Exception $e) {
                        Log::info('Could not load doctrine from Denngarr seat-fitting: ' . $e->getMessage());
                    }
                }
                
                if ($doctrine) {
                    // Build doctrine link - we know the correct route
                    try {
                        $doctrineUrl = route('fitting.doctrineviewdetails', ['doctrine_id' => $doctrine->id]);
                        $validated['doctrine'] = "[{$doctrine->name}]({$doctrineUrl})";
                        $validated['doctrine_url'] = $doctrineUrl;
                    } catch (\Exception $e) {
                        // If route fails for any reason, just use the name
                        Log::info('Could not generate doctrine URL: ' . $e->getMessage());
                        $validated['doctrine'] = $doctrine->name;
                    }
                    $validated['doctrine_name'] = $doctrine->name;
                }
            }
            
            // Handle role mention
            if ($validated['mention_type'] === 'role' && !empty($validated['role_mention'])) {
                $role = DiscordRole::find($validated['role_mention']);
                if ($role) {
                    $validated['custom_mention'] = $role->getMentionString();
                }
            }
            
            // Handle channel link
            if (!empty($validated['channel_link'])) {
                $channel = DiscordChannel::find($validated['channel_link']);
                if ($channel) {
                    $validated['channel_url'] = $channel->getChannelLink();
                    $validated['channel_mention'] = $channel->getMentionString();
                }
            }
            
            $successCount = 0;
            $failCount = 0;
            $helper = new DiscordHelper();
            
            foreach ($validated['webhook_ids'] as $webhookId) {
                $webhook = DiscordWebhook::find($webhookId);
                
                if (!$webhook || !$webhook->is_active) {
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
            
        } catch (\Exception $e) {
            Log::error('Discord multiple ping send error', [
                'error' => $e->getMessage(),
                'user' => auth()->id()
            ]);
            
            return redirect()->back()->with('error', 'Failed to send pings. Please check logs.');
        }
    }
}
