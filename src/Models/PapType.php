<?php

namespace MattFalahe\Seat\DiscordPings\Models;

use Illuminate\Database\Eloquent\Model;

class PapType extends Model
{
    protected $table = 'discord_pap_types';

    protected $fillable = ['name', 'sort_order', 'is_active'];

    protected $casts = [
        'is_active'  => 'boolean',
        'sort_order' => 'integer',
    ];

    public function scopeActive($query)
    {
        return $query->where('is_active', true);
    }

    public function scopeOrdered($query)
    {
        return $query->orderBy('sort_order')->orderBy('name');
    }
}
