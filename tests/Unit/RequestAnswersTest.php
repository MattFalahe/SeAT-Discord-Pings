<?php

namespace DiscordPings\Tests\Unit;

use DiscordPings\Models\DiscordWebhook;
use DiscordPings\Models\PingHistory;
use DiscordPings\Models\PingRequestAnswer;
use DiscordPings\Services\PingRequests\RequestAnswers;
use DiscordPings\Services\PingRequests\RequestMessage;
use GuzzleHttp\Client;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use PHPUnit\Framework\TestCase;

class RequestAnswersTest extends TestCase
{
    private const ADDRESS = 'https://discord.com/api/webhooks/123456/aBcD';

    protected function setUp(): void
    {
        Model::$created = [];
        Model::$failCreateWith = null;
        Model::$found = [];
        Model::$updated = [];
        Model::$failQueryWith = null;
        Cache::$store = [];
        Cache::$failLockWith = null;
        Log::$lines = [];
        DB::$rows = [];
        Client::$posts = [];
        Client::$patches = [];
        Client::$gets = [];
        Client::$replies = [];
        $GLOBALS['FAKE_ROUTES'] = [];
    }

    private function reply(int $status = 200, array $body = ['id' => '999']): object
    {
        return new class($status, $body) {
            public function __construct(private int $status, private array $body)
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
                return [];
            }
        };
    }

    /** A member's request that reached Discord, on a webhook that's still there. */
    private function request(array $overrides = []): PingHistory
    {
        Model::$found[DiscordWebhook::class][3] = new DiscordWebhook(['id' => 3, 'webhook_url' => self::ADDRESS, 'is_active' => true]);

        $request = new PingHistory($overrides + [
            'id' => 50,
            'webhook_id' => 3,
            'status' => 'sent',
            'discord_message_id' => '999',
            'fields' => ['source' => PingHistory::SOURCE_MEMBER_REQUEST, 'button' => 'Mining fleet'],
        ]);
        Model::$found[PingHistory::class][$request->id] = $request;

        return $request;
    }

    /** Discord's copy of the request's message, read back before the edit. */
    private function discordCopy(): object
    {
        return $this->reply(200, ['id' => '999', 'embeds' => [[
            'type' => 'rich',
            'title' => 'Mining fleet',
            'description' => 'Belt 4',
            'fields' => [['name' => 'Sent by', 'value' => 'Ore Hauler', 'inline' => true]],
        ]]]);
    }

    /** @return array<int, array> the answer rows written, oldest first */
    private function answers(): array
    {
        return array_column(array_filter(Model::$created, fn ($row) => $row[0] === PingRequestAnswer::class), 1);
    }

    private function fcAccount(int $id = 8): object
    {
        return (object) ['id' => $id, 'name' => 'Account ' . $id];
    }

    public function test_a_broadcast_answering_a_request_is_recorded_and_said_on_discord(): void
    {
        $this->request();
        Client::$replies = [$this->discordCopy(), $this->reply()];

        RequestAnswers::afterBroadcast(['answers_request' => '50', 'fc_name' => 'Fleet Boss'], $this->fcAccount(), (object) ['id' => 77]);

        $this->assertSame([[
            'request_history_id' => 50,
            'user_id' => 8,
            'fc_name' => 'Fleet Boss',
            'broadcast_history_id' => 77,
        ]], $this->answers());

        $fields = array_column(Client::$patches[0][1]['json']['embeds'][0]['fields'], 'value', 'name');
        $this->assertStringStartsWith('Fleet Boss at <t:', $fields[RequestMessage::ANSWERED]);
    }

    public function test_a_broadcast_answering_nothing_does_nothing(): void
    {
        $this->request();

        RequestAnswers::afterBroadcast(['fc_name' => 'Fleet Boss'], $this->fcAccount());
        RequestAnswers::afterBroadcast(['answers_request' => 'abc'], $this->fcAccount());

        $this->assertSame([], $this->answers());
        $this->assertSame([], Client::$gets);
    }

    /** Only a member's request that reached Discord can be answered, whatever the id says. */
    public function test_only_a_delivered_member_request_counts(): void
    {
        $this->request(['id' => 51, 'status' => 'blocked']);
        $this->request(['id' => 52, 'fields' => ['button' => 'An FC broadcast']]);

        foreach ([51, 52, 53] as $id) {
            RequestAnswers::afterBroadcast(['answers_request' => $id, 'fc_name' => 'Fleet Boss'], $this->fcAccount());
        }

        $this->assertSame([], $this->answers());
    }

    /** The same broadcast to several webhooks, or a second one from the same FC, names them once and edits once. */
    public function test_an_fc_is_named_once_however_many_times_they_answer(): void
    {
        $this->request();
        Client::$replies = [$this->discordCopy(), $this->reply()];

        RequestAnswers::afterBroadcast(['answers_request' => 50, 'fc_name' => 'Fleet Boss'], $this->fcAccount(), (object) ['id' => 77]);
        RequestAnswers::afterBroadcast(['answers_request' => 50, 'fc_name' => 'Fleet Boss'], $this->fcAccount(), (object) ['id' => 78]);

        $this->assertCount(1, $this->answers());
        $this->assertCount(1, Client::$patches);
    }

    public function test_a_second_fc_is_added_under_the_first(): void
    {
        $this->request();
        Client::$replies = [$this->discordCopy(), $this->reply(), $this->discordCopy(), $this->reply()];

        RequestAnswers::afterBroadcast(['answers_request' => 50, 'fc_name' => 'Fleet Boss'], $this->fcAccount(8));
        RequestAnswers::afterBroadcast(['answers_request' => 50, 'fc_name' => 'Second FC'], $this->fcAccount(9));

        $fields = array_column(Client::$patches[1][1]['json']['embeds'][0]['fields'], 'value', 'name');
        $this->assertMatchesRegularExpression('/^Fleet Boss at .*\nSecond FC at /', $fields[RequestMessage::ANSWERED]);
    }

    /** A scheduled broadcast with no FC name runs as the scheduler, so the account's main says who it was. */
    public function test_with_no_fc_name_the_accounts_main_is_named(): void
    {
        $this->request();
        DB::$rows['users'] = (object) ['main_character_id' => 90000001];
        DB::$rows['character_infos'] = (object) ['name' => 'Main Pilot'];
        Client::$replies = [$this->discordCopy(), $this->reply()];

        RequestAnswers::afterBroadcast(['answers_request' => 50, 'fc_name' => '  '], (object) ['id' => 8, 'name' => 'Scheduled Ping']);

        $this->assertSame('Main Pilot', $this->answers()[0]['fc_name']);
    }

    /** The broadcast is already on Discord, so nothing that goes wrong here may reach whoever sent it. */
    public function test_a_failure_is_logged_and_never_thrown(): void
    {
        $this->request();
        Model::$failCreateWith = new \RuntimeException('Table is missing');

        RequestAnswers::afterBroadcast(['answers_request' => 50, 'fc_name' => 'Fleet Boss'], $this->fcAccount());

        $this->assertSame([], Client::$patches);
        $this->assertStringContainsString('Could not record who answered', implode("\n", Log::$lines));
    }

    public function test_answers_come_back_by_request_first_one_first(): void
    {
        foreach ([[2, 60, 'Later', 1762604000], [1, 60, 'First', 1762603800], [3, 61, 'Other', 1762603900]] as [$id, $requestId, $name, $at]) {
            Model::$found[PingRequestAnswer::class][$id] = new PingRequestAnswer(['id' => $id, 'request_history_id' => $requestId, 'fc_name' => $name, 'created_at' => $at, 'broadcast_history_id' => null]);
        }

        $answers = RequestAnswers::forRequests([60, '61', 62, 0]);

        $this->assertSame(['First', 'Later'], array_column($answers[60], 'name'));
        $this->assertSame([['name' => 'Other', 'at' => 1762603900, 'broadcast_id' => null]], $answers[61]);
        $this->assertArrayNotHasKey(62, $answers);
    }

    /** Null and not empty, so a page can say it couldn't check rather than that nobody answered. */
    public function test_answers_that_cannot_be_read_come_back_as_null(): void
    {
        Model::$failQueryWith = new \RuntimeException('Gone away');

        $this->assertNull(RequestAnswers::forRequests([60]));
        $this->assertSame([], RequestAnswers::forRequests([]));
    }

    public function test_when_is_read_from_whatever_the_row_holds(): void
    {
        $rows = [
            (object) ['request_history_id' => 1, 'fc_name' => 'A', 'created_at' => new \DateTimeImmutable('@1762603800'), 'broadcast_history_id' => 9],
            (object) ['request_history_id' => 1, 'fc_name' => 'B', 'created_at' => '2025-11-08 12:10:00', 'broadcast_history_id' => 0],
        ];

        $grouped = RequestAnswers::grouped($rows);

        $this->assertSame(1762603800, $grouped[1][0]['at']);
        $this->assertSame(9, $grouped[1][0]['broadcast_id']);
        $this->assertSame(strtotime('2025-11-08 12:10:00'), $grouped[1][1]['at']);
        $this->assertNull($grouped[1][1]['broadcast_id']);
    }

    public function test_a_page_gets_the_names_and_a_time_the_browser_reads(): void
    {
        $this->assertSame(
            [['name' => 'Fleet Boss', 'at' => '2025-11-08T12:10:00Z']],
            RequestAnswers::forPage([3 => ['name' => 'Fleet Boss', 'at' => 1762603800, 'broadcast_id' => 9]])
        );
    }
}
