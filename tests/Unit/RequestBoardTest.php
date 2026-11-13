<?php

namespace DiscordPings\Tests\Unit;

use DiscordPings\Models\PingHistory;
use DiscordPings\Models\PingRequestButton;
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
            (object) ['name' => 'FC Name']
        );

        $this->assertSame([
            'message' => "Mining fleet for Ore Hauler: forming up NOW!\nSystem: Jita\nShips: Hulk",
            'fc_name' => 'FC Name',
            'formup_location' => '',
            'embed_type' => 'prepping',
        ], $prefill);
    }

    public function test_an_emergency_says_so_first(): void
    {
        $prefill = RequestBoard::prefill($this->request(['button' => 'Tackled', 'main' => 'Pilot', 'type' => PingRequestButton::TYPE_EMERGENCY], 'System: Q-3HS5'), (object) ['name' => 'FC Name']);

        $this->assertSame("🆘 Tackled for Pilot: forming up NOW!\nSystem: Q-3HS5", $prefill['message']);
    }

    /** A press with nothing filled in is recorded under the button's name, which the first line already says. */
    public function test_an_empty_press_is_not_said_twice(): void
    {
        $prefill = RequestBoard::prefill($this->request(['button' => 'Tackled', 'main' => 'Pilot', 'type' => PingRequestButton::TYPE_EMERGENCY], 'Tackled'), (object) ['name' => 'FC Name']);

        $this->assertSame('🆘 Tackled for Pilot: forming up NOW!', $prefill['message']);
    }

    public function test_a_row_with_no_main_uses_the_name_it_was_recorded_under(): void
    {
        $prefill = RequestBoard::prefill($this->request(['button' => 'PvP fleet'], 'Roam'), (object) []);

        $this->assertSame("PvP fleet for Recorded Name: forming up NOW!\nRoam", $prefill['message']);
        $this->assertSame('', $prefill['fc_name']);
    }

    public function test_the_message_fits_the_send_form(): void
    {
        $prefill = RequestBoard::prefill($this->request(['button' => 'Long', 'main' => 'Pilot'], str_repeat('é', 3000)), (object) ['name' => 'FC Name']);

        $this->assertSame(2000, mb_strlen($prefill['message']));
    }
}
