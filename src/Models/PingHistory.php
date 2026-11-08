<?php

namespace DiscordPings\Models;

use Illuminate\Database\Eloquent\Model;
use Seat\Web\Models\User;

class PingHistory extends Model
{
    /** fields.source on a row written by a line member pressing a button. */
    public const SOURCE_MEMBER_REQUEST = 'member_request';

    protected $table = 'discord_ping_histories';

    protected $fillable = [
        'webhook_id', 'user_id', 'user_name', 'message', 'fields',
        'webhook_response', 'status', 'error_message', 'discord_message_id'
    ];

    protected $casts = [
        'fields' => 'array',
        'webhook_response' => 'array',
        'created_at' => 'datetime',
    ];

    public function isMemberRequest(): bool
    {
        return ($this->fields['source'] ?? null) === self::SOURCE_MEMBER_REQUEST;
    }

    public function scopeMemberRequests($query)
    {
        return $query->where('fields->source', self::SOURCE_MEMBER_REQUEST);
    }

    /** A row with no source at all is an ordinary broadcast, and a plain != would skip those. */
    public function scopeBroadcasts($query)
    {
        return $query->where(function ($inner) {
            $inner->whereNull('fields->source')
                ->orWhere('fields->source', '!=', self::SOURCE_MEMBER_REQUEST);
        });
    }

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
}
