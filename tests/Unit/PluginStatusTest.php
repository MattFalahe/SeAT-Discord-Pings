<?php

namespace DiscordPings\Tests\Unit;

use DiscordPings\Services\Ecosystem\PluginStatus;
use Illuminate\Support\Facades\Schema;
use PHPUnit\Framework\TestCase;

class PluginStatusTest extends TestCase
{
    private const NONE = [
        'manager-core' => false,
        'structure-manager' => false,
        'mining-manager' => false,
        'hr-manager' => false,
        'seat-connector' => false,
        'warlof-discord' => false,
        'seat-fitting' => false,
    ];

    /** What each feature needs on top of Manager Core. */
    private const PARTNERS = [
        'structure-timers' => 'Structure Manager',
        'fc-opportunities' => 'Structure Manager',
        'moon-extractions' => 'Mining Manager',
        'fc-activity' => 'HR Manager',
    ];

    private const PARTIALS = __DIR__ . '/../../src/resources/views/help/partials/';

    protected function tearDown(): void
    {
        Schema::$tables = [];
    }

    private static function resolve(array $installed): array
    {
        return PluginStatus::resolve($installed + self::NONE);
    }

    private static function labels(array $badges): array
    {
        return array_map(fn (array $b) => $b['label'] . '/' . $b['tone'] . ($b['pulse'] ? '/pulse' : ''), $badges);
    }

    public function test_with_nothing_installed_manager_core_is_recommended_and_every_feature_is_marked(): void
    {
        $status = self::resolve([]);

        $this->assertSame(['state' => 'red', 'banner' => 'Not installed', 'coming_soon' => false, 'installed' => false], $status['hub']);
        $this->assertSame(['Not installed/danger/pulse', 'Recommended/info'], self::labels($status['plugins']['manager-core']['badges']));

        foreach (self::PARTNERS as $key => $partner) {
            $this->assertSame('red', $status['features'][$key]['state'], $key);
            $this->assertSame('Plugin not installed', $status['features'][$key]['banner'], $key);
            $this->assertSame('Manager Core and ' . $partner, $status['features'][$key]['needs'], $key);
        }

        foreach (['sender-discord' => 'SeAT Connector', 'role-list' => 'SeAT Connector', 'doctrines' => 'SeAT Fitting'] as $key => $needs) {
            $this->assertSame('yellow', $status['features'][$key]['state'], $key);
            $this->assertSame($needs, $status['features'][$key]['needs'], $key);
        }

        foreach (['structure-manager', 'mining-manager', 'hr-manager', 'seat-connector', 'seat-fitting'] as $key) {
            $this->assertSame(['Not installed/warning'], self::labels($status['plugins'][$key]['badges']), $key);
        }
    }

    public function test_manager_core_alone_turns_its_box_on_and_leaves_each_partner_feature_yellow(): void
    {
        $status = self::resolve(['manager-core' => true]);

        $this->assertSame(['state' => 'on', 'banner' => null, 'coming_soon' => false, 'installed' => true], $status['hub']);
        $this->assertSame(['Installed/success'], self::labels($status['plugins']['manager-core']['badges']));

        foreach (self::PARTNERS as $key => $partner) {
            $this->assertSame('yellow', $status['features'][$key]['state'], $key);
            $this->assertSame($partner, $status['features'][$key]['needs'], $key);
            $this->assertSame('Manager Core', $status['features'][$key]['using'], $key);
        }
    }

    public function test_a_partner_without_manager_core_is_cut_off_from_it(): void
    {
        $status = self::resolve(['structure-manager' => true, 'mining-manager' => true, 'hr-manager' => true]);

        foreach (['structure-manager', 'mining-manager', 'hr-manager'] as $key) {
            $this->assertSame(['Installed/success', 'Needs Manager Core/danger'], self::labels($status['plugins'][$key]['badges']), $key);
        }
        foreach (array_keys(self::PARTNERS) as $key) {
            $this->assertSame('red', $status['features'][$key]['state'], $key);
            $this->assertSame('Manager Core', $status['features'][$key]['needs'], $key);
        }
        $this->assertSame(['Not installed/danger/pulse', 'Recommended/info'], self::labels($status['plugins']['manager-core']['badges']));
    }

