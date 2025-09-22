<?php

namespace MattFalahe\Seat\DiscordPings\Models;

use Illuminate\Database\Eloquent\Model;
use Seat\Web\Models\User;

class PingHistory extends Model
{
    protected $table = 'discord_pings_histories';

    protected $fillable = [
        'webhook_id', 'user_id', 'user_name', 'message', 'fields',
        'webhook_response', 'status', 'error_message', 'discord_message_id'
    ];

    protected $casts = [
        'fields' => 'array',
        'webhook_response' => 'array',
        'created_at' => 'datetime',
    ];

    /**
     * Get the webhook
     */
    public function webhook()
    {
        return $this->belongsTo(DiscordWebhook::class, 'webhook_id');
    }

    /**
     * Get the user
     */
    public function user()
    {
        return $this->belongsTo(User::class, 'user_id');
    }

    /**
     * Scope for successful pings
     */
    public function scopeSuccessful($query)
    {
        return $query->where('status', 'sent');
    }

    /**
     * Scope for failed pings
     */
    public function scopeFailed($query)
    {
        return $query->where('status', 'failed');
    }
}
