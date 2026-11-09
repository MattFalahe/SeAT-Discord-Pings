<?php

namespace DiscordPings\Services\PingRequests;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

/**
 * Where a member can say they are, out of what SeAT already holds. Database
 * only, since this answers while somebody is typing, and any part it can't
 * read just goes missing from the list.
 */
class LocationLookup
{
    private const GROUP_PLANET = 7;
    private const GROUP_MOON = 8;
    private const GROUP_STARGATE = 10;
    private const GROUP_STATION = 15;

    /** @return array<int, array{0: int, 1: string}> id and name of every system */
    public function systems(): array
    {
        return $this->quietly('solar systems', fn () => DB::table('solar_systems')
            ->orderBy('name')
            ->get(['system_id', 'name'])
            ->map(fn ($system) => [(int) $system->system_id, (string) $system->name])
            ->all(), []);
    }

    /**
     * @param array{corporations: int[], alliances: int[]} $member
     * @return array<int, array{label: string, options: string[]}>
     */
    public function locations(int $systemId, array $member): array
    {
        $system = $this->quietly('the system name', fn () => DB::table('solar_systems')->where('system_id', $systemId)->value('name'), null);

        if ($system === null) {
            return [];
        }

        $owners = $this->ownCorporations($member);

        return self::groups(
            (string) $system,
            $this->celestials($systemId),
            $this->structures($systemId, $owners),
            $this->moonStructures($systemId, $owners)
        );
    }

    /**
     * Quickest to reach first: a moon with a structure on it is most likely
     * where somebody is mining.
     *
     * @param array<int, object> $celestials rows with itemID, groupID and itemName, in orbit order
     * @param string[] $structures
     * @param array<int, string> $moonStructures structure name by moon id
     * @return array<int, array{label: string, options: string[]}>
     */
    public static function groups(string $system, array $celestials, array $structures, array $moonStructures): array
    {
        $moonsWithStructure = [];
        $stations = $structures;
        $gates = [];
        $bodies = [];

        foreach ($celestials as $celestial) {
            $name = (string) $celestial->itemName;
            $group = (int) $celestial->groupID;

            if ($group === self::GROUP_STATION) {
                $stations[] = $name;
            } elseif ($group === self::GROUP_STARGATE) {
                $gates[] = $system . ' - ' . $name;
            } elseif ($group === self::GROUP_MOON && isset($moonStructures[(int) $celestial->itemID])) {
                $moonsWithStructure[] = $name . ' / ' . $moonStructures[(int) $celestial->itemID];
            } else {
                $bodies[] = $name;
            }
        }

        sort($stations, SORT_NATURAL | SORT_FLAG_CASE);
        sort($gates, SORT_NATURAL | SORT_FLAG_CASE);

        $groups = [];

        foreach ([
            'Moons with a structure' => $moonsWithStructure,
            'Structures and stations' => $stations,
            'Stargates' => $gates,
            'Planets and moons' => $bodies,
        ] as $label => $options) {
            if ($options !== []) {
                $groups[] = ['label' => $label, 'options' => $options];
            }
        }

        return $groups;
    }

    /**
     * The member's corporations and every corporation in their alliances.
     *
     * @return int[]
     */
    private function ownCorporations(array $member): array
    {
        $inAlliances = empty($member['alliances']) ? [] : $this->quietly('alliance corporations', fn () => DB::table('corporation_infos')
            ->whereIn('alliance_id', $member['alliances'])
            ->pluck('corporation_id')
            ->all(), []);

        return array_values(array_unique(array_merge($member['corporations'], array_map('intval', $inAlliances))));
    }

    /** @return array<int, object> */
    private function celestials(int $systemId): array
    {
        return $this->quietly('celestials', fn () => DB::table('mapDenormalize')
            ->where('solarSystemID', $systemId)
            ->whereIn('groupID', [self::GROUP_PLANET, self::GROUP_MOON, self::GROUP_STARGATE, self::GROUP_STATION])
            ->orderBy('celestialIndex')
            ->orderBy('orbitIndex')
            ->orderBy('itemName')
            ->get(['itemID', 'groupID', 'itemName'])
            ->all(), []);
    }

    /**
     * Only the member's own side, so another group's private structure names
     * stay private. A corporation's own structures often have no owner in
     * universe_structures, which is why its structure list gets read as well.
     *
     * @return string[]
     */
    private function structures(int $systemId, array $owners): array
    {
        if ($owners === []) {
            return [];
        }

        return $this->quietly('structures', fn () => DB::table('universe_structures')
            ->where('solar_system_id', $systemId)
            ->where(function ($query) use ($owners) {
                $query->whereIn('owner_id', $owners)
                    ->orWhereIn('structure_id', function ($own) use ($owners) {
                        $own->select('structure_id')->from('corporation_structures')->whereIn('corporation_id', $owners);
                    });
            })
            ->orderBy('name')
            ->pluck('name')
            ->all(), []);
    }

    /**
     * Extractions are the only thing that says which moon a structure is on.
     * Stored positions can't: the refineries running them usually have none.
     *
     * @return array<int, string> structure name by moon id, the latest extraction winning
     */
    private function moonStructures(int $systemId, array $owners): array
    {
        if ($owners === []) {
            return [];
        }

        return $this->quietly('moon extractions', function () use ($systemId, $owners) {
            $names = [];

            $extractions = DB::table('corporation_industry_mining_extractions as extraction')
                ->join('universe_structures as structure', 'structure.structure_id', '=', 'extraction.structure_id')
                ->where('structure.solar_system_id', $systemId)
                ->whereIn('extraction.corporation_id', $owners)
                ->orderBy('extraction.chunk_arrival_time')
                ->get(['extraction.moon_id', 'structure.name']);

            foreach ($extractions as $extraction) {
                $names[(int) $extraction->moon_id] = (string) $extraction->name;
            }

            return $names;
        }, []);
    }

    private function quietly(string $what, callable $read, $fallback)
    {
        try {
            return $read();
        } catch (\Throwable $e) {
            Log::warning('[DiscordPings] Could not read ' . $what . ' for ping request suggestions: ' . $e->getMessage());

            return $fallback;
        }
    }
}