    public function test_everything_there_turns_every_feature_on_and_says_with_what(): void
    {
        $status = self::resolve(array_fill_keys(array_keys(self::NONE), true));

        $this->assertSame('on', $status['hub']['state']);
        foreach ([
            'structure-timers' => 'Manager Core and Structure Manager',
            'fc-opportunities' => 'Manager Core and Structure Manager',
            'moon-extractions' => 'Manager Core and Mining Manager',
            'fc-activity' => 'Manager Core and HR Manager',
            'sender-discord' => 'SeAT Connector',
            'role-list' => 'SeAT Connector',
            'doctrines' => 'SeAT Fitting',
        ] as $key => $using) {
            $this->assertSame('on', $status['features'][$key]['state'], $key);
            $this->assertNull($status['features'][$key]['banner'], $key);
            $this->assertSame($using, $status['features'][$key]['using'], $key);
        }
        foreach (array_keys(PluginStatus::PLUGINS) as $key) {
            $this->assertSame(['Installed/success'], self::labels($status['plugins'][$key]['badges']), $key);
        }
    }

    public function test_warlof_covers_the_role_list_but_not_the_sender_so_seat_connector_stays_yellow(): void
    {
        $status = self::resolve(['warlof-discord' => true]);

        $this->assertSame('on', $status['features']['role-list']['state']);
        $this->assertSame('Warlof Discord', $status['features']['role-list']['using']);
        $this->assertSame('yellow', $status['features']['sender-discord']['state']);
        $this->assertSame(['Not installed/warning'], self::labels($status['plugins']['seat-connector']['badges']));
    }

    public function test_warlof_is_found_but_not_listed(): void
    {
        $this->assertSame(
            ['manager-core', 'structure-manager', 'mining-manager', 'hr-manager', 'seat-connector', 'seat-fitting'],
            array_keys(self::resolve([])['plugins'])
        );
    }

    public function test_seat_connector_is_found_only_with_both_its_tables(): void
    {
        foreach ([
            [[], false, false],
            [['seat_connector_users'], false, false],
            [['seat_connector_sets'], false, false],
            [['seat_connector_users', 'seat_connector_sets'], true, false],
            [['warlof_discord_connector_roles'], false, true],
            [['discord_connector_roles'], false, true],
        ] as [$tables, $connector, $warlof]) {
            Schema::$tables = $tables;
            $found = PluginStatus::detect();

            $this->assertSame($connector, $found['seat-connector'], implode(', ', $tables));
            $this->assertSame($warlof, $found['warlof-discord'], implode(', ', $tables));
        }
    }

    public function test_the_other_plugins_are_found_by_their_classes_and_none_is_here(): void
    {
        Schema::$tables = ['seat_connector_users', 'seat_connector_sets'];
        $found = PluginStatus::detect();

        foreach (['manager-core', 'structure-manager', 'mining-manager', 'hr-manager', 'seat-fitting'] as $key) {
            $this->assertFalse($found[$key], $key);
        }
    }

    public function test_detect_answers_for_every_plugin_a_feature_can_use(): void
    {
        $sources = array_keys(PluginStatus::PLUGINS);
        foreach (PluginStatus::FEATURES as $needs) {
            foreach ($needs as $group) {
                $sources = array_merge($sources, $group['any']);
            }
        }
        $sources = array_values(array_unique($sources));
        $found = array_keys(PluginStatus::detect());
        sort($sources);
        sort($found);

        $this->assertSame($sources, $found);
    }

    public function test_help_has_a_box_for_every_feature_and_a_row_for_every_plugin(): void
    {
        $features = file_get_contents(self::PARTIALS . 'ecosystem-features.blade.php');
        foreach (array_keys(PluginStatus::FEATURES) as $key) {
            $this->assertStringContainsString("'{$key}' => [", $features, $key);
        }

        $plugins = file_get_contents(self::PARTIALS . 'ecosystem-plugins.blade.php');
        foreach (array_keys(PluginStatus::PLUGINS) as $key) {
            $this->assertStringContainsString("'{$key}' => [", $plugins, $key);
        }
    }
}
