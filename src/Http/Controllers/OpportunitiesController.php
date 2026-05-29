<?php

namespace DiscordPings\Http\Controllers;

use Carbon\Carbon;
use DiscordPings\Models\TacticalEvent;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Illuminate\Support\Facades\Log;

/**
 * FC Opportunities: an FC-focused board listing upcoming structure timers
 * and fleet ops ingested via Manager Core. Each row offers a one-click
 * "Schedule formup ping" that opens the Scheduled Broadcast form pre-filled
 * from the op, plus links into Structure Manager and the Broadcasts Calendar.
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

            return view('discordpings::opportunities.index', compact(
                'events', 'rangeDays',
                'managerCoreInstalled', 'structureManagerInstalled', 'miningManagerInstalled'
            ));

        } catch (\Exception $e) {
            Log::error('Discord Pings opportunities view error: ' . $e->getMessage());
            return redirect()->back()->with('error', 'Unable to load FC Opportunities. Please check logs.');
        }
    }
}
