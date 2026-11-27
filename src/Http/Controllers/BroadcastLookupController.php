<?php

namespace DiscordPings\Http\Controllers;

use DiscordPings\Services\DiscordUserResolver;
use DiscordPings\Services\MemberMentions;
use DiscordPings\Services\PingRequests\LocationLookup;
use DiscordPings\Services\PingRequests\MemberAccount;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;

/**
 * The FC side of the system and spot suggestions, for the Send and Schedule
 * forms. The same lookup the Ping Requests page uses, behind the FC's own
 * permission rather than Line Member's. Also what the mention box pings, and
 * who it can pick from.
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

    /** The same lookup the broadcast makes as it goes out, so the line under the box can't promise a ping it won't get. */
    public function mentions(Request $request)
    {
        $text = $request->query('text');

        return response()->json(MemberMentions::check(is_string($text) ? $text : ''));
    }

    public function members()
    {
        return response()->json(DiscordUserResolver::linkedMembers());
    }
}
