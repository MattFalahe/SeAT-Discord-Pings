<?php

namespace DiscordPings\Http\Controllers;

use Carbon\Carbon;
use DiscordPings\Models\PingRequestButton;
use DiscordPings\Models\TacticalEvent;
use DiscordPings\Services\PingRequests\ErrorText;
use DiscordPings\Services\PingRequests\RequestBoard;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Illuminate\Support\Facades\Log;

/**
 * FC Opportunities: an FC-focused board listing upcoming structure timers,
 * fleet ops and moon extractions ingested via Manager Core. Each row opens
 * the Send form (Form-up) or the Scheduled Broadcast form (Schedule)
 * pre-filled from the op, and links back to the plugin it came from.
 * Requests line members sent recently get a table of their own above it.
 *
 * Honors the per-event corp/role visibility contract: an FC only sees ops
 * scoped to their corporation/role (or globally scoped ones).
 */
class OpportunitiesController extends Controller
{
    public function index(Request $request)
    {
        try {
            $rangeDays = (int) $request->input('range', 7);
            $rangeDays = max(1, min(30, $rangeDays));

            $now    = Carbon::now();
            $cutoff = $now->copy()->addDays($rangeDays);

            // Window: include ops up to 2h elapsed (operators often want to
            // see what just slipped) through `range` days into the future.
            $events = TacticalEvent::onCalendar()
                ->visibleTo(auth()->user())
                ->whereNotNull('eve_time')
                ->where('eve_time', '>=', $now->copy()->subHours(2))
                ->where('eve_time', '<=', $cutoff)
                ->with(['scheduledPings' => function ($q) {
                    $q->where('is_active', true);
                }])
                ->orderBy('eve_time', 'asc')
                ->get();

            $managerCoreInstalled      = class_exists('\ManagerCore\Services\EventBus');
            $structureManagerInstalled = class_exists('\StructureManager\Services\TimerEventPublisher');
            $miningManagerInstalled    = class_exists('\MiningManager\Services\Events\MoonExtractionEventPublisher');

            // Read on its own, so a problem with the requests never takes the timers down with it.
            $requests = [];
            $requestsInUse = false;
            $requestHours = RequestBoard::HOURS;
            try {
                $requests = RequestBoard::forViewer(auth()->user());
                $requestsInUse = $requests !== [] || PingRequestButton::active()->exists();
            } catch (\Throwable $e) {
                Log::warning('[DiscordPings] Could not load member requests for FC Opportunities: ' . ErrorText::safe($e->getMessage()));
            }

            return view('discordpings::opportunities.index', compact(
                'events', 'rangeDays',
                'managerCoreInstalled', 'structureManagerInstalled', 'miningManagerInstalled',
                'requests', 'requestsInUse', 'requestHours'
            ));

        } catch (\Exception $e) {
            Log::error('Discord Pings opportunities view error: ' . $e->getMessage());
            return redirect()->back()->with('error', 'Unable to load FC Opportunities. Please check logs.');
        }
    }
}
