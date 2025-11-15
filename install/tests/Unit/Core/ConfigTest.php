<?php
/**
 * Config Unit Tests
 *
 * Tests for the configuration manager with dot notation support.
 *
 * @package EvolutionCMS\Install\Tests\Unit\Core
 */

declare(strict_types=1);

namespace EvolutionCMS\Install\Tests\Unit\Core;

use EvolutionCMS\Install\Core\Config;
use EvolutionCMS\Install\Core\ConfigException;
use PHPUnit\Framework\TestCase;

/**
 * Class ConfigTest
 */
class ConfigTest extends TestCase
{
    private Config $config;

    protected function setUp(): void
    {
        $this->config = new Config([
            'app' => [
                'name' => 'Evolution CMS',
                'debug' => true,
                'version' => '3.0',
            ],
            'database' => [
                'host' => 'localhost',
                'port' => 3306,
            ],
        ]);
    }

    public function testGet(): void
    {
        $this->assertEquals('Evolution CMS', $this->config->get('app.name'));
        $this->assertEquals('localhost', $this->config->get('database.host'));
        $this->assertEquals(3306, $this->config->get('database.port'));
    }

    public function testGetWithDefault(): void
    {
        $this->assertEquals('default', $this->config->get('nonexistent', 'default'));
        $this->assertEquals(999, $this->config->get('database.timeout', 999));
    }

    public function testSet(): void
    {
        $this->config->set('new.config.key', 'value');
        $this->assertEquals('value', $this->config->get('new.config.key'));

        $this->config->set('app.name', 'New Name');
        $this->assertEquals('New Name', $this->config->get('app.name'));
    }

    public function testHas(): void
    {
        $this->assertTrue($this->config->has('app.name'));
        $this->assertTrue($this->config->has('database.host'));
        $this->assertFalse($this->config->has('nonexistent'));
        $this->assertFalse($this->config->has('app.nonexistent'));
    }

    public function testAll(): void
    {
        $all = $this->config->all();

        $this->assertIsArray($all);
        $this->assertArrayHasKey('app', $all);
        $this->assertArrayHasKey('database', $all);
        $this->assertEquals('Evolution CMS', $all['app']['name']);
    }

    public function testMerge(): void
    {
        $this->config->merge([
            'app' => [
                'timezone' => 'UTC',
            ],
            'cache' => [
                'driver' => 'file',
            ],
        ]);

        // Original values should still exist
        $this->assertEquals('Evolution CMS', $this->config->get('app.name'));

        // New values should be merged
        $this->assertEquals('UTC', $this->config->get('app.timezone'));
        $this->assertEquals('file', $this->config->get('cache.driver'));
    }

    public function testGetRequired(): void
    {
        $value = $this->config->getRequired('app.name');
        $this->assertEquals('Evolution CMS', $value);
    }

    public function testGetRequiredThrowsException(): void
    {
        $this->expectException(ConfigException::class);
        $this->expectExceptionMessage('Required configuration key not found: nonexistent');

        $this->config->getRequired('nonexistent');
    }

    public function testGetString(): void
    {
        $this->assertEquals('Evolution CMS', $this->config->getString('app.name'));
        $this->assertEquals('3.0', $this->config->getString('app.version'));
        $this->assertEquals('default', $this->config->getString('nonexistent', 'default'));
    }

    public function testGetInt(): void
    {
        $this->assertEquals(3306, $this->config->getInt('database.port'));
        $this->assertEquals(0, $this->config->getInt('nonexistent'));
        $this->assertEquals(999, $this->config->getInt('nonexistent', 999));
    }

    public function testGetBool(): void
    {
        $this->assertTrue($this->config->getBool('app.debug'));
        $this->assertFalse($this->config->getBool('nonexistent'));
        $this->assertTrue($this->config->getBool('nonexistent', true));
    }

    public function testGetArray(): void
    {
        $app = $this->config->getArray('app');
        $this->assertIsArray($app);
        $this->assertArrayHasKey('name', $app);

        $empty = $this->config->getArray('nonexistent');
        $this->assertIsArray($empty);
        $this->assertEmpty($empty);

        $default = $this->config->getArray('nonexistent', ['key' => 'value']);
        $this->assertEquals(['key' => 'value'], $default);
    }

    public function testRemove(): void
    {
        $this->assertTrue($this->config->has('app.name'));

        $this->config->remove('app.name');
        $this->assertFalse($this->config->has('app.name'));

        // Other keys should still exist
        $this->assertTrue($this->config->has('app.debug'));
    }

    public function testClear(): void
    {
        $this->assertNotEmpty($this->config->all());

        $this->config->clear();
        $this->assertEmpty($this->config->all());
        $this->assertFalse($this->config->has('app.name'));
    }

    public function testLoadFileThrowsExceptionForNonexistent(): void
    {
        $this->expectException(ConfigException::class);
        $this->expectExceptionMessage('Configuration file not found');

        $this->config->loadFile('/nonexistent/config.php');
    }
}
