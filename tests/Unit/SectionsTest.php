<?php

namespace DiscordPings\Tests\Unit;

use DiscordPings\Models\PingRequestButton;
use DiscordPings\Services\PingRequests\Sections;
use PHPUnit\Framework\TestCase;

class SectionsTest extends TestCase
{
    private const ALLIANCE_FORMAT = "Rorqual Tackled\nRorqual Name:\nSystem: Location In System (Anomaly or Moon):\nName of Cyno (On perch, 300km away):";

    private const GENERAL_FORMAT = "Tackled Pilot Name\nShip Tackled\nSystem: Location In System (Anomaly or any close object):\nName of Cyno (On perch, 300km away):\nPanic Active? \nCyno Inhibitor active? \nGrid Dscan";

    /** @return array<int, string> the answer keys of a form, in order */
    private function keys(array $rows): array
    {
        return array_values(array_filter(array_column(Sections::lines($rows), 'key')));
    }

    private function row(string $label, string $box = Sections::TEXT, bool $live = false): array
    {
        return ['label' => $label, 'box' => $box, 'live' => $live];
    }

    public function test_an_alliance_format_keeps_its_heading_and_gets_its_boxes(): void
    {
        $this->assertSame([
            $this->row('Rorqual Tackled', Sections::FIXED),
            $this->row('Rorqual Name:', Sections::CHARACTER),
            $this->row('System: Location In System (Anomaly or Moon):', Sections::LOCATION),
            $this->row('Name of Cyno (On perch, 300km away):', Sections::CHARACTER),
        ], Sections::fromFormat(self::ALLIANCE_FORMAT));
    }

    /** The format that came out as one free box when only a colon made a section. */
    public function test_with_no_colon_anywhere_every_line_is_a_section_in_any_order(): void
    {
        $this->assertSame(
            [Sections::LOCATION, Sections::CHARACTER, Sections::SHIP, Sections::CHARACTER],
            array_column(Sections::fromFormat("Location\nPilot Name\nShip type tackled\nCyno name"), 'box')
        );
        $this->assertSame(
            [Sections::CHARACTER, Sections::SHIP, Sections::LOCATION, Sections::CHARACTER],
            array_column(Sections::fromFormat("Cyno name\nShip type tackled\nLocation\nPilot Name"), 'box')
        );
    }

    public function test_the_general_tackle_format_reads_the_way_it_was_written(): void
    {
        $rows = Sections::fromFormat(self::GENERAL_FORMAT);

        $this->assertSame(
            [Sections::CHARACTER, Sections::SHIP, Sections::LOCATION, Sections::CHARACTER, Sections::YES_NO, Sections::YES_NO, Sections::LONG],
            array_column($rows, 'box')
        );
        $this->assertSame([false, false, false, false, true, true, true], array_column($rows, 'live'));
        $this->assertSame('Panic Active?', $rows[4]['label']);
    }

    public function test_it_reads_the_same_as_the_tackled_starting_point(): void
    {
        $this->assertSame(
            array_column(Sections::STARTING_POINTS['Tackled'], 'box'),
            array_column(Sections::fromFormat(self::GENERAL_FORMAT), 'box')
        );
    }

    public function test_a_question_mark_is_yes_or_no_unless_it_asks_what_or_where(): void
    {
        $this->assertSame(
            [Sections::YES_NO, Sections::LOCATION, Sections::SHIP, Sections::TEXT],
            array_column(Sections::fromFormat("Logi on grid?\nWhere?\nWhat ship?\nWho tackled you?"), 'box')
        );
    }

    public function test_a_single_line_is_a_heading_that_leaves_one_free_box(): void
    {
        $rows = Sections::fromFormat('Looking for something to do');

        $this->assertSame([$this->row('Looking for something to do', Sections::FIXED)], $rows);
        $this->assertFalse(Sections::hasQuestions($rows));
        $this->assertSame([Sections::FREE_TEXT], Sections::savedKeys($rows));
    }

    public function test_bare_lines_with_nothing_recognised_are_still_sections(): void
    {
        $this->assertSame([Sections::TEXT, Sections::TEXT], array_column(Sections::fromFormat("Doctrine\nWhen"), 'box'));
    }

    public function test_only_whole_words_count(): void
    {
        foreach (['Delegate:', 'Salt:', 'Renamed:', 'Ecosystem:', 'Shipment:'] as $section) {
            $this->assertSame(Sections::TEXT, Sections::fromFormat($section)[0]['box'], $section);
        }
    }

    public function test_a_place_or_a_ship_beats_a_name_and_a_dscan_beats_a_place(): void
    {
        $this->assertSame(
            [Sections::LOCATION, Sections::SHIP, Sections::LONG],
            array_column(Sections::fromFormat("System name:\nShip name:\nDscan of the system:"), 'box')
        );
    }

