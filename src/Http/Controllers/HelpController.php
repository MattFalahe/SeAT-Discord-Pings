<?php

namespace MattFalahe\Seat\DiscordPings\Http\Controllers;

use Illuminate\Routing\Controller;

class HelpController extends Controller
{
    /**
     * Display the help & documentation page
     */
    public function index()
    {
        return view('discordpings::help.index');
    }
}
