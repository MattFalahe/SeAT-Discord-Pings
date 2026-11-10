<?php

namespace DiscordPings\Services\PingRequests;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

/**
 * Ship names for the boxes that ask for one, out of SeAT's copy of the static
 * data. Published hulls only, so CCP's test ships and ones never released
 * stay out of the list.
 */
class ShipLookup
{
    private const CATEGORY_SHIP = 6;

    /** @return string[] */
    public function names(): array
    {
        try {
            $names = DB::table('invTypes')
                ->join('invGroups', 'invGroups.groupID', '=', 'invTypes.groupID')
                ->where('invGroups.categoryID', self::CATEGORY_SHIP)
                ->where('invTypes.published', 1)
                ->orderBy('invTypes.typeName')
                ->pluck('invTypes.typeName')
                ->map(fn ($name) => (string) $name)
                ->all();

            return array_values(array_unique($names));
        } catch (\Throwable $e) {
            Log::warning('[DiscordPings] Could not read ship types for ping request suggestions: ' . $e->getMessage());

            return [];
        }
    }
}
