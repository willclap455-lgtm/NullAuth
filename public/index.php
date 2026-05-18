<?php

declare(strict_types=1);

$autoload = dirname(__DIR__) . '/vendor/autoload.php';
if (is_file($autoload)) {
    require $autoload;
} else {
    require dirname(__DIR__) . '/src/Support/helpers.php';
    spl_autoload_register(static function (string $class): void {
        $prefix = 'NullAuth\\';
        if (!str_starts_with($class, $prefix)) {
            return;
        }

        $relative = str_replace('\\', DIRECTORY_SEPARATOR, substr($class, strlen($prefix)));
        $path = dirname(__DIR__) . '/src/' . $relative . '.php';
        if (is_file($path)) {
            require $path;
        }
    });
}

NullAuth\Bootstrap::run();

