<?php

namespace DiscordPings\Tests\Unit;

use DiscordPings\Services\PingRequests\RequestMessage;
use DiscordPings\Services\PingRequests\SenderIdentity;
use PHPUnit\Framework\TestCase;

class RequestMessageTest extends TestCase
{
    private const ROLE = '1227722401236652123';

    private function payload(string $body = 'Rorqual Tackled', ?string $role = self::ROLE, ?SenderIdentity $sender = null, string $title = "Help, I'm tackled"): array
    {
        return RequestMessage::payload(
            $title,
            $body,
            $role,
            $sender ?? new SenderIdentity('Matt Falahe', 'Minc Corp'),
            0xDC3545,
            'SeAT Broadcast',
            '2026-11-08T12:00:00Z'
        );
    }

    /** @return array<string, string> field values by field name */
    private function fields(array $payload): array
    {
        return array_column($payload['embeds'][0]['fields'], 'value', 'name');
    }

    private function length(string $text): int
    {
        return (int) (strlen(mb_convert_encoding($text, 'UTF-16LE', 'UTF-8')) / 2);
    }

    /** @dataProvider typedPings */
    public function test_what_a_member_types_never_pings(string $typed): void
    {
        $payload = $this->payload($typed);

        $this->assertSame('<@&' . self::ROLE . '>', $payload['content']);
        $this->assertSame(['roles' => [self::ROLE]], $payload['allowed_mentions']);
        $this->assertSame($typed, $payload['embeds'][0]['description']);
    }

    public static function typedPings(): array
    {
        return [
            'everyone' => ['@everyone come help'],
            'here' => ['@here come help'],
            'a role' => ['<@&123456789> come help'],
            'a person' => ['<@456789> come help'],
        ];
    }

    public function test_a_button_with_no_role_pings_nobody(): void
    {
        $payload = $this->payload('@everyone', null);

        $this->assertArrayNotHasKey('content', $payload);
        $this->assertSame(['parse' => []], $payload['allowed_mentions']);
    }

    public function test_a_role_that_is_not_a_number_pings_nobody(): void
    {
        $payload = $this->payload('hi', '@everyone');

        $this->assertArrayNotHasKey('content', $payload);
        $this->assertSame(['parse' => []], $payload['allowed_mentions']);
    }

    public function test_the_block_underneath_names_the_person_behind_it(): void
    {
        $fields = $this->fields($this->payload('hi', self::ROLE, new SenderIdentity('Matt Falahe', 'Minc Corp', true, '42', 'matt')));

        $this->assertSame(['Sent by' => 'Matt Falahe', 'Corporation' => 'Minc Corp', 'Discord' => '<@42> (matt)'], $fields);
    }

    public function test_with_no_connector_the_discord_line_is_left_out(): void
    {
        $this->assertArrayNotHasKey('Discord', $this->fields($this->payload('hi', self::ROLE, new SenderIdentity('Matt Falahe', 'Minc Corp', false))));
    }

    public function test_with_a_connector_and_no_link_it_says_so(): void
    {
        $this->assertSame('not linked', $this->fields($this->payload('hi', self::ROLE, new SenderIdentity('Matt Falahe', 'Minc Corp', true)))['Discord']);
    }

    public function test_a_stored_name_is_optional(): void
    {
        $this->assertSame('<@42>', $this->fields($this->payload('hi', self::ROLE, new SenderIdentity('Matt Falahe', 'Minc Corp', true, '42')))['Discord']);
    }

    public function test_a_missing_corporation_says_so_instead_of_breaking_the_field(): void
    {
        $this->assertSame('Unknown corporation', $this->fields($this->payload('hi', self::ROLE, new SenderIdentity('Matt Falahe')))['Corporation']);
    }

    public function test_an_empty_body_sends_no_description(): void
    {
        $this->assertArrayNotHasKey('description', $this->payload('')['embeds'][0]);
    }

    public function test_the_title_is_cut_to_discords_limit(): void
    {
        $title = $this->payload('hi', self::ROLE, null, str_repeat('a', 400))['embeds'][0]['title'];

        $this->assertSame(256, $this->length($title));
    }

    public function test_the_description_is_cut_to_discords_limit(): void
    {
        $description = $this->payload(str_repeat('a', 5000))['embeds'][0]['description'];

        $this->assertSame(4096, $this->length($description));
    }

