<?php

namespace MattFalahe\Seat\DiscordPings\Models;

use Illuminate\Database\Eloquent\Model;
use Carbon\Carbon;

class ScheduledPing extends Model
{
    protected $table = 'discord_scheduled_pings';

    protected $fillable = [
        'webhook_id', 'user_id', 'message', 'fields', 'scheduled_at',
        'repeat_interval', 'repeat_until', 'is_active', 'last_sent_at', 'times_sent'
    ];

    protected $casts = [
        'fields' => 'array',
        'scheduled_at' => 'datetime',
        'repeat_until' => 'datetime',
        'last_sent_at' => 'datetime',
        'is_active' => 'boolean',
    ];

    /**
     * Get the webhook
     */
    public function webhook()
    {
        return $this->belongsTo(DiscordWebhook::class, 'webhook_id');
    }

    /**
     * Scope for due pings
     */
    public function scopeDue($query)
    {
        return $query->where('is_active', true)
                     ->where('scheduled_at', '<=', Carbon::now())
                     ->where(function ($q) {
                         $q->whereNull('repeat_until')
                           ->orWhere('repeat_until', '>=', Carbon::now());
                     });
    }

    /**
     * Calculate next run time
     */
    public function calculateNextRun()
    {
        if (!$this->repeat_interval) {
            return null;
        }

        switch ($this->repeat_interval) {
            case 'hourly':
                return $this->scheduled_at->addHour();
            case 'daily':
                return $this->scheduled_at->addDay();
            case 'weekly':
                return $this->scheduled_at->addWeek();
            case 'monthly':
                return $this->scheduled_at->addMonth();
            default:
                return null;
        }
    }
}
