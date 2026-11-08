<?php

namespace DiscordPings\Tests\Unit;

use DiscordPings\Services\PingRequests\ErrorText;
use PHPUnit\Framework\TestCase;

class ErrorTextTest extends TestCase
{
    private const TOKEN = 'aBcD-eFgH_iJkLmNoPqRsTuV';

    /** @dataProvider messagesWithAnAddress */
    public function test_no_token_survives(string $message): void
    {
        $safe = ErrorText::safe($message);

        $this->assertStringNotContainsString(self::TOKEN, $safe);
        $this->assertStringContainsString('[webhook address removed]', $safe);
    }

    public static function messagesWithAnAddress(): array
    {
        return [
            'what guzzle says' => ['Client error: `POST https://discord.com/api/webhooks/123456/' . self::TOKEN . '?wait=true` resulted in a `404 Not Found` response'],
            'curl timeout' => ['cURL error 28: Operation timed out (see https://curl.se/libcurl/c/libcurl-errors.html) for https://discord.com/api/webhooks/123456/' . self::TOKEN],
            'older host' => ['failed on https://discordapp.com/api/webhooks/123456/' . self::TOKEN],
            'versioned api' => ['failed on https://discord.com/api/v10/webhooks/123456/' . self::TOKEN],
            'canary' => ['failed on https://canary.discord.com/api/webhooks/123456/' . self::TOKEN],
            'just the path' => ['POST /api/webhooks/123456/' . self::TOKEN . ' failed'],
            'with a thread' => ['failed on https://discord.com/api/webhooks/123456/' . self::TOKEN . '?thread_id=99&wait=true'],
        ];
    }

    public function test_what_is_around_the_address_stays(): void
    {
        $this->assertSame(
            'cURL error 28 for [webhook address removed] after 8 seconds',
            ErrorText::safe('cURL error 28 for https://discord.com/api/webhooks/1/' . self::TOKEN . ' after 8 seconds')
        );
    }

    public function test_text_with_no_address_is_left_alone(): void
    {
        $this->assertSame('Discord answered 404: Unknown Webhook', ErrorText::safe('Discord answered 404: Unknown Webhook'));
    }
}
