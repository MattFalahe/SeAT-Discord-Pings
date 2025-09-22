<?php

namespace MattFalahe\Seat\DiscordPings\Helpers;

use GuzzleHttp\Client;
use GuzzleHttp\Exception\RequestException;
use MattFalahe\Seat\DiscordPings\Models\PingHistory;
use Carbon\Carbon;

class DiscordHelper
{
    private $client;

    public function __construct()
    {
        $this->client = new Client([
            'timeout' => 10,
            'verify' => false,
        ]);
    }

    /**
     * Send a ping to Discord
     */
    public function sendPing($webhook, $data, $user)
    {
        try {
            // Build the payload
            $payload = $this->buildPayload($data, $user, $webhook);
            
            // Send to Discord
            $response = $this->client->post($webhook->webhook_url, [
                'json' => $payload
            ]);

            // Log to history
            $history = PingHistory::create([
                'webhook_id' => $webhook->id,
                'user_id' => $user->id,
                'user_name' => $user->name,
                'message' => $data['message'],
                'fields' => $this->extractFields($data),
                'webhook_response' => json_decode($response->getBody(), true),
                'status' => 'sent',
                'discord_message_id' => $response->getHeader('X-Message-Id')[0] ?? null,
            ]);

            return ['success' => true, 'history_id' => $history->id];

        } catch (RequestException $e) {
            // Log failure
            PingHistory::create([
                'webhook_id' => $webhook->id,
                'user_id' => $user->id,
                'user_name' => $user->name,
                'message' => $data['message'],
                'fields' => $this->extractFields($data),
                'status' => 'failed',
                'error_message' => $e->getMessage(),
            ]);

            return ['success' => false, 'error' => $e->getMessage()];
        }
    }

    /**
     * Build Discord payload
     */
    private function buildPayload($data, $user, $webhook)
    {
        $payload = [];

        // Add mention if needed
        if (isset($data['mention_type']) && $data['mention_type'] !== 'none') {
            switch ($data['mention_type']) {
                case 'everyone':
                    $payload['content'] = '@everyone';
                    break;
                case 'here':
                    $payload['content'] = '@here';
                    break;
                case 'custom':
                    $payload['content'] = $data['custom_mention'] ?? '';
                    break;
            }
        }

        // Build embed
        $embed = [
            'title' => '📢 Fleet Broadcast',
            'description' => $data['message'],
            'color' => hexdec(str_replace('#', '', $data['embed_color'] ?? $webhook->embed_color)),
            'fields' => [],
            'footer' => [
                'text' => sprintf(
                    'This was a coord broadcast from %s to discord at %s EVE',
                    $user->name,
                    Carbon::now()->format('Y-m-d H:i:s.u')
                ),
                'icon_url' => config('discord-pings.discord.default_avatar')
            ],
            'timestamp' => Carbon::now()->toIso8601String()
        ];

        // Add optional fields
        $fieldMappings = [
            'fc_name' => ['name' => '👤 FC Name', 'inline' => true],
            'formup_location' => ['name' => '📍 Formup Location', 'inline' => true],
            'pap_type' => ['name' => '🎯 PAP Type', 'inline' => true],
            'comms' => ['name' => '🎧 Comms', 'inline' => false],
            'doctrine' => ['name' => '🚀 Doctrine', 'inline' => false],
        ];

        foreach ($fieldMappings as $key => $field) {
            if (!empty($data[$key])) {
                $embed['fields'][] = [
                    'name' => $field['name'],
                    'value' => $data[$key],
                    'inline' => $field['inline']
                ];
            }
        }

        $payload['embeds'] = [$embed];

        // Add username and avatar
        $payload['username'] = config('discord-pings.discord.default_username');
        $payload['avatar_url'] = config('discord-pings.discord.default_avatar');

        return $payload;
    }

    /**
     * Extract fields from data
     */
    private function extractFields($data)
    {
        $fields = [];
        $fieldKeys = ['fc_name', 'formup_location', 'pap_type', 'comms', 'doctrine'];
        
        foreach ($fieldKeys as $key) {
            if (isset($data[$key]) && !empty($data[$key])) {
                $fields[$key] = $data[$key];
            }
        }
        
        return $fields;
    }

    /**
     * Test a webhook
     */
    public function testWebhook($webhook)
    {
        $testData = [
            'message' => 'This is a test ping from SeAT Discord Pings plugin.',
            'fc_name' => 'Test FC',
            'formup_location' => 'Test System',
            'pap_type' => 'Strategic',
            'comms' => 'Test Comms Channel',
            'doctrine' => 'Test Ships',
            'embed_color' => '#00FF00'
        ];

        $testUser = (object) [
            'id' => 0,
            'name' => 'System Test'
        ];

        return $this->sendPing($webhook, $testData, $testUser);
    }
}
