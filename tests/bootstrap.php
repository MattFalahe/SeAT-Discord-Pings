<?php

// The tests run without SeAT or a database, so only code that needs neither is
// loaded: the rules, the message building and the source files themselves. The
// request sender runs against the stand-ins in Fake/.

require __DIR__ . '/vendor/autoload.php';
require __DIR__ . '/Fake/stand-ins.php';

spl_autoload_register(function (string $class): void {
    foreach (['DiscordPings\\Tests\\' => __DIR__ . '/', 'DiscordPings\\' => dirname(__DIR__) . '/src/'] as $prefix => $root) {
        if (strncmp($class, $prefix, strlen($prefix)) === 0) {
            $file = $root . str_replace('\\', '/', substr($class, strlen($prefix))) . '.php';
            if (is_file($file)) {
                require $file;
            }

            return;
        }
    }
});
