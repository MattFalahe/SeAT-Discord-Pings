<?php

namespace DiscordPings\Tests\Unit;

use DiscordPings\Services\DiscordUserResolver;
use DiscordPings\Services\MemberMentions;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Schema;
use PHPUnit\Framework\TestCase;

class MemberMentionsTest extends TestCase
{
    private const MATT = '111111111111111111';
    private const MATT_ALONE = '222222222222222222';
    private const BOB = '333333333333333333';
    private const OTHER_BOB = '444444444444444444';
    private const DIRECTORS = '555555555555555555';

    protected function setUp(): void
    {
        Schema::$tables = [];
        DB::$tables = [];
        DB::$broken = [];
        Log::$lines = [];
    }

    private function members(): array
    {
        return [
            ['id' => self::MATT, 'main' => 'Matt Falahe', 'discord' => 'matt_falahe'],
            ['id' => self::MATT_ALONE, 'main' => 'Matt', 'discord' => 'someone_else'],
            ['id' => self::BOB, 'main' => 'Bob', 'discord' => 'bob'],
        ];
    }

    private function resolve(string $text, ?array $members = null, array $roles = []): array
    {
        return MemberMentions::resolve($text, $members ?? $this->members(), $roles);
    }

    public function test_what_discord_already_reads_goes_as_typed(): void
    {
        $text = '<@' . self::MATT . '> <@!' . self::BOB . '> <@&' . self::DIRECTORS . '> <#' . self::DIRECTORS . '> @everyone @here <t:1700000000:R>';

        $result = $this->resolve($text, null, [self::DIRECTORS => 'Directors']);

        $this->assertSame($text, $result['text']);
        $this->assertSame(['Matt Falahe', 'Bob', 'role Directors', '@everyone', '@here'], $result['pings']);
        $this->assertSame([], $result['unknown']);
    }

    public function test_a_typed_main_name_pings_its_account(): void
    {
        $result = $this->resolve('Form up @Matt Falahe and @Bob');

        $this->assertSame('Form up <@' . self::MATT . '> and <@' . self::BOB . '>', $result['text']);
        $this->assertSame(['Matt Falahe', 'Bob'], $result['pings']);
    }

    public function test_the_longest_name_wins_and_case_does_not_matter(): void
    {
        $this->assertSame('<@' . self::MATT . '>', $this->resolve('@matt falahe')['text'], 'Matt Falahe, not the Matt before it');
        $this->assertSame('<@' . self::MATT_ALONE . '> Falahy', $this->resolve('@MATT Falahy')['text']);
    }

    public function test_a_discord_username_pings_its_account(): void
    {
        $this->assertSame('<@' . self::MATT . '>', $this->resolve('@matt_falahe')['text']);
        $this->assertSame('<@' . self::MATT_ALONE . '>', $this->resolve('@Someone_Else')['text']);
    }

    public function test_an_older_username_with_its_number_is_found_either_way_it_is_typed(): void
    {
        $members = [['id' => self::BOB, 'main' => null, 'discord' => 'Bobby#1234']];

        $this->assertSame('<@' . self::BOB . '>', $this->resolve('@bobby', $members)['text']);
        $this->assertSame('<@' . self::BOB . '>', $this->resolve('@Bobby#1234', $members)['text']);
        $this->assertSame(['bobby'], $this->resolve('@bobby', $members)['pings'], 'With no main, the Discord name is what the form shows');
    }

    public function test_punctuation_after_a_name_stays_where_it_was(): void
    {
        $this->assertSame('Ping <@' . self::MATT . '>.', $this->resolve('Ping @matt_falahe.')['text']);
        $this->assertSame("<@" . self::MATT . ">'s fleet", $this->resolve("@Matt Falahe's fleet")['text']);
        $this->assertSame('<@' . self::BOB . '>, <@' . self::MATT . '>', $this->resolve('@Bob, @Matt Falahe')['text']);
    }

    public function test_a_name_that_runs_on_is_not_a_shorter_one(): void
    {
        $result = $this->resolve('@Mattress');

        $this->assertSame('@Mattress', $result['text']);
        $this->assertSame(['@Mattress'], $result['unknown']);
    }

    public function test_a_name_nobody_has_is_left_as_typed_and_said(): void
    {
        $result = $this->resolve('@nobody and @Bob');

        $this->assertSame('@nobody and <@' . self::BOB . '>', $result['text']);
        $this->assertSame(['@nobody'], $result['unknown']);
        $this->assertSame(['Bob'], $result['pings']);
    }

    public function test_a_name_two_accounts_share_pings_neither(): void
    {
        $members = [
            ['id' => self::BOB, 'main' => 'Bob Prime', 'discord' => 'Bob#1111'],
            ['id' => self::OTHER_BOB, 'main' => 'Bob Second', 'discord' => 'Bob#2222'],
        ];

        $result = $this->resolve('@bob', $members);

        $this->assertSame('@bob', $result['text']);
        $this->assertSame(['@bob'], $result['ambiguous']);
        $this->assertSame([], $result['unknown']);
    }

    public function test_a_main_and_a_username_of_one_account_are_not_a_clash(): void
    {
        $result = $this->resolve('@bob');

        $this->assertSame('<@' . self::BOB . '>', $result['text']);
        $this->assertSame([], $result['ambiguous']);
    }

    public function test_a_bare_id_is_a_member_unless_it_is_a_known_role(): void
    {
        $roles = [self::DIRECTORS => 'Directors'];

        $result = $this->resolve(self::MATT . ' ' . self::DIRECTORS . ' @' . self::BOB . ' 999999999999999999', null, $roles);

        $this->assertSame('<@' . self::MATT . '> <@&' . self::DIRECTORS . '> <@' . self::BOB . '> <@999999999999999999>', $result['text']);
        $this->assertSame(['Matt Falahe', 'role Directors', 'Bob', 'Discord user …999999'], $result['pings']);
    }

