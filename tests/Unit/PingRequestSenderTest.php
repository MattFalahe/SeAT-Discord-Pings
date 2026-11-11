<?php

namespace DiscordPings\Tests\Unit;

use DiscordPings\Models\DiscordWebhook;
use DiscordPings\Models\PingHistory;
use DiscordPings\Models\PingRequestButton;
use DiscordPings\Services\PingRequests\PingRequestSender;
use DiscordPings\Services\PingRequests\RequestMessage;
use DiscordPings\Services\PingRequests\Sections;
use GuzzleHttp\Client;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Facades\Schema;
use PHPUnit\Framework\TestCase;

class PingRequestSenderTest extends TestCase
{
    private const TOKEN = 'aBcD-eFgH_iJkLmNoPqRsTuV';
    private const ADDRESS = 'https://discord.com/api/webhooks/123456/' . self::TOKEN;

    private object $user;

    protected function setUp(): void
    {
        Model::$created = [];
        Model::$failCreateWith = null;
        Model::$found = [];
        Model::$updated = [];
        Cache::$store = [];
        Cache::$seconds = [];
        Cache::$failPutWith = null;
        Cache::$failLockWith = null;
        RateLimiter::$hits = [];
        Log::$lines = [];
        Schema::$tables = [];
        DB::$rows = [];
        Client::$posts = [];
        Client::$patches = [];
        Client::$replies = [];

        $this->user = (object) ['id' => 7, 'name' => 'Account Name', 'main_character_id' => 90000001];
    }

    private function webhook(array $overrides = []): DiscordWebhook
    {
        return new DiscordWebhook($overrides + ['id' => 3, 'webhook_url' => self::ADDRESS, 'embed_color' => '#5865F2', 'is_active' => true]);
    }

    private function button(string $type, array $overrides = []): PingRequestButton
    {
        return new PingRequestButton($overrides + [
            'id' => 11,
            'name' => "Help, I'm tackled",
            'type' => $type,
            'starter_text' => 'Rorqual Tackled',
            'mention_role_id' => '555',
            'cooldown_minutes' => 15,
            'webhook' => $this->webhook(),
        ]);
    }

    private function reply(int $status = 200, array $body = ['id' => '999'], array $headers = []): object
    {
        return new class($status, $body, $headers) {
            public function __construct(private int $status, private array $body, private array $headers)
            {
            }

            public function getStatusCode(): int
            {
                return $this->status;
            }

            public function getBody(): string
            {
                return json_encode($this->body);
            }

            public function getHeader(string $name): array
            {
                return $this->headers[$name] ?? [];
            }
        };
    }

    private function press(PingRequestButton $button, array $answers = [], ?string $savedForm = null): array
    {
        return (new PingRequestSender())->press($button, $answers, $this->user, $savedForm);
    }

    /** @return array<int, array> the history rows written, oldest first */
    private function history(): array
    {
        return array_column(array_filter(Model::$created, fn ($row) => $row[0] === PingHistory::class), 1);
    }

    /** The general format, which asks three things once the message is out. */
    private function tackled(string $type = PingRequestButton::TYPE_EMERGENCY, array $overrides = []): PingRequestButton
    {
        return $this->button($type, $overrides + ['sections' => Sections::STARTING_POINTS['Tackled']]);
    }

    /** @return array<string, string> each question's answer key, by the question */
    private function keys(): array
    {
        return array_column(Sections::lines($this->tackled()->sectionRows()), 'key', 'text');
    }

    /** Presses the general format and hands back the token its Active requests entry carries. */
    private function pressTackled(array $answers = [], ?DiscordWebhook $webhook = null, ?string $savedForm = null): string
    {
        $webhook ??= $this->webhook();
        Model::$found[DiscordWebhook::class][$webhook->id] = $webhook;
        Client::$replies[] = $this->reply();

        return $this->press($this->tackled(PingRequestButton::TYPE_EMERGENCY, ['webhook' => $webhook]), $answers, $savedForm)['active']['token'];
    }

    private function addDetails(string $token, array $answers, ?object $user = null): array
    {
        return (new PingRequestSender())->addDetails($token, $answers, $user ?? $this->user);
    }

    private function close(string $token, ?object $user = null): array
    {
        return (new PingRequestSender())->close($token, $user ?? $this->user);
    }

    private function openFor(?object $user = null): array
    {
        return (new PingRequestSender())->openFor($user ?? $this->user);
    }

    private function someoneElse(): object
    {
        return (object) ['id' => 8, 'name' => 'Someone Else', 'main_character_id' => 90000002];
    }

    /** Moves when a kept request closes, the way time passing would. */
    private function closesIn(string $token, int $seconds): void
    {
        Cache::$store['discordpings:requests:details:' . $token]['closes_at'] = time() + $seconds;
    }

