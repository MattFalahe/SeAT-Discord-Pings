<?php

namespace DiscordPings\Models;

use DiscordPings\Services\PingRequests\MemberAccount;
use DiscordPings\Services\PingRequests\Sections;
use Illuminate\Database\Eloquent\Model;

class PingRequestButton extends Model
{
    public const TYPE_EMERGENCY = 'emergency';
    public const TYPE_REQUEST = 'request';

    /** Everyone on the page holds these two already, so ticking one couldn't narrow anything. */
    private const NEVER_AN_AUDIENCE = ['view', 'line_member'];

    protected $table = 'discord_ping_request_buttons';

    protected $fillable = [
        'name', 'webhook_id', 'mention_role_id', 'ping_notice', 'starter_text', 'sections', 'type', 'cooldown_minutes',
        'corporation_id', 'alliance_id', 'audience_permissions', 'is_active', 'sort_order',
    ];

    protected $casts = [
        'webhook_id' => 'integer',
        'cooldown_minutes' => 'integer',
        'corporation_id' => 'integer',
        'alliance_id' => 'integer',
        'audience_permissions' => 'array',
        'sections' => 'array',
        'is_active' => 'boolean',
        'sort_order' => 'integer',
    ];

    /**
     * The Broadcast permissions a button can be limited to. Read from the
     * registered list, so a permission that gets added or merged later needs
     * no change here.
     *
     * @return array<string, string> labels by full permission name
     */
    public static function audienceOptions(): array
    {
        $options = [];

        foreach ((array) config('seat.permissions.discordpings', []) as $key => $permission) {
            if (! in_array($key, self::NEVER_AN_AUDIENCE, true)) {
                $options['discordpings.' . $key] = trans($permission['label'] ?? $key);
            }
        }

        return $options;
    }

    /**
     * A button shows when both of these pass, and an empty one passes:
     *   - the member is in its corporation or its alliance, either will do
     *   - the member holds any one of its permissions
     *
     * Roles and squads still decide who holds those permissions, that just
     * isn't this query's business.
     */
    public function scopeVisibleTo($query, $user)
    {
        if ($user && ($user->isAdmin() || $user->can('discordpings.admin'))) {
            return $query;
        }

        $member = $user ? MemberAccount::affiliations((int) $user->id) : ['corporations' => [], 'alliances' => []];
        $held = $user ? array_filter(array_keys(self::audienceOptions()), fn ($permission) => $user->can($permission)) : [];

        return $query
            ->where(function ($q) use ($member) {
                $q->where(fn ($open) => $open->whereNull('corporation_id')->whereNull('alliance_id'));

                if (! empty($member['corporations'])) {
                    $q->orWhereIn('corporation_id', $member['corporations']);
                }

                if (! empty($member['alliances'])) {
                    $q->orWhereIn('alliance_id', $member['alliances']);
                }
            })
            ->where(function ($q) use ($held) {
                $q->whereNull('audience_permissions');

                foreach ($held as $permission) {
                    $q->orWhereJsonContains('audience_permissions', $permission);
                }
            });
    }

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

    /**
     * The button's form, a row per section. A button nobody has saved since
     * sections became rows is read from its old text, the way a paste would be.
     *
     * @return array<int, array{label: string, box: string, live: bool}>
     */
    public function sectionRows(): array
    {
        $rows = $this->sections;

        return is_array($rows) && $rows !== [] ? Sections::clean($rows) : Sections::fromFormat($this->starter_text);
    }
}