    public function test_a_field_value_is_cut_to_discords_limit(): void
    {
        $fields = $this->fields($this->payload('hi', self::ROLE, new SenderIdentity(str_repeat('a', 2000), 'Minc Corp')));

        $this->assertSame(1024, $this->length($fields['Sent by']));
    }

    public function test_the_whole_embed_stays_under_discords_total(): void
    {
        $embed = $this->payload(str_repeat('a', 9000), self::ROLE, new SenderIdentity(str_repeat('b', 2000), str_repeat('c', 2000), true, '42', str_repeat('d', 2000)), str_repeat('e', 400))['embeds'][0];

        $total = $this->length($embed['title']) + $this->length($embed['description']);
        foreach ($embed['fields'] as $field) {
            $total += $this->length($field['name']) + $this->length($field['value']);
        }

        $this->assertLessThanOrEqual(6000, $total);
    }

    public function test_emoji_are_counted_the_way_discord_counts_them(): void
    {
        $description = $this->payload(str_repeat('😀', 3000))['embeds'][0]['description'];

        $this->assertLessThanOrEqual(4096, $this->length($description));
        $this->assertGreaterThan(4090, $this->length($description));
    }

    /** @dataProvider maskedLinks */
    public function test_a_masked_link_stops_being_a_link(string $typed, string $sent): void
    {
        $this->assertSame($sent, $this->payload($typed)['embeds'][0]['description']);
    }

    public static function maskedLinks(): array
    {
        return [
            'plain' => ['[free isk](https://bad.example)', '[free isk\\](https://bad.example)'],
            'already escaped' => ['[free isk\\](https://bad.example)', '[free isk\\](https://bad.example)'],
            'escaped twice' => ['[free isk\\\\](https://bad.example)', '[free isk\\](https://bad.example)'],
            'two of them' => ['[a](b) [c](d)', '[a\\](b) [c\\](d)'],
            'brackets that are not a link' => ['[Rorqual Tackled] (now)', '[Rorqual Tackled] (now)'],
        ];
    }

    public function test_names_cannot_carry_a_masked_link_either(): void
    {
        $fields = $this->fields($this->payload('hi', self::ROLE, new SenderIdentity('[x](y)', '[x](y)')));

        $this->assertSame('[x\\](y)', $fields['Sent by']);
        $this->assertSame('[x\\](y)', $fields['Corporation']);
    }

    public function test_broken_text_still_makes_a_message_that_can_be_sent(): void
    {
        $this->assertNotFalse(json_encode($this->payload("bad \xC3\x28 text")));
    }

    public function test_a_new_body_changes_the_description_and_nothing_else(): void
    {
        $embed = $this->payload('Rorqual Tackled')['embeds'][0];

        $changed = RequestMessage::withBody($embed, "Rorqual Tackled\nPanic Active? Yes");

        $this->assertSame("Rorqual Tackled\nPanic Active? Yes", $changed['description']);
        unset($embed['description'], $changed['description']);
        $this->assertSame($embed, $changed);
    }

    public function test_an_empty_new_body_takes_the_description_away(): void
    {
        $this->assertArrayNotHasKey('description', RequestMessage::withBody($this->payload('Rorqual Tackled')['embeds'][0], ''));
    }

    public function test_a_new_body_only_gets_the_room_the_rest_of_the_embed_leaves(): void
    {
        $embed = $this->payload('hi', self::ROLE, new SenderIdentity(str_repeat('b', 2000), str_repeat('c', 2000)))['embeds'][0];

        $changed = RequestMessage::withBody($embed, str_repeat('a', 9000));

        $total = $this->length($changed['title']) + $this->length($changed['description']);
        foreach ($changed['fields'] as $field) {
            $total += $this->length($field['name']) + $this->length($field['value']);
        }

        $this->assertLessThanOrEqual(6000, $total);
        $this->assertLessThan(4096, $this->length($changed['description']));
    }

    public function test_a_new_body_cannot_carry_a_masked_link_either(): void
    {
        $changed = RequestMessage::withBody($this->payload('hi')['embeds'][0], '[free isk](https://bad.example)');

        $this->assertSame('[free isk\\](https://bad.example)', $changed['description']);
    }
}