    private function editedDescription(int $edit = 0): string
    {
        return Client::$patches[$edit][1]['json']['embeds'][0]['description'] ?? '';
    }

    public function test_an_emergency_posts_on_every_press(): void
    {
        $button = $this->button(PingRequestButton::TYPE_EMERGENCY);
        Client::$replies = [$this->reply(), $this->reply(), $this->reply()];

        foreach (range(1, 3) as $press) {
            $this->assertTrue($this->press($button)['ok']);
        }

        $this->assertCount(3, Client::$posts);
        $this->assertSame(['sent', 'sent', 'sent'], array_column($this->history(), 'status'));
    }

    public function test_nothing_of_the_plugins_own_can_hold_an_emergency_up(): void
    {
        RateLimiter::$hits = [
            'discordpings:requests:min:3' => 9999, 'discordpings:requests:hour:3' => 9999,
            'discordpings:min:3' => 9999, 'discordpings:hour:3' => 9999,
        ];
        Cache::$store['discordpings:requests:pressed:11:7'] = time();
        Client::$replies = [$this->reply()];

        $this->assertTrue($this->press($this->button(PingRequestButton::TYPE_EMERGENCY))['ok']);
        $this->assertCount(1, Client::$posts);
    }

    public function test_an_emergency_leaves_no_cooldown_or_counters_behind(): void
    {
        Client::$replies = [$this->reply()];

        $this->press($this->button(PingRequestButton::TYPE_EMERGENCY));

        // Only the request kept open for the member, and the list it's on.
        $this->assertSame([], array_values(preg_grep('/^discordpings:requests:(details:[0-9a-f]{40}|open:7)$/', array_keys(Cache::$store), PREG_GREP_INVERT)));
        $this->assertSame([], RateLimiter::$hits);
    }

    public function test_a_request_is_held_by_its_cooldown_and_says_when(): void
    {
        $button = $this->button(PingRequestButton::TYPE_REQUEST);
        Client::$replies = [$this->reply()];

        $this->assertTrue($this->press($button)['ok']);
        $second = $this->press($button);

        $this->assertFalse($second['ok']);
        $this->assertSame('blocked', $second['status']);
        $this->assertStringContainsString('15 minutes', $second['message']);
        $this->assertGreaterThan(0, $second['retry_in']);
        $this->assertCount(1, Client::$posts);
    }

    public function test_a_blocked_press_is_written_down(): void
    {
        $button = $this->button(PingRequestButton::TYPE_REQUEST);
        Client::$replies = [$this->reply()];

        $this->press($button);
        $this->press($button);

        $this->assertSame(['sent', 'blocked'], array_column($this->history(), 'status'));
    }

    public function test_one_members_cooldown_does_not_touch_another(): void
    {
        $button = $this->button(PingRequestButton::TYPE_REQUEST);
        Client::$replies = [$this->reply(), $this->reply()];

        $this->press($button);
        $this->user = (object) ['id' => 8, 'name' => 'Someone Else', 'main_character_id' => 90000002];

        $this->assertTrue($this->press($button)['ok']);
    }

    public function test_a_request_that_did_not_arrive_can_be_pressed_again_at_once(): void
    {
        $button = $this->button(PingRequestButton::TYPE_REQUEST);
        Client::$replies = [$this->reply(500, ['message' => 'Server Error']), $this->reply()];

        $this->assertFalse($this->press($button)['ok']);
        $this->assertTrue($this->press($button)['ok']);
        $this->assertCount(2, Client::$posts);
    }

    public function test_a_request_never_touches_the_fc_counters(): void
    {
        Client::$replies = [$this->reply()];

        $this->press($this->button(PingRequestButton::TYPE_REQUEST));

        $this->assertArrayNotHasKey('discordpings:min:3', RateLimiter::$hits);
        $this->assertArrayNotHasKey('discordpings:hour:3', RateLimiter::$hits);
        $this->assertSame(1, RateLimiter::$hits['discordpings:requests:min:3']);
    }

    public function test_a_full_request_counter_holds_it_back_and_costs_no_cooldown(): void
    {
        RateLimiter::$hits = ['discordpings:requests:min:3' => 10];

        $result = $this->press($this->button(PingRequestButton::TYPE_REQUEST));

        $this->assertSame('rate_limited', $result['status']);
        $this->assertStringContainsString('42 seconds', $result['message']);
        $this->assertSame([], Client::$posts);
        $this->assertSame([], Cache::$store);
    }

    public function test_it_asks_discord_to_confirm_the_message_was_saved(): void
    {
        Client::$replies = [$this->reply()];

        $this->press($this->button(PingRequestButton::TYPE_EMERGENCY));

        $this->assertSame(self::ADDRESS . '?wait=true', Client::$posts[0][0]);
    }

