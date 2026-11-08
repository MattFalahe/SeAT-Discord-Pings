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

    public static function payload(
        string $title,
        string $body,
        ?string $mentionRoleId,
        SenderIdentity $sender,
        int $color,
        string $username,
        string $timestamp
    ): array {
        $title = self::fit($title, self::TITLE);

        $fields = [
            self::field('Sent by', $sender->mainName !== '' ? $sender->mainName : 'Unknown'),
            self::field('Corporation', $sender->corporation ?: 'Unknown corporation'),
        ];

        $discord = $sender->discordLine();
        if ($discord !== null) {
            $fields[] = self::field('Discord', $discord);
        }

        $used = self::length($title);
        foreach ($fields as $field) {
            $used += self::length($field['name']) + self::length($field['value']);
        }

        $embed = [
            'title' => $title,
            'color' => $color,
            'fields' => $fields,
            'timestamp' => $timestamp,
        ];

        $description = self::fit(self::plain($body), min(self::DESCRIPTION, max(1, self::TOTAL - $used)));
        if ($description !== '') {
            $embed['description'] = $description;
        }

        $payload = ['embeds' => [$embed], 'username' => $username];

        if ($mentionRoleId !== null && ctype_digit($mentionRoleId)) {
            $payload['content'] = '<@&' . $mentionRoleId . '>';
            $payload['allowed_mentions'] = ['roles' => [$mentionRoleId]];
        } else {
            $payload['allowed_mentions'] = ['parse' => []];
        }

        return $payload;
    }

    /** @return array{name: string, value: string, inline: bool} */
    private static function field(string $name, string $value): array
    {
        return ['name' => $name, 'value' => self::fit(self::plain($value), self::FIELD_VALUE), 'inline' => true];
    }

    /**
     * Stops [text](url) from becoming a link. The message appears under the
     * Broadcast name, so a link dressed up as something else would be taken
     * at face value. Escaping the bracket leaves the address showing in full.
     */
    private static function plain(string $text): string
    {
        return (string) preg_replace('/\\\\*\]\(/', '\\\\](', mb_scrub($text, 'UTF-8'));
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
