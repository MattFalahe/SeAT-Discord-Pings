<?php

namespace DiscordPings\Services\PingRequests;

use Carbon\Carbon;
use DiscordPings\Models\PingHistory;
use DiscordPings\Models\PingRequestButton;
use DiscordPings\Services\BroadcastForm;

/**
 * Member requests on FC Opportunities. Only what reached Discord shows, since
 * a press held back by a cooldown or a limit was never a call anybody heard.
 * An FC sees the requests made with a button open to one of their
 * corporations or alliances, and admins see them all.
 *
 * The link in a request's Discord message and Form-up on the board both come
 * through here, so neither can open a request the FC couldn't see on the board.
 */
class RequestBoard
{
    /** How long a request stays on the board after it went out, and can be answered. */
    public const HOURS = 24;

    /** The board pages in the browser, so one visit never loads more than this. */
    private const MOST = 200;

    /** What the Send form's message box takes. */
    private const MESSAGE_LENGTH = 2000;

    /** @return PingHistory[] newest first */
    public static function forViewer($user): array
    {
        $rows = self::open()->latest()->take(self::MOST)->get()->all();

        return self::seesAll($user) ? $rows : self::madeWith($rows, self::openButtons($user));
    }

    /** The request Form-up on the board names. */
    public static function find($user, $id): ?PingHistory
    {
        if (! is_numeric($id)) {
            return null;
        }

        return self::visibleTo($user, self::open()->find((int) $id));
    }

    /** The request the link in its Discord message names. */
    public static function findByRef($user, $ref): ?PingHistory
    {
        if (! self::isRef($ref)) {
            return null;
        }

        return self::visibleTo($user, self::open()->where('fields->ref', $ref)->first());
    }

    /**
     * Made before the message goes out, because the link has to be in it and
     * the History row is only written once Discord has answered.
     */
    public static function newRef(): string
    {
        return bin2hex(random_bytes(8));
    }

    public static function isRef($ref): bool
    {
        return is_string($ref) && strlen($ref) === 16 && ctype_xdigit($ref);
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
     * Where an answer starts: what the button sets for its answers, then who
     * asked and what they wrote, for the FC to turn into their own call before
     * it goes anywhere. A button that sets no broadcast type answers with
     * PREPING, since somebody is waiting on it.
     */
    public static function prefill(PingHistory $row, string $fcName, array $answerDefaults = []): array
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

        return BroadcastForm::layer(
            ['embed_type' => 'prepping'],
            BroadcastForm::clean($answerDefaults, BroadcastForm::ANSWER_KEYS),
            [
                'message' => mb_substr(implode("\n", $lines), 0, self::MESSAGE_LENGTH),
                'fc_name' => $fcName,
            ]
        );
    }

    /** What the request's button sets for its answers, as the button stands now. */
    public static function answerDefaultsFor(PingHistory $row): array
    {
        $buttonId = (int) ($row->fields['button_id'] ?? 0);
        $button = $buttonId > 0 ? PingRequestButton::find($buttonId) : null;

        return $button && is_array($button->answer_defaults) ? $button->answer_defaults : [];
    }

    /** Reached Discord within the last HOURS. */
    private static function open()
    {
        return PingHistory::memberRequests()
            ->where('status', 'sent')
            ->where('created_at', '>=', Carbon::now()->subHours(self::HOURS));
    }

    private static function visibleTo($user, ?PingHistory $row): ?PingHistory
    {
        if (! $row || self::seesAll($user)) {
            return $row;
        }

        return self::madeWith([$row], self::openButtons($user))[0] ?? null;
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
