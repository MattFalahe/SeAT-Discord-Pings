<?php

namespace DiscordPings\Services;

use Illuminate\Support\Facades\Log;

/**
 * Turns what an FC types in the mention box into something Discord pings.
 *
 * A webhook only pings a member written as <@id>. Typed as @name it posts as
 * grey text and nobody hears it. So a name is looked up among the accounts
 * seat-connector has linked: first the SeAT main, which is what it sets their
 * server nickname from, then the Discord username. A bare user ID is wrapped,
 * or made a role mention when it's a role this SeAT knows. Whatever Discord
 * already reads (<@id>, <@&id>, <#id>, @everyone, @here) goes as typed.
 *
 * A name nobody has, or that more than one account matches, is left as typed
 * rather than pinging the wrong person, and the form says which.
 *
 * This runs as the broadcast goes out, so a scheduled one finds whoever is
 * linked by then.
 */
final class MemberMentions
{
    /**
     * The most the box takes. A typed mention needs at least four characters
     * and comes out at most 23, so a full box still fits in Discord's 2000.
     */
    public const MAX_LENGTH = 300;

    /** What Discord accepts as the box's text, with every name it could find made a ping. */
    public static function forDiscord(?string $text): string
    {
        $text = (string) $text;

        $names = self::hasNames($text);
        $ids = self::hasBareIds($text);

        if (! $names && ! $ids) {
            return $text;
        }

        try {
            $members = $names ? DiscordUserResolver::linkedMembers() : [];
            $roles = $ids ? self::knownRoles() : [];

            return self::resolve($text, $members, $roles)['text'];
        } catch (\Throwable $e) {
            // A broadcast must still go out, so it goes as typed.
            Log::warning('[DiscordPings] MemberMentions: could not look up the mentions: ' . $e->getMessage());

            return $text;
        }
    }

    /**
     * For the form: who the box will ping, and what it couldn't find.
     *
     * @return array{pings: string[], unknown: string[], ambiguous: string[], connector: bool}
     */
    public static function check(?string $text): array
    {
        $text = mb_substr((string) $text, 0, self::MAX_LENGTH);
        $connector = DiscordUserResolver::isAvailable();
        $result = self::resolve($text, $connector ? DiscordUserResolver::linkedMembers() : [], self::knownRoles());

        return [
            'pings' => $result['pings'],
            'unknown' => $result['unknown'],
            'ambiguous' => $result['ambiguous'],
            'connector' => $connector,
        ];
    }

    /**
     * @param array<int, array{id: string, main?: ?string, discord?: ?string}> $members linked accounts
     * @param array<string, string> $roles Discord roles this SeAT knows, name by id
     * @return array{text: string, pings: string[], unknown: string[], ambiguous: string[]}
     */
    public static function resolve(string $text, array $members, array $roles = []): array
    {
        $byMain = [];
        $byDiscord = [];
        $names = [];

        foreach ($members as $member) {
            $id = (string) ($member['id'] ?? '');
            if (! self::isSnowflake($id)) {
                continue;
            }

            $main = trim((string) ($member['main'] ?? ''));
            $discord = self::username((string) ($member['discord'] ?? ''));

            if ($main !== '') {
                $byMain[strtolower($main)][$id] = true;
            }

            if ($discord !== '') {
                $byDiscord[$discord][$id] = true;
            }

            $names[$id] = $main !== '' ? $main : ($discord !== '' ? $discord : null);
        }

        // Longest first, so "Matt Falahe" is found before a "Matt".
        uksort($byMain, fn ($a, $b) => strlen($b) <=> strlen($a));

        $out = '';
        $pings = [];
        $unknown = [];
        $ambiguous = [];
        $length = strlen($text);
        $at = 0;

        while ($at < $length && preg_match('/<[^<>\s]*>|\d+|@/', $text, $found, PREG_OFFSET_CAPTURE, $at)) {
            [$token, $start] = $found[0];
            $out .= substr($text, $at, $start - $at);
            $at = $start + strlen($token);

            // Part of a word, like an address or a longer number: not a mention.
            $glued = $start > 0 && preg_match('/\w/', $text[$start - 1]);

            if ($token[0] === '<') {
                $out .= $token;

                if (preg_match('/^<@!?(\d{17,20})>$/', $token, $user)) {
                    $pings[] = self::memberLabel($user[1], $names);
                } elseif (preg_match('/^<@&(\d{17,20})>$/', $token, $role)) {
                    $pings[] = self::roleLabel($role[1], $roles);
                }

                continue;
            }

            if ($token !== '@') {
                $wordAfter = $at < $length && preg_match('/\w/', $text[$at]);

                if ($glued || $wordAfter || ! self::isSnowflake($token)) {
                    $out .= $token;
                } else {
                    $out .= self::ping($token, $roles, $names, $pings);
                }

                continue;
            }

            $rest = substr($text, $at);

            if ($glued) {
                $out .= '@';
                continue;
            }

            if (preg_match('/^(everyone|here)(?!\w)/', $rest, $literal)) {
                $out .= '@' . $literal[1];
                $at += strlen($literal[1]);
                $pings[] = '@' . $literal[1];
                continue;
            }

            if (preg_match('/^\d{17,20}(?!\w)/', $rest, $typedId)) {
                $out .= self::ping($typedId[0], $roles, $names, $pings);
                $at += strlen($typedId[0]);
                continue;
            }

            [$used, $ids] = self::lookUp($rest, $byMain, $byDiscord);

            if (count($ids) === 1) {
                $out .= '<@' . array_key_first($ids) . '>';
                $at += $used;
                $pings[] = self::memberLabel((string) array_key_first($ids), $names);
                continue;
            }

            // Left as typed, and the name after it is copied like any other text.
            $out .= '@';

            if (count($ids) > 1) {
                $ambiguous[] = '@' . substr($rest, 0, $used);
            } elseif (preg_match('/^[^\s@<>,;]+/', $rest, $word)) {
                $unknown[] = '@' . $word[0];
            }
        }

        if ($at < $length) {
            $out .= substr($text, $at);
        }

        return [
            'text' => $out,
            'pings' => array_values(array_unique($pings)),
            'unknown' => array_values(array_unique($unknown)),
            'ambiguous' => array_values(array_unique($ambiguous)),
        ];
    }

