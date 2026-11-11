<?php

namespace DiscordPings\Services\PingRequests;

/**
 * A button's form: one row per section, each saying what kind of box it is and
 * whether its answer can be kept on a saved form.
 *
 * The admin picks the box for every row, so nothing here has to work out what
 * a section is for from how it's worded. The one place that still guesses is
 * fromFormat(), and all its guesses do is fill in the dropdowns for the admin
 * to check.
 */
class Sections
{
    /** The answer key for the one box a form with nothing to fill in gets. */
    public const FREE_TEXT = 'text';

    public const TEXT = 'text';
    public const LOCATION = 'location';
    public const CHARACTER = 'character';
    public const SHIP = 'ship';
    public const YES_NO = 'yesno';
    public const LONG = 'long';
    public const FIXED = 'fixed';

    /** In the order Settings offers them. */
    public const BOXES = [
        self::TEXT => 'Text',
        self::LOCATION => 'System and spot',
        self::CHARACTER => 'My characters',
        self::SHIP => 'Ship',
        self::YES_NO => 'Yes / No',
        self::LONG => 'Long text',
        self::FIXED => 'Fixed line, no box',
    ];

    public const YES = 'Yes';
    public const NO = 'No';

    public const MOST_ROWS = 20;
    public const LONGEST_LABEL = 200;

    /**
     * Ready-made forms Settings can start a button from. The live rows are the
     * ones that are only true at the moment it happens, so no saved form keeps them.
     */
    public const STARTING_POINTS = [
        'Tackled' => [
            ['label' => 'Tackled Pilot Name', 'box' => self::CHARACTER, 'live' => false],
            ['label' => 'Ship Tackled', 'box' => self::SHIP, 'live' => false],
            ['label' => 'System: Location In System (Anomaly or any close object)', 'box' => self::LOCATION, 'live' => false],
            ['label' => 'Name of Cyno (On perch, 300km away)', 'box' => self::CHARACTER, 'live' => false],
            ['label' => 'Panic Active?', 'box' => self::YES_NO, 'live' => true],
            ['label' => 'Cyno Inhibitor active?', 'box' => self::YES_NO, 'live' => true],
            ['label' => 'Grid Dscan', 'box' => self::LONG, 'live' => true],
        ],
        'Mining fleet' => [
            ['label' => 'Mining fleet wanted', 'box' => self::FIXED, 'live' => false],
            ['label' => 'System', 'box' => self::LOCATION, 'live' => false],
            ['label' => 'Ore or ice', 'box' => self::TEXT, 'live' => false],
            ['label' => 'When', 'box' => self::TEXT, 'live' => false],
        ],
        'PvP fleet' => [
            ['label' => 'PvP fleet wanted', 'box' => self::FIXED, 'live' => false],
            ['label' => 'Forming from', 'box' => self::LOCATION, 'live' => false],
            ['label' => 'When', 'box' => self::TEXT, 'live' => false],
            ['label' => 'Doctrine or ship', 'box' => self::SHIP, 'live' => false],
        ],
    ];

    /**
     * Words that point a pasted line at a box, tried in this order. Long text
     * goes first because "Grid Dscan" is a d-scan and not a place, and places
     * go before names because "System name" isn't a character.
     */
    private const WORDS = [
        self::LONG => '/\b(?:d-?scan|intel|notes?|details?|description|comments?|enem(?:y|ies)|hostiles?)\b/i',
        self::LOCATION => '/\b(?:(?:system|location|moon|planet|station|structure|gate)s?|anomaly|where|forming|staging)\b/i',
        self::SHIP => '/\b(?:ships?|hulls?)\b/i',
        self::CHARACTER => '/\b(?:name|character|pilot|cyno|alt)s?\b/i',
    ];

    /** "Panic active?" is answered yes or no, "Where?" isn't. */
    private const ASKS_FOR_MORE = '/^(?:what|where|which|who|whose|when|why|how)\b/i';