    public function test_a_format_in_another_language_still_gets_its_boxes(): void
    {
        $this->assertSame([Sections::TEXT, Sections::TEXT], array_column(Sections::fromFormat("Система:\nИмя пилота:"), 'box'));
    }

    public function test_windows_line_endings_and_blank_lines_read_the_same(): void
    {
        $this->assertSame(
            Sections::fromFormat(self::ALLIANCE_FORMAT),
            Sections::fromFormat("\r\n" . str_replace("\n", "\r\n\r\n", self::ALLIANCE_FORMAT) . "\r\n  \r\n")
        );
    }

    public function test_nothing_pasted_is_no_sections(): void
    {
        $this->assertSame([], Sections::fromFormat(null));
        $this->assertSame([], Sections::fromFormat(" \n :: \n"));
    }

    /** Saved forms on a button from before rows were matched to the old text by these keys. */
    public function test_a_button_read_from_its_old_text_keeps_the_keys_its_saved_forms_use(): void
    {
        $this->assertSame(
            ['q' . substr(md5('rorqualname#1'), 0, 12), 'q' . substr(md5('systemlocationinsystemanomalyormoon#1'), 0, 12), 'q' . substr(md5('nameofcynoonperch300kmaway#1'), 0, 12)],
            $this->keys(Sections::fromFormat(self::ALLIANCE_FORMAT))
        );
    }

    public function test_tidying_up_a_label_keeps_what_was_saved_against_it(): void
    {
        $this->assertSame($this->keys([$this->row('Rorqual Name:')]), $this->keys([$this->row('RORQUAL  name')]));
    }

    public function test_the_same_label_twice_gets_two_keys(): void
    {
        $this->assertCount(2, array_unique($this->keys([$this->row('Name'), $this->row('Name')])));
    }

    public function test_the_alliance_format_fills_in_to_the_message_fcs_expect(): void
    {
        $rows = Sections::fromFormat(self::ALLIANCE_FORMAT);
        [$name, $system, $cyno] = $this->keys($rows);

        $this->assertSame(
            "Rorqual Tackled\nRorqual Name: Matt Falahe\nSystem: Location In System (Anomaly or Moon): RD-FWY X - Moon 1\nName of Cyno (On perch, 300km away): Digital Justice",
            Sections::compose($rows, [$name => 'Matt Falahe', $system => 'RD-FWY X - Moon 1', $cyno => 'Digital Justice'])
        );
    }

    public function test_a_section_typed_without_a_colon_gets_one_in_the_message(): void
    {
        $rows = [$this->row('Location', Sections::LOCATION), $this->row('Panic Active?', Sections::YES_NO, true)];
        [$location, $panic] = $this->keys($rows);

        $this->assertSame("Location: Q-3HS5\nPanic Active? Yes", Sections::compose($rows, [$location => 'Q-3HS5', $panic => 'Yes']));
    }

    public function test_a_yes_or_no_takes_nothing_else(): void
    {
        $rows = [$this->row('Panic Active?', Sections::YES_NO)];
        [$panic] = $this->keys($rows);

        $this->assertSame('', Sections::compose($rows, [$panic => 'yes, and @everyone']));
        $this->assertSame('Panic Active? No', Sections::compose($rows, [$panic => 'No']));
    }

    public function test_long_text_keeps_its_lines_under_its_section(): void
    {
        $rows = [$this->row('Grid Dscan', Sections::LONG), $this->row('Pilot', Sections::CHARACTER)];
        [$dscan, $pilot] = $this->keys($rows);

        $this->assertSame(
            "Grid Dscan:\n5 Revelation\n\n20 Ishtar\nPilot: Matt Falahe",
            Sections::compose($rows, [$dscan => "\r\n5 Revelation\r\n\r\n\r\n\r\n20 Ishtar\r\n", $pilot => 'Matt Falahe'])
        );
        $this->assertSame('Grid Dscan: https://dscan.info/v/abc', Sections::compose($rows, [$dscan => 'https://dscan.info/v/abc']));
    }

    public function test_any_other_answer_cannot_add_lines_of_its_own(): void
    {
        $rows = Sections::fromFormat(self::ALLIANCE_FORMAT);
        [$name] = $this->keys($rows);

        $this->assertSame(
            "Rorqual Tackled\nRorqual Name: Matt Falahe Second Line",
            Sections::compose($rows, [$name => "Matt Falahe\r\nSecond Line"])
        );
    }

    public function test_a_section_left_empty_is_left_out(): void
    {
        $rows = Sections::fromFormat(self::ALLIANCE_FORMAT);
        [$name, $system] = $this->keys($rows);

        $this->assertSame("Rorqual Tackled\nRorqual Name: Matt Falahe", Sections::compose($rows, [$name => 'Matt Falahe', $system => '   ']));
    }