    public function test_an_address_that_already_has_a_thread_keeps_it(): void
    {
        $button = $this->button(PingRequestButton::TYPE_EMERGENCY, ['webhook' => $this->webhook(['webhook_url' => self::ADDRESS . '?thread_id=9'])]);
        Client::$replies = [$this->reply()];

        $this->press($button);

        $this->assertSame(self::ADDRESS . '?thread_id=9&wait=true', Client::$posts[0][0]);
    }

    public function test_the_webhook_address_never_reaches_the_member_or_history(): void
    {
        Client::$replies = [new \RuntimeException('cURL error 28 for ' . self::ADDRESS . '?wait=true')];

        $result = $this->press($this->button(PingRequestButton::TYPE_EMERGENCY));

        $this->assertFalse($result['ok']);
        $this->assertStringContainsString('Not delivered', $result['message']);
        $this->assertStringNotContainsString(self::TOKEN, json_encode($result));
        $this->assertStringNotContainsString(self::TOKEN, json_encode($this->history()));
    }

    public function test_a_failure_saying_nothing_useful_still_tells_the_member(): void
    {
        Client::$replies = [$this->reply(404, ['message' => 'Unknown Webhook', 'code' => 10015])];

        $result = $this->press($this->button(PingRequestButton::TYPE_EMERGENCY));

        $this->assertFalse($result['ok']);
        $this->assertStringNotContainsString('Unknown Webhook', $result['message']);
        $this->assertSame('Discord answered 404: Unknown Webhook', $this->history()[0]['error_message']);
    }

    public function test_a_short_limit_from_discord_is_waited_out_once(): void
    {
        Client::$replies = [$this->reply(429, ['retry_after' => 0.01], ['Retry-After' => ['0.01']]), $this->reply()];

        $this->assertTrue($this->press($this->button(PingRequestButton::TYPE_EMERGENCY))['ok']);
        $this->assertCount(2, Client::$posts);
    }

    public function test_a_long_limit_from_discord_is_reported_with_the_wait(): void
    {
        Client::$replies = [$this->reply(429, ['retry_after' => 30])];

        $result = $this->press($this->button(PingRequestButton::TYPE_EMERGENCY));

        $this->assertFalse($result['ok']);
        $this->assertSame(30, $result['retry_in']);
        $this->assertStringContainsString('30 seconds', $result['message']);
        $this->assertCount(1, Client::$posts);
    }

    public function test_a_history_failure_does_not_undo_a_delivered_press(): void
    {
        Model::$failCreateWith = new \RuntimeException('database has gone away');
        Client::$replies = [$this->reply()];

        $this->assertTrue($this->press($this->button(PingRequestButton::TYPE_EMERGENCY))['ok']);
    }

    public function test_no_webhook_means_not_delivered_and_no_post(): void
    {
        $result = $this->press($this->button(PingRequestButton::TYPE_EMERGENCY, ['webhook' => null]));

        $this->assertFalse($result['ok']);
        $this->assertSame('not_configured', $result['status']);
        $this->assertSame([], Client::$posts);
    }

    public function test_a_switched_off_webhook_gets_no_post(): void
    {
        $result = $this->press($this->button(PingRequestButton::TYPE_EMERGENCY, ['webhook' => $this->webhook(['is_active' => false])]));

        $this->assertSame('not_configured', $result['status']);
        $this->assertSame([], Client::$posts);
    }

    public function test_history_says_it_was_a_member_and_who(): void
    {
        DB::$rows['character_infos'] = (object) ['name' => 'Matt Falahe'];
        DB::$rows['character_affiliations'] = (object) ['corporation' => 'Minc Corp', 'universe' => null];
        Client::$replies = [$this->reply()];

        $this->press($this->button(PingRequestButton::TYPE_EMERGENCY), [], 'Rorqual on Matt');

        $row = $this->history()[0];
        $this->assertSame('Matt Falahe', $row['user_name']);
        $this->assertSame(7, $row['user_id']);
        $this->assertSame('999', $row['discord_message_id']);
        $this->assertSame([
            'source' => PingHistory::SOURCE_MEMBER_REQUEST,
            'button_id' => 11,
            'button' => "Help, I'm tackled",
            'type' => PingRequestButton::TYPE_EMERGENCY,
            'saved_form' => 'Rorqual on Matt',
            'main' => 'Matt Falahe',
            'corporation' => 'Minc Corp',
        ], $row['fields']);
    }

    /** The history page prints each of these as text, so an array here is a crash. */
    public function test_history_fields_are_all_plain_values(): void
    {
        Client::$replies = [$this->reply()];

        $this->press($this->button(PingRequestButton::TYPE_EMERGENCY));

        foreach ($this->history()[0]['fields'] as $key => $value) {
            $this->assertIsScalar($value, $key);
        }
    }

