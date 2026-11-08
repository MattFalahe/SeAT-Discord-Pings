<?php

namespace DiscordPings\Models\Concerns;

use Illuminate\Support\Facades\DB;

/**
 * For anything limited by corporation_id and role_id columns.
 *
 * A row is visible when BOTH gates pass:
 *   - corporation_id is null (no gate) OR one of the viewer's corps
 *   - role_id is null (no gate) OR one of the viewer's SeAT roles
 *
 * Corp membership uses the canonical ecosystem resolver
 * (refresh_tokens + character_affiliations).
 */
trait VisibleToViewer
{
    public function scopeVisibleTo($query, $user)
    {
        // Admin bypass — superusers and Broadcast admins see every row
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
}
