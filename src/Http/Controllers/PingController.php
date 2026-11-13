<?php
namespace DiscordPings\Http\Controllers;

use Illuminate\Routing\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use DiscordPings\Models\DiscordWebhook;
use DiscordPings\Models\DiscordRole;
use DiscordPings\Models\DiscordChannel;
use DiscordPings\Models\PingHistory;
use DiscordPings\Models\StagingLocation;
use DiscordPings\Models\PingTemplate;
use DiscordPings\Models\PapType;
use DiscordPings\Models\TacticalEvent;
use DiscordPings\Helpers\DiscordHelper;
use DiscordPings\Jobs\SendPingJob;
use DiscordPings\Services\BroadcastForm;
use DiscordPings\Services\DiscordUserResolver;
use DiscordPings\Services\PingRequests\RequestAnswers;
use DiscordPings\Services\PingRequests\RequestBoard;

class PingController extends Controller
{
    /**
     * Show the send ping page
     */
    public function index(Request $request)
    {
        try {
            $user = auth()->user();
            $webhooks = DiscordWebhook::active()->get();
            $roles = DiscordRole::active()->get();
            $channels = DiscordChannel::active()->get();
            $stagings = StagingLocation::active()->get();
            $papTypes = PapType::active()->ordered()->get();

            $templates = PingTemplate::forUser(auth()->id())->get();

            $recentPings = PingHistory::where('user_id', auth()->id())
                ->broadcasts()
                ->with('webhook')
                ->latest()
                ->take(5)
                ->get();

            $hasFittingPlugin = (bool) DiscordHelper::detectFittingDoctrineClass();
            // listFittingDoctrines() returns a PHP array; wrap in collect() so
            // the blade's ($doctrines ?? collect())->count() pattern is type-
            // correct (empty arrays bypass `?? collect()` and crash on ->count())
            $doctrines = collect(DiscordHelper::listFittingDoctrines());

            // Empty, or what Settings fills in. Whatever the page was opened
            // for goes on top.
            $mainName = DiscordUserResolver::mainCharacterName($user);
            $prefill = BroadcastForm::forForm($mainName);

            // FC Opportunities → "Form-up" (immediate Send): when arriving
            // with ?tactical_event_id=N, pre-fill the form with urgent
            // "forming up NOW" copy + PREPING embed type. Mirrors the
            // schedule path's pre-fill but uses 'send' mode of the
            // TacticalEvent::buildBroadcastPrefill helper.
            $tacticalEvent = null;
            $rawTacticalEventId = $request->input('tactical_event_id');
            if ($rawTacticalEventId) {
                $tacticalEvent = TacticalEvent::visibleTo($user)
                    ->find((int) $rawTacticalEventId);
                if ($tacticalEvent) {
                    $prefill = BroadcastForm::layer($prefill, $tacticalEvent->buildBroadcastPrefill('send'));
                }
            }

            // A member's request, from Form-up on FC Opportunities or the link
            // in its Discord message. The board's own rule decides either way,
            // so neither can open a request the FC couldn't see there.
            $pingRequest = null;
            $pingRequestMissing = false;
            if (! $tacticalEvent && ($request->filled('ping_request_id') || $request->filled('request'))) {
                $pingRequest = $request->filled('ping_request_id')
                    ? RequestBoard::find($user, $request->input('ping_request_id'))
                    : RequestBoard::findByRef($user, $request->input('request'));

                if ($pingRequest) {
                    $prefill = BroadcastForm::layer($prefill, RequestBoard::prefill($pingRequest, $mainName, RequestBoard::answerDefaultsFor($pingRequest)));
                } else {
                    $pingRequestMissing = true;
                }
            }

            $pingRequestAnswers = $pingRequest ? (RequestAnswers::forRequests([$pingRequest->id])[(int) $pingRequest->id] ?? []) : [];
            $suggestions = BroadcastForm::suggestions($user);

            return view('discordpings::send', compact(
                'webhooks',
                'roles',
                'channels',
                'stagings',
                'papTypes',
                'templates',
                'recentPings',
                'doctrines',
                'hasFittingPlugin',
                'prefill',
                'tacticalEvent',
                'pingRequest',
                'pingRequestMissing',
                'pingRequestAnswers',
                'suggestions'
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
                'embed_type' => 'nullable|string|in:fleet,announcement,message,prepping',
                'fc_name' => 'nullable|string|max:100',
                'formup_location' => 'nullable|string|max:100',
                'pap_type' => 'nullable|string|max:100',
                'comms' => 'nullable|string|max:200',
                'doctrine' => 'nullable|string|max:200',
                'doctrine_id' => 'nullable|integer',
                'mention_type' => 'nullable|string|in:none,everyone,here,role,custom',
                'role_mention' => 'nullable|exists:discord_roles,id',
                'channel_link' => 'nullable|exists:discord_channels,id',
                'custom_mention' => 'nullable|string|max:100',
                'embed_color' => 'nullable|string|regex:/^#[0-9A-Fa-f]{6}$/',
                'answers_request' => 'nullable|integer',
            ]);

            $validated = $this->withAnsweredRequest($request, $validated);

            $webhook = DiscordWebhook::find($validated['webhook_id']);
            
            if (!$webhook || !$webhook->is_active) {
                return redirect()->back()->with('error', 'Invalid or inactive webhook.');
            }
            
            $doctrine = !empty($validated['doctrine_id'])
                ? DiscordHelper::findFittingDoctrine((int) $validated['doctrine_id'])
                : null;

            if ($doctrine) {
                try {
                    $doctrineUrl = route('fitting.doctrineviewdetails', ['doctrine_id' => $doctrine->id]);
                    $validated['doctrine'] = "[{$doctrine->name}]({$doctrineUrl})";
                    $validated['doctrine_url'] = $doctrineUrl;
                } catch (\Exception $e) {
                    Log::info('Could not generate doctrine URL: ' . $e->getMessage());
                    $validated['doctrine'] = $doctrine->name;
                }
                $validated['doctrine_name'] = $doctrine->name;
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
            
            SendPingJob::dispatch(
                $webhook->id,
                $validated,
                auth()->id(),
                auth()->user()->name,
            );

            return redirect()->back()->with('success', 'Broadcast queued for delivery to Discord.');

        } catch (\Exception $e) {
            Log::error('Discord ping send error', [
                'error' => $e->getMessage(),
                'user' => auth()->id()
            ]);

            return redirect()->back()->with('error', 'Failed to send broadcast. Please check logs.');
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
                'embed_type' => 'nullable|string|in:fleet,announcement,message,prepping',
                'fc_name' => 'nullable|string|max:100',
                'formup_location' => 'nullable|string|max:100',
                'pap_type' => 'nullable|string|max:100',
                'comms' => 'nullable|string|max:200',
                'doctrine' => 'nullable|string|max:200',
                'doctrine_id' => 'nullable|integer',
                'mention_type' => 'nullable|string|in:none,everyone,here,role,custom',
                'role_mention' => 'nullable|exists:discord_roles,id',
                'channel_link' => 'nullable|exists:discord_channels,id',
                'custom_mention' => 'nullable|string|max:100',
                'embed_color' => 'nullable|string|regex:/^#[0-9A-Fa-f]{6}$/',
                'answers_request' => 'nullable|integer',
            ]);

            $validated = $this->withAnsweredRequest($request, $validated);

            $doctrine = !empty($validated['doctrine_id'])
                ? DiscordHelper::findFittingDoctrine((int) $validated['doctrine_id'])
                : null;

            if ($doctrine) {
                try {
                    $doctrineUrl = route('fitting.doctrineviewdetails', ['doctrine_id' => $doctrine->id]);
                    $validated['doctrine'] = "[{$doctrine->name}]({$doctrineUrl})";
                    $validated['doctrine_url'] = $doctrineUrl;
                } catch (\Exception $e) {
                    Log::info('Could not generate doctrine URL: ' . $e->getMessage());
                    $validated['doctrine'] = $doctrine->name;
                }
                $validated['doctrine_name'] = $doctrine->name;
            }

            // Handle role mention
            if (($validated['mention_type'] ?? 'none') === 'role' && !empty($validated['role_mention'])) {
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
            
            $queuedCount = 0;
            $skippedCount = 0;

            foreach ($validated['webhook_ids'] as $webhookId) {
                $webhook = DiscordWebhook::find($webhookId);

                if (!$webhook || !$webhook->is_active) {
                    $skippedCount++;
                    continue;
                }

                $data = $validated;
                $data['webhook_id'] = $webhookId;

                SendPingJob::dispatch(
                    $webhook->id,
                    $data,
                    auth()->id(),
                    auth()->user()->name,
                );
                $queuedCount++;
            }

            $message = "Queued broadcast to {$queuedCount} webhook(s)";
            if ($skippedCount > 0) {
                $message .= "; {$skippedCount} skipped (inactive or missing)";
            }

            return redirect()->back()->with('success', $message);
            
        } catch (\Exception $e) {
            Log::error('Discord multiple ping send error', [
                'error' => $e->getMessage(),
                'user' => auth()->id()
            ]);
            
            return redirect()->back()->with('error', 'Failed to send broadcasts. Please check logs.');
        }
    }

    /**
     * Keeps the request this broadcast answers only while the board would
     * still show it to this FC. The form's hidden field is never taken on trust.
     * It counts as answered once the broadcast reaches Discord.
     */
    private function withAnsweredRequest(Request $request, array $validated): array
    {
        unset($validated['answers_request']);

        if ($request->filled('answers_request') && RequestBoard::find(auth()->user(), $request->input('answers_request'))) {
            $validated['answers_request'] = (int) $request->input('answers_request');
        }

        return $validated;
    }
}