    public function test_an_account_with_no_main_on_file_is_named_by_its_account(): void
    {
        Client::$replies = [$this->reply()];

        $this->press($this->button(PingRequestButton::TYPE_EMERGENCY));

        $this->assertSame('Account Name', $this->history()[0]['user_name']);
        $fields = array_column(Client::$posts[0][1]['json']['embeds'][0]['fields'], 'value', 'name');
        $this->assertSame('Account Name', $fields['Sent by']);
        $this->assertSame('Unknown corporation', $fields['Corporation']);
    }

    public function test_the_discord_line_comes_from_the_connector_when_it_is_there(): void
    {
        Schema::$tables = ['seat_connector_users'];
        DB::$rows['seat_connector_users'] = (object) ['connector_id' => '4242', 'connector_name' => 'matt'];
        Client::$replies = [$this->reply()];

        $this->press($this->button(PingRequestButton::TYPE_EMERGENCY));

        $fields = array_column(Client::$posts[0][1]['json']['embeds'][0]['fields'], 'value', 'name');
        $this->assertSame('<@4242> (matt)', $fields['Discord']);
    }

    public function test_emergencies_are_red_and_requests_take_the_webhook_colour(): void
    {
        Client::$replies = [$this->reply(), $this->reply()];

        $this->press($this->button(PingRequestButton::TYPE_EMERGENCY));
        $this->press($this->button(PingRequestButton::TYPE_REQUEST));

        $this->assertSame(0xDC3545, Client::$posts[0][1]['json']['embeds'][0]['color']);
        $this->assertSame(0x5865F2, Client::$posts[1][1]['json']['embeds'][0]['color']);
    }

    public function test_an_emergency_that_asks_after_the_press_stays_open_for_ten_minutes(): void
    {
        Client::$replies = [$this->reply()];

        $result = $this->press($this->tackled(), [], 'Main');
        $token = $result['active']['token'];

        $this->assertTrue($result['ok']);
        $this->assertStringContainsString('Active requests', $result['message']);
        $this->assertMatchesRegularExpression('/^[0-9a-f]{40}$/', $token);
        $this->assertEqualsWithDelta(600, Cache::$seconds['discordpings:requests:details:' . $token], 2);
        $this->assertEqualsWithDelta(600, $result['active']['closesIn'], 2);
        // Still no cooldown and no counters, only the request and the list it's on.
        $this->assertSame(['discordpings:requests:details:' . $token, 'discordpings:requests:open:7'], array_keys(Cache::$store));
        $this->assertSame([], RateLimiter::$hits);
    }

    public function test_with_no_saved_form_every_section_waits_to_be_filled_in(): void
    {
        Client::$replies = [$this->reply()];

        $active = $this->press($this->tackled())['active'];

        $this->assertSame(array_values($this->keys()), array_column($active['lines'], 'key'));
        $this->assertFalse($active['freeText']);
        $this->assertNull($active['savedForm']);
    }

    public function test_from_a_saved_form_only_what_comes_after_the_press_is_asked(): void
    {
        $keys = $this->keys();
        Client::$replies = [$this->reply()];

        $active = $this->press($this->tackled(), [$keys['Tackled Pilot Name'] => 'Some Pilot'], 'Main')['active'];

        $this->assertSame([$keys['Panic Active?'], $keys['Cyno Inhibitor active?'], $keys['Grid Dscan']], array_column($active['lines'], 'key'));
        $this->assertSame('Main', $active['savedForm']);
    }

    public function test_from_a_saved_form_with_nothing_asked_after_nothing_stays_open(): void
    {
        Client::$replies = [$this->reply()];

        $result = $this->press($this->button(PingRequestButton::TYPE_EMERGENCY, ['sections' => Sections::STARTING_POINTS['Mining fleet']]), [], 'Main');

        $this->assertTrue($result['ok']);
        $this->assertArrayNotHasKey('active', $result);
        $this->assertSame('Sent to Discord.', $result['message']);
        $this->assertSame([], Cache::$store);
    }

    public function test_a_plain_emergency_sent_with_no_form_gets_a_box_for_a_message(): void
    {
        Model::$found[DiscordWebhook::class][3] = $this->webhook();
        Client::$replies = [$this->reply(), $this->reply()];

        $active = $this->press($this->button(PingRequestButton::TYPE_EMERGENCY))['active'];
        $this->addDetails($active['token'], [Sections::FREE_TEXT => "5 Loki on grid\n2 Sabre"]);

        $this->assertTrue($active['freeText']);
        $this->assertSame([], $active['lines']);
        $this->assertSame("Rorqual Tackled\n\n5 Loki on grid\n2 Sabre", $this->editedDescription());
    }

    public function test_a_request_never_stays_open(): void
    {
        Client::$replies = [$this->reply()];

        $result = $this->press($this->tackled(PingRequestButton::TYPE_REQUEST));

        $this->assertArrayNotHasKey('active', $result);
        $this->assertSame([], preg_grep('/:(details|open):/', array_keys(Cache::$store)));
        $this->assertNotContains(RequestMessage::LIVE, array_column(Client::$posts[0][1]['json']['embeds'][0]['fields'], 'name'));
    }

