<?php

namespace DiscordPings\Services\PingRequests;

class ErrorText
{
    /**
     * HTTP errors quote the full webhook address, and the token in it is all
     * anyone needs to post as that webhook. Nothing that gets logged, stored
     * or shown may keep it.
     */
    public static function safe(string $text): string
    {
        return (string) preg_replace('~(?:https?://[^\s`\'"]*)?/webhooks/[^\s`\'"]+~i', '[webhook address removed]', $text);
    }
}
