<?php
namespace MattFalahe\Seat\DiscordPings\Http\Controllers;

use Illuminate\Routing\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use MattFalahe\Seat\DiscordPings\Models\ScheduledPing;
use MattFalahe\Seat\DiscordPings\Models\DiscordWebhook;
use MattFalahe\Seat\DiscordPings\Models\DiscordRole;
use MattFalahe\Seat\DiscordPings\Models\DiscordChannel;
use MattFalahe\Seat\DiscordPings\Models\StagingLocation;
use MattFalahe\Seat\DiscordPings\Models\PapType;
use MattFalahe\Seat\DiscordPings\Models\PingHistory;
use Carbon\Carbon;
use Illuminate\Support\Str;

class ScheduledController extends Controller
{
    /**
     * Display scheduled pings
     */
    public function index()
    {
        try {
            $canSeeAll = auth()->user()->can('discordpings.manage_scheduled');
            $with = $canSeeAll ? ['webhook', 'user'] : ['webhook'];
            $query = ScheduledPing::with($with);

            if (!$canSeeAll) {
                $query->where('user_id', auth()->id());
            }

            $scheduledPings = $query->orderBy('scheduled_at')->get();

            return view('discordpings::scheduled.index', compact('scheduledPings', 'canSeeAll'));
            
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
            $roles = DiscordRole::active()->get();
            $channels = DiscordChannel::active()->get();
            $stagings = StagingLocation::active()->get();
            $papTypes = PapType::active()->ordered()->get();

            // Check for seat-fitting plugin
            $doctrines = [];
            $hasFittingPlugin = false;
            
            if (class_exists('CryptaTech\Seat\Fitting\Models\Doctrine')) {
                $hasFittingPlugin = true;
                try {
                    $doctrines = \CryptaTech\Seat\Fitting\Models\Doctrine::all();
                } catch (\Exception $e) {
                    Log::info('CryptaTech seat-fitting plugin found but could not load doctrines: ' . $e->getMessage());
                }
            } elseif (class_exists('Denngarr\Seat\Fitting\Models\Doctrine')) {
                $hasFittingPlugin = true;
                try {
                    $doctrines = \Denngarr\Seat\Fitting\Models\Doctrine::all();
                } catch (\Exception $e) {
                    Log::info('Denngarr seat-fitting plugin found but could not load doctrines: ' . $e->getMessage());
                }
            }
            
            return view('discordpings::scheduled.create', compact(
                'webhooks',
                'roles',
                'channels',
                'stagings',
                'papTypes',
                'doctrines',
                'hasFittingPlugin'
            ));
            
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
            // First validate the basic fields
            $validated = $request->validate([
                'webhook_id' => 'required|exists:discord_webhooks,id',
                'message' => 'required|string|max:2000',
                'embed_type' => 'nullable|string|in:fleet,announcement,message,prepping',
                'repeat_interval' => 'nullable|in:hourly,daily,weekly,monthly',
                // Optional fields
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
            ]);
            
            // Handle the UTC time from JavaScript
            $scheduledAtUtc = $request->input('scheduled_at_utc');
            $repeatUntilUtc = $request->input('repeat_until_utc');
            
            // If no UTC time provided (JavaScript disabled), fall back to direct input as local time
            if (!$scheduledAtUtc) {
                $scheduledAtUtc = $request->input('scheduled_at');
            }
            
            // Parse and validate the scheduled time
            try {
                $scheduledAt = Carbon::parse($scheduledAtUtc);
                
                // Make sure it's in the future (check in UTC)
                if ($scheduledAt->isPast()) {
                    return redirect()->back()
                        ->withInput()
                        ->with('error', 'Scheduled time must be in the future.');
                }
            } catch (\Exception $e) {
                return redirect()->back()
                    ->withInput()
                    ->with('error', 'Invalid scheduled date/time format.');
            }
            
            // Parse repeat until if provided
            $repeatUntil = null;
            if ($repeatUntilUtc) {
                try {
                    $repeatUntil = Carbon::parse($repeatUntilUtc);
                    
                    if ($repeatUntil->lte($scheduledAt)) {
                        return redirect()->back()
                            ->withInput()
                            ->with('error', 'Repeat until time must be after scheduled time.');
                    }
                } catch (\Exception $e) {
                    // If parsing fails, set to null (no repeat end)
                    $repeatUntil = null;
                }
            }
            
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
                return redirect()->back()->with('error', 'You have reached the maximum number of scheduled broadcasts.');
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
                    try {
                        $doctrineUrl = route('fitting.doctrineviewdetails', ['doctrine_id' => $doctrine->id]);
                        $validated['doctrine'] = "[{$doctrine->name}]({$doctrineUrl})";
                        $validated['doctrine_url'] = $doctrineUrl;
                    } catch (\Exception $e) {
                        $validated['doctrine'] = $doctrine->name;
                    }
                    $validated['doctrine_name'] = $doctrine->name;
                }
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
            
            // Build fields array
            $fields = [];
            $fieldKeys = ['fc_name', 'formup_location', 'pap_type', 'comms', 'doctrine', 
                         'doctrine_name', 'doctrine_url', 'mention_type', 'custom_mention', 
                         'embed_color', 'embed_type', 'role_mention', 'channel_link', 
                         'channel_url', 'channel_mention'];
            
            foreach ($fieldKeys as $key) {
                if (isset($validated[$key]) && !empty($validated[$key])) {
                    $fields[$key] = $validated[$key];
                }
            }
            
            // Create scheduled ping - times are already in Carbon format
            ScheduledPing::create([
                'webhook_id' => $validated['webhook_id'],
                'user_id' => auth()->id(),
                'message' => $validated['message'],
                'fields' => $fields,
                'scheduled_at' => $scheduledAt,  // Already a Carbon instance in UTC
                'repeat_interval' => $validated['repeat_interval'] ?? null,
                'repeat_until' => $repeatUntil,  // Already a Carbon instance in UTC or null
                'is_active' => true,
            ]);
            
            // Success message showing EVE time
            return redirect()->route('discordpings.scheduled')
                ->with('success', sprintf(
                    'Broadcast scheduled successfully for %s EVE!',
                    $scheduledAt->format('Y-m-d H:i:s')
                ));
                
        } catch (\Exception $e) {
            Log::error('Discord Pings scheduled store error: ' . $e->getMessage() . ' Trace: ' . $e->getTraceAsString());
            return redirect()->back()
                ->withInput()
                ->with('error', 'Failed to schedule broadcast. Error: ' . $e->getMessage());
        }
    }
    
    /**
     * Delete scheduled ping
     */
    public function edit($id)
    {
        try {
            $ping = ScheduledPing::findOrFail($id);

            if ($ping->user_id != auth()->id() && !auth()->user()->can('discordpings.manage_scheduled')) {
                abort(403, 'Unauthorized');
            }

            $webhooks  = DiscordWebhook::active()->get();
            $roles     = DiscordRole::active()->get();
            $channels  = DiscordChannel::active()->get();
            $stagings  = StagingLocation::active()->get();
            $papTypes  = PapType::active()->ordered()->get();

            $hasFittingPlugin = false;
            $doctrines = collect();
            foreach (['CryptaTech\Seat\Fitting\Models\Doctrine', 'Denngarr\Seat\Fitting\Models\Doctrine'] as $class) {
                if (class_exists($class)) {
                    try { $doctrines = $class::orderBy('name')->get(); $hasFittingPlugin = true; break; } catch (\Exception $e) {}
                }
            }

            // If a specific occurrence time was passed from the calendar, use it to pre-fill
            $fromTime = request('from');
            if ($fromTime) {
                try {
                    $ping->scheduled_at = Carbon::parse($fromTime)->utc();
                } catch (\Exception $e) {}
            }

            return view('discordpings::scheduled.edit', compact(
                'ping', 'webhooks', 'roles', 'channels', 'stagings', 'papTypes', 'hasFittingPlugin', 'doctrines'
            ));
        } catch (\Exception $e) {
            Log::error('Discord Pings scheduled edit error: ' . $e->getMessage());
            return redirect()->back()->with('error', 'Unable to load scheduled ping for editing.');
        }
    }

    public function update(Request $request, $id)
    {
        try {
            $ping = ScheduledPing::findOrFail($id);

            if ($ping->user_id != auth()->id() && !auth()->user()->can('discordpings.manage_scheduled')) {
                abort(403, 'Unauthorized');
            }

            $validated = $request->validate([
                'webhook_id'       => 'required|exists:discord_webhooks,id',
                'message'          => 'required|string|max:2000',
                'embed_type'       => 'nullable|string|in:fleet,announcement,message,prepping',
                'repeat_interval'  => 'nullable|in:hourly,daily,weekly,monthly',
                'fc_name'          => 'nullable|string|max:100',
                'formup_location'  => 'nullable|string|max:100',
                'pap_type'         => 'nullable|string|max:100',
                'comms'            => 'nullable|string|max:200',
                'doctrine'         => 'nullable|string|max:200',
                'doctrine_id'      => 'nullable|integer',
                'mention_type'     => 'nullable|string|in:none,everyone,here,role,custom',
                'role_mention'     => 'nullable|exists:discord_roles,id',
                'channel_link'     => 'nullable|exists:discord_channels,id',
                'custom_mention'   => 'nullable|string|max:100',
                'embed_color'      => 'nullable|string|regex:/^#[0-9A-Fa-f]{6}$/',
            ]);

            $scheduledAtUtc = $request->input('scheduled_at_utc') ?: $request->input('scheduled_at');
            $repeatUntilUtc = $request->input('repeat_until_utc');

            try {
                $scheduledAt = Carbon::parse($scheduledAtUtc);
                if ($scheduledAt->isPast()) {
                    return redirect()->back()->withInput()->with('error', 'Scheduled time must be in the future.');
                }
            } catch (\Exception $e) {
                return redirect()->back()->withInput()->with('error', 'Invalid scheduled date/time format.');
            }

            $repeatUntil = null;
            if ($repeatUntilUtc) {
                try {
                    $repeatUntil = Carbon::parse($repeatUntilUtc);
                    if ($repeatUntil->lte($scheduledAt)) {
                        return redirect()->back()->withInput()->with('error', 'Repeat until time must be after scheduled time.');
                    }
                } catch (\Exception $e) {
                    $repeatUntil = null;
                }
            }

            $webhook = DiscordWebhook::find($validated['webhook_id']);
            if (!$webhook || !$webhook->is_active) {
                return redirect()->back()->with('error', 'Invalid or inactive webhook.');
            }

            // Handle doctrine
            if (!empty($validated['doctrine_id'])) {
                foreach (['CryptaTech\Seat\Fitting\Models\Doctrine', 'Denngarr\Seat\Fitting\Models\Doctrine'] as $class) {
                    if (class_exists($class)) {
                        try {
                            $doctrine = $class::find($validated['doctrine_id']);
                            if ($doctrine) {
                                try { $validated['doctrine'] = "[{$doctrine->name}](" . route('fitting.doctrineviewdetails', ['doctrine_id' => $doctrine->id]) . ")"; } catch (\Exception $e) { $validated['doctrine'] = $doctrine->name; }
                                $validated['doctrine_name'] = $doctrine->name;
                                break;
                            }
                        } catch (\Exception $e) {}
                    }
                }
            }

            // Handle role mention
            if (($validated['mention_type'] ?? 'none') === 'role' && !empty($validated['role_mention'])) {
                $role = DiscordRole::find($validated['role_mention']);
                if ($role) $validated['custom_mention'] = $role->getMentionString();
            }

            // Handle channel link
            if (!empty($validated['channel_link'])) {
                $channel = DiscordChannel::find($validated['channel_link']);
                if ($channel) {
                    $validated['channel_url']     = $channel->getChannelLink();
                    $validated['channel_mention'] = $channel->getMentionString();
                }
            }

            $fields = [];
            foreach (['fc_name', 'formup_location', 'pap_type', 'comms', 'doctrine', 'doctrine_name', 'doctrine_url', 'mention_type', 'custom_mention', 'embed_color', 'embed_type', 'role_mention', 'channel_link', 'channel_url', 'channel_mention'] as $key) {
                if (isset($validated[$key]) && !empty($validated[$key])) {
                    $fields[$key] = $validated[$key];
                }
            }

            $ping->update([
                'webhook_id'      => $validated['webhook_id'],
                'message'         => $validated['message'],
                'fields'          => $fields,
                'scheduled_at'    => $scheduledAt,
                'repeat_interval' => $validated['repeat_interval'] ?? null,
                'repeat_until'    => $repeatUntil,
                'is_active'       => true,
            ]);

            return redirect()->route('discordpings.scheduled.calendar')
                ->with('success', sprintf('Broadcast updated and rescheduled for %s EVE!', $scheduledAt->format('Y-m-d H:i')));

        } catch (\Exception $e) {
            Log::error('Discord Pings scheduled update error: ' . $e->getMessage());
            return redirect()->back()->withInput()->with('error', 'Failed to update scheduled broadcast. Error: ' . $e->getMessage());
        }
    }

    public function destroy($id)
    {
        try {
            $scheduledPing = ScheduledPing::findOrFail($id);

            // Allow deletion if owner or has manage_scheduled permission
            if ($scheduledPing->user_id != auth()->id() && !auth()->user()->can('discordpings.manage_scheduled')) {
                abort(403, 'Unauthorized');
            }
            
            $scheduledPing->delete();
            
            return redirect()->back()->with('success', 'Scheduled ping deleted successfully!');
            
        } catch (\Exception $e) {
            Log::error('Discord Pings scheduled delete error: ' . $e->getMessage());
            return redirect()->back()->with('error', 'Failed to delete scheduled ping. Please check logs.');
        }
    }

    /**
     * Bulk delete inactive scheduled pings older than X days (admin/director only)
     */
    public function bulkDestroyInactive(Request $request)
    {
        try {
            $days = (int) $request->input('days', 30);

            if (!in_array($days, [7, 30])) {
                return redirect()->back()->with('error', 'Invalid days value. Must be 7 or 30.');
            }

            $cutoff = Carbon::now()->subDays($days);

            $count = ScheduledPing::where('is_active', false)
                ->where('scheduled_at', '<', $cutoff)
                ->count();

            ScheduledPing::where('is_active', false)
                ->where('scheduled_at', '<', $cutoff)
                ->delete();

            return redirect()->back()->with('success', "Cleared {$count} inactive scheduled ping(s) older than {$days} days.");

        } catch (\Exception $e) {
            Log::error('Discord Pings bulk clear error: ' . $e->getMessage());
            return redirect()->back()->with('error', 'Failed to clear inactive pings. Please check logs.');
        }
    }

    /**
     * Display calendar view
     */
    public function calendar()
    {
        try {
            $webhooks = DiscordWebhook::active()->get();

            return view('discordpings::scheduled.calendar', compact('webhooks'));
        } catch (\Exception $e) {
            Log::error('Discord Pings calendar view error: ' . $e->getMessage());
            return redirect()->back()->with('error', 'Unable to load calendar. Please check logs.');
        }
    }

    /**
     * Return scheduled events as JSON for FullCalendar
     */
    public function calendarEvents(Request $request)
    {
        try {
            $start = Carbon::parse($request->input('start'));
            $end = Carbon::parse($request->input('end'));

            $canSeeAll = auth()->user()->can('discordpings.manage_scheduled');
            $with = $canSeeAll ? ['webhook', 'user'] : ['webhook'];
            $query = ScheduledPing::with($with);

            if (!$canSeeAll) {
                $query->where('user_id', auth()->id());
            }

            $pings = $query
                ->where(function ($q) use ($end) {
                    $q->where('scheduled_at', '<=', $end);
                })
                ->get();

            $events = [];

            foreach ($pings as $ping) {
                $color = $ping->webhook ? $ping->webhook->embed_color : '#6c757d';
                $webhookName = $ping->webhook ? $ping->webhook->name : 'Deleted';
                $creatorName = $ping->relationLoaded('user') && $ping->user ? $ping->user->name : null;

                if (!$ping->repeat_interval || !$ping->is_active) {
                    // One-time ping or inactive recurring: show only the scheduled_at occurrence
                    if ($ping->scheduled_at->gte($start) && $ping->scheduled_at->lte($end)) {
                        $events[] = $this->buildCalendarEvent($ping, $color, $webhookName, null, $creatorName);
                    }
                } else {
                    // Active recurring ping: expand occurrences within the range
                    $current = $ping->scheduled_at->copy();
                    $repeatEnd = $ping->repeat_until ?? $end;
                    $limit = 200; // Safety limit
                    $count = 0;

                    while ($current->lte($end) && $current->lte($repeatEnd) && $count < $limit) {
                        if ($current->gte($start)) {
                            $events[] = $this->buildCalendarEvent($ping, $color, $webhookName, $current->copy(), $creatorName);
                        }

                        switch ($ping->repeat_interval) {
                            case 'hourly':
                                $current->addHour();
                                break;
                            case 'daily':
                                $current->addDay();
                                break;
                            case 'weekly':
                                $current->addWeek();
                                break;
                            case 'monthly':
                                $current->addMonth();
                                break;
                            default:
                                $count = $limit; // break the loop
                        }
                        $count++;
                    }
                }
            }

            // Add manually sent pings from history
            $canSeeAllHistory = auth()->user()->can('discordpings.view_all_history');
            $canSeeOwnHistory = auth()->user()->can('discordpings.view_history');

            if ($canSeeOwnHistory || $canSeeAllHistory) {
                $historyQuery = PingHistory::with(['webhook', 'user'])
                    ->where('status', 'sent')
                    ->whereBetween('created_at', [$start, $end]);

                if (!$canSeeAllHistory) {
                    $historyQuery->where('user_id', auth()->id());
                }

                foreach ($historyQuery->get() as $history) {
                    $color = $history->webhook ? $history->webhook->embed_color : '#6c757d';
                    $webhookName = $history->webhook ? $history->webhook->name : 'Deleted';
                    $creatorName = $history->user ? $history->user->name : ($history->user_name ?? 'Unknown');

                    $events[] = [
                        'id' => 'history_' . $history->id,
                        'title' => Str::limit($history->message, 40),
                        'start' => $history->created_at->toIso8601String(),
                        'backgroundColor' => 'transparent',
                        'borderColor' => 'transparent',
                        'extendedProps' => [
                            'ping_id' => null,
                            'history_id' => $history->id,
                            'message' => $history->message,
                            'webhook' => $webhookName,
                            'webhookColor' => $color,
                            'repeat' => 'Manual',
                            'repeatUntil' => null,
                            'timesSent' => 1,
                            'fields' => $history->fields,
                            'createdBy' => $creatorName,
                            'isActive' => false,
                            'isHistory' => true,
                        ],
                    ];
                }
            }

            return response()->json($events);
        } catch (\Exception $e) {
            Log::error('Discord Pings calendar events error: ' . $e->getMessage());
            return response()->json([], 500);
        }
    }

    /**
     * Build a single calendar event array
     */
    private function buildCalendarEvent(ScheduledPing $ping, string $color, string $webhookName, ?Carbon $overrideTime = null, ?string $creatorName = null)
    {
        $time = $overrideTime ?? $ping->scheduled_at;

        return [
            'id' => $ping->id . '_' . $time->timestamp,
            'title' => Str::limit($ping->message ?: '(no message)', 40),
            'start' => $time->toIso8601String(),
            'color' => $color,
            'extendedProps' => [
                'ping_id' => $ping->id,
                'message' => $ping->message,
                'webhook' => $webhookName,
                'webhookColor' => $color,
                'repeat' => $ping->repeat_interval ? ucfirst($ping->repeat_interval) : 'Once',
                'repeatUntil' => $ping->repeat_until ? $ping->repeat_until->format('Y-m-d H:i') . ' EVE' : null,
                'timesSent' => $ping->times_sent,
                'fields' => $ping->fields,
                'createdBy' => $creatorName,
                'isActive' => (bool) $ping->is_active,
            ],
        ];
    }
}