    public function test_an_emergency_that_never_arrived_has_nothing_to_add_to(): void
    {
        Client::$replies = [$this->reply(500, ['message' => 'Server Error'])];

        $result = $this->press($this->tackled());

        $this->assertFalse($result['ok']);
        $this->assertArrayNotHasKey('active', $result);
        $this->assertSame([], Cache::$store);
    }

    /** Discord's own time format, so every reader sees their local time and a countdown that keeps itself current. */
    public function test_the_message_says_until_when_it_can_change(): void
    {
        Client::$replies = [$this->reply()];

        $active = $this->press($this->tackled())['active'];
        $closesAt = time() + $active['closesIn'];
        $live = Client::$posts[0][1]['json']['embeds'][0]['fields'][0];

        $this->assertSame(RequestMessage::LIVE, $live['name']);
        $this->assertFalse($live['inline']);
        $this->assertMatchesRegularExpression('/^Until <t:(\d+):t> \(<t:\1:R>\)$/', $live['value']);
        $this->assertEqualsWithDelta($closesAt, (int) preg_replace('/\D.*$/', '', substr($live['value'], 9)), 2);
    }

    public function test_from_a_saved_form_with_nothing_to_add_the_message_has_no_countdown(): void
    {
        Client::$replies = [$this->reply()];

        $this->press($this->button(PingRequestButton::TYPE_EMERGENCY, ['sections' => Sections::STARTING_POINTS['Mining fleet']]), [], 'Main');

        $this->assertSame(['Sent by', 'Corporation'], array_column(Client::$posts[0][1]['json']['embeds'][0]['fields'], 'name'));
    }

    public function test_on_discord_section_names_are_bold_and_history_keeps_plain_text(): void
    {
        $keys = $this->keys();
        Client::$replies = [$this->reply()];

        $this->press($this->tackled(), [$keys['Tackled Pilot Name'] => 'Some Pilot', $keys['Ship Tackled'] => 'Rorqual'], 'Main');

        $this->assertSame("**Tackled Pilot Name:** Some Pilot\n\n**Ship Tackled:** Rorqual", Client::$posts[0][1]['json']['embeds'][0]['description']);
        $this->assertSame("Tackled Pilot Name: Some Pilot\nShip Tackled: Rorqual", $this->history()[0]['message']);
    }

    public function test_an_answer_edits_the_message_that_went_out(): void
    {
        $token = $this->pressTackled();
        Client::$replies = [$this->reply()];

        $result = $this->addDetails($token, [$this->keys()['Panic Active?'] => 'Yes']);

        $this->assertTrue($result['ok']);
        $this->assertSame('updated', $result['status']);
        $this->assertCount(1, Client::$posts);
        $this->assertCount(1, Client::$patches);
        $this->assertSame(self::ADDRESS . '/messages/999', Client::$patches[0][0]);
        $this->assertSame('**Panic Active?** Yes', $this->editedDescription());
    }

    /** No content and no allowed_mentions, so Discord keeps the role line as it was and pings nobody. */
    public function test_the_edit_changes_the_embed_and_nothing_else(): void
    {
        $keys = $this->keys();
        $token = $this->pressTackled([$keys['Tackled Pilot Name'] => 'Some Pilot'], null, 'Main');
        Client::$replies = [$this->reply()];

        $this->addDetails($token, [$keys['Panic Active?'] => 'Yes']);

        $edit = Client::$patches[0][1]['json'];
        $this->assertSame(['embeds'], array_keys($edit));

        $sent = Client::$posts[0][1]['json']['embeds'][0];
        $edited = $edit['embeds'][0];
        $this->assertSame("**Tackled Pilot Name:** Some Pilot\n\n**Panic Active?** Yes", $edited['description']);

        unset($sent['description'], $edited['description']);
        $this->assertSame($sent, $edited);
    }

    public function test_each_update_replaces_the_one_before(): void
    {
        $keys = $this->keys();
        $token = $this->pressTackled();
        Client::$replies = [$this->reply(), $this->reply(), $this->reply()];

        $this->addDetails($token, [$keys['Panic Active?'] => 'Yes']);
        $this->addDetails($token, [$keys['Panic Active?'] => 'No', $keys['Cyno Inhibitor active?'] => 'Yes']);
        $this->addDetails($token, [$keys['Panic Active?'] => '', $keys['Cyno Inhibitor active?'] => 'Yes', $keys['Grid Dscan'] => "3 Loki\n2 Sabre"]);

        $this->assertSame("**Panic Active?** No\n\n**Cyno Inhibitor active?** Yes", $this->editedDescription(1));
        $this->assertSame("**Cyno Inhibitor active?** Yes\n\n**Grid Dscan:**\n3 Loki\n2 Sabre", $this->editedDescription(2));
    }

