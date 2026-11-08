<?php

namespace DiscordPings\Models;

use DiscordPings\Models\Concerns\VisibleToViewer;
use Illuminate\Database\Eloquent\Model;

class PingRequestButton extends Model
{
    use VisibleToViewer;

    public const TYPE_EMERGENCY = 'emergency';
    public const TYPE_REQUEST = 'request';

    protected $table = 'discord_ping_request_buttons';

    protected $fillable = [
        'name', 'webhook_id', 'mention_role_id', 'starter_text', 'type', 'cooldown_minutes',
        'corporation_id', 'role_id', 'is_active', 'sort_order',
    ];

    protected $casts = [
        'webhook_id' => 'integer',
        'cooldown_minutes' => 'integer',
        'corporation_id' => 'integer',
        'role_id' => 'integer',
        'is_active' => 'boolean',
        'sort_order' => 'integer',
    ];

    public function webhook()
    {
        return $this->belongsTo(DiscordWebhook::class, 'webhook_id');
    }

    public function presets()
    {
        return $this->hasMany(PingRequestPreset::class, 'button_id');
    }

    public function scopeActive($query)
    {
        return $query->where('is_active', true);
    }

    /** Emergencies come first, because that's the button somebody is looking for in a hurry. */
    public function scopeForMembers($query)
    {
        return $query
            ->orderByRaw('CASE WHEN type = ? THEN 0 ELSE 1 END', [self::TYPE_EMERGENCY])
            ->orderBy('sort_order')
            ->orderBy('name');
    }

    public function isEmergency(): bool
    {
        return $this->type === self::TYPE_EMERGENCY;
    }
}
