<?php

namespace MattFalahe\Seat\DiscordPings\Models;

use Illuminate\Database\Eloquent\Model;

class StagingLocation extends Model
{
    protected $table = 'discord_staging_locations';

    protected $fillable = [
        'name', 
        'system_name', 
        'structure_name',
        'description', 
        'is_active', 
        'is_default',
        'created_by'
    ];

    protected $casts = [
        'is_active' => 'boolean',
        'is_default' => 'boolean',
    ];

    /**
     * Get the full location string
     */
    public function getFullLocationString()
    {
        if ($this->structure_name) {
            return "{$this->system_name} - {$this->structure_name}";
        }
        return $this->system_name;
    }

    /**
     * Scope for active locations
     */
    public function scopeActive($query)
    {
        return $query->where('is_active', true);
    }

    /**
     * Scope for default location
     */
    public function scopeDefault($query)
    {
        return $query->where('is_default', true);
    }
}
