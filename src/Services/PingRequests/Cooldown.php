<?php

namespace DiscordPings\Services\PingRequests;

class Cooldown
{
    /** An emergency is never held back: every second counts, so every press posts. */
    public static function secondsLeft(bool $isEmergency, int $cooldownMinutes, ?int $lastPressedAt, int $now): int
    {
        if ($isEmergency || $cooldownMinutes <= 0 || $lastPressedAt === null) {
            return 0;
        }

        return max(0, $lastPressedAt + $cooldownMinutes * 60 - $now);
    }

    public static function describe(int $seconds): string
    {
        if ($seconds < 60) {
            return $seconds . ($seconds === 1 ? ' second' : ' seconds');
        }

        $minutes = (int) ceil($seconds / 60);
        if ($minutes < 60) {
            return $minutes . ($minutes === 1 ? ' minute' : ' minutes');
        }

        $hours = intdiv($minutes, 60);
        $rest = $minutes % 60;
        $text = $hours . ($hours === 1 ? ' hour' : ' hours');

        return $rest === 0 ? $text : $text . ' ' . $rest . ($rest === 1 ? ' minute' : ' minutes');
    }
}
