<?php

namespace DiscordPings\Models;

use Carbon\Carbon;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;

/**
 * A tactical event ingested from another plugin via Manager Core's EventBus.
 *
 * Today the only publisher is Structure Manager (`structure_manager.timer.*`):
 * auto-detected reinforce timers, anchoring, fuel-expiry timers, and manually
 * entered fleet ops. Rows are written by StructureTimerHandler and surfaced on
 * the Broadcasts Calendar.
 *
 * Stays empty and inert when Manager Core is not installed.
 */
class TacticalEvent extends Model
{
    protected $table = 'discord_tactical_events';

    protected $fillable = [
        'source_plugin', 'external_timer_id', 'last_event_id',
        'event_type', 'category_group', 'severity', 'title',
        'eve_time', 'is_manual', 'is_elapsed',
        'structure_id', 'structure_name', 'structure_type',
        'system_id', 'system_name', 'system_security',
        'owner_corporation_name', 'attacker_corporation_name',
        'corporation_id', 'role_id',
        'url', 'notes', 'status', 'payload',
        'pinged_24h_at', 'pinged_1h_at', 'last_seen_at',
    ];

    protected $casts = [
        'eve_time'      => 'datetime',
        'last_seen_at'  => 'datetime',
        'pinged_24h_at' => 'datetime',
        'pinged_1h_at'  => 'datetime',
        'is_manual'     => 'boolean',
        'is_elapsed'    => 'boolean',
        'payload'       => 'array',
    ];

    /**
     * Scheduled pings an FC has set up for this op (form-up broadcasts
     * created from the Calendar modal or the FC Opportunities board).
     * Pure informational correlation — pruning a tactical event does
     * NOT cascade-delete its scheduled-ping rows.
     */
    public function scheduledPings()
    {
        return $this->hasMany(ScheduledPing::class, 'tactical_event_id');
    }

    /**
     * Active timers — still relevant, shown solid on the calendar.
     */
    public function scopeActive($query)
    {
        return $query->where('status', 'active');
    }

    /**
     * Everything that belongs on the calendar — active plus elapsed (elapsed
     * renders dimmed). Dismissed timers are hidden.
     */
    public function scopeOnCalendar($query)
    {
        return $query->where('status', '!=', 'dismissed');
    }

    /**
     * Apply the cross-plugin visibility contract for a given viewer.
     *
     * An event is visible when BOTH gates pass:
     *   - corporation_id is null (global) OR one of the viewer's corps
     *   - role_id is null (no gate) OR one of the viewer's SeAT roles
     *
     * Corp membership uses the canonical ecosystem resolver
     * (refresh_tokens + character_affiliations).
     */
    public function scopeVisibleTo($query, $user)
    {
        // Admin bypass — superusers and Broadcast admins see every event
        // regardless of corp/role scope. Mirrors SM's StructureBoard pattern
        // (StructureBoardController::index sets $isBoardAdmin from isAdmin()
        // and lets that bypass Timer::visibleTo()). Without this, operators
        // testing via the Notification Lab couldn't see their test events
        // in FC Opportunities: test structures are owned by the 2.1B test
        // corp range that no real user is a member of, so the per-corp
        // filter below silently hides them.
        if ($user && ($user->isAdmin() || $user->can('discordpings.admin'))) {
            return $query;
        }

        $corpIds = [];
        $roleIds = [];

        if ($user) {
            try {
                $corpIds = DB::table('refresh_tokens')
                    ->where('refresh_tokens.user_id', $user->id)
                    ->whereNull('refresh_tokens.deleted_at')
                    ->join('character_affiliations', 'refresh_tokens.character_id', '=', 'character_affiliations.character_id')
                    ->pluck('character_affiliations.corporation_id')
                    ->map(fn ($id) => (int) $id)
                    ->unique()
                    ->values()
                    ->all();
            } catch (\Throwable $e) {
                $corpIds = [];
            }

            try {
                $roleIds = $user->roles->pluck('id')->map(fn ($id) => (int) $id)->all();
            } catch (\Throwable $e) {
                $roleIds = [];
            }
        }

        return $query
            ->where(function ($q) use ($corpIds) {
                $q->whereNull('corporation_id');
                if (! empty($corpIds)) {
                    $q->orWhereIn('corporation_id', $corpIds);
                }
            })
            ->where(function ($q) use ($roleIds) {
                $q->whereNull('role_id');
                if (! empty($roleIds)) {
                    $q->orWhereIn('role_id', $roleIds);
                }
            });
    }

