<?php

namespace MattFalahe\Seat\DiscordPings\Models;

use Illuminate\Database\Eloquent\Model;

class DiscordChannel extends Model
{
    protected $table = 'discord_channels';

    protected $fillable = [
        'name', 'channel_id', 'server_id', 'channel_url', 
        'channel_type', 'description', 'is_active', 'created_by'
    ];

    protected $casts = [
        'is_active' => 'boolean',
    ];

    /**
     * Get the channel link
     */
    public function getChannelLink()
    {
        return $this->channel_url ?: "https://discord.com/channels/{$this->server_id}/{$this->channel_id}";
    }

    /**
     * Get the channel mention
     */
    public function getMentionString()
    {
        return "<#{$this->channel_id}>";
    }

    /**
     * Scope for active channels
     */
    public function scopeActive($query)
    {
        return $query->where('is_active', true);
    }

    /**
     * Parse channel and server IDs from a Discord channel URL
     */
    public static function parseChannelUrl($url)
    {
        // Pattern: https://discord.com/channels/SERVER_ID/CHANNEL_ID
        if (preg_match('/discord\.com\/channels\/(\d+)\/(\d+)/', $url, $matches)) {
            return [
                'server_id' => $matches[1],
                'channel_id' => $matches[2],
            ];
        }
        
        return null;
    }
}
