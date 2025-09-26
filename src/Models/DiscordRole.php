<?php

namespace MattFalahe\Seat\DiscordPings\Models;

use Illuminate\Database\Eloquent\Model;

class DiscordRole extends Model
{
    protected $table = 'discord_roles';

    protected $fillable = [
        'name', 'role_id', 'mention_format', 'color', 
        'description', 'is_active', 'created_by'
    ];

    protected $casts = [
        'is_active' => 'boolean',
    ];

    /**
     * Get the mention string for this role
     */
    public function getMentionString()
    {
        return $this->mention_format ?: "<@&{$this->role_id}>";
    }

    /**
     * Scope for active roles
     */
    public function scopeActive($query)
    {
        return $query->where('is_active', true);
    }

    /**
     * Parse role ID from a Discord role mention or URL
     */
    public static function parseRoleId($input)
    {
        // Remove <@& and > if it's a mention
        if (preg_match('/<@&(\d+)>/', $input, $matches)) {
            return $matches[1];
        }
        
        // If it's just numbers, return as is
        if (preg_match('/^\d+$/', $input)) {
            return $input;
        }
        
        return null;
    }
}
