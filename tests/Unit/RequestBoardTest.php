<?php

namespace DiscordPings\Tests\Unit;

use DiscordPings\Models\PingHistory;
use DiscordPings\Models\PingRequestButton;
use DiscordPings\Services\BroadcastForm;
use DiscordPings\Services\PingRequests\RequestBoard;
use PHPUnit\Framework\TestCase;

class RequestBoardTest extends TestCase
{
    private function request(array $fields, string $message = '', string $userName = 'Recorded Name'): PingHistory
    {
        return new PingHistory([
            'fields' => $fields + ['source' => PingHistory::SOURCE_MEMBER_REQUEST],
            'message' => $message,
            'user_name' => $userName,
        ]);
    }

    public function test_an_fc_sees_the_requests_made_with_buttons_open_to_them(): void
    {
        $first = $this->request(['button_id' => 3]);
        $closed = $this->request(['button_id' => 4]);
        $second = $this->request(['button_id' => 7]);

        $this->assertSame([$first, $second], RequestBoard::madeWith([$first, $closed, $second], [3, 7]));
    }

    /** Nothing says any more which group a deleted button was for, so its requests go to admins only. */
    public function test_a_request_whose_button_is_gone_stays_off(): void
    {
        $this->assertSame([], RequestBoard::madeWith([$this->request([])], [3]));
        $this->assertSame([], RequestBoard::madeWith([$this->request(['button_id' => 3])], []));
    }

    public function test_an_emergency_is_told_from_a_request(): void
    {
        $this->assertTrue($this->request(['type' => PingRequestButton::TYPE_EMERGENCY])->isEmergencyRequest());
        $this->assertFalse($this->request(['type' => PingRequestButton::TYPE_REQUEST])->isEmergencyRequest());
        $this->assertFalse($this->request([])->isEmergencyRequest());
    }

    public function test_a_broadcast_is_never_an_emergency_request(): void
    {
        $this->assertFalse((new PingHistory(['fields' => ['type' => PingRequestButton::TYPE_EMERGENCY]]))->isEmergencyRequest());
        $this->assertFalse((new PingHistory(['fields' => null]))->isEmergencyRequest());
    }

    public function test_form_up_starts_from_who_asked_and_what_they_wrote(): void
    {
        $prefill = RequestBoard::prefill(
            $this->request(['button' => 'Mining fleet', 'main' => 'Ore Hauler', 'type' => PingRequestButton::TYPE_REQUEST], "System: Jita\nShips: Hulk"),
            'FC Name'
        );

        $this->assertSame([
            'embed_type' => 'prepping',
            'message' => "Mining fleet for Ore Hauler: forming up NOW!\nSystem: Jita\nShips: Hulk",
            'fc_name' => 'FC Name',
        ], $prefill);
    }

    /** What the button sets comes first, and where to form up and what to fly stay the FC's call whatever it says. */
    public function test_an_answer_starts_from_what_the_button_sets(): void
    {
        $prefill = RequestBoard::prefill($this->request(['button' => 'Mining fleet', 'main' => 'Ore Hauler'], 'Belt'), 'FC Name', [
            'webhook_id' => '4',
            'embed_type' => 'fleet',
            'mention_type' => 'role',
            'role_mention' => 12,
            'comms' => 'Mining channel',
            'formup_location' => 'Jita',
            'doctrine' => 'Hulks',
            'message' => 'Typed by an admin',
        ]);

        $this->assertSame([
            'embed_type' => 'fleet',
            'webhook_id' => 4,
            'mention_type' => 'role',
            'role_mention' => 12,
            'comms' => 'Mining channel',
            'message' => "Mining fleet for Ore Hauler: forming up NOW!\nBelt",
            'fc_name' => 'FC Name',
        ], $prefill);
    }

    /** Laid over the Send form's own defaults, a request leaves alone what it has nothing to say about. */
    public function test_an_answer_keeps_the_send_forms_defaults_it_says_nothing_about(): void
    {
        $values = BroadcastForm::layer(
            ['formup_location' => '1DQ1-A', 'doctrine' => 'Ferox', 'fc_name' => 'Default Main'],
            RequestBoard::prefill($this->request(['button' => 'PvP fleet', 'main' => 'Pilot'], 'Roam'), '')
        );

        $this->assertSame('1DQ1-A', $values['formup_location']);
        $this->assertSame('Ferox', $values['doctrine']);
        $this->assertSame('Default Main', $values['fc_name']);
    }

    public function test_an_emergency_says_so_first(): void
    {
        $prefill = RequestBoard::prefill($this->request(['button' => 'Tackled', 'main' => 'Pilot', 'type' => PingRequestButton::TYPE_EMERGENCY], 'System: Q-3HS5'), 'FC Name');

        $this->assertSame("🆘 Tackled for Pilot: forming up NOW!\nSystem: Q-3HS5", $prefill['message']);
    }

    /** A press with nothing filled in is recorded under the button's name, which the first line already says. */
    public function test_an_empty_press_is_not_said_twice(): void
    {
        $prefill = RequestBoard::prefill($this->request(['button' => 'Tackled', 'main' => 'Pilot', 'type' => PingRequestButton::TYPE_EMERGENCY], 'Tackled'), 'FC Name');

        $this->assertSame('🆘 Tackled for Pilot: forming up NOW!', $prefill['message']);
    }

    public function test_a_row_with_no_main_uses_the_name_it_was_recorded_under(): void
    {
        $prefill = RequestBoard::prefill($this->request(['button' => 'PvP fleet'], 'Roam'), '');

        $this->assertSame("PvP fleet for Recorded Name: forming up NOW!\nRoam", $prefill['message']);
        $this->assertArrayNotHasKey('fc_name', $prefill);
    }

    /** The link in a request's Discord message names it by this, so it has to be one that can't be guessed or mistaken. */
    public function test_a_ref_is_sixteen_hex_characters_and_nothing_else_passes(): void
    {
        $ref = RequestBoard::newRef();

        $this->assertTrue(RequestBoard::isRef($ref));
        $this->assertNotSame($ref, RequestBoard::newRef());

        foreach (['', '123', str_repeat('g', 16), str_repeat('a', 17), "aaaaaaaaaaaaaaa'", null, 1234567890123456, ['a']] as $wrong) {
            $this->assertFalse(RequestBoard::isRef($wrong));
        }
    }

    public function test_a_wrong_ref_or_id_is_not_looked_up(): void
    {
        $this->assertNull(RequestBoard::findByRef((object) ['id' => 1], 'not-a-ref'));
        $this->assertNull(RequestBoard::find((object) ['id' => 1], 'abc'));
    }

    public function test_the_message_fits_the_send_form(): void
    {
        $prefill = RequestBoard::prefill($this->request(['button' => 'Long', 'main' => 'Pilot'], str_repeat('é', 3000)), 'FC Name');

        $this->assertSame(2000, mb_strlen($prefill['message']));
    }
}
