<?php

namespace DiscordPings\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * A member's saved answers for one button. A member can keep several, because
 * the pilot in trouble can be any of their alts.
 */
class PingRequestPreset extends Model
{
    protected $table = 'discord_ping_request_presets';

    protected $fillable = ['user_id', 'button_id', 'name', 'answers'];

    protected $casts = [
        'user_id' => 'integer',
        'button_id' => 'integer',
        'answers' => 'array',
    ];

    public function button()
    {
        return $this->belongsTo(PingRequestButton::class, 'button_id');
    }
}