    /**
     * Build the broadcast-form prefill payload for this tactical event,
     * tailored to one of two FC workflows:
     *
     *   - 'send'     : the FC wants to send a broadcast RIGHT NOW (form-up
     *                  call). No scheduled_at. Embed type defaults to
     *                  'prepping' (the urgent "we're staging" template).
     *                  Message is short + urgent.
     *
     *   - 'schedule' : the FC wants to schedule a broadcast that fires
     *                  before the timer / window closes. scheduled_at is
     *                  pre-populated to (eve_time − formup_offset_minutes),
     *                  clamped to "now + 5 min" if the deadline is past.
     *                  Embed type defaults to 'fleet'. Message is informational.
     *
     * Both modes return the same canonical keys, so callers can simply
     * spread the result into the form's `old()` defaults. The schedule
     * mode adds a `scheduled_at` key; send mode omits it.
     *
     * Category-aware: mining ops use moon name + window-closes language;
     * structure timers use structure name + timer language; hostile and
     * defense ops get the matching emoji prefix.
     */
    public function buildBroadcastPrefill(string $mode = 'schedule', ?object $user = null): array
    {
        $user ??= auth()->user();
        $isMining   = $this->category_group === 'mining';
        $payload    = is_array($this->payload) ? $this->payload : [];

        // Op label — emoji + short noun phrase matching the calendar/board.
        $opLabel = match (true) {
            $isMining                                  => '⛏️ Mining',
            $this->event_type === 'hostile_op'         => '⚔️ Hostile op',
            $this->event_type === 'defense_op'         => '🛡️ Defense op',
            default => trim(ucwords(str_replace(['_', '-'], ' ', (string) $this->event_type))) ?: 'Structure op',
        };

        // Target line — for mining we lead with moon, fall back to structure.
        // For structure events we lead with structure_name then system.
        if ($isMining) {
            $moonName      = $payload['moon_name']      ?? null;
            $structureName = $payload['structure_name'] ?? $this->structure_name;
            $target = $moonName ?: $structureName ?: ($this->system_name ?: 'TBC');
            if ($moonName && $structureName && $structureName !== $moonName) {
                $target .= ' (' . $structureName . ')';
            }
        } else {
            $target = $this->structure_name
                ? $this->structure_name . ($this->system_name ? ' (' . $this->system_name . ')' : '')
                : ($this->system_name ?: 'TBC');
        }

        $eveTimeStr    = optional($this->eve_time)->format('Y-m-d H:i');
        $deadlineLabel = $isMining ? 'Window closes' : 'Timer';

        if ($mode === 'send') {
            // Immediate send — urgent + concise. PREPING embed type signals
            // "we're forming up RIGHT NOW" in Discord.
            $message = sprintf(
                "%s — forming up NOW!\n%s\n%s: %s EVE",
                $opLabel,
                $target,
                $deadlineLabel,
                $eveTimeStr ?: 'TBC'
            );

            return [
                'message'         => $message,
                'fc_name'         => optional($user)->name ?: '',
                'formup_location' => $this->system_name ?: '',
                'embed_type'      => 'prepping',
            ];
        }

        // Default: schedule mode.
        $offsetMinutes = (int) PluginSetting::getValue(
            'formup_offset_minutes',
            (int) config('discordpings.structure_events.formup_offset_minutes', 30)
        );
        $offsetMinutes = max(5, min(720, $offsetMinutes));

        $scheduledAt = $this->eve_time
            ? $this->eve_time->copy()->subMinutes($offsetMinutes)
            : Carbon::now()->addMinutes($offsetMinutes);

        if ($scheduledAt->isPast()) {
            $scheduledAt = Carbon::now()->addMinutes(5);
        }

        $message = sprintf(
            '%s: %s. %s at %s EVE. Form up %d min prior.',
            $opLabel,
            $target,
            $deadlineLabel,
            $eveTimeStr ?: 'TBC',
            $offsetMinutes
        );

        return [
            'scheduled_at'    => $scheduledAt->format('Y-m-d\TH:i'),
            'message'         => $message,
            'fc_name'         => optional($user)->name ?: '',
            'formup_location' => $this->system_name ?: '',
            'embed_type'      => 'fleet',
        ];
    }
}
