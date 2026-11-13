<?php

namespace DiscordPings\Services;

use DiscordPings\Models\PingHistory;
use DiscordPings\Models\PluginSetting;
use DiscordPings\Services\PingRequests\MemberAccount;

/**
 * What the Send and Schedule forms start from.
 *
 * Settings picks between an empty form and one filled in from defaults, and
 * each ping request button can say what an answer to it starts from. The
 * message is never a default: it only starts filled in when the broadcast
 * answers something.
 */
final class BroadcastForm
{
    public const EMPTY = 'empty';
    public const DEFAULTS = 'defaults';

    public const EMBED_TYPES = ['fleet', 'announcement', 'message', 'prepping'];
    public const MENTION_TYPES = ['none', 'everyone', 'here', 'role', 'custom'];

    /** What a button can set for its answers. Where to form up and what to fly stays the FC's call. */
    public const ANSWER_KEYS = [
        'webhook_id', 'embed_type', 'mention_type', 'role_mention', 'custom_mention',
        'channel_link', 'embed_color', 'pap_type', 'comms',
    ];

    /** Every box but the message, and fc_main for starting the FC name as the sender's main. */
    public const DEFAULT_KEYS = [
        'webhook_id', 'embed_type', 'mention_type', 'role_mention', 'custom_mention',
        'channel_link', 'embed_color', 'fc_main', 'formup_location', 'pap_type', 'comms', 'doctrine',
    ];

    private const MODE_SETTING = 'send_form_mode';
    private const VALUES_SETTING = 'send_form_defaults';

    /** How many of an FC's own broadcasts the suggestions are read from. */
    private const LOOKED_BACK = 50;

    /** The same limits the Send form checks, so a default can't be one it refuses. */
    private const LENGTHS = ['custom_mention' => 100, 'formup_location' => 100, 'pap_type' => 100, 'comms' => 200, 'doctrine' => 200];

    /**
     * Only the keys asked for, each in a shape the form takes. Anything empty
     * or not allowed is left out, so it never covers what a layer below says.
     */
    public static function clean(array $input, array $keys): array
    {
        $clean = [];

        foreach ($keys as $key) {
            $value = $input[$key] ?? null;

            switch ($key) {
                case 'webhook_id':
                case 'role_mention':
                case 'channel_link':
                    if (is_numeric($value) && (int) $value > 0) {
                        $clean[$key] = (int) $value;
                    }
                    break;

                case 'embed_type':
                    if (in_array($value, self::EMBED_TYPES, true)) {
                        $clean[$key] = $value;
                    }
                    break;

                case 'mention_type':
                    if (in_array($value, self::MENTION_TYPES, true)) {
                        $clean[$key] = $value;
                    }
                    break;

                case 'embed_color':
                    if (is_string($value) && preg_match('/^#[0-9A-Fa-f]{6}$/', $value)) {
                        $clean[$key] = $value;
                    }
                    break;

                case 'fc_main':
                    if (filter_var($value, FILTER_VALIDATE_BOOLEAN)) {
                        $clean[$key] = true;
                    }
                    break;

                default:
                    $text = is_scalar($value) ? trim((string) $value) : '';
                    if ($text !== '') {
                        $clean[$key] = mb_substr($text, 0, self::LENGTHS[$key] ?? 100);
                    }
            }
        }

        return $clean;
    }

    /** Later layers win wherever they say something. */
    public static function layer(array ...$layers): array
    {
        $values = [];

        foreach ($layers as $layer) {
            foreach ($layer as $key => $value) {
                if ($value !== null && $value !== '') {
                    $values[$key] = $value;
                }
            }
        }

        return $values;
    }

    /**
     * What a form starts with before anything it answers, from what Settings
     * holds. Empty unless the defaults are switched on.
     */
    public static function starting(?string $mode, ?string $saved, ?string $mainName): array
    {
        if ($mode !== self::DEFAULTS) {
            return [];
        }

        $values = self::decode($saved);
        $fcMain = ! empty($values['fc_main']);
        unset($values['fc_main']);

        if ($fcMain && $mainName !== null && $mainName !== '') {
            $values['fc_name'] = $mainName;
        }

        return $values;
    }

    /** The defaults as Settings saved them, cleaned again in case a key was dropped since. */
    public static function decode(?string $saved): array
    {
        $values = is_string($saved) ? json_decode($saved, true) : null;

        return is_array($values) ? self::clean($values, self::DEFAULT_KEYS) : [];
    }

    public static function mode(): string
    {
        return PluginSetting::getValue(self::MODE_SETTING) === self::DEFAULTS ? self::DEFAULTS : self::EMPTY;
    }

    public static function saved(): array
    {
        return self::decode(PluginSetting::getValue(self::VALUES_SETTING));
    }

    /** For the Send and Schedule pages, with the sender's main for the FC name. */
    public static function forForm(?string $mainName): array
    {
        return self::starting(self::mode(), PluginSetting::getValue(self::VALUES_SETTING), $mainName);
    }

    public static function save(string $mode, array $values): void
    {
        PluginSetting::setValue(self::MODE_SETTING, $mode === self::DEFAULTS ? self::DEFAULTS : self::EMPTY);
        PluginSetting::setValue(self::VALUES_SETTING, json_encode(self::clean($values, self::DEFAULT_KEYS)));
    }

    /**
     * What the form's boxes suggest as the FC types: their own characters for
     * the FC name, and what they've typed before in the free-text boxes.
     * Systems and the spots in them come from the lookup routes.
     *
     * @return array{characters: string[], typedBefore: array{comms: string[], doctrine: string[], formup_location: string[]}}
     */
    public static function suggestions($user): array
    {
        $userId = (int) ($user->id ?? 0);

        try {
            $fields = PingHistory::where('user_id', $userId)
                ->broadcasts()
                ->latest()
                ->take(self::LOOKED_BACK)
                ->get()
                ->map(fn ($row) => $row->fields)
                ->all();
        } catch (\Throwable $e) {
            $fields = [];
        }

        return [
            'characters' => MemberAccount::characterNames($userId),
            'typedBefore' => self::typedBefore($fields),
        ];
    }

    /**
     * Each value once, newest first. A doctrine picked from SeAT Fitting is
     * kept as a link, so its plain name is the one offered.
     *
     * @param array<int, mixed> $fieldsList the fields of past broadcasts, newest first
     * @return array{comms: string[], doctrine: string[], formup_location: string[]}
     */
    public static function typedBefore(array $fieldsList, int $most = 15): array
    {
        $found = ['comms' => [], 'doctrine' => [], 'formup_location' => []];
        $seen = [];

        foreach ($fieldsList as $fields) {
            if (! is_array($fields)) {
                continue;
            }

            $values = [
                'comms' => $fields['comms'] ?? null,
                'doctrine' => $fields['doctrine_name'] ?? $fields['doctrine'] ?? null,
                'formup_location' => $fields['formup_location'] ?? null,
            ];

            foreach ($values as $key => $value) {
                $value = is_string($value) ? trim($value) : '';
                $lower = mb_strtolower($value);

                if ($value === '' || count($found[$key]) >= $most || isset($seen[$key][$lower])) {
                    continue;
                }

                $seen[$key][$lower] = true;
                $found[$key][] = $value;
            }
        }

        return $found;
    }
}
