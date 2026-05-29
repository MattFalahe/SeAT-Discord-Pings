<?php

namespace DiscordPings\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * Key-value store for UI-editable plugin settings (the discord_settings
 * table). Distinct from discordpings.config.php: the config file holds
 * install-time defaults, this holds operator overrides set from the
 * Settings page.
 */
class PluginSetting extends Model
{
    protected $table = 'discord_settings';

    protected $fillable = ['key', 'value'];

    /**
     * Read a raw setting value, falling back to $default when the key is
     * absent or the table is unavailable (e.g. before migrations run).
     */
    public static function getValue(string $key, $default = null)
    {
        try {
            $row = static::query()->where('key', $key)->first();

            return $row ? $row->value : $default;
        } catch (\Throwable $e) {
            return $default;
        }
    }

    /**
     * Read a setting as a boolean.
     */
    public static function getBool(string $key, bool $default = true): bool
    {
        $value = static::getValue($key, null);

        if ($value === null) {
            return $default;
        }

        return filter_var($value, FILTER_VALIDATE_BOOLEAN);
    }

    /**
     * Persist a setting value.
     */
    public static function setValue(string $key, $value): void
    {
        static::updateOrCreate(['key' => $key], ['value' => (string) $value]);
    }
}