    /**
     * Rows out of a format somebody pasted, one per line, with the box guessed
     * from the wording. A wrong guess is one dropdown for the admin to change.
     *
     * @return array<int, array{label: string, box: string, live: bool}>
     */
    public static function fromFormat(?string $format): array
    {
        $lines = [];

        foreach (preg_split('/\R/u', (string) $format) ?: [] as $line) {
            $line = self::tidy($line);

            if (self::hasText($line)) {
                $lines[] = $line;
            }
        }

        // Alliances mark the sections with a colon and leave the heading bare.
        // With nothing marked at all, every line is a section.
        $marked = array_filter($lines, fn ($line) => self::isMarked($line));
        $headings = $marked !== [] || count($lines) === 1;

        $rows = [];

        foreach (array_slice($lines, 0, self::MOST_ROWS) as $line) {
            $box = self::guess($line, $headings);
            $rows[] = ['label' => $line, 'box' => $box, 'live' => $box === self::YES_NO || $box === self::LONG];
        }

        return $rows;
    }

    /**
     * Rows as they came from the Settings form, or from storage, put into
     * shape. Anything that isn't a known box becomes plain text.
     *
     * @return array<int, array{label: string, box: string, live: bool}>
     */
    public static function clean(array $rows): array
    {
        $clean = [];

        foreach ($rows as $row) {
            if (! is_array($row) || count($clean) >= self::MOST_ROWS) {
                continue;
            }

            $label = self::tidy($row['label'] ?? '');

            if (! self::hasText($label)) {
                continue;
            }

            $box = is_string($row['box'] ?? null) && isset(self::BOXES[$row['box']]) ? $row['box'] : self::TEXT;

            $clean[] = [
                'label' => $label,
                'box' => $box,
                'live' => $box !== self::FIXED && filter_var($row['live'] ?? false, FILTER_VALIDATE_BOOLEAN),
            ];
        }

        return $clean;
    }

    /**
     * @param array<int, array{label: string, box: string, live: bool}> $rows
     * @return array<int, array{text: string, box: string, live: bool, key: ?string}>
     */
    public static function lines(array $rows): array
    {
        $lines = [];
        $seen = [];

        foreach ($rows as $row) {
            if ($row['box'] === self::FIXED) {
                $lines[] = ['text' => $row['label'], 'box' => self::FIXED, 'live' => false, 'key' => null];
                continue;
            }

            // Keyed on the letters of the label, so tidying up a label's case
            // or punctuation doesn't lose what people have saved against it.
            $label = mb_strtolower((string) preg_replace('/[^\p{L}\p{N}]+/u', '', $row['label']));
            $seen[$label] = ($seen[$label] ?? 0) + 1;

            $lines[] = [
                'text' => $row['label'],
                'box' => $row['box'],
                'live' => (bool) $row['live'],
                'key' => 'q' . substr(md5($label . '#' . $seen[$label]), 0, 12),
            ];
        }

        return $lines;
    }

    public static function hasQuestions(array $rows): bool
    {
        foreach ($rows as $row) {
            if ($row['box'] !== self::FIXED) {
                return true;
            }
        }

        return false;
    }

    /** Whether anything gets asked once the button has been pressed. */
    public static function hasLive(array $rows): bool
    {
        return self::liveKeys($rows) !== [];
    }

    /** @return string[] the answers a saved form can keep */
    public static function savedKeys(array $rows): array
    {
        if (! self::hasQuestions($rows)) {
            return [self::FREE_TEXT];
        }

        return self::keys($rows, false);
    }

    /** @return string[] the answers only given after the press */
    public static function liveKeys(array $rows): array
    {
        return self::keys($rows, true);
    }

    /**
     * What a saved form can put in a message. A section that has since become
     * one for after the press is left out, because an answer saved last week
     * would be posted as if it were true now.
     */
    public static function savedAnswers(array $rows, array $answers): array
    {
        return array_intersect_key($answers, array_flip(self::savedKeys($rows)));
    }

    /**
     * A section nobody answered is left out rather than posted empty, so an
     * emergency pressed with no saved form still reads properly.
     *
     * @param array<string, mixed> $answers
     */
    public static function compose(array $rows, array $answers): string
    {
        return trim(implode("\n", self::parts($rows, $answers, false)));
    }

