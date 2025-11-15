<?php
/**
 * Application Bootstrap
 *
 * Main application class that bootstraps the installer, manages the service
 * container, handles configuration, and orchestrates the installation process.
 *
 * This class serves as the central point for the installer application,
 * replacing the procedural index.php approach with an object-oriented design.
 *
 * @package EvolutionCMS\Install\Core
 */

declare(strict_types=1);

namespace EvolutionCMS\Install\Core;

/**
 * Class Application
 *
 * Main application class for the Evolution CMS installer.
 *
 * Example usage:
 * ```php
 * $app = new Application('/path/to/install');
 * $app->bootstrap();
 * $app->run();
 * ```
 *
 * @package EvolutionCMS\Install\Core
 */
class Application
{
    /**
     * Application version
     */
    public const VERSION = '1.0.0';

    /**
     * Installer base path
     *
     * @var string
     */
    private string $basePath;

    /**
     * Dependency injection container
     *
     * @var Container
     */
    private Container $container;

    /**
     * Configuration manager
     *
     * @var Config
     */
    private Config $config;

    /**
     * Application bootstrapped flag
     *
     * @var bool
     */
    private bool $bootstrapped = false;

    /**
     * Constructor
     *
     * @param string $basePath Installer base directory path
     */
    public function __construct(string $basePath)
    {
        $this->basePath = rtrim($basePath, '/');
        $this->container = new Container();
        $this->config = new Config();

        // Register core services in container
        $this->registerCoreServices();
    }

    /**
     * Bootstrap the application
     *
     * Initializes paths, loads configuration, sets up error handling,
     * and prepares the application for execution.
     *
     * @return void
     */
    public function bootstrap(): void
    {
        if ($this->bootstrapped) {
            return;
        }

        // Define paths
        $this->definePaths();

        // Load configuration
        $this->loadConfiguration();

        // Set up error handling
        $this->setupErrorHandling();

        // Set up environment
        $this->setupEnvironment();

        // Mark as bootstrapped
        $this->bootstrapped = true;
    }

    /**
     * Run the application
     *
     * Executes the main application logic. For now, this delegates to
     * the existing installer, but will eventually handle routing and
     * controller dispatch.
     *
     * @return void
     */
    public function run(): void
    {
        if (!$this->bootstrapped) {
            $this->bootstrap();
        }

        // For Phase 1, we delegate to the existing installer
        // In future phases, this will handle routing and controller dispatch
        require $this->basePath . '/index.php';
    }

    /**
     * Get the dependency injection container
     *
     * @return Container
     */
    public function getContainer(): Container
    {
        return $this->container;
    }

    /**
     * Get the configuration manager
     *
     * @return Config
     */
    public function getConfig(): Config
    {
        return $this->config;
    }

    /**
     * Get the installer base path
     *
     * @return string
     */
    public function getBasePath(): string
    {
        return $this->basePath;
    }

    /**
     * Get the Evolution CMS base path
     *
     * @return string
     */
    public function getModxBasePath(): string
    {
        return dirname($this->basePath) . '/';
    }

    /**
     * Register core services in the container
     *
     * Registers essential services like Config, Container itself, and
     * Application instance.
     *
     * @return void
     */
    private function registerCoreServices(): void
    {
        // Register container instance (for dependency injection)
        $this->container->instance(Container::class, $this->container);

        // Register config instance
        $this->container->instance(Config::class, $this->config);

        // Register application instance
        $this->container->instance(Application::class, $this);
        $this->container->instance(self::class, $this);
    }

    /**
     * Define application paths
     *
     * Sets up path constants for installer and Evolution CMS.
     *
     * @return void
     */
    private function definePaths(): void
    {
        // Define installer path
        if (!defined('MODX_SETUP_PATH')) {
            define('MODX_SETUP_PATH', $this->basePath . '/');
        }

        // Define Evolution CMS base path
        if (!defined('MODX_BASE_PATH')) {
            $definePathFile = $this->getModxBasePath() . 'define-path.php';
            if (file_exists($definePathFile)) {
                include $definePathFile;
            }

            if (!defined('MODX_BASE_PATH')) {
                define('MODX_BASE_PATH', $this->getModxBasePath());
            }
        }

        // Set paths in configuration
        $this->config->set('paths.install', $this->basePath);
        $this->config->set('paths.base', $this->getModxBasePath());
        $this->config->set('paths.config', $this->basePath . '/config');
        $this->config->set('paths.resources', $this->basePath . '/resources');
        $this->config->set('paths.src', $this->basePath . '/src');
    }

    /**
     * Load application configuration
     *
     * Loads configuration from config directory and environment files.
     *
     * @return void
     */
    private function loadConfiguration(): void
    {
        $configPath = $this->basePath . '/config';

        // Load default configuration if it exists
        if (is_dir($configPath)) {
            try {
                $this->config->loadDirectory($configPath);
            } catch (ConfigException $e) {
                // Config directory might be empty in Phase 1
                // This is acceptable for now
            }
        }

        // Load environment-specific configuration if available
        // (Will be implemented in future phases)
    }

    /**
     * Set up error handling
     *
     * Configures PHP error reporting and custom error handlers.
     *
     * @return void
     */
    private function setupErrorHandling(): void
    {
        // Get error reporting level from config or use default
        $errorLevel = $this->config->get('app.error_reporting', E_ALL & ~E_NOTICE);
        error_reporting($errorLevel);

        // Get display errors setting from config or use default
        $displayErrors = $this->config->get('app.display_errors', '1');
        ini_set('display_errors', $displayErrors);

        // Custom error handler can be registered here in future phases
    }

    /**
     * Set up application environment
     *
     * Configures PHP settings and environment variables.
     *
     * @return void
     */
    private function setupEnvironment(): void
    {
        // Set default timezone if configured
        $timezone = $this->config->get('app.timezone', 'UTC');
        date_default_timezone_set($timezone);

        // Set charset
        $charset = $this->config->get('app.charset', 'utf-8');
        header('Content-Type: text/html; charset=' . $charset);

        // Set memory limit if configured
        $memoryLimit = $this->config->get('app.memory_limit');
        if ($memoryLimit !== null) {
            ini_set('memory_limit', $memoryLimit);
        }

        // Set max execution time if configured
        $maxExecutionTime = $this->config->get('app.max_execution_time');
        if ($maxExecutionTime !== null) {
            set_time_limit((int) $maxExecutionTime);
        }
    }

    /**
     * Get a service from the container
     *
     * Convenience method for accessing container services.
     *
     * @param string $id Service identifier
     * @return mixed Service instance
     * @throws NotFoundException If service not found
     */
    public function make(string $id)
    {
        return $this->container->get($id);
    }

    /**
     * Bind a service to the container
     *
     * Convenience method for registering services.
     *
     * @param string $abstract Service identifier
     * @param mixed $concrete Service implementation
     * @param bool $singleton Register as singleton
     * @return void
     */
    public function bind(string $abstract, $concrete, bool $singleton = false): void
    {
        if ($singleton) {
            $this->container->singleton($abstract, $concrete);
        } else {
            $this->container->bind($abstract, $concrete);
        }
    }

    /**
     * Check if the application has been bootstrapped
     *
     * @return bool
     */
    public function isBootstrapped(): bool
    {
        return $this->bootstrapped;
    }
}
