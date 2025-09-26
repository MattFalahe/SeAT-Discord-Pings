<?php

namespace MattFalahe\Seat\DiscordPings\Helpers;

use GuzzleHttp\Client;
use GuzzleHttp\Exception\RequestException;
use MattFalahe\Seat\DiscordPings\Models\PingHistory;
use Carbon\Carbon;
use Illuminate\Support\Facades\Log;

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
            Log::error('Discord ping send error', [
                'webhook_id' => $webhook->id,
                'user_id' => $user->id,
                'error' => $e->getMessage()
            ]);
            
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
                case 'role':
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
            ],
            'timestamp' => Carbon::now()->toIso8601String()
        ];

        // Add optional fields
         $fieldMappings = [
        'fc_name' => ['name' => '👤 FC Name', 'inline' => true],
        'formup_location' => ['name' => '📍 Formup Location', 'inline' => true],
        'pap_type' => ['name' => '🎯 PAP Type', 'inline' => true],
        'comms' => ['name' => '🎧 Comms', 'inline' => false],
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
    
    // Handle doctrine field specially - it might contain a link
    if (!empty($data['doctrine'])) {
        // Check if we have a doctrine URL from seat-fitting
        if (!empty($data['doctrine_url']) && !empty($data['doctrine_name'])) {
            // Create a clickable link in Discord
            $doctrineValue = "[{$data['doctrine_name']}]({$data['doctrine_url']})";
        } else {
            // Use plain text doctrine
            $doctrineValue = $data['doctrine'];
        }
        
        $embed['fields'][] = [
            'name' => '🚀 Doctrine',
            'value' => $doctrineValue,
            'inline' => false
        ];
    }
    
    // Add channel link if provided
    if (!empty($data['channel_url'])) {
        $channelValue = !empty($data['channel_mention']) 
            ? "Join: {$data['channel_mention']}\n[Click to open]({$data['channel_url']})"
            : "[Join Channel]({$data['channel_url']})";
            
        $embed['fields'][] = [
            'name' => '💬 Channel',
            'value' => $channelValue,
            'inline' => false
        ];
    }

        $payload['embeds'] = [$embed];

        // Add username and avatar
        $payload['username'] = config('discordpings.default_username', 'SeAT Fleet Pings');
        
        return $payload;
    }

    /**
     * Extract fields from data
     */
    private function extractFields($data)
    {
        $fields = [];
        $fieldKeys = ['fc_name', 'formup_location', 'pap_type', 'comms', 'doctrine', 'channel_url', 'channel_mention', 'doctrine_name', 'doctrine_url'];
        
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