    public function test_other_numbers_and_addresses_are_not_mentions(): void
    {
        $text = 'fc@example.com, 16 pilots, 1234567890123456, 123456789012345678901, x' . self::MATT . ', ' . self::MATT . 'x';

        $result = $this->resolve($text);

        $this->assertSame($text, $result['text']);
        $this->assertSame([], $result['pings']);
        $this->assertSame([], $result['unknown']);
    }

    public function test_an_account_without_a_proper_id_is_never_pinged(): void
    {
        $result = $this->resolve('@Ghost', [['id' => 'not-a-snowflake', 'main' => 'Ghost', 'discord' => 'ghost']]);

        $this->assertSame('@Ghost', $result['text']);
        $this->assertSame(['@Ghost'], $result['unknown']);
    }

    public function test_a_full_box_of_the_shortest_mentions_still_fits_in_a_discord_message(): void
    {
        $members = [['id' => '12345678901234567890', 'main' => null, 'discord' => 'ab']];
        $text = str_repeat('@ab ', intdiv(MemberMentions::MAX_LENGTH, 4));

        $result = $this->resolve($text, $members);

        $this->assertSame(MemberMentions::MAX_LENGTH, strlen($text));
        $this->assertStringNotContainsString('@ab', $result['text']);
        $this->assertLessThanOrEqual(2000, strlen($result['text']));
    }

    private function linkRow(string $id, ?string $discord, ?string $main, string $account, bool $active = true): object
    {
        return (object) [
            's.connector_type' => 'discord',
            'u.active' => $active,
            'connector_id' => $id,
            'connector_name' => $discord,
            'main' => $main,
            'account' => $account,
        ];
    }

    public function test_the_linked_accounts_come_by_main_with_the_account_name_when_the_main_is_not_loaded(): void
    {
        Schema::$tables = ['seat_connector_users'];
        DB::$tables['seat_connector_users'] = [
            $this->linkRow(self::BOB, 'bob', null, 'Bob'),
            $this->linkRow(self::MATT, 'matt_falahe', 'Matt Falahe', 'Matt Falahe'),
            $this->linkRow(self::MATT, 'matt_falahe', 'Matt Falahe', 'Matt Falahe'),
            $this->linkRow(self::OTHER_BOB, 'gone', 'Gone Pilot', 'Gone Pilot', false),
        ];

        $this->assertSame([
            ['id' => self::BOB, 'main' => 'Bob', 'discord' => 'bob'],
            ['id' => self::MATT, 'main' => 'Matt Falahe', 'discord' => 'matt_falahe'],
        ], DiscordUserResolver::linkedMembers());
    }

    public function test_without_seat_connector_there_is_nobody_to_list(): void
    {
        $this->assertSame([], DiscordUserResolver::linkedMembers());
    }

    public function test_a_broadcast_finds_the_names_as_it_goes_out(): void
    {
        Schema::$tables = ['seat_connector_users'];
        DB::$tables['seat_connector_users'] = [$this->linkRow(self::MATT, 'matt_falahe', 'Matt Falahe', 'Matt Falahe')];

        $this->assertSame('<@' . self::MATT . '> @nobody', MemberMentions::forDiscord('@Matt Falahe @nobody'));
    }

    public function test_a_broadcast_with_nothing_to_look_up_reads_nothing(): void
    {
        Schema::$tables = ['seat_connector_users', 'discord_roles'];
        DB::$broken = [
            'seat_connector_users' => new \RuntimeException('should not be read'),
            'discord_roles' => new \RuntimeException('should not be read'),
        ];

        $text = '@everyone <@&' . self::DIRECTORS . '> <@' . self::MATT . '>';

        $this->assertSame($text, MemberMentions::forDiscord($text));
        $this->assertSame('', MemberMentions::forDiscord(null));
        $this->assertSame([], Log::$lines);
    }

    public function test_a_broadcast_still_goes_out_as_typed_when_the_lookup_fails(): void
    {
        Schema::$tables = ['seat_connector_users'];
        DB::$broken = ['seat_connector_users' => new \RuntimeException('gone away')];

        $this->assertSame('@Matt Falahe', MemberMentions::forDiscord('@Matt Falahe'));
        $this->assertNotEmpty(Log::$lines);
    }

    public function test_the_form_is_told_what_pings_and_what_does_not(): void
    {
        Schema::$tables = ['seat_connector_users', 'discord_roles'];
        DB::$tables['seat_connector_users'] = [$this->linkRow(self::MATT, 'matt_falahe', 'Matt Falahe', 'Matt Falahe')];
        DB::$tables['discord_roles'] = [(object) ['role_id' => self::DIRECTORS, 'name' => 'Directors', 'color' => null, 'is_active' => true]];

        $this->assertSame([
            'pings' => ['Matt Falahe', 'role Directors'],
            'unknown' => ['@nobody'],
            'ambiguous' => [],
            'connector' => true,
        ], MemberMentions::check('@matt_falahe ' . self::DIRECTORS . ' @nobody'));
    }

    public function test_without_seat_connector_the_form_says_a_name_cannot_be_found(): void
    {
        $check = MemberMentions::check('@Matt Falahe ' . self::MATT);

        $this->assertSame(['Discord user …111111'], $check['pings']);
        $this->assertSame(['@Matt'], $check['unknown']);
        $this->assertFalse($check['connector']);
    }
}
