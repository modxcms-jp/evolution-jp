<?php
/**
 * Application Unit Tests
 *
 * Tests for the main application bootstrap class.
 *
 * @package EvolutionCMS\Install\Tests\Unit\Core
 */

declare(strict_types=1);

namespace EvolutionCMS\Install\Tests\Unit\Core;

use EvolutionCMS\Install\Core\Application;
use EvolutionCMS\Install\Core\Config;
use EvolutionCMS\Install\Core\Container;
use PHPUnit\Framework\TestCase;

/**
 * Class ApplicationTest
 */
class ApplicationTest extends TestCase
{
    private string $testPath;

    protected function setUp(): void
    {
        // Use installer directory for testing
        $this->testPath = dirname(__DIR__, 3);
    }

    public function testConstruct(): void
    {
        $app = new Application($this->testPath);

        $this->assertInstanceOf(Application::class, $app);
        $this->assertEquals($this->testPath, $app->getBasePath());
    }

    public function testGetContainer(): void
    {
        $app = new Application($this->testPath);
        $container = $app->getContainer();

        $this->assertInstanceOf(Container::class, $container);
    }

    public function testGetConfig(): void
    {
        $app = new Application($this->testPath);
        $config = $app->getConfig();

        $this->assertInstanceOf(Config::class, $config);
    }

    public function testBootstrap(): void
    {
        $app = new Application($this->testPath);

        $this->assertFalse($app->isBootstrapped());

        $app->bootstrap();

        $this->assertTrue($app->isBootstrapped());

        // Paths should be configured
        $config = $app->getConfig();
        $this->assertEquals($this->testPath, $config->get('paths.install'));
        $this->assertNotNull($config->get('paths.base'));
    }

    public function testBootstrapOnlyOnce(): void
    {
        $app = new Application($this->testPath);

        $app->bootstrap();
        $this->assertTrue($app->isBootstrapped());

        // Second bootstrap should not cause issues
        $app->bootstrap();
        $this->assertTrue($app->isBootstrapped());
    }

    public function testMake(): void
    {
        $app = new Application($this->testPath);

        // Container should be registered
        $container = $app->make(Container::class);
        $this->assertInstanceOf(Container::class, $container);

        // Config should be registered
        $config = $app->make(Config::class);
        $this->assertInstanceOf(Config::class, $config);

        // Application itself should be registered
        $appInstance = $app->make(Application::class);
        $this->assertSame($app, $appInstance);
    }

    public function testBind(): void
    {
        $app = new Application($this->testPath);

        $app->bind('test.service', function () {
            return 'test value';
        });

        $value = $app->make('test.service');
        $this->assertEquals('test value', $value);
    }

    public function testBindSingleton(): void
    {
        $app = new Application($this->testPath);

        $counter = 0;
        $app->bind('counter', function () use (&$counter) {
            $counter++;
            return new \stdClass();
        }, true);

        $instance1 = $app->make('counter');
        $instance2 = $app->make('counter');

        $this->assertSame($instance1, $instance2);
        $this->assertEquals(1, $counter);
    }

    public function testGetBasePath(): void
    {
        $app = new Application($this->testPath);
        $this->assertEquals($this->testPath, $app->getBasePath());
    }

    public function testGetModxBasePath(): void
    {
        $app = new Application($this->testPath);
        $expected = dirname($this->testPath) . '/';

        $this->assertEquals($expected, $app->getModxBasePath());
    }

    public function testVersion(): void
    {
        $this->assertIsString(Application::VERSION);
        $this->assertNotEmpty(Application::VERSION);
    }
}