    public function test_from_a_saved_form_only_what_is_asked_after_the_press_can_be_changed(): void
    {
        $keys = $this->keys();
        $token = $this->pressTackled([$keys['Tackled Pilot Name'] => 'Some Pilot'], null, 'Main');
        Client::$replies = [$this->reply()];

        $this->addDetails($token, [$keys['Tackled Pilot Name'] => 'Someone Else', 'qdeadbeef0000' => 'stray', $keys['Panic Active?'] => 'Yes']);

        $this->assertSame("**Tackled Pilot Name:** Some Pilot\n\n**Panic Active?** Yes", $this->editedDescription());
    }

    public function test_with_no_saved_form_every_section_can_be_filled_in_after(): void
    {
        $keys = $this->keys();
        $token = $this->pressTackled();
        Client::$replies = [$this->reply()];

        $this->addDetails($token, [$keys['Tackled Pilot Name'] => 'Some Pilot', 'qdeadbeef0000' => 'stray', $keys['Panic Active?'] => 'Yes']);

        $this->assertSame("**Tackled Pilot Name:** Some Pilot\n\n**Panic Active?** Yes", $this->editedDescription());
    }

    public function test_pressing_again_keeps_the_first_one_open(): void
    {
        $keys = $this->keys();
        $first = $this->pressTackled();
        $second = $this->pressTackled();
        Client::$replies = [$this->reply(), $this->reply()];

        $this->assertTrue($this->addDetails($first, [$keys['Panic Active?'] => 'Yes'])['ok']);
        $this->assertTrue($this->addDetails($second, [$keys['Panic Active?'] => 'No'])['ok']);
        $this->assertCount(2, Client::$posts);
        $this->assertEqualsCanonicalizing([$first, $second], array_column($this->openFor(), 'token'));
    }

    public function test_the_open_ones_come_back_after_a_reload_with_their_answers(): void
    {
        $keys = $this->keys();
        $first = $this->pressTackled();
        $second = $this->pressTackled([], null, 'Main');
        Cache::$store['discordpings:requests:details:' . $first]['sent_at'] -= 60;
        Client::$replies = [$this->reply()];

        $this->addDetails($first, [$keys['Panic Active?'] => 'Yes', $keys['Ship Tackled'] => 'Rorqual']);
        $open = $this->openFor();

        $this->assertSame([$second, $first], array_column($open, 'token'));
        $this->assertSame("Help, I'm tackled", $open[1]['button']);
        $this->assertSame([$keys['Panic Active?'] => 'Yes', $keys['Ship Tackled'] => 'Rorqual'], array_filter((array) $open[1]['answers']));
        $this->assertCount(7, $open[1]['lines']);
        $this->assertCount(3, $open[0]['lines']);
    }

    public function test_presses_in_the_same_second_still_come_back_newest_first(): void
    {
        $first = $this->pressTackled();
        $second = $this->pressTackled();
        $third = $this->pressTackled();

        foreach ([$first, $second, $third] as $token) {
            Cache::$store['discordpings:requests:details:' . $token]['sent_at'] = 1762603200;
        }

        $this->assertSame([$third, $second, $first], array_column($this->openFor(), 'token'));
    }

    public function test_nobody_else_sees_the_open_ones(): void
    {
        $this->pressTackled();

        $this->assertSame([], $this->openFor($this->someoneElse()));
    }

    public function test_an_answer_does_not_start_the_ten_minutes_again(): void
    {
        $token = $this->pressTackled();
        $this->closesIn($token, 120);
        Client::$replies = [$this->reply()];

        $this->addDetails($token, [$this->keys()['Panic Active?'] => 'Yes']);

        $this->assertEqualsWithDelta(120, Cache::$seconds['discordpings:requests:details:' . $token], 2);
        $this->assertEqualsWithDelta(120, $this->openFor()[0]['closesIn'], 2);
    }

    public function test_once_its_time_is_up_it_can_neither_be_added_to_nor_listed(): void
    {
        $token = $this->pressTackled();
        $this->closesIn($token, -1);

        $this->assertSame('expired', $this->addDetails($token, [$this->keys()['Panic Active?'] => 'Yes'])['status']);
        $this->assertSame([], $this->openFor());
        $this->assertSame([], Client::$patches);
    }

