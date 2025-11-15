<?php
/**
 * Configuration Manager
 *
 * Manages application configuration with support for environment-based settings,
 * dot notation access, and default values. Provides type-safe access to
 * configuration values.
 *
 * @package EvolutionCMS\Install\Core
 */

declare(strict_types=1);

namespace EvolutionCMS\Install\Core;

/**
 * Class Config
 *
 * Configuration repository with dot notation support and type safety.
 *
 * Example usage:
 * ```php
 * $config = new Config(['database' => ['host' => 'localhost']]);
 * $host = $config->get('database.host'); // 'localhost'
 * $port = $config->get('database.port', 3306); // 3306 (default)
 * ```
 *
 * @package EvolutionCMS\Install\Core
 */
class Config
{
    /**
     * Configuration data
     *
     * @var array<string, mixed>
     */
    private array $data = [];

    /**
     * Constructor
     *
     * @param array<string, mixed> $data Initial configuration data
     */
    public function __construct(array $data = [])
    {
        $this->data = $data;
    }

    /**
     * Get a configuration value using dot notation
     *
     * Supports nested array access using dot notation:
     * - 'database.host' accesses $config['database']['host']
     * - 'app.name' accesses $config['app']['name']
     *
     * @param string $key Configuration key (supports dot notation)
     * @param mixed $default Default value if key not found
     * @return mixed Configuration value or default
     */
    public function get(string $key, $default = null)
    {
        $keys = explode('.', $key);
        $value = $this->data;

        foreach ($keys as $segment) {
            if (!is_array($value) || !array_key_exists($segment, $value)) {
                return $default;
            }
            $value = $value[$segment];
        }

        return $value;
    }

    /**
     * Set a configuration value using dot notation
     *
     * @param string $key Configuration key (supports dot notation)
     * @param mixed $value Value to set
     * @return void
     */
    public function set(string $key, $value): void
    {
        $keys = explode('.', $key);
        $data = &$this->data;

        foreach ($keys as $i => $segment) {
            if ($i === count($keys) - 1) {
                $data[$segment] = $value;
            } else {
                if (!isset($data[$segment]) || !is_array($data[$segment])) {
                    $data[$segment] = [];
                }
                $data = &$data[$segment];
            }
        }
    }

    /**
     * Check if a configuration key exists
     *
     * @param string $key Configuration key (supports dot notation)
     * @return bool True if key exists
     */
    public function has(string $key): bool
    {
        $keys = explode('.', $key);
        $value = $this->data;

        foreach ($keys as $segment) {
            if (!is_array($value) || !array_key_exists($segment, $value)) {
                return false;
            }
            $value = $value[$segment];
        }

        return true;
    }

    /**
     * Get all configuration data
     *
     * @return array<string, mixed> All configuration data
     */
    public function all(): array
    {
        return $this->data;
    }

    /**
     * Merge additional configuration data
     *
     * Recursively merges new data into existing configuration.
     *
     * @param array<string, mixed> $data Configuration data to merge
     * @return void
     */
    public function merge(array $data): void
    {
        $this->data = $this->arrayMergeRecursive($this->data, $data);
    }

    /**
     * Load configuration from a PHP file
     *
     * The file should return an associative array of configuration values.
     *
     * @param string $path Path to configuration file
     * @return void
     * @throws ConfigException If file doesn't exist or doesn't return an array
     */
    public function loadFile(string $path): void
    {
        if (!file_exists($path)) {
            throw new ConfigException("Configuration file not found: {$path}");
        }

        $data = require $path;

        if (!is_array($data)) {
            throw new ConfigException("Configuration file must return an array: {$path}");
        }

        $this->merge($data);
    }

    /**
     * Load configuration from a directory
     *
     * Loads all PHP files from the specified directory and merges them
     * into the configuration. File names become top-level keys.
     *
     * Example: config/database.php becomes ['database' => [...]]
     *
     * @param string $path Path to configuration directory
     * @return void
     * @throws ConfigException If directory doesn't exist
     */
    public function loadDirectory(string $path): void
    {
        if (!is_dir($path)) {
            throw new ConfigException("Configuration directory not found: {$path}");
        }

        $files = glob($path . '/*.php');
        if ($files === false) {
            return;
        }

        foreach ($files as $file) {
            $key = basename($file, '.php');
            $data = require $file;

            if (is_array($data)) {
                $this->set($key, $data);
            }
        }
    }

    /**
     * Get a required configuration value
     *
     * Throws an exception if the key doesn't exist, ensuring critical
     * configuration values are always present.
     *
     * @param string $key Configuration key (supports dot notation)
     * @return mixed Configuration value
     * @throws ConfigException If key doesn't exist
     */
    public function getRequired(string $key)
    {
        if (!$this->has($key)) {
            throw new ConfigException("Required configuration key not found: {$key}");
        }

        return $this->get($key);
    }

    /**
     * Get a string configuration value
     *
     * @param string $key Configuration key
     * @param string $default Default value
     * @return string Configuration value as string
     */
    public function getString(string $key, string $default = ''): string
    {
        return (string) $this->get($key, $default);
    }

    /**
     * Get an integer configuration value
     *
     * @param string $key Configuration key
     * @param int $default Default value
     * @return int Configuration value as integer
     */
    public function getInt(string $key, int $default = 0): int
    {
        return (int) $this->get($key, $default);
    }

    /**
     * Get a boolean configuration value
     *
     * @param string $key Configuration key
     * @param bool $default Default value
     * @return bool Configuration value as boolean
     */
    public function getBool(string $key, bool $default = false): bool
    {
        return (bool) $this->get($key, $default);
    }

    /**
     * Get an array configuration value
     *
     * @param string $key Configuration key
     * @param array<mixed> $default Default value
     * @return array<mixed> Configuration value as array
     */
    public function getArray(string $key, array $default = []): array
    {
        $value = $this->get($key, $default);
        return is_array($value) ? $value : $default;
    }

    /**
     * Recursively merge arrays
     *
     * Similar to array_merge_recursive but doesn't create duplicate entries.
     *
     * @param array<mixed> $array1 First array
     * @param array<mixed> $array2 Second array
     * @return array<mixed> Merged array
     */
    private function arrayMergeRecursive(array $array1, array $array2): array
    {
        $merged = $array1;

        foreach ($array2 as $key => $value) {
            if (is_array($value) && isset($merged[$key]) && is_array($merged[$key])) {
                $merged[$key] = $this->arrayMergeRecursive($merged[$key], $value);
            } else {
                $merged[$key] = $value;
            }
        }

        return $merged;
    }

    /**
     * Remove a configuration value
     *
     * @param string $key Configuration key (supports dot notation)
     * @return void
     */
    public function remove(string $key): void
    {
        $keys = explode('.', $key);
        $lastKey = array_pop($keys);
        $data = &$this->data;

        foreach ($keys as $segment) {
            if (!isset($data[$segment]) || !is_array($data[$segment])) {
                return;
            }
            $data = &$data[$segment];
        }

        unset($data[$lastKey]);
    }

    /**
     * Clear all configuration data
     *
     * @return void
     */
    public function clear(): void
    {
        $this->data = [];
    }
}
