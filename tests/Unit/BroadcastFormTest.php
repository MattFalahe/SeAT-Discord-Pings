<?php

namespace DiscordPings\Tests\Unit;

use DiscordPings\Services\BroadcastForm;
use PHPUnit\Framework\TestCase;

class BroadcastFormTest extends TestCase
{
    public function test_only_the_keys_asked_for_are_kept_each_in_the_shape_the_form_takes(): void
    {
        $clean = BroadcastForm::clean([
            'webhook_id' => '4',
            'role_mention' => '0',
            'channel_link' => 'seven',
            'embed_type' => 'prepping',
            'mention_type' => 'role',
            'embed_color' => '#00ff00',
            'fc_main' => 'on',
            'comms' => '  Fleet channel  ',
            'doctrine' => '',
            'message' => 'Never a default',
            'webhook_url' => 'https://discord.com/api/webhooks/1/x',
        ], BroadcastForm::DEFAULT_KEYS);

        $this->assertSame([
            'webhook_id' => 4,
            'embed_type' => 'prepping',
            'mention_type' => 'role',
            'embed_color' => '#00ff00',
            'fc_main' => true,
            'comms' => 'Fleet channel',
        ], $clean);
    }

    public function test_a_value_the_form_would_refuse_is_left_out(): void
    {
        $clean = BroadcastForm::clean([
            'embed_type' => 'shout',
            'mention_type' => 'everyone-and-more',
            'embed_color' => '#abc',
            'fc_main' => 'no',
            'pap_type' => ['Strategic'],
        ], BroadcastForm::DEFAULT_KEYS);

        $this->assertSame([], $clean);
    }

    public function test_text_is_cut_to_what_the_send_form_takes(): void
    {
        $clean = BroadcastForm::clean(['formup_location' => str_repeat('a', 150), 'comms' => str_repeat('é', 250)], BroadcastForm::DEFAULT_KEYS);

        $this->assertSame(100, mb_strlen($clean['formup_location']));
        $this->assertSame(200, mb_strlen($clean['comms']));
    }

    /** The message only starts filled in when the broadcast answers something, and a button never picks where or what. */
    public function test_neither_list_of_defaults_can_carry_a_message(): void
    {
        $this->assertNotContains('message', BroadcastForm::DEFAULT_KEYS);
        $this->assertNotContains('message', BroadcastForm::ANSWER_KEYS);

        foreach (['formup_location', 'doctrine', 'fc_name', 'fc_main'] as $theFcsCall) {
            $this->assertNotContains($theFcsCall, BroadcastForm::ANSWER_KEYS);
        }
    }

    public function test_later_layers_win_only_where_they_say_something(): void
    {
        $this->assertSame(
            ['embed_type' => 'fleet', 'comms' => 'Fleet', 'fc_name' => 'Pilot'],
            BroadcastForm::layer(['embed_type' => 'prepping', 'comms' => 'Fleet'], ['embed_type' => 'fleet', 'comms' => '', 'fc_name' => null], ['fc_name' => 'Pilot'])
        );
    }

    public function test_an_empty_form_starts_with_nothing_whatever_is_saved(): void
    {
        $saved = json_encode(['comms' => 'Fleet', 'fc_main' => true]);

        $this->assertSame([], BroadcastForm::starting(BroadcastForm::EMPTY, $saved, 'Main Pilot'));
        $this->assertSame([], BroadcastForm::starting(null, $saved, 'Main Pilot'));
    }

    public function test_defaults_start_with_the_saved_values_and_the_senders_main(): void
    {
        $saved = json_encode(['comms' => 'Fleet', 'webhook_id' => 2, 'fc_main' => true]);

        $this->assertSame(
            ['webhook_id' => 2, 'comms' => 'Fleet', 'fc_name' => 'Main Pilot'],
            BroadcastForm::starting(BroadcastForm::DEFAULTS, $saved, 'Main Pilot')
        );
    }

    public function test_the_fc_name_is_left_empty_when_it_is_off_or_there_is_no_main(): void
    {
        $this->assertSame(['comms' => 'Fleet'], BroadcastForm::starting(BroadcastForm::DEFAULTS, json_encode(['comms' => 'Fleet']), 'Main Pilot'));
        $this->assertSame(['comms' => 'Fleet'], BroadcastForm::starting(BroadcastForm::DEFAULTS, json_encode(['comms' => 'Fleet', 'fc_main' => true]), ''));
    }

    /** Settings may hold a key that has since been dropped, or nothing readable at all. */
    public function test_saved_defaults_are_cleaned_again_when_read(): void
    {
        $this->assertSame(['comms' => 'Fleet'], BroadcastForm::decode(json_encode(['comms' => 'Fleet', 'old_key' => 'x', 'message' => 'Hi'])));
        $this->assertSame([], BroadcastForm::decode('not json'));
        $this->assertSame([], BroadcastForm::decode(null));
    }

    public function test_what_was_typed_before_is_offered_newest_first_and_once(): void
    {
        $typed = BroadcastForm::typedBefore([
            ['comms' => 'Fleet 1', 'formup_location' => 'Jita'],
            ['comms' => 'fleet 1', 'doctrine' => 'Ferox', 'formup_location' => ' '],
            null,
            ['comms' => 'Fleet 2', 'doctrine_name' => 'Muninn', 'doctrine' => '12'],
        ]);

        $this->assertSame(['Fleet 1', 'Fleet 2'], $typed['comms']);
        $this->assertSame(['Ferox', 'Muninn'], $typed['doctrine']);
        $this->assertSame(['Jita'], $typed['formup_location']);
    }

    public function test_only_so_many_are_offered(): void
    {
        $typed = BroadcastForm::typedBefore(array_map(fn ($i) => ['comms' => 'Channel ' . $i], range(1, 30)), 5);

        $this->assertSame(['Channel 1', 'Channel 2', 'Channel 3', 'Channel 4', 'Channel 5'], $typed['comms']);
    }
}
