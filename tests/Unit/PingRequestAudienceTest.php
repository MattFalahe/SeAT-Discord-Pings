<?php

namespace DiscordPings\Tests\Unit;

use DiscordPings\Models\PingRequestButton;
use DiscordPings\Services\PingRequests\MemberAccount;
use Illuminate\Support\Facades\DB;
use PHPUnit\Framework\TestCase;

class PingRequestAudienceTest extends TestCase
{
    protected function setUp(): void
    {
        DB::$tables = [];
        DB::$broken = [];
        $GLOBALS['FAKE_CONFIG']['seat.permissions.discordpings'] = require __DIR__ . '/../../src/Config/Permissions/discordpings.permissions.php';
    }

    protected function tearDown(): void
    {
        unset($GLOBALS['FAKE_CONFIG']['seat.permissions.discordpings']);
    }

    private function token(int $userId, int $corporation, ?int $alliance): object
    {
        return (object) ['refresh_tokens.user_id' => $userId, 'corporation_id' => $corporation, 'alliance_id' => $alliance];
    }

    /** Everyone who can open the page has both, so offering them would only look like a limit. */
    public function test_view_and_line_member_cannot_be_ticked(): void
    {
        $options = PingRequestButton::audienceOptions();

        $this->assertArrayNotHasKey('discordpings.view', $options);
        $this->assertArrayNotHasKey('discordpings.line_member', $options);
    }

    public function test_every_other_permission_can_be_ticked_under_its_full_name(): void
    {
        $registered = array_keys($GLOBALS['FAKE_CONFIG']['seat.permissions.discordpings']);
        $expected = array_map(fn ($key) => 'discordpings.' . $key, array_values(array_diff($registered, ['view', 'line_member'])));

        $this->assertSame($expected, array_keys(PingRequestButton::audienceOptions()));
        $this->assertContains('discordpings.send', $expected);
    }

    public function test_a_permission_shows_under_its_translated_label(): void
    {
        $this->assertSame('Translated discordpings::permissions.send_label', PingRequestButton::audienceOptions()['discordpings.send']);
    }

    public function test_every_character_on_the_account_counts(): void
    {
        DB::$tables['refresh_tokens'] = [$this->token(5, 100, 900), $this->token(5, 200, null), $this->token(5, 100, 900), $this->token(6, 300, 901)];

        $this->assertSame(['corporations' => [100, 200], 'alliances' => [900]], MemberAccount::affiliations(5));
    }

    public function test_a_failed_read_means_no_corporations_rather_than_an_error(): void
    {
        DB::$broken['refresh_tokens'] = new \RuntimeException('gone');

        $this->assertSame(['corporations' => [], 'alliances' => []], MemberAccount::affiliations(5));
        $this->assertSame([], MemberAccount::characterNames(5));
    }
}
