<?php

namespace DiscordPings\Services\PingRequests;

use Illuminate\Support\Facades\DB;

/**
 * What's on a member's SeAT account. Always every character on it, because
 * the one in trouble can be any alt.
 */
class MemberAccount
{
    /**
     * A read that fails comes back empty, which keeps limited buttons hidden.
     *
     * @return array{corporations: int[], alliances: int[]}
     */
    public static function affiliations(int $userId): array
    {
        try {
            $rows = DB::table('refresh_tokens')
                ->join('character_affiliations', 'refresh_tokens.character_id', '=', 'character_affiliations.character_id')
                ->where('refresh_tokens.user_id', $userId)
                ->whereNull('refresh_tokens.deleted_at')
                ->get(['character_affiliations.corporation_id', 'character_affiliations.alliance_id'])
                ->all();
        } catch (\Throwable $e) {
            return ['corporations' => [], 'alliances' => []];
        }

        return [
            'corporations' => self::ids(array_column($rows, 'corporation_id')),
            'alliances' => self::ids(array_column($rows, 'alliance_id')),
        ];
    }

    /** @return string[] */
    public static function characterNames(int $userId): array
    {
        try {
            $names = DB::table('refresh_tokens')
                ->join('character_infos', 'refresh_tokens.character_id', '=', 'character_infos.character_id')
                ->where('refresh_tokens.user_id', $userId)
                ->whereNull('refresh_tokens.deleted_at')
                ->orderBy('character_infos.name')
                ->pluck('character_infos.name')
                ->all();
        } catch (\Throwable $e) {
            return [];
        }

        return array_values(array_filter($names, fn ($name) => is_string($name) && $name !== ''));
    }

    /** @return int[] */
    private static function ids(array $values): array
    {
        return array_values(array_unique(array_map('intval', array_filter($values))));
    }
}
