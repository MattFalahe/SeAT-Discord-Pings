<?php

namespace DiscordPings\Tests\Unit;

use DiscordPings\Models\DiscordWebhook;
use DiscordPings\Models\PingHistory;
use DiscordPings\Models\PingRequestButton;
use DiscordPings\Services\PingRequests\PingRequestSender;
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
        Cache::$store = [];
        RateLimiter::$hits = [];
        Log::$lines = [];
        Schema::$tables = [];
        DB::$rows = [];
        Client::$posts = [];
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

        $this->assertSame([], Cache::$store);
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
}
