<?php

spl_autoload_register(static function (string $class): void {
    if (strpos($class, 'TinyMCE7\\') !== 0) {
        return;
    }

    $relative = substr($class, strlen('TinyMCE7\\'));
    $path = __DIR__ . '/TinyMCE7/' . str_replace('\\', '/', $relative) . '.php';

    if (is_file($path)) {
        require_once $path;
    }
});
