<?php

namespace DiscordPings\Tests\Structure;

use PHPUnit\Framework\TestCase;

/**
 * Things a mistake in the source would break on a live install that nothing
 * else catches before then, read from the files themselves.
 */
class SourceTest extends TestCase
{
    private const ROOT = __DIR__ . '/../../';
    private const SRC = self::ROOT . 'src/';
    private const HELP_VIEW = 'src/resources/views/help/index.blade.php';

    /** Everything before this has shipped and can't be renamed, so the naming rules start here. */
    private const FIRST_RULED_MIGRATION = 20;

    /** @return string[] paths of the files under src/ with this ending */
    private static function files(string $ending, string $under = ''): array
    {
        $found = [];
        foreach (new \RecursiveIteratorIterator(new \RecursiveDirectoryIterator(self::SRC . $under, \FilesystemIterator::SKIP_DOTS)) as $file) {
            if (str_ends_with($file->getPathname(), $ending)) {
                $found[] = $file->getPathname();
            }
        }
        sort($found);

        return $found;
    }

    /**
     * Blade reads a directive with \B@\w+, so counting @if and @endif proves
     * nothing: {{ $h }}h@endif is not a directive, and leaves its @if open.
     */
    public function test_every_blade_directive_is_closed_the_way_blade_reads_it(): void
    {
        $opens = [
            'if' => 'endif', 'unless' => 'endunless', 'isset' => 'endisset', 'foreach' => 'endforeach',
            'forelse' => 'endforelse', 'for' => 'endfor', 'while' => 'endwhile', 'push' => 'endpush',
        ];
        $closes = array_flip($opens) + ['endsection' => 'section', 'stop' => 'section', 'endphp' => 'php'];

        foreach (self::files('.blade.php') as $path) {
            $source = preg_replace('/\{\{--.*?--\}\}/s', '', file_get_contents($path));
            $source = preg_replace('/\B@php\b(?!\s*\().*?\B@endphp\b/s', '', $source);
            // A section with its content in the call has nothing to close.
            $source = preg_replace("/\B@section\(\s*'[^']*'\s*,.*\)\s*$/m", '', $source);

            preg_match_all('/\B@(\w+)/', $source, $matches);
            $stack = [];
            foreach ($matches[1] as $directive) {
                if ($directive === 'section') {
                    $stack[] = 'section';
                } elseif (isset($opens[$directive])) {
                    $stack[] = $directive;
                } elseif (isset($closes[$directive])) {
                    $this->assertSame($closes[$directive], array_pop($stack), basename($path) . ": @$directive closes the wrong block");
                }
            }

            $this->assertSame([], $stack, basename($path) . ': left open');
        }
    }

    /** The menu and the permission list name their keys in config, not through trans(), and a miss shows as raw text. */
    public function test_every_language_key_named_in_the_source_exists(): void
    {
        $lang = [];
        foreach (self::files('.php', 'resources/lang/en') as $path) {
            $lang[basename($path, '.php')] = require $path;
        }

        foreach (self::files('.php') as $path) {
            preg_match_all("/(?:\b(?:trans|__)\(\s*|'(?:label|description)'\s*=>\s*)'discordpings::([a-z_]+)\.([a-zA-Z0-9_.]+)'\s*[,)]/", file_get_contents($path), $matches, PREG_SET_ORDER);
            foreach ($matches as [, $file, $key]) {
                $value = $lang[$file] ?? null;
                foreach (explode('.', $key) as $part) {
                    $value = is_array($value) ? ($value[$part] ?? null) : null;
                }
                $this->assertNotNull($value, basename($path) . " names $file.$key, which is not there");
            }
        }
    }

    /** @return string[] the migrations the naming rules apply to, in number order */
    private static function ruledMigrations(): array
    {
        $found = array_filter(
            self::files('.php', 'database/migrations'),
            fn ($path) => (int) substr(basename($path), 11, 6) >= self::FIRST_RULED_MIGRATION
        );
        usort($found, fn ($a, $b) => substr(basename($a), 11, 6) <=> substr(basename($b), 11, 6));

        return $found;
    }

    public function test_new_migrations_are_named_classes_matching_their_file(): void
    {
        foreach (self::ruledMigrations() as $path) {
            $source = file_get_contents($path);
            $suffix = preg_replace('/^\d{4}_\d{2}_\d{2}_\d{6}_/', '', basename($path, '.php'));
            $class = str_replace(' ', '', ucwords(str_replace('_', ' ', $suffix)));

            $this->assertStringNotContainsString('return new class', $source, basename($path));
            $this->assertMatchesRegularExpression('/\bclass ' . $class . ' extends Migration\b/', $source, basename($path));
            $this->assertStringContainsString('discord_ping', $suffix, basename($path) . ' has to name the plugin: migrations share one namespace');
        }
    }

    public function test_new_migration_numbers_run_in_order_with_none_repeated(): void
    {
        $numbers = array_map(fn ($path) => (int) substr(basename($path), 11, 6), self::ruledMigrations());

        $this->assertSame(range(self::FIRST_RULED_MIGRATION, self::FIRST_RULED_MIGRATION + count($numbers) - 1), $numbers);
    }

    public function test_index_names_fit_in_what_mysql_allows(): void
    {
        foreach (self::ruledMigrations() as $path) {
            preg_match_all("/'(dp_[a-z0-9_]+)'/", file_get_contents($path), $matches);
            foreach ($matches[1] as $name) {
                $this->assertLessThanOrEqual(64, strlen($name), $name);
            }
        }
    }

