<?php
/**
 * Container Unit Tests
 *
 * Tests for the PSR-11 dependency injection container.
 *
 * @package EvolutionCMS\Install\Tests\Unit\Core
 */

declare(strict_types=1);

namespace EvolutionCMS\Install\Tests\Unit\Core;

use EvolutionCMS\Install\Core\Container;
use EvolutionCMS\Install\Core\ContainerException;
use EvolutionCMS\Install\Core\NotFoundException;
use PHPUnit\Framework\TestCase;

/**
 * Class ContainerTest
 */
class ContainerTest extends TestCase
{
    private Container $container;

    protected function setUp(): void
    {
        $this->container = new Container();
    }

    public function testBindAndGet(): void
    {
        $this->container->bind('test', function () {
            return 'test value';
        });

        $this->assertTrue($this->container->has('test'));
        $this->assertEquals('test value', $this->container->get('test'));
    }

    public function testSingleton(): void
    {
        $counter = 0;
        $this->container->singleton('counter', function () use (&$counter) {
            $counter++;
            return new \stdClass();
        });

        $instance1 = $this->container->get('counter');
        $instance2 = $this->container->get('counter');

        $this->assertSame($instance1, $instance2);
        $this->assertEquals(1, $counter, 'Singleton should only be instantiated once');
    }

    public function testInstance(): void
    {
        $object = new \stdClass();
        $object->value = 'test';

        $this->container->instance('object', $object);

        $retrieved = $this->container->get('object');
        $this->assertSame($object, $retrieved);
        $this->assertEquals('test', $retrieved->value);
    }

    public function testNotFoundExceptionThrown(): void
    {
        $this->expectException(NotFoundException::class);
        $this->expectExceptionMessage("Service 'nonexistent' not found");

        $this->container->get('nonexistent');
    }

    public function testAutoWiring(): void
    {
        // Create a simple class to test auto-wiring
        $testClass = new class {
            public string $name = 'auto-wired';
        };

        $className = get_class($testClass);
        $instance = $this->container->get($className);

        $this->assertInstanceOf($className, $instance);
        $this->assertEquals('auto-wired', $instance->name);
    }

    public function testFlush(): void
    {
        $this->container->bind('test', 'value');
        $this->assertTrue($this->container->has('test'));

        $this->container->flush();
        $this->assertFalse($this->container->has('test'));
    }

    public function testHas(): void
    {
        $this->assertFalse($this->container->has('test'));

        $this->container->bind('test', 'value');
        $this->assertTrue($this->container->has('test'));
    }

    public function testBindClass(): void
    {
        $this->container->bind('stdClass', \stdClass::class);
        $instance = $this->container->get('stdClass');

        $this->assertInstanceOf(\stdClass::class, $instance);
    }

    public function testBindObject(): void
    {
        $object = new \stdClass();
        $object->test = 'value';

        $this->container->bind('object', $object);
        $retrieved = $this->container->get('object');

        $this->assertSame($object, $retrieved);
    }
}