    /**
     * The longest name the text after an @ starts with, and every account it
     * could be.
     *
     * @return array{0: int, 1: array<string, true>}
     */
    private static function lookUp(string $rest, array $byMain, array $byDiscord): array
    {
        $candidates = [];

        foreach ($byMain as $main => $ids) {
            $size = strlen($main);

            if (strncasecmp($rest, $main, $size) === 0 && ! self::continuesName($rest, $size)) {
                $candidates[] = [$size, $ids];
                break;
            }
        }

        // An older account can still be stored as name#1234, and either way of typing it is fine.
        if (preg_match('/^([A-Za-z0-9_.]{2,32})(#\d{4})?/', $rest, $typed)) {
            $discord = strtolower($typed[1]);
            $size = strlen($typed[0]);

            // A full stop after the name more likely ends the sentence.
            if (! isset($byDiscord[$discord]) && empty($typed[2])) {
                $trimmed = rtrim($discord, '.');
                $size -= strlen($discord) - strlen($trimmed);
                $discord = $trimmed;
            }

            if ($discord !== '' && isset($byDiscord[$discord]) && ! self::continuesName($rest, $size)) {
                $candidates[] = [$size, $byDiscord[$discord]];
            }
        }

        if ($candidates === []) {
            return [0, []];
        }

        $longest = max(array_column($candidates, 0));
        $ids = [];

        foreach ($candidates as [$size, $matches]) {
            if ($size === $longest) {
                $ids += $matches;
            }
        }

        return [$longest, $ids];
    }

    private static function continuesName(string $rest, int $size): bool
    {
        return isset($rest[$size]) && preg_match('/[A-Za-z0-9_]/', $rest[$size]);
    }

    /** A Discord username as a typed one is matched: lower case, without an old #1234. */
    private static function username(string $stored): string
    {
        return strtolower(trim(preg_replace('/#\d{4}$/', '', trim($stored))));
    }

    private static function isSnowflake(string $value): bool
    {
        return (bool) preg_match('/^\d{17,20}$/', $value);
    }

    /** A typed ID as a role mention when it's a role this SeAT knows, and a member's otherwise. */
    private static function ping(string $id, array $roles, array $names, array &$pings): string
    {
        if (isset($roles[$id])) {
            $pings[] = self::roleLabel($id, $roles);

            return '<@&' . $id . '>';
        }

        $pings[] = self::memberLabel($id, $names);

        return '<@' . $id . '>';
    }

    private static function memberLabel(string $id, array $names): string
    {
        return $names[$id] ?? 'Discord user …' . substr($id, -6);
    }

    private static function roleLabel(string $id, array $roles): string
    {
        return 'role ' . ($roles[$id] ?? '…' . substr($id, -6));
    }

    /** An @ that isn't markup, @everyone or @here, so there's a name to look up. */
    private static function hasNames(string $text): bool
    {
        return (bool) preg_match('/(?<![<\w])@(?!(?:everyone|here)(?!\w))/', $text);
    }

    /** A user or role ID typed on its own or after an @, not inside Discord's own markup. */
    private static function hasBareIds(string $text): bool
    {
        return (bool) preg_match('/(?<![\w&#!])(?<!<@)\d{17,20}(?!\w)/', $text);
    }

    /** @return array<string, string> */
    private static function knownRoles(): array
    {
        $roles = [];

        foreach (DiscordRoleResolver::listRoles() as $role) {
            $roles[(string) $role['id']] = (string) ($role['name'] ?? '');
        }

        return $roles;
    }
}
