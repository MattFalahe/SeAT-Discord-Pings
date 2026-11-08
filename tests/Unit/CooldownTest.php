<?php

namespace DiscordPings\Tests\Unit;

use DiscordPings\Services\PingRequests\Cooldown;
use PHPUnit\Framework\TestCase;

class CooldownTest extends TestCase
{
    private const NOW = 1_800_000_000;

    public function test_an_emergency_never_waits_even_straight_after_a_press(): void
    {
        $this->assertSame(0, Cooldown::secondsLeft(true, 60, self::NOW, self::NOW));
    }

    public function test_a_request_waits_out_its_buttons_cooldown(): void
    {
        $this->assertSame(900, Cooldown::secondsLeft(false, 15, self::NOW, self::NOW));
        $this->assertSame(1, Cooldown::secondsLeft(false, 15, self::NOW, self::NOW + 899));
    }

    public function test_a_request_can_go_once_the_cooldown_is_over(): void
    {
        $this->assertSame(0, Cooldown::secondsLeft(false, 15, self::NOW, self::NOW + 900));
        $this->assertSame(0, Cooldown::secondsLeft(false, 15, self::NOW, self::NOW + 5000));
    }

    public function test_a_button_never_pressed_has_nothing_to_wait_for(): void
    {
        $this->assertSame(0, Cooldown::secondsLeft(false, 15, null, self::NOW));
    }

    public function test_no_cooldown_set_means_no_wait(): void
    {
        $this->assertSame(0, Cooldown::secondsLeft(false, 0, self::NOW, self::NOW));
    }

    /** @dataProvider waits */
    public function test_a_blocked_press_says_when_it_can_go_again(int $seconds, string $said): void
    {
        $this->assertSame($said, Cooldown::describe($seconds));
    }

    public static function waits(): array
    {
        return [
            [1, '1 second'],
            [45, '45 seconds'],
            [60, '1 minute'],
            [61, '2 minutes'],
            [900, '15 minutes'],
            [3600, '1 hour'],
            [3660, '1 hour 1 minute'],
            [7500, '2 hours 5 minutes'],
        ];
    }
}