    public function test_closing_tells_discord_and_stops_any_more_updates(): void
    {
        $keys = $this->keys();
        $token = $this->pressTackled();
        Client::$replies = [$this->reply(), $this->reply()];

        $this->addDetails($token, [$keys['Panic Active?'] => 'Yes']);
        $result = $this->close($token);

        $this->assertTrue($result['ok']);
        $this->assertSame('closed', $result['status']);
        $closed = Client::$patches[1][1]['json'];
        $this->assertSame(['embeds'], array_keys($closed));
        $this->assertSame('**Panic Active?** Yes', $closed['embeds'][0]['description']);
        $this->assertMatchesRegularExpression('/^Closed by the sender at <t:\d+:t>$/', $closed['embeds'][0]['fields'][0]['value']);

        $this->assertSame('expired', $this->addDetails($token, [$keys['Panic Active?'] => 'No'])['status']);
        $this->assertSame([], $this->openFor());
        $this->assertArrayNotHasKey('discordpings:requests:open:7', Cache::$store);
        $this->assertCount(2, Client::$patches);
    }

    public function test_closing_one_leaves_the_others_open(): void
    {
        $first = $this->pressTackled();
        $second = $this->pressTackled();
        Client::$replies = [$this->reply()];

        $this->close($first);

        $this->assertSame([$second], array_column($this->openFor(), 'token'));
    }

    public function test_nobody_else_can_close_it(): void
    {
        $token = $this->pressTackled();

        $result = $this->close($token, $this->someoneElse());

        $this->assertSame('closed', $result['status']);
        $this->assertSame([], Client::$patches);
        $this->assertSame([$token], array_column($this->openFor(), 'token'));
    }

    public function test_closing_one_that_has_already_gone_changes_nothing(): void
    {
        $result = $this->close(str_repeat('ab', 20));

        $this->assertTrue($result['ok']);
        $this->assertSame('closed', $result['status']);
        $this->assertSame([], Client::$patches);
    }

    public function test_a_close_discord_cannot_take_still_closes_it_here(): void
    {
        $token = $this->pressTackled();
        Client::$replies = [$this->reply(500, ['message' => 'Server Error'])];

        $result = $this->close($token);

        $this->assertTrue($result['ok']);
        $this->assertSame('closed_here', $result['status']);
        $this->assertSame('expired', $this->addDetails($token, [$this->keys()['Panic Active?'] => 'Yes'])['status']);
        $this->assertStringNotContainsString(self::TOKEN, implode("\n", Log::$lines));
    }

    public function test_a_list_that_cannot_be_written_still_hands_the_request_back(): void
    {
        Cache::$failLockWith = new \RuntimeException('Lock wait timeout');
        Client::$replies = [$this->reply()];

        $result = $this->press($this->tackled());

        $this->assertNotNull($result['active']);
        $this->assertStringContainsString('Could not list', implode("\n", Log::$lines));
        $this->assertSame([], $this->openFor());
    }

    public function test_only_the_buttons_own_answers_are_kept_for_the_details(): void
    {
        $keys = $this->keys();
        $token = $this->pressTackled([$keys['Ship Tackled'] => 'Rorqual', 'qdeadbeef0000' => str_repeat('x', 1000)]);

        $this->assertSame([$keys['Ship Tackled'] => 'Rorqual'], Cache::$store['discordpings:requests:details:' . $token]['answers']);
    }

    public function test_another_member_cannot_add_to_it(): void
    {
        $token = $this->pressTackled();
        $other = (object) ['id' => 8, 'name' => 'Someone Else', 'main_character_id' => 90000002];

        $result = $this->addDetails($token, [$this->keys()['Panic Active?'] => 'Yes'], $other);

        $this->assertFalse($result['ok']);
        $this->assertSame('expired', $result['status']);
        $this->assertSame([], Client::$patches);
    }

    /** @dataProvider unknownTokens */
    public function test_an_unknown_token_changes_nothing(string $token): void
    {
        $this->pressTackled();

        $result = $this->addDetails($token, [$this->keys()['Panic Active?'] => 'Yes']);

        $this->assertSame('expired', $result['status']);
        $this->assertSame([], Client::$patches);
    }

    public static function unknownTokens(): array
    {
        return [
            'never handed out' => [str_repeat('ab', 20)],
            'not a token' => ['../../etc'],
            'empty' => [''],
        ];
    }

    public function test_a_thread_webhook_names_its_thread_for_the_edit(): void
    {
        $token = $this->pressTackled([], $this->webhook(['webhook_url' => self::ADDRESS . '?thread_id=9']));
        Client::$replies = [$this->reply()];

        $this->addDetails($token, [$this->keys()['Panic Active?'] => 'Yes']);

        $this->assertSame(self::ADDRESS . '/messages/999?thread_id=9', Client::$patches[0][0]);
    }

    public function test_history_shows_what_discord_shows(): void
    {
        $token = $this->pressTackled();
        $historyId = array_key_first(Model::$found[PingHistory::class]);
        Client::$replies = [$this->reply()];

        $this->addDetails($token, [$this->keys()['Panic Active?'] => 'Yes']);

        $this->assertSame([[PingHistory::class, $historyId, ['message' => 'Panic Active? Yes']]], Model::$updated);
    }

