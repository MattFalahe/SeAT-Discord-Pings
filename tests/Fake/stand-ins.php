<?php

// Stand-ins for the little of Laravel and Guzzle the request sender touches.
// They remember what was asked of them, so a test can see what went out
// without a Discord, a Redis or a database.

namespace Illuminate\Database\Eloquent {
    abstract class Model
    {
        /** @var array<int, array{0: class-string, 1: array}> every create(), in order */
        public static array $created = [];
        public static ?\Throwable $failCreateWith = null;

        /** @var array<class-string, array<int, Model>> what find() can see, by class and id */
        public static array $found = [];

        /** @var array<int, array{0: class-string, 1: mixed, 2: array}> every update(), in order, with the row's id */
        public static array $updated = [];

        private static int $lastId = 0;

        protected array $attributes = [];

        public function __construct(array $attributes = [])
        {
            $this->attributes = $attributes;
        }

        public function __get($name)
        {
            return $this->attributes[$name] ?? null;
        }

        public function __set($name, $value)
        {
            $this->attributes[$name] = $value;
        }

        /** The row as written goes in $created, and the id a database would give it only on what comes back. */
        public static function create(array $attributes)
        {
            if (self::$failCreateWith) {
                throw self::$failCreateWith;
            }
            self::$created[] = [static::class, $attributes];

            $model = new static($attributes + ['id' => ++self::$lastId]);
            self::$found[static::class][$model->id] = $model;

            return $model;
        }

        public static function find($id)
        {
            return self::$found[static::class][$id] ?? null;
        }

        public function update(array $attributes): bool
        {
            self::$updated[] = [static::class, $this->attributes['id'] ?? null, $attributes];
            $this->attributes = $attributes + $this->attributes;

            return true;
        }
    }
}

namespace Illuminate\Support\Facades {
    class Cache
    {
        public static array $store = [];

        /** @var array<string, int> how long each put() was told to keep its key */
        public static array $seconds = [];

        public static ?\Throwable $failPutWith = null;

        public static function put(string $key, $value, int $seconds): bool
        {
            if (self::$failPutWith) {
                throw self::$failPutWith;
            }
            self::$store[$key] = $value;
            self::$seconds[$key] = $seconds;

            return true;
        }

        public static function add(string $key, $value, int $seconds): bool
        {
            if (array_key_exists($key, self::$store)) {
                return false;
            }
            self::$store[$key] = $value;

            return true;
        }

        public static function get(string $key)
        {
            return self::$store[$key] ?? null;
        }

        public static function forget(string $key): void
        {
            unset(self::$store[$key]);
        }
    }

    class RateLimiter
    {
        public static array $hits = [];

        public static function tooManyAttempts(string $key, int $max): bool
        {
            return (self::$hits[$key] ?? 0) >= $max;
        }

        public static function availableIn(string $key): int
        {
            return 42;
        }

        public static function hit(string $key, int $seconds): void
        {
            self::$hits[$key] = (self::$hits[$key] ?? 0) + 1;
        }
    }

    class Log
    {
        public static array $lines = [];

        public static function __callStatic($level, $arguments)
        {
            self::$lines[] = (string) ($arguments[0] ?? '');
        }
    }

    class Schema
    {
        public static array $tables = [];

        public static function hasTable(string $table): bool
        {
            return in_array($table, self::$tables, true);
        }
    }

    class DB
    {
        /** @var array<string, object> the one row a query on this table finds */
        public static array $rows = [];

        /** @var array<string, array<int, object>> every row get() and pluck() can see, by table */
        public static array $tables = [];

        /** @var array<string, \Throwable> tables a list query blows up on */
        public static array $broken = [];

        public static function table(string $table)
        {
            return new class(explode(' ', $table)[0]) {
                private array $equals = [];

                public function __construct(private string $table)
                {
                }

                public function __call($method, $arguments)
                {
                    return $this;
                }

                public function where(...$arguments)
                {
                    if (count($arguments) === 2 && is_string($arguments[0])) {
                        $this->equals[$arguments[0]] = $arguments[1];
                    }

                    return $this;
                }

                public function value(string $column)
                {
                    return DB::$rows[$this->table]->$column ?? null;
                }

                public function first()
                {
                    return DB::$rows[$this->table] ?? null;
                }

                public function get(array $columns = ['*'])
                {
                    return new \Illuminate\Support\Collection($this->matching());
                }

                public function pluck(string $column)
                {
                    return new \Illuminate\Support\Collection(array_map(fn ($row) => $row->$column, $this->matching()));
                }

                private function matching(): array
                {
                    if (isset(DB::$broken[$this->table])) {
                        throw DB::$broken[$this->table];
                    }

                    return array_values(array_filter(DB::$tables[$this->table] ?? [], function ($row) {
                        foreach ($this->equals as $column => $value) {
                            if ($row->$column != $value) {
                                return false;
                            }
                        }

                        return true;
                    }));
                }
            };
        }
    }
}

namespace Illuminate\Support {
    class Collection
    {
        public function __construct(private array $items = [])
        {
        }

        public function map(callable $callback): self
        {
            return new self(array_map($callback, $this->items));
        }

        public function mapWithKeys(callable $callback): self
        {
            $mapped = [];
            foreach ($this->items as $item) {
                $mapped += $callback($item);
            }

            return new self($mapped);
        }

        public function all(): array
        {
            return $this->items;
        }
    }
}

namespace GuzzleHttp {
    class Client
    {
        /** @var array<int, array{0: string, 1: array}> every post, in order */
        public static array $posts = [];

        /** @var array<int, array{0: string, 1: array}> every patch, in order */
        public static array $patches = [];

        /** @var array<int, object> what the next posts and patches get back, in the order they're made; a Throwable is thrown */
        public static array $replies = [];

        public function __construct(array $config = [])
        {
        }

        public function post(string $url, array $options = [])
        {
            self::$posts[] = [$url, $options];

            return self::nextReply('post', $url);
        }

        public function patch(string $url, array $options = [])
        {
            self::$patches[] = [$url, $options];

            return self::nextReply('patch', $url);
        }

        private static function nextReply(string $verb, string $url)
        {
            $reply = array_shift(self::$replies);

            if ($reply === null) {
                throw new \LogicException('A ' . $verb . ' nobody expected: ' . $url);
            }
            if ($reply instanceof \Throwable) {
                throw $reply;
            }

            return $reply;
        }
    }
}

namespace {
    if (!function_exists('config')) {
        function config($key, $default = null)
        {
            return $GLOBALS['FAKE_CONFIG'][$key] ?? $default;
        }
    }

    if (!function_exists('trans')) {
        function trans($key)
        {
            return 'Translated ' . $key;
        }
    }
}
