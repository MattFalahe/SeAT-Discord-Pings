<?php

namespace MattFalahe\Seat\DiscordPings\Models;

use Illuminate\Database\Eloquent\Model;
use Seat\Web\Models\Acl\Role;

class DiscordWebhook extends Model
{
    protected $table = 'discord_webhooks';

    protected $fillable = [
        'name', 'webhook_url', 'channel_type', 'embed_color',
        'enable_mentions', 'default_mention', 'is_active', 'created_by'
    ];

    protected $casts = [
        'is_active' => 'boolean',
        'enable_mentions' => 'boolean',
    ];

    /**
     * Get histories for this webhook
     */
    public function histories()
    {
        return $this->hasMany(PingHistory::class, 'webhook_id');
    }

    /**
     * Get roles that can use this webhook
     */
    public function roles()
    {
        return $this->belongsToMany(Role::class, 'discord_webhook_roles', 'webhook_id', 'role_id');
    }

    /**
     * Scope for active webhooks
     */
    public function scopeActive($query)
    {
        return $query->where('is_active', true);
    }
}
