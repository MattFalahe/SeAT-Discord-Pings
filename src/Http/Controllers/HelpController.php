<?php

namespace DiscordPings\Http\Controllers;

use DiscordPings\Services\VersionChecker;
use Illuminate\Routing\Controller;

class HelpController extends Controller
{
    /**
     * Display the help & documentation page
     */
    public function index()
    {
        // Latest-version check shown in the Overview card. The service caches
        // for 6h + has a 3s timeout, so a Packagist outage can never slow
        // the Help page meaningfully or break the render.
        $versionStatus = app(VersionChecker::class)->getStatus();

        return view('discordpings::help.index', compact('versionStatus'));
    }
}
