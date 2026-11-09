<?php

namespace DiscordPings\Tests\Unit;

use DiscordPings\Services\PingRequests\LocationLookup;
use PHPUnit\Framework\TestCase;

class LocationLookupTest extends TestCase
{
    private const MOON = 8;
    private const PLANET = 7;
    private const GATE = 10;
    private const STATION = 15;

    private function celestial(int $id, int $group, string $name): object
    {
        return (object) ['itemID' => $id, 'groupID' => $group, 'itemName' => $name];
    }

    /** @return array<string, string[]> the options under each label */
    private function grouped(array $celestials, array $structures = [], array $moonStructures = []): array
    {
        return array_column(LocationLookup::groups('RD-FWY', $celestials, $structures, $moonStructures), 'options', 'label');
    }

    public function test_a_moon_with_a_known_structure_reads_the_way_alliances_write_it(): void
    {
        $groups = $this->grouped([$this->celestial(1, self::MOON, 'RD-FWY X - Moon 1')], [], [1 => 'RD-FWY - PRIVATE - The Veil of Ashes']);

        $this->assertSame(['RD-FWY X - Moon 1 / RD-FWY - PRIVATE - The Veil of Ashes'], $groups['Moons with a structure']);
    }

    public function test_a_moon_with_a_structure_comes_first_and_only_once(): void
    {
        $groups = LocationLookup::groups('RD-FWY', [
            $this->celestial(1, self::PLANET, 'RD-FWY X'),
            $this->celestial(2, self::MOON, 'RD-FWY X - Moon 1'),
            $this->celestial(3, self::MOON, 'RD-FWY X - Moon 2'),
        ], [], [2 => 'Refinery']);

        $this->assertSame('Moons with a structure', $groups[0]['label']);
        $this->assertSame(['RD-FWY X', 'RD-FWY X - Moon 2'], $groups[1]['options']);
    }

    public function test_a_moon_with_nothing_known_stays_in_orbit_order_with_its_planet(): void
    {
        $groups = $this->grouped([
            $this->celestial(1, self::PLANET, 'RD-FWY IX'),
            $this->celestial(2, self::MOON, 'RD-FWY IX - Moon 1'),
            $this->celestial(3, self::PLANET, 'RD-FWY X'),
        ]);

        $this->assertSame(['RD-FWY IX', 'RD-FWY IX - Moon 1', 'RD-FWY X'], $groups['Planets and moons']);
    }

    public function test_a_gate_says_which_system_it_is_in(): void
    {
        $groups = $this->grouped([$this->celestial(1, self::GATE, 'Stargate (F2A-GX)')]);

        $this->assertSame(['RD-FWY - Stargate (F2A-GX)'], $groups['Stargates']);
    }

    public function test_npc_stations_and_structures_share_a_sorted_list(): void
    {
        $groups = $this->grouped([$this->celestial(1, self::STATION, 'RD-FWY IV - Moon 2 - Refinery')], ['RD-FWY - Miners Lounge', 'RD-FWY - PRIVATE - The Veil of Ashes']);

        $this->assertSame(['RD-FWY - Miners Lounge', 'RD-FWY - PRIVATE - The Veil of Ashes', 'RD-FWY IV - Moon 2 - Refinery'], $groups['Structures and stations']);
    }

    public function test_a_structure_number_only_matches_its_own_moon(): void
    {
        $groups = $this->grouped([$this->celestial(5, self::MOON, 'RD-FWY X - Moon 1')], [], [6 => 'Somewhere else']);

        $this->assertArrayNotHasKey('Moons with a structure', $groups);
        $this->assertSame(['RD-FWY X - Moon 1'], $groups['Planets and moons']);
    }

    public function test_an_empty_group_is_left_out(): void
    {
        $this->assertSame([], LocationLookup::groups('RD-FWY', [], [], []));
    }
}
