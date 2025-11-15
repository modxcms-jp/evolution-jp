<?php
/**
 * Dependency Injection Container (PSR-11 Compatible)
 *
 * A lightweight dependency injection container that implements PSR-11
 * Container Interface. Supports singleton and factory bindings, automatic
 * dependency resolution, and constructor injection.
 *
 * @package EvolutionCMS\Install\Core
 * @see https://www.php-fig.org/psr/psr-11/
 */

declare(strict_types=1);

namespace EvolutionCMS\Install\Core;

use Closure;
use ReflectionClass;
use ReflectionException;
use ReflectionParameter;

/**
 * Class Container
 *
 * Lightweight PSR-11 compliant dependency injection container.
 *
 * @package EvolutionCMS\Install\Core
 */
class Container
{
    /**
     * Service bindings registry
     *
     * Maps abstract identifiers to concrete implementations or factory functions.
     *
     * @var array<string, mixed>
     */
    private array $bindings = [];

    /**
     * Singleton instances registry
     *
     * Stores resolved singleton instances to ensure single instance per container.
     *
     * @var array<string, object>
     */
    private array $instances = [];

    /**
     * Singleton flags
     *
     * Tracks which bindings should be resolved as singletons.
     *
     * @var array<string, bool>
     */
    private array $singletons = [];

    /**
     * Bind a service to the container
     *
     * Registers a concrete implementation or factory function for an abstract identifier.
     * Each call to get() will create a new instance unless bound as singleton.
     *
     * @param string $abstract Abstract identifier (interface or class name)
     * @param mixed $concrete Concrete implementation (class name, instance, or Closure)
     * @return void
     */
    public function bind(string $abstract, $concrete): void
    {
        $this->bindings[$abstract] = $concrete;
    }

    /**
     * Bind a service as singleton
     *
     * Registers a service that will be resolved once and reused for subsequent requests.
     *
     * @param string $abstract Abstract identifier (interface or class name)
     * @param mixed $concrete Concrete implementation (class name, instance, or Closure)
     * @return void
     */
    public function singleton(string $abstract, $concrete): void
    {
        $this->bindings[$abstract] = $concrete;
        $this->singletons[$abstract] = true;
    }

    /**
     * Register an existing instance as singleton
     *
     * Useful for registering objects that have already been instantiated.
     *
     * @param string $abstract Abstract identifier
     * @param object $instance Instance to register
     * @return void
     */
    public function instance(string $abstract, object $instance): void
    {
        $this->instances[$abstract] = $instance;
        $this->singletons[$abstract] = true;
    }

    /**
     * Resolve and retrieve a service from the container
     *
     * Implements PSR-11 get() method. Returns the resolved instance,
     * creating it if necessary. For singletons, returns the cached instance.
     *
     * @param string $id Service identifier
     * @return mixed Resolved service instance
     * @throws ContainerException If service cannot be resolved
     * @throws NotFoundException If service is not found
     */
    public function get(string $id)
    {
        // Return existing singleton instance if available
        if (isset($this->instances[$id])) {
            return $this->instances[$id];
        }

        // Check if service is registered
        if (!$this->has($id)) {
            // Try auto-wiring if class exists
            if (class_exists($id)) {
                return $this->resolve($id);
            }

            throw new NotFoundException("Service '{$id}' not found in container");
        }

        // Resolve the concrete implementation
        $concrete = $this->bindings[$id];
        $instance = $this->resolve($concrete);

        // Cache singleton instances
        if ($this->isSingleton($id)) {
            $this->instances[$id] = $instance;
        }

        return $instance;
    }

    /**
     * Check if service is registered in the container
     *
     * Implements PSR-11 has() method.
     *
     * @param string $id Service identifier
     * @return bool True if service is registered
     */
    public function has(string $id): bool
    {
        return isset($this->bindings[$id]) || isset($this->instances[$id]);
    }

    /**
     * Check if service is registered as singleton
     *
     * @param string $abstract Service identifier
     * @return bool True if service is singleton
     */
    private function isSingleton(string $abstract): bool
    {
        return isset($this->singletons[$abstract]) && $this->singletons[$abstract] === true;
    }

