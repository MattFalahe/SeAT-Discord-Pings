<?php

namespace DiscordPings\Services\PingRequests;

/**
 * Turns a button's starter text into a form, and the answers back into a message.
 *
 * Alliances already have their own formats for these, so nobody should have to
 * rebuild one field by field. The admin pastes the format in: a line ending in
 * a colon is a question, every other line goes out as written.
 */
class StarterText
{
    /** The answer key for the one box a starter with no questions gets. */
    public const FREE_TEXT = 'text';

    /**
     * @return array<int, array{kind: string, text: string, key: ?string}>
     */
    public static function lines(?string $starter): array
    {
        $lines = [];
        $seen = [];

        foreach (explode("\n", str_replace(["\r\n", "\r"], "\n", (string) $starter)) as $line) {
            $line = rtrim($line);

            if (! self::isQuestion($line)) {
                $lines[] = ['kind' => 'text', 'text' => $line, 'key' => null];
                continue;
            }

            // Keyed on the letters of the label, so tidying up a label's case
            // or punctuation doesn't lose what people have saved against it.
            $label = mb_strtolower((string) preg_replace('/[^\p{L}\p{N}]+/u', '', $line));
            $seen[$label] = ($seen[$label] ?? 0) + 1;

            $lines[] = ['kind' => 'question', 'text' => $line, 'key' => 'q' . substr(md5($label . '#' . $seen[$label]), 0, 12)];
        }

        return $lines;
    }

    public static function hasQuestions(?string $starter): bool
    {
        foreach (self::lines($starter) as $line) {
            if ($line['kind'] === 'question') {
                return true;
            }
        }

        return false;
    }

    /**
     * A question nobody answered is left out rather than posted empty, so an
     * emergency pressed with no saved form still reads properly.
     *
     * @param array<string, mixed> $answers
     */
    public static function compose(?string $starter, array $answers): string
    {
        $lines = self::lines($starter);
        $out = [];

        foreach ($lines as $line) {
            if ($line['kind'] === 'text') {
                $out[] = $line['text'];
                continue;
            }

            $answer = self::oneLine($answers[$line['key']] ?? null);
            if ($answer !== '') {
                $out[] = $line['text'] . ' ' . $answer;
            }
        }

        if (! self::hasQuestions($starter)) {
            $typed = is_string($answers[self::FREE_TEXT] ?? null) ? trim($answers[self::FREE_TEXT]) : '';
            if ($typed !== '') {
                $out[] = str_replace(["\r\n", "\r"], "\n", $typed);
            }
        }

        return trim(implode("\n", $out));
    }

    private static function isQuestion(string $line): bool
    {
        return str_ends_with($line, ':') && trim($line, " \t:") !== '';
    }

    private static function oneLine($answer): string
    {
        if (! is_string($answer)) {
            return '';
        }

        return trim((string) preg_replace('/\s*[\r\n]+\s*/', ' ', $answer));
    }
}