    public function test_clearing_every_answer_leaves_history_with_the_button_name(): void
    {
        $token = $this->pressTackled();
        Client::$replies = [$this->reply()];

        $this->addDetails($token, [$this->keys()['Panic Active?'] => '']);

        $this->assertArrayNotHasKey('description', Client::$patches[0][1]['json']['embeds'][0]);
        $this->assertSame(['message' => "Help, I'm tackled"], Model::$updated[0][2]);
    }

    public function test_updates_stop_once_a_message_has_had_its_share(): void
    {
        $token = $this->pressTackled();
        RateLimiter::$hits['discordpings:requests:detail-updates:' . $token] = 50;

        $result = $this->addDetails($token, [$this->keys()['Panic Active?'] => 'Yes']);

        $this->assertSame('rate_limited', $result['status']);
        $this->assertSame([], Client::$patches);
    }

    /** The limiter keeps its count in the cache as well, and on the details' own key it would write over them. */
    public function test_the_update_count_has_a_key_of_its_own(): void
    {
        $token = $this->pressTackled();
        Client::$replies = [$this->reply()];

        $this->addDetails($token, [$this->keys()['Panic Active?'] => 'Yes']);

        $this->assertSame(1, RateLimiter::$hits['discordpings:requests:detail-updates:' . $token]);
        $this->assertArrayNotHasKey('discordpings:requests:details:' . $token, RateLimiter::$hits);
    }

    public function test_a_switched_off_webhook_gets_no_edit(): void
    {
        $token = $this->pressTackled();
        Model::$found[DiscordWebhook::class][3] = $this->webhook(['is_active' => false]);

        $result = $this->addDetails($token, [$this->keys()['Panic Active?'] => 'Yes']);

        $this->assertSame('not_configured', $result['status']);
        $this->assertSame([], Client::$patches);
    }

    public function test_a_failed_edit_says_the_message_is_as_it_was(): void
    {
        $token = $this->pressTackled();
        Client::$replies = [$this->reply(500, ['message' => 'Server Error'])];

        $result = $this->addDetails($token, [$this->keys()['Panic Active?'] => 'Yes']);

        $this->assertFalse($result['ok']);
        $this->assertSame('failed', $result['status']);
        $this->assertStringContainsString('still on Discord as it was', $result['message']);
        $this->assertSame([], Model::$updated);
    }

    public function test_a_message_gone_from_discord_closes_the_box(): void
    {
        $keys = $this->keys();
        $token = $this->pressTackled();
        Client::$replies = [$this->reply(404, ['message' => 'Unknown Message', 'code' => 10008])];

        $result = $this->addDetails($token, [$keys['Panic Active?'] => 'Yes']);
        $again = $this->addDetails($token, [$keys['Panic Active?'] => 'No']);

        $this->assertSame('expired', $result['status']);
        $this->assertStringContainsString("can't find this message", $result['message']);
        $this->assertSame('expired', $again['status']);
        $this->assertCount(1, Client::$patches);
    }

    public function test_a_long_limit_on_an_edit_says_when(): void
    {
        $token = $this->pressTackled();
        Client::$replies = [$this->reply(429, ['retry_after' => 30])];

        $result = $this->addDetails($token, [$this->keys()['Panic Active?'] => 'Yes']);

        $this->assertSame(30, $result['retry_in']);
        $this->assertStringContainsString('30 seconds', $result['message']);
    }

    public function test_the_webhook_address_stays_out_of_an_edit_that_fails(): void
    {
        $token = $this->pressTackled();
        Client::$replies = [new \RuntimeException('cURL error 28 for ' . self::ADDRESS . '/messages/999')];

        $result = $this->addDetails($token, [$this->keys()['Panic Active?'] => 'Yes']);

        $this->assertFalse($result['ok']);
        $this->assertStringNotContainsString(self::TOKEN, json_encode($result));
        $this->assertStringNotContainsString(self::TOKEN, implode("\n", Log::$lines));
    }

    public function test_the_details_token_is_never_written_down(): void
    {
        $token = $this->pressTackled();

        $this->assertStringNotContainsString($token, json_encode($this->history()));
        $this->assertStringNotContainsString($token, implode("\n", Log::$lines));
    }

    public function test_a_cache_that_cannot_keep_the_details_does_not_undo_the_press(): void
    {
        Cache::$failPutWith = new \RuntimeException('Redis has gone away');
        Client::$replies = [$this->reply()];

        $result = $this->press($this->tackled());

        $this->assertTrue($result['ok']);
        $this->assertNull($result['active']);
        // Nothing to point the member at, so it doesn't.
        $this->assertSame('Sent to Discord.', $result['message']);
        $this->assertStringContainsString('Could not keep', implode("\n", Log::$lines));
    }
}
