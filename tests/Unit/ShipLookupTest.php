<?php

namespace DiscordPings\Tests\Unit;

use DiscordPings\Services\PingRequests\ShipLookup;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use PHPUnit\Framework\TestCase;

class ShipLookupTest extends TestCase
{
    protected function setUp(): void
    {
        DB::$tables = [];
        DB::$broken = [];
        Log::$lines = [];
    }

    private function type(string $name, int $category = 6, int $published = 1): object
    {
        return (object) ['invGroups.categoryID' => $category, 'invTypes.published' => $published, 'invTypes.typeName' => $name];
    }

    public function test_only_published_ships_are_offered(): void
    {
        DB::$tables['invTypes'] = [$this->type('Rorqual'), $this->type('Test Hull', 6, 0), $this->type('Veldspar', 25)];

        $this->assertSame(['Rorqual'], (new ShipLookup())->names());
    }

    public function test_a_name_is_only_offered_once(): void
    {
        DB::$tables['invTypes'] = [$this->type('Rorqual'), $this->type('Rorqual')];

        $this->assertSame(['Rorqual'], (new ShipLookup())->names());
    }

    public function test_static_data_it_cannot_read_leaves_the_box_to_free_typing(): void
    {
        DB::$broken['invTypes'] = new \RuntimeException("Table 'seat.invTypes' doesn't exist");

        $this->assertSame([], (new ShipLookup())->names());
        $this->assertStringContainsString('ship types', Log::$lines[0]);
    }
}
