<?php

namespace DiscordPings\Services;

use DiscordPings\Services\PingRequests\SenderIdentity;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Schema;

/**
 * Works out who a SeAT account is on Discord, and who to name when one of its
 * members presses a ping request button.
 *
 * The connector is detected by its table, the same way DiscordRoleResolver
 * does it, so nothing here needs seat-connector to be installed. Everything is
 * read from what SeAT already holds: a press must never wait on ESI.
 */
class DiscordUserResolver
{
    private const CONNECTOR_TABLE = 'seat_connector_users';

    public static function isAvailable(): bool
    {
        try {
            return Schema::hasTable(self::CONNECTOR_TABLE);
        } catch (\Throwable $e) {
            return false;
        }
    }

    /**
     * The linked Discord account of a SeAT user.
     *
     * @return array{id: string, name: ?string}|null
     */
    public static function discordAccount(int $userId): ?array
    {
        if (! self::isAvailable()) {
            return null;
        }

        try {
            $row = DB::table(self::CONNECTOR_TABLE)
                ->where('connector_type', 'discord')
                ->where('user_id', $userId)
                ->first(['connector_id', 'connector_name']);
        } catch (\Throwable $e) {
            Log::warning('[DiscordPings] DiscordUserResolver: could not read the connector: ' . $e->getMessage());
            return null;
        }

        if (! $row || (string) $row->connector_id === '') {
            return null;
        }

        return [
            'id' => (string) $row->connector_id,
            'name' => $row->connector_name !== null && $row->connector_name !== '' ? (string) $row->connector_name : null,
        ];
    }

    public static function senderIdentity($user): SenderIdentity
    {
        $mainId = (int) ($user->main_character_id ?? 0);
        $account = self::discordAccount((int) $user->id);

        return new SenderIdentity(
            self::mainName($mainId) ?? (string) $user->name,
            self::corporationName($mainId),
            self::isAvailable(),
            $account['id'] ?? null,
            $account['name'] ?? null,
        );
    }

    /** The SeAT main character's name, or the account's own name when it has no main. */
    public static function mainCharacterName($user): string
    {
        if (! $user) {
            return '';
        }

        return self::mainName((int) ($user->main_character_id ?? 0)) ?? (string) ($user->name ?? '');
    }

    /** For a job, which only has the account's id. */
    public static function mainNameOfUser(int $userId): ?string
    {
        try {
            $mainId = (int) DB::table('users')->where('id', $userId)->value('main_character_id');
        } catch (\Throwable $e) {
            return null;
        }

        return self::mainName($mainId);
    }

    private static function mainName(int $mainId): ?string
    {
        if ($mainId === 0) {
            return null;
        }

        try {
            $name = DB::table('character_infos')->where('character_id', $mainId)->value('name');
        } catch (\Throwable $e) {
            return null;
        }

        return $name !== null && $name !== '' ? (string) $name : null;
    }

    private static function corporationName(int $mainId): ?string
    {
        if ($mainId === 0) {
            return null;
        }

        try {
            // A corporation nobody has a token in can be missing from
            // corporation_infos while SeAT still knows what it's called.
            $row = DB::table('character_affiliations as a')
                ->leftJoin('corporation_infos as c', 'c.corporation_id', '=', 'a.corporation_id')
                ->leftJoin('universe_names as u', 'u.entity_id', '=', 'a.corporation_id')
                ->where('a.character_id', $mainId)
                ->select('c.name as corporation', 'u.name as universe')
                ->first();
        } catch (\Throwable $e) {
            return null;
        }

        $name = $row->corporation ?? $row->universe ?? null;

        return $name !== null && $name !== '' ? (string) $name : null;
    }
}
