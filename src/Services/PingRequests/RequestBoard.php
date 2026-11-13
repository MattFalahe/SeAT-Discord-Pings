<?php

namespace DiscordPings\Services\PingRequests;

use Carbon\Carbon;
use DiscordPings\Models\PingHistory;
use DiscordPings\Models\PingRequestButton;

/**
 * Member requests on FC Opportunities. Only what reached Discord shows, since
 * a press held back by a cooldown or a limit was never a call anybody heard.
 * An FC sees the requests made with a button open to one of their
 * corporations or alliances, and admins see them all.
 */
class RequestBoard
{
    /** How long a request stays on the board after it went out. */
    public const HOURS = 24;

    /** The board pages in the browser, so one visit never loads more than this. */
    private const MOST = 200;

    /** What the Send form's message box takes. */
    private const MESSAGE_LENGTH = 2000;

    /** @return PingHistory[] newest first */
    public static function forViewer($user): array
    {
        $rows = PingHistory::memberRequests()
            ->where('status', 'sent')
            ->where('created_at', '>=', Carbon::now()->subHours(self::HOURS))
            ->latest()
            ->take(self::MOST)
            ->get()
            ->all();

        return self::seesAll($user) ? $rows : self::madeWith($rows, self::openButtons($user));
    }

    /** The request a Form-up link names, when this FC could see it on the board. */
    public static function find($user, $id): ?PingHistory
    {
        if (! is_numeric($id)) {
            return null;
        }

        $row = PingHistory::memberRequests()->where('status', 'sent')->find((int) $id);

        if (! $row || self::seesAll($user)) {
            return $row;
        }

        return self::madeWith([$row], self::openButtons($user))[0] ?? null;
    }

    /**
     * The requests made with one of these buttons. One from a deleted button
     * is left out, since nothing says any more which group it was for.
     *
     * @param PingHistory[] $rows
     * @param int[] $buttonIds
     * @return PingHistory[]
     */
    public static function madeWith(array $rows, array $buttonIds): array
    {
        $open = array_flip($buttonIds);

        return array_values(array_filter($rows, fn ($row) => isset($open[(int) ($row->fields['button_id'] ?? 0)])));
    }

    /**
     * Where Form-up starts: who asked and what they wrote, for the FC to turn
     * into their own call before it goes anywhere.
     *
     * @return array{message: string, fc_name: string, formup_location: string, embed_type: string}
     */
    public static function prefill(PingHistory $row, $user): array
    {
        $fields = is_array($row->fields) ? $row->fields : [];
        $button = (string) ($fields['button'] ?? 'Ping request');
        $from = (string) ($fields['main'] ?? $row->user_name);
        $text = trim((string) $row->message);

        $lines = [($row->isEmergencyRequest() ? '🆘 ' : '') . $button . ' for ' . $from . ': forming up NOW!'];

        // A press with nothing filled in is recorded under the button's name.
        if ($text !== '' && $text !== $button) {
            $lines[] = $text;
        }

        return [
            'message' => mb_substr(implode("\n", $lines), 0, self::MESSAGE_LENGTH),
            'fc_name' => (string) ($user->name ?? ''),
            'formup_location' => '',
            'embed_type' => 'prepping',
        ];
    }

    private static function seesAll($user): bool
    {
        return $user && ($user->isAdmin() || $user->can('discordpings.admin'));
    }

    /** @return int[] */
    private static function openButtons($user): array
    {
        return PingRequestButton::inGroupOf($user)->pluck('id')->map(fn ($id) => (int) $id)->all();
    }
}
