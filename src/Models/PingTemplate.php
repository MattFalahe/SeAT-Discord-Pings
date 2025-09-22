<?php

namespace MattFalahe\Seat\DiscordPings\Models;

use Illuminate\Database\Eloquent\Model;

class PingTemplate extends Model
{
    protected $table = 'discord_pings_templates';

    protected $fillable = [
        'name', 'template', 'fields', 'created_by', 'is_global'
    ];

    protected $casts = [
        'fields' => 'array',
        'is_global' => 'boolean',
    ];

    /**
     * Scope for global templates
     */
    public function scopeGlobal($query)
    {
        return $query->where('is_global', true);
    }

    /**
     * Scope for user templates
     */
    public function scopeForUser($query, $userId)
    {
        return $query->where(function ($q) use ($userId) {
            $q->where('created_by', $userId)
              ->orWhere('is_global', true);
        });
    }
}
