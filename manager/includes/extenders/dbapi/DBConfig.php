<?php

/**
 * Database configuration class
 *
 * Immutable configuration object for database connections
 */
class DBConfig
{
    public readonly string $host;
    public readonly string $username;
    public readonly string $password;
    public readonly string $database;
    public readonly string $charset;
    public readonly string $tablePrefix;
    public readonly int $connectTimeout;
    public readonly int $port;
    public readonly string $connectionMethod;

    /**
     * Create a new database configuration
     *
     * @param array $config Configuration array with keys:
     *                      - host: Database host (default: 'localhost')
     *                      - username: Database username (default: '')
     *                      - password: Database password (default: '')
     *                      - database: Database name (default: '')
     *                      - charset: Character set (default: 'utf8mb4')
     *                      - table_prefix: Table prefix (default: '')
     *                      - connect_timeout: Connection timeout in seconds (default: 10)
     *                      - port: Database port (default: 3306)
     *                      - connection_method: Connection method (default: 'SET CHARACTER SET')
     */
    public function __construct(array $config)
    {
        $host = $config['host'] ?? 'localhost';

        // Extract port from host if specified (e.g., "localhost:3307")
        if (strpos($host, ':') !== false) {
            [$host, $port] = explode(':', $host, 2);
            $this->host = $host;
            $this->port = (int)$port;
        } else {
            $this->host = $host;
            $this->port = $config['port'] ?? 3306;
        }

        $this->username = $config['username'] ?? '';
        $this->password = $config['password'] ?? '';
        $this->database = $config['database'] ?? '';
        $this->charset = $config['charset'] ?? 'utf8mb4';
        $this->tablePrefix = $config['table_prefix'] ?? '';
        $this->connectTimeout = $config['connect_timeout'] ?? 10;
        $this->connectionMethod = $config['connection_method'] ?? 'SET CHARACTER SET';
    }

    /**
     * Create configuration from global variables
     *
     * @return self
     */
    public static function fromGlobals(): self
    {
        return new self([
            'host' => globalv('database_server', 'localhost'),
            'username' => globalv('database_user', ''),
            'password' => globalv('database_password', ''),
            'database' => globalv('dbase', ''),
            'charset' => globalv('database_connection_charset', 'utf8mb4'),
            'table_prefix' => globalv('table_prefix', ''),
            'connection_method' => globalv('database_connection_method', 'SET CHARACTER SET'),
        ]);
    }

    /**
     * Convert configuration to array (for debugging)
     *
     * @param bool $maskPassword Whether to mask the password
     * @return array
     */
    public function toArray(bool $maskPassword = true): array
    {
        return [
            'host' => $this->host,
            'port' => $this->port,
            'username' => $this->username,
            'password' => $maskPassword ? '***' : $this->password,
            'database' => $this->database,
            'charset' => $this->charset,
            'table_prefix' => $this->tablePrefix,
            'connect_timeout' => $this->connectTimeout,
            'connection_method' => $this->connectionMethod,
        ];
    }
}
