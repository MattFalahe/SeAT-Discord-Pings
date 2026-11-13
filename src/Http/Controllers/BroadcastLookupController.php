<?php

namespace DiscordPings\Http\Controllers;

use DiscordPings\Services\PingRequests\LocationLookup;
use DiscordPings\Services\PingRequests\MemberAccount;
use Illuminate\Routing\Controller;

/**
 * The FC side of the system and spot suggestions, for the Send and Schedule
 * forms. The same lookup the Ping Requests page uses, behind the FC's own
 * permission rather than Line Member's.
 */
class BroadcastLookupController extends Controller
{
    public function systems(LocationLookup $lookup)
    {
        $systems = $lookup->systems();
        $response = response()->json($systems);

        // The map hardly ever changes, but an empty list means the read failed and that mustn't stick for a day.
        return $systems === [] ? $response : $response->header('Cache-Control', 'private, max-age=86400');
    }

    /** Structures only from the FC's own corporations and alliances, like the member's page. */
    public function locations($id, LocationLookup $lookup)
    {
        return response()->json($lookup->locations((int) $id, MemberAccount::affiliations((int) auth()->id())));
    }
}
