<?php

namespace DiscordPings\Tests\Unit;

use DiscordPings\Http\Controllers\TemplateController;
use PHPUnit\Framework\TestCase;

/** What a template keeps of the form it was saved from, so loading it brings the whole form back. */
class TemplateFieldsTest extends TestCase
{
    public function test_every_box_of_the_form_is_kept(): void
    {
        $fields = TemplateController::fieldsFrom([
            'name' => 'Stratop',
            'message' => 'Kept in its own column',
            'webhook_id' => '3',
            'embed_type' => 'fleet',
            'mention_type' => 'role',
            'role_mention' => '12',
            'custom_mention' => '',
            'channel_link' => '5',
            'embed_color' => '#FF0000',
            'fc_name' => ' Fleet Boss ',
            'formup_location' => 'Jita',
            'pap_type' => 'Strategic',
            'comms' => 'Fleet 1',
            'doctrine' => 'Ferox',
        ]);

        $this->assertSame([
            'webhook_id' => 3,
            'embed_type' => 'fleet',
            'mention_type' => 'role',
            'embed_color' => '#FF0000',
            'fc_name' => 'Fleet Boss',
            'formup_location' => 'Jita',
            'pap_type' => 'Strategic',
            'comms' => 'Fleet 1',
            'mention_role_id' => 12,
            'channel_link' => 5,
            'doctrine' => 'Ferox',
        ], $fields);
    }

    /** Templates have always kept the role under this name, and older pages still send it that way. */
    public function test_the_role_is_kept_under_its_old_name_from_either_box(): void
    {
        $this->assertSame(['mention_role_id' => 7], TemplateController::fieldsFrom(['role_mention' => '7']));
        $this->assertSame(['mention_role_id' => 8], TemplateController::fieldsFrom(['mention_role_id' => '8']));
    }

    public function test_several_webhooks_are_kept_as_a_list_and_one_as_itself(): void
    {
        $this->assertSame(['webhook_ids' => [3, 4]], TemplateController::fieldsFrom(['webhook_ids' => ['3', '4', '3'], 'webhook_id' => '9']));
        $this->assertSame(['webhook_id' => 3], TemplateController::fieldsFrom(['webhook_ids' => ['3']]));
    }

    /** With no SeAT Fitting to find it in, a picked doctrine falls back to what was typed and keeps no dead id. */
    public function test_a_doctrine_that_cannot_be_found_keeps_only_the_typed_name(): void
    {
        $this->assertSame(['doctrine' => 'Ferox'], TemplateController::fieldsFrom(['doctrine_id' => '4', 'doctrine' => 'Ferox']));
        $this->assertSame([], TemplateController::fieldsFrom(['doctrine_id' => '4']));
    }

    public function test_an_empty_form_keeps_nothing(): void
    {
        $this->assertSame([], TemplateController::fieldsFrom(['name' => 'Empty', 'message' => 'Hi', 'fc_name' => '  ', 'webhook_ids' => []]));
    }
}
