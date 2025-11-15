<?php
/**
 * PSR-4 Autoloader for Evolution CMS Installer
 *
 * Automatically loads classes from the src/ directory based on
 * their namespace and class name.
 *
 * @package EvolutionCMS\Install
 */

spl_autoload_register(function ($class) {
    $prefix = 'EvolutionCMS\\Install\\';
    $base_dir = __DIR__ . '/src/';

    // Does the class use the namespace prefix?
    $len = strlen($prefix);
    if (strncmp($prefix, $class, $len) !== 0) {
        // No, move to the next registered autoloader
        return;
    }

    // Get the relative class name
    $relative_class = substr($class, $len);

    // Replace namespace separators with directory separators
    // and append with .php
    $file = $base_dir . str_replace('\\', '/', $relative_class) . '.php';

    // If the file exists, require it
    if (file_exists($file)) {
        require $file;
    }
});