    /**
     * Resolve a concrete implementation
     *
     * Handles different types of concrete implementations:
     * - Closure: Execute and return result
     * - Object instance: Return as-is
     * - Class name: Instantiate with dependency injection
     *
     * @param mixed $concrete Concrete implementation to resolve
     * @return mixed Resolved instance
     * @throws ContainerException If resolution fails
     */
    private function resolve($concrete)
    {
        // Execute factory function
        if ($concrete instanceof Closure) {
            return $concrete($this);
        }

        // Return existing instance
        if (is_object($concrete)) {
            return $concrete;
        }

        // Instantiate class with dependency injection
        if (is_string($concrete) && class_exists($concrete)) {
            return $this->build($concrete);
        }

        throw new ContainerException("Unable to resolve: " . print_r($concrete, true));
    }

    /**
     * Build a class instance with automatic dependency injection
     *
     * Uses reflection to analyze constructor parameters and automatically
     * inject dependencies from the container.
     *
     * @param string $className Fully qualified class name
     * @return object Instantiated class with dependencies injected
     * @throws ContainerException If class cannot be instantiated
     */
    private function build(string $className): object
    {
        try {
            $reflector = new ReflectionClass($className);

            // Check if class is instantiable
            if (!$reflector->isInstantiable()) {
                throw new ContainerException("Class '{$className}' is not instantiable");
            }

            $constructor = $reflector->getConstructor();

            // No constructor - simple instantiation
            if ($constructor === null) {
                return new $className();
            }

            // Resolve constructor parameters
            $parameters = $constructor->getParameters();
            $dependencies = $this->resolveDependencies($parameters);

            // Instantiate with dependencies
            return $reflector->newInstanceArgs($dependencies);
        } catch (ReflectionException $e) {
            throw new ContainerException("Failed to build '{$className}': " . $e->getMessage(), 0, $e);
        }
    }

    /**
     * Resolve constructor parameter dependencies
     *
     * Analyzes each parameter and attempts to resolve it from the container.
     * Supports type-hinted dependencies and default values.
     *
     * @param ReflectionParameter[] $parameters Constructor parameters
     * @return array Resolved dependencies
     * @throws ContainerException If dependency cannot be resolved
     */
    private function resolveDependencies(array $parameters): array
    {
        $dependencies = [];

        foreach ($parameters as $parameter) {
            $dependency = $this->resolveParameter($parameter);
            $dependencies[] = $dependency;
        }

        return $dependencies;
    }

    /**
     * Resolve a single constructor parameter
     *
     * @param ReflectionParameter $parameter Parameter to resolve
     * @return mixed Resolved parameter value
     * @throws ContainerException If parameter cannot be resolved
     */
    private function resolveParameter(ReflectionParameter $parameter)
    {
        // Get parameter type
        $type = $parameter->getType();

        // No type hint - use default value if available
        if ($type === null) {
            if ($parameter->isDefaultValueAvailable()) {
                return $parameter->getDefaultValue();
            }

            throw new ContainerException(
                "Cannot resolve parameter '{$parameter->getName()}' - no type hint or default value"
            );
        }

        // Handle union types (PHP 8.0+)
        if (method_exists($type, 'getName')) {
            $typeName = $type->getName();

            // Skip built-in types (string, int, bool, array, etc.)
            if ($type->isBuiltin()) {
                if ($parameter->isDefaultValueAvailable()) {
                    return $parameter->getDefaultValue();
                }

                throw new ContainerException(
                    "Cannot resolve built-in type '{$typeName}' for parameter '{$parameter->getName()}'"
                );
            }

            // Resolve class dependency from container
            try {
                return $this->get($typeName);
            } catch (NotFoundException $e) {
                // Try default value if parameter is optional
                if ($parameter->isDefaultValueAvailable()) {
                    return $parameter->getDefaultValue();
                }

                // Allow null if parameter is nullable
                if ($parameter->allowsNull()) {
                    return null;
                }

                throw $e;
            }
        }

        throw new ContainerException(
            "Cannot resolve parameter '{$parameter->getName()}' - unsupported type"
        );
    }

    /**
     * Clear all bindings and instances
     *
     * Useful for testing or resetting the container state.
     *
     * @return void
     */
    public function flush(): void
    {
        $this->bindings = [];
        $this->instances = [];
        $this->singletons = [];
    }
}
