<?php

namespace DiscordPings\Tests\Unit;

use DiscordPings\Services\DiscordRoleResolver;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Schema;
use PHPUnit\Framework\TestCase;

class DiscordRoleResolverTest extends TestCase
{
    private const TAB = DiscordRoleResolver::PROVIDER_DISCORD_ROLES_TABLE;
    private const CONNECTOR = DiscordRoleResolver::PROVIDER_SEAT_CONNECTOR;

    protected function setUp(): void
    {
        Schema::$tables = ['discord_roles', 'seat_connector_sets'];
        DB::$tables = [];
        DB::$broken = [];
        Log::$lines = [];
    }

    private function tabRole(string $id, string $name, ?string $color = null, bool $active = true): object
    {
        return (object) ['role_id' => $id, 'name' => $name, 'color' => $color, 'is_active' => $active];
    }

    private function connectorRole(string $id, string $name, string $type = 'discord'): object
    {
        return (object) ['connector_id' => $id, 'name' => $name, 'connector_type' => $type];
    }

    private function byId(array $roles): array
    {
        return array_combine(array_map(fn ($role) => 'id' . $role['id'], $roles), $roles);
    }

    public function test_a_role_in_both_places_comes_back_once_and_says_so(): void
    {
        DB::$tables['discord_roles'] = [$this->tabRole('111', 'Directors', '#ff0000')];
        DB::$tables['seat_connector_sets'] = [$this->connectorRole('111', 'directors-synced')];

        $roles = DiscordRoleResolver::listRoles();

        $this->assertCount(1, $roles);
        $this->assertSame('Directors', $roles[0]['name'], "The tab's name is the one somebody chose");
        $this->assertSame('#ff0000', $roles[0]['color']);
        $this->assertSame(self::TAB, $roles[0]['source']);
        $this->assertSame([self::TAB, self::CONNECTOR], $roles[0]['sources']);
    }

    public function test_every_source_is_listed_and_the_names_come_back_in_order(): void
    {
        DB::$tables['discord_roles'] = [$this->tabRole('111', 'zulu')];
        DB::$tables['seat_connector_sets'] = [$this->connectorRole('222', 'Alpha'), $this->connectorRole('333', 'mike')];

        $roles = DiscordRoleResolver::listRoles();

        $this->assertSame(['Alpha', 'mike', 'zulu'], array_column($roles, 'name'));
        $this->assertSame(self::CONNECTOR, $this->byId($roles)['id222']['source']);
        $this->assertSame(self::TAB, $this->byId($roles)['id111']['source']);
    }

    public function test_the_tab_still_works_with_no_connector_installed(): void
    {
        Schema::$tables = ['discord_roles'];
        DB::$tables['discord_roles'] = [$this->tabRole('111', 'Directors')];

        $this->assertSame([self::TAB], DiscordRoleResolver::listRoles()[0]['sources']);
    }

    public function test_a_role_switched_off_on_the_tab_is_not_offered(): void
    {
        DB::$tables['discord_roles'] = [$this->tabRole('111', 'Old role', null, false), $this->tabRole('222', 'Current')];

        $this->assertSame(['Current'], array_column(DiscordRoleResolver::listRoles(), 'name'));
    }

    public function test_only_the_connectors_discord_roles_are_offered(): void
    {
        DB::$tables['seat_connector_sets'] = [$this->connectorRole('111', 'A discord role'), $this->connectorRole('222', 'A teamspeak group', 'teamspeak')];

        $this->assertSame(['A discord role'], array_column(DiscordRoleResolver::listRoles(), 'name'));
    }

    /** A connector table that won't read shouldn't cost anybody the roles on the tab. */
    public function test_a_source_that_fails_leaves_the_others_and_says_why_in_the_log(): void
    {
        DB::$tables['discord_roles'] = [$this->tabRole('111', 'Directors')];
        DB::$broken['seat_connector_sets'] = new \RuntimeException('table went away');

        $this->assertSame(['Directors'], array_column(DiscordRoleResolver::listRoles(), 'name'));
        $this->assertStringContainsString('table went away', Log::$lines[0]);
    }

    public function test_add_role_still_only_offers_what_is_not_on_the_tab_yet(): void
    {
        DB::$tables['discord_roles'] = [$this->tabRole('111', 'Directors')];
        DB::$tables['seat_connector_sets'] = [$this->connectorRole('111', 'Directors'), $this->connectorRole('222', 'Miners')];

        $roles = DiscordRoleResolver::listAvailableRoles();

        $this->assertSame(['Miners'], array_column($roles, 'name'));
        $this->assertSame([self::CONNECTOR], $roles[0]['sources']);
    }

    public function test_add_role_offers_nothing_without_a_connector(): void
    {
        Schema::$tables = ['discord_roles'];
        DB::$tables['discord_roles'] = [$this->tabRole('111', 'Directors')];

        $this->assertSame([], DiscordRoleResolver::listAvailableRoles());
    }

    public function test_the_plugin_is_called_seat_broadcast_on_the_badge(): void
    {
        $this->assertSame('SeAT Broadcast', DiscordRoleResolver::providerShortLabel(self::TAB));
    }
}
