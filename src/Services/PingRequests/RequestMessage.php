<?php

namespace DiscordPings\Services\PingRequests;

/**
 * Builds the webhook payload for a button press.
 *
 * Whatever a member types goes in the embed and nowhere else. The only thing
 * in content is the role from the button's settings, and allowed_mentions is
 * limited to that role, so nobody can ping @everyone by typing it into a form.
 */
class RequestMessage
{
    // Discord's limits. Going over any of them is a 400, and the request is lost.
    private const TITLE = 256;
    private const DESCRIPTION = 4096;
    private const FIELD_VALUE = 1024;
    private const TOTAL = 6000;

    /** The field that tells whoever reads the message that it can still change, and until when. */
    public const LIVE = 'Live updates';

    /**
     * @param int|null $liveUntil when the sender stops being able to add to it, for a message they still can
     */
    public static function payload(
        string $title,
        string $body,
        ?string $mentionRoleId,
        SenderIdentity $sender,
        int $color,
        string $username,
        string $timestamp,
        ?int $liveUntil = null
    ): array {
        $title = self::fit($title, self::TITLE);

        $fields = [];

        if ($liveUntil !== null) {
            $fields[] = self::field(self::LIVE, self::liveUntil($liveUntil), false);
        }

        $fields[] = self::field('Sent by', $sender->mainName !== '' ? $sender->mainName : 'Unknown');
        $fields[] = self::field('Corporation', $sender->corporation ?: 'Unknown corporation');

        $discord = $sender->discordLine();
        if ($discord !== null) {
            $fields[] = self::field('Discord', $discord);
        }

        $embed = self::withBody([
            'title' => $title,
            'color' => $color,
            'fields' => $fields,
            'timestamp' => $timestamp,
        ], $body);

        $payload = ['embeds' => [$embed], 'username' => $username];

        if ($mentionRoleId !== null && ctype_digit($mentionRoleId)) {
            $payload['content'] = '<@&' . $mentionRoleId . '>';
            $payload['allowed_mentions'] = ['roles' => [$mentionRoleId]];
        } else {
            $payload['allowed_mentions'] = ['parse' => []];
        }

        return $payload;
    }

    /**
     * The embed with a new body. The first message is built with it, and so is
     * the edit when a member adds to an emergency. That edit changes nothing
     * but the embed, so the role isn't pinged a second time.
     */
    public static function withBody(array $embed, string $body): array
    {
        $used = self::length((string) ($embed['title'] ?? ''));
        foreach ($embed['fields'] ?? [] as $field) {
            $used += self::length((string) $field['name']) + self::length((string) $field['value']);
        }

        unset($embed['description']);

        $description = self::fit(self::plain($body), min(self::DESCRIPTION, max(1, self::TOTAL - $used)));
        if ($description !== '') {
            $embed['description'] = $description;
        }

        return $embed;
    }

    /**
     * Swaps the countdown for when the sender closed it, so nobody waits on an
     * update that isn't coming. Everything else stays as it was.
     */
    public static function closed(array $embed, int $at): array
    {
        return self::liveLine($embed, 0, 'Closed by the sender at <t:' . $at . ':t>');
    }

    /**
     * Under the countdown, when the sender last added to it and how long ago,
     * so whoever reads it can tell fresh news from old at a glance.
     */
    public static function updated(array $embed, int $at): array
    {
        return self::liveLine($embed, 1, 'Last updated at <t:' . $at . ':t> (<t:' . $at . ':R>)');
    }

    /** The countdown or the close goes on the first line of Live updates, the last update on the second. */
    private static function liveLine(array $embed, int $line, string $text): array
    {
        foreach ($embed['fields'] ?? [] as $i => $field) {
            if (($field['name'] ?? null) === self::LIVE) {
                $lines = explode("\n", (string) ($field['value'] ?? ''), 2);
                $lines[$line] = $text;
                $embed['fields'][$i]['value'] = implode("\n", $lines);
            }
        }

        return $embed;
    }

    /**
     * In Discord's own time format, so every reader sees their own local time
     * and a countdown their app keeps current without the message being edited.
     */
    private static function liveUntil(int $until): string
    {
        return 'Until <t:' . $until . ':t> (<t:' . $until . ':R>)';
    }

    /** @return array{name: string, value: string, inline: bool} */
    private static function field(string $name, string $value, bool $inline = true): array
    {
        return ['name' => $name, 'value' => self::fit(self::plain($value), self::FIELD_VALUE), 'inline' => $inline];
    }

    /**
     * Stops [text](url) from becoming a link. The message appears under the
     * Broadcast name, so a link dressed up as something else would be taken
     * at face value. Escaping the brackets leaves the address showing in full.
     */
    private static function plain(string $text): string
    {
        $text = mb_scrub($text, 'UTF-8');

        // Without a "](" there's no link to stop, so the text goes out as typed,
        // brackets in a link included.
        if (! str_contains($text, '](')) {
            return $text;
        }

        // The "[" needs escaping too, because the markdown Discord is built on
        // takes a backslash before the "]" as part of the link text and still
        // makes the link. Backslashes already in front of either go first, so
        // one typed there can't cancel ours out.
        return (string) preg_replace(['/\\\\*\[/', '/\\\\*\]\(/'], ['\\\\[', '\\\\]('], $text);
    }

    private static function fit(string $text, int $limit): string
    {
        if (self::length($text) <= $limit) {
            return $text;
        }

        $room = max(0, $limit - 1);
        while (self::length($text) > $room) {
            $text = mb_substr($text, 0, mb_strlen($text) - (int) ceil((self::length($text) - $room) / 2));
        }

        return $text . '…';
    }

    /** Discord counts the way JavaScript does, so an emoji can be two. */
    private static function length(string $text): int
    {
        return (int) (strlen(mb_convert_encoding($text, 'UTF-16LE', 'UTF-8')) / 2);
    }
}