    /** An emergency pressed with no saved form still has to go out. */
    public function test_no_answers_at_all_leaves_the_fixed_lines(): void
    {
        $this->assertSame('Rorqual Tackled', Sections::compose(Sections::fromFormat(self::ALLIANCE_FORMAT), []));
    }

    public function test_a_form_with_nothing_to_fill_in_gets_one_free_box_after_its_text(): void
    {
        $this->assertSame(
            "Help, I am bored\nAnyone up for a roam?\nOut of Jita",
            Sections::compose([$this->row('Help, I am bored', Sections::FIXED)], [Sections::FREE_TEXT => "Anyone up for a roam?\r\nOut of Jita"])
        );
    }

    public function test_the_free_box_is_ignored_when_there_are_sections(): void
    {
        $this->assertSame('Rorqual Tackled', Sections::compose(Sections::fromFormat(self::ALLIANCE_FORMAT), [Sections::FREE_TEXT => 'ignored']));
    }

    public function test_no_sections_is_just_what_was_typed(): void
    {
        $this->assertSame('hello', Sections::compose([], [Sections::FREE_TEXT => 'hello']));
        $this->assertSame('', Sections::compose([], []));
    }

    public function test_answers_it_does_not_ask_for_are_ignored(): void
    {
        $this->assertSame('Rorqual Tackled', Sections::compose(Sections::fromFormat(self::ALLIANCE_FORMAT), ['qnotaquestion' => '@everyone', 'other' => ['nested']]));
    }

    public function test_a_saved_form_never_keeps_what_is_asked_after_the_press(): void
    {
        $rows = Sections::STARTING_POINTS['Tackled'];
        $keys = $this->keys($rows);
        $answers = array_combine($keys, ['Matt', 'Rorqual', 'Q-3HS5', 'Digital Justice', 'No', 'No', 'nothing on scan']);

        $this->assertSame(array_slice($keys, 0, 4), Sections::savedKeys($rows));
        $this->assertSame(array_slice($keys, 4), Sections::liveKeys($rows));
        $this->assertSame(array_slice($answers, 0, 4, true), Sections::savedAnswers($rows, $answers));
        $this->assertTrue(Sections::hasLive($rows));
    }

    public function test_a_form_that_only_asks_after_the_press_has_nothing_to_save(): void
    {
        $this->assertSame([], Sections::savedKeys([$this->row('Panic Active?', Sections::YES_NO, true)]));
    }

    public function test_rows_from_settings_are_put_into_shape(): void
    {
        $this->assertSame([
            $this->row('Pilot name', Sections::CHARACTER),
            $this->row('Something odd'),
            $this->row('Heading', Sections::FIXED),
            $this->row('Panic?', Sections::YES_NO, true),
        ], Sections::clean([
            ['label' => "  Pilot \t name ", 'box' => 'character', 'live' => '0'],
            ['label' => '', 'box' => 'text'],
            ['label' => ' : ', 'box' => 'text'],
            ['label' => 'Something odd', 'box' => 'made-up', 'live' => null],
            ['label' => 'Heading', 'box' => 'fixed', 'live' => '1'],
            ['label' => 'Panic?', 'box' => 'yesno', 'live' => '1'],
            'not a row',
            ['label' => ['nested'], 'box' => ['nested']],
        ]));
    }

    public function test_rows_stop_at_the_most_a_form_has_and_labels_at_their_longest(): void
    {
        $rows = Sections::clean(array_fill(0, 30, ['label' => str_repeat('a', 500), 'box' => 'text']));

        $this->assertCount(Sections::MOST_ROWS, $rows);
        $this->assertSame(Sections::LONGEST_LABEL, mb_strlen($rows[0]['label']));
        $this->assertCount(Sections::MOST_ROWS, Sections::fromFormat(str_repeat("Line:\n", 30)));
    }

    public function test_every_starting_point_is_already_in_shape(): void
    {
        foreach (Sections::STARTING_POINTS as $name => $rows) {
            $this->assertSame($rows, Sections::clean($rows), $name);
        }
    }

    public function test_a_button_saved_before_rows_is_read_from_its_old_text(): void
    {
        $old = new PingRequestButton(['sections' => null, 'starter_text' => self::ALLIANCE_FORMAT]);
        $saved = new PingRequestButton(['sections' => [['label' => ' Location ', 'box' => 'location', 'live' => false]], 'starter_text' => 'ignored']);
        $emptied = new PingRequestButton(['sections' => null, 'starter_text' => null]);

        $this->assertSame(Sections::fromFormat(self::ALLIANCE_FORMAT), $old->sectionRows());
        $this->assertSame([$this->row('Location', Sections::LOCATION)], $saved->sectionRows());
        $this->assertSame([], $emptied->sectionRows());
    }
}
