<?php

namespace DiscordPings\Models;

use Illuminate\Database\Eloquent\Model;

/** An FC's broadcast that answered a member's request. */
class PingRequestAnswer extends Model
{
    protected $table = 'discord_ping_request_answers';

    protected $fillable = ['request_history_id', 'user_id', 'fc_name', 'broadcast_history_id'];

    protected $casts = [
        'request_history_id' => 'integer',
        'user_id' => 'integer',
        'broadcast_history_id' => 'integer',
        'created_at' => 'datetime',
    ];
}