    /** SeAT's tables are read, never written. Our data lives in our own tables. */
    public function test_nothing_writes_a_table_that_is_not_the_plugins(): void
    {
        $this->expectNotToPerformAssertions();

        foreach (self::files('.php') as $path) {
            preg_match_all("/DB::table\('([a-zA-Z_]+)(?: as \w+)?'\)((?:(?!DB::table|;).)*?)->(insert|insertOrIgnore|insertUsing|insertGetId|update|updateOrInsert|upsert|delete|truncate|increment|decrement)\(/s", file_get_contents($path), $matches, PREG_SET_ORDER);
            foreach ($matches as [, $table]) {
                if (!str_starts_with($table, 'discord_')) {
                    $this->fail(basename($path) . " writes $table");
                }
            }
        }
    }

    /** A column or an index on SeAT's own tables is SeAT's to add, not ours. */
    public function test_nothing_changes_a_table_that_is_not_the_plugins(): void
    {
        foreach (self::files('.php', 'database/migrations') as $path) {
            $source = file_get_contents($path);

            preg_match_all("/Schema::(?:create|table|drop|dropIfExists|rename)\(\s*'([a-zA-Z_]+)'/", $source, $named);
            preg_match_all('/\b(?:ALTER TABLE|CREATE (?:UNIQUE )?INDEX \S+ ON|DROP TABLE(?: IF EXISTS)?)\s+`?([a-zA-Z_]+)/i', $source, $statements);

            foreach (array_merge($named[1], $statements[1]) as $table) {
                $this->assertStringStartsWith('discord_', $table, basename($path));
            }
        }

        foreach (self::files('.php') as $path) {
            if (!str_contains(str_replace('\\', '/', $path), 'database/migrations')) {
                $this->assertDoesNotMatchRegularExpression('/Schema::(create|table|drop|dropIfExists|rename)\(/', file_get_contents($path), basename($path) . ' changes a schema outside a migration');
            }
        }
    }

    public function test_every_command_is_registered(): void
    {
        $provider = file_get_contents(self::SRC . 'DiscordPingsServiceProvider.php');

        foreach (glob(self::SRC . 'Console/Commands/*.php') as $path) {
            $this->assertStringContainsString(basename($path, '.php') . '::class', $provider, basename($path));
        }
    }

    /** A command that narrows one of these is a fatal when the class loads, and php -l can't see that. */
    public function test_no_command_narrows_a_method_symfony_declares_public(): void
    {
        $this->expectNotToPerformAssertions();

        $taken = [
            'run', 'execute', 'initialize', 'interact', 'configure', 'handle',
            'getName', 'setName', 'getHelp', 'setHelp', 'getDescription', 'setDescription',
            'isEnabled', 'getDefinition', 'setDefinition', 'getApplication', 'setApplication',
            'addOption', 'addArgument', 'getSynopsis', 'mergeApplicationDefinition',
            'ask', 'confirm', 'choice', 'secret', 'call', 'callSilent',
            'option', 'options', 'argument', 'arguments', 'line', 'info', 'warn', 'error', 'comment', 'table',
        ];

        foreach (glob(self::SRC . 'Console/Commands/*.php') as $path) {
            preg_match_all('/^\s*(private|protected)\s+(?:static\s+)?function\s+(\w+)\s*\(/m', file_get_contents($path), $matches, PREG_SET_ORDER);
            foreach ($matches as [, $visibility, $method]) {
                if (in_array($method, $taken, true)) {
                    $this->fail(basename($path) . ": $visibility function $method() narrows a method Symfony declares public");
                }
            }
        }
    }

    public function test_the_docs_cover_every_command_and_name_none_that_is_gone(): void
    {
        $known = [];
        foreach (glob(self::SRC . 'Console/Commands/*.php') as $path) {
            $this->assertSame(1, preg_match("/signature = 'discordpings:([a-z-]+)/", file_get_contents($path), $signature), basename($path));
            $known[] = $signature[1];
        }

        foreach (['README.md', self::HELP_VIEW] as $file) {
            $docs = file_get_contents(self::ROOT . $file);

            foreach ($known as $command) {
                $this->assertStringContainsString("discordpings:$command", $docs, "$file leaves out $command");
            }

            preg_match_all('/discordpings:([a-z-]+)/', $docs, $named);
            foreach (array_unique($named[1]) as $command) {
                $this->assertContains($command, $known, "$file names $command, which is not a command");
            }
        }
    }

    /** A package name with a branch in it stops working the day the branch goes, and people copy what the docs say. */
    public function test_the_docs_name_the_package_and_no_branch(): void
    {
        foreach (['README.md', 'CHANGELOG.md', self::HELP_VIEW] as $file) {
            $this->assertDoesNotMatchRegularExpression('/seat-discord-pings:dev|dev-dev-|dev-2\.0/', file_get_contents(self::ROOT . $file), $file);
        }
    }

    /** An address made by hand can send a list, and casting one to text is a 500. */
    public function test_no_query_value_is_cast_to_text_directly(): void
    {
        foreach (self::files('.php') as $path) {
            $this->assertStringNotContainsString('(string) $request->query(', file_get_contents($path), basename($path));
        }
    }

    /** All of this is public, and the CSS goes out to every browser that opens a page. */
    public function test_nothing_published_names_a_private_note(): void
    {
        $note = '/feedback_[a-z_]{4,}|(?:project|reference|user)_[a-z0-9_]{4,}\.md|MEMORY\.md|project memory|\(memory\)|north star|claude|anthropic|co-authored-by|ai-assisted|\[\[[a-z0-9_-]+\]\]/i';

        foreach (['.php', '.css', '.js'] as $ending) {
            foreach (self::files($ending) as $path) {
                if (str_contains(str_replace('\\', '/', $path), '/vendor/')) {
                    continue;
                }
                $this->assertDoesNotMatchRegularExpression($note, file_get_contents($path), basename($path));
            }
        }
    }
}
