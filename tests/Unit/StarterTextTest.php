<?php

namespace DiscordPings\Tests\Unit;

use DiscordPings\Services\PingRequests\StarterText;
use PHPUnit\Framework\TestCase;

class StarterTextTest extends TestCase
{
    private const ALLIANCE_FORMAT = "Rorqual Tackled\nRorqual Name:\nSystem: Location In System (Anomaly or Moon):\nName of Cyno (On perch, 300km away):";

    /** @return array<int, string> the question keys of a starter, in order */
    private function keys(string $starter): array
    {
        $keys = [];
        foreach (StarterText::lines($starter) as $line) {
            if ($line['kind'] === 'question') {
                $keys[] = $line['key'];
            }
        }

        return $keys;
    }

    public function test_a_line_ending_in_a_colon_is_a_question_and_the_rest_stay_as_text(): void
    {
        $lines = StarterText::lines(self::ALLIANCE_FORMAT);

        $this->assertSame(['text', 'question', 'question', 'question'], array_column($lines, 'kind'));
        $this->assertSame('System: Location In System (Anomaly or Moon):', $lines[2]['text']);
        $this->assertNull($lines[0]['key']);
    }

    public function test_a_box_gets_its_suggestions_from_its_section_name(): void
    {
        $lines = StarterText::lines("Capital Tackled\nCapital Name:\nSystem: Location In System (Anomaly or any close object):\nName of Cyno (On perch, 300km away):\nDoctrine or ship:");

        $this->assertSame(
            [null, StarterText::LOOKUP_CHARACTER, StarterText::LOOKUP_LOCATION, StarterText::LOOKUP_CHARACTER, null],
            array_column($lines, 'lookup')
        );
    }

    public function test_a_place_beats_a_name_in_the_same_section(): void
    {
        $this->assertSame(StarterText::LOOKUP_LOCATION, StarterText::lines('System name:')[0]['lookup']);
    }

    public function test_only_whole_words_count(): void
    {
        foreach (['Delegate:', 'Salt:', 'Renamed:', 'Ecosystem:'] as $section) {
            $this->assertNull(StarterText::lines($section)[0]['lookup'], $section);
        }
    }

    public function test_a_colon_in_the_middle_of_a_line_is_not_a_question(): void
    {
        $this->assertFalse(StarterText::hasQuestions('Doctrine: armour, bring logi'));
    }

    public function test_a_line_of_only_colons_is_not_a_question(): void
    {
        $this->assertFalse(StarterText::hasQuestions(' :: '));
    }

    public function test_windows_line_endings_read_the_same(): void
    {
        $this->assertSame(
            StarterText::lines(self::ALLIANCE_FORMAT),
            StarterText::lines(str_replace("\n", "\r\n", self::ALLIANCE_FORMAT))
        );
    }

    public function test_the_alliance_format_fills_in_to_the_message_fcs_expect(): void
    {
        [$name, $system, $cyno] = $this->keys(self::ALLIANCE_FORMAT);

        $this->assertSame(
            "Rorqual Tackled\nRorqual Name: Matt Falahe\nSystem: Location In System (Anomaly or Moon): RD-FWY X - Moon 1\nName of Cyno (On perch, 300km away): Digital Justice",
            StarterText::compose(self::ALLIANCE_FORMAT, [$name => 'Matt Falahe', $system => 'RD-FWY X - Moon 1', $cyno => 'Digital Justice'])
        );
    }

    public function test_a_question_left_empty_is_left_out(): void
    {
        [$name, $system] = $this->keys(self::ALLIANCE_FORMAT);

        $this->assertSame(
            "Rorqual Tackled\nRorqual Name: Matt Falahe",
            StarterText::compose(self::ALLIANCE_FORMAT, [$name => 'Matt Falahe', $system => '   '])
        );
    }

    /** An emergency pressed with no saved form still has to go out. */
    public function test_no_answers_at_all_leaves_the_plain_text(): void
    {
        $this->assertSame('Rorqual Tackled', StarterText::compose(self::ALLIANCE_FORMAT, []));
    }

    public function test_an_answer_cannot_add_lines_of_its_own(): void
    {
        [$name] = $this->keys(self::ALLIANCE_FORMAT);

        $this->assertSame(
            "Rorqual Tackled\nRorqual Name: Matt Falahe Second Line",
            StarterText::compose(self::ALLIANCE_FORMAT, [$name => "Matt Falahe\r\nSecond Line"])
        );
    }

    public function test_a_starter_with_no_questions_gets_one_free_box_after_its_text(): void
    {
        $this->assertFalse(StarterText::hasQuestions('Help, I am bored'));
        $this->assertSame(
            "Help, I am bored\nAnyone up for a roam?\nOut of Jita",
            StarterText::compose('Help, I am bored', [StarterText::FREE_TEXT => "Anyone up for a roam?\r\nOut of Jita"])
        );
    }

    public function test_the_free_box_is_ignored_when_there_are_questions(): void
    {
        $this->assertSame('Rorqual Tackled', StarterText::compose(self::ALLIANCE_FORMAT, [StarterText::FREE_TEXT => 'ignored']));
    }

    public function test_an_empty_starter_is_just_what_was_typed(): void
    {
        $this->assertSame('hello', StarterText::compose(null, [StarterText::FREE_TEXT => 'hello']));
        $this->assertSame('', StarterText::compose(null, []));
    }

    public function test_the_same_label_twice_gets_two_keys(): void
    {
        $keys = $this->keys("Name:\nName:");

        $this->assertCount(2, array_unique($keys));
    }

    public function test_tidying_up_a_label_keeps_what_was_saved_against_it(): void
    {
        $this->assertSame($this->keys('Rorqual Name:'), $this->keys('RORQUAL  name  :'));
    }

    public function test_answers_it_does_not_ask_for_are_ignored(): void
    {
        $this->assertSame('Rorqual Tackled', StarterText::compose(self::ALLIANCE_FORMAT, ['qnotaquestion' => '@everyone', 'other' => ['nested']]));
    }
}