    /**
     * The same message the way Discord shows it: each section's name in bold
     * and a blank line between sections, so a long form doesn't run together.
     * Labels and answers are escaped, so a stray star or underscore in either
     * can't carry the bold on into the rest of the message. Fixed lines go out
     * exactly as the admin wrote them.
     *
     * @param array<string, mixed> $answers
     */
    public static function forDiscord(array $rows, array $answers): string
    {
        return trim(implode("\n\n", self::parts($rows, $answers, true)));
    }

    /** @return string[] */
    private static function parts(array $rows, array $answers, bool $discord): array
    {
        $out = [];

        foreach (self::lines($rows) as $line) {
            if ($line['key'] === null) {
                $out[] = $line['text'];
                continue;
            }

            $answer = self::answer($line['box'], $answers[$line['key']] ?? null);

            if ($answer === '') {
                continue;
            }

            $prompt = self::prompt($line['text']);
            $gap = str_contains($answer, "\n") ? "\n" : ' ';

            $out[] = $discord
                ? '**' . self::literal($prompt) . '**' . $gap . self::literal($answer)
                : $prompt . $gap . $answer;
        }

        if (! self::hasQuestions($rows)) {
            $typed = is_string($answers[self::FREE_TEXT] ?? null) ? trim($answers[self::FREE_TEXT]) : '';

            if ($typed !== '') {
                $typed = str_replace(["\r\n", "\r"], "\n", $typed);
                $out[] = $discord ? self::literal($typed) : $typed;
            }
        }

        return $out;
    }

    /** Shows on Discord exactly as typed, with nothing in it read as formatting. */
    private static function literal(string $text): string
    {
        $text = (string) preg_replace('/[\\\\*_~`|]/', '\\\\$0', $text);

        // A quote, a heading or a list only counts at the start of a line, and ">>>" would quote everything after it.
        return (string) preg_replace('/^([ \t]*)([>#-])/m', '$1\\\\$2', $text);
    }

    /** @return string[] */
    private static function keys(array $rows, bool $live): array
    {
        $keys = [];

        foreach (self::lines($rows) as $line) {
            if ($line['key'] !== null && $line['live'] === $live) {
                $keys[] = $line['key'];
            }
        }

        return $keys;
    }

    private static function guess(string $line, bool $headings): string
    {
        if (str_ends_with($line, '?') && ! preg_match(self::ASKS_FOR_MORE, $line)) {
            return self::YES_NO;
        }

        foreach (self::WORDS as $box => $words) {
            if (preg_match($words, $line)) {
                return $box;
            }
        }

        return $headings && ! self::isMarked($line) ? self::FIXED : self::TEXT;
    }

    private static function isMarked(string $line): bool
    {
        return str_ends_with($line, ':') || str_ends_with($line, '?');
    }

    /** It reads as a question in the message whether or not the admin typed the colon. */
    private static function prompt(string $label): string
    {
        return preg_match('/[:?]$/', $label) ? $label : $label . ':';
    }

    private static function answer(string $box, $answer): string
    {
        if (! is_string($answer)) {
            return '';
        }

        if ($box === self::YES_NO) {
            return in_array($answer, [self::YES, self::NO], true) ? $answer : '';
        }

        // A d-scan or a list of hostiles only reads properly line by line.
        if ($box === self::LONG) {
            return trim((string) preg_replace("/\n{3,}/", "\n\n", str_replace(["\r\n", "\r"], "\n", $answer)));
        }

        // Anywhere else a line break would pass for another section of the format.
        return trim((string) preg_replace('/\s*[\r\n]+\s*/', ' ', $answer));
    }

    private static function tidy($label): string
    {
        $label = trim((string) preg_replace('/\s+/u', ' ', is_string($label) ? $label : ''));

        return mb_substr($label, 0, self::LONGEST_LABEL);
    }

    private static function hasText(string $label): bool
    {
        return trim($label, " \t:?") !== '';
    }
}
