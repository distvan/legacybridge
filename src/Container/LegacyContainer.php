<?php

declare(strict_types=1);

namespace LegacyBridge\Container;

use Psr\Container\NotFoundExceptionInterface;
use Psr\Container\ContainerExceptionInterface;
use Throwable;

/**
 * A minimal PSR-11 container for explicit service registration.
 *
 * This container:
 * - Does NOT use reflection or autowiring
 * - Requires explicit service registration via set()
 * - Resolves services lazily via defined factories
 * - Stores both factories and resolved instances
 *
 * This implementation aligns with ADR-003: Avoiding Auto-Wiring and Reflection.
 *
 * @see https://www.php-fig.org/psr/psr-11/
 * @see docs/adr/003-no-autowiring-or-reflection.md
 */
final class LegacyContainer implements Container
{
    /**
     * Registered service factories
     *
     * @var array<string, callable>
     */
    private array $factories = [];

    /**
     * Cached service instances
     *
     * @var array<string, mixed>
     */
    private array $instances = [];

    /**
     * Register a service factory.
     *
     * This is the only way to register services - no autowiring, no reflection.
     * The factory is a callable that receives the container and returns the service.
     *
     * Usage:
     * ```php
     * $container->set('my_service', function(ContainerInterface $container) {
     *     return new MyService($container->get('dependency'));
     * });
     * ```
     *
     * @param string $id Service identifier
     * @param callable $factory Service factory - signature: function(ContainerInterface): mixed
     * @return void
     */
    public function set(string $id, callable $factory): void
    {
        $this->factories[$id] = $factory;
        // Clear cached instance when registering a new factory
        unset($this->instances[$id]);
    }

    /**
     * Retrieve a service from the container.
     *
     * Services are resolved lazily on first access.
     * Subsequent calls return the cached instance.
     *
     * @param string $id Service identifier
     * @return mixed The service instance
     * @throws NotFoundExceptionInterface If the service is not registered
     * @throws ContainerExceptionInterface If the factory throws during resolution
     */
    public function get(string $id): mixed
    {
        if (!$this->has($id)) {
            throw new class($id) extends \RuntimeException implements NotFoundExceptionInterface {
                public function __construct(string $id)
                {
                    parent::__construct(
                        "Service '$id' not found in container. Register it using \$container->set('$id', \$factory)."
                    );
                }
            };
        }

        // Return cached instance if available
        if (isset($this->instances[$id])) {
            return $this->instances[$id];
        }

        // Resolve the service via factory
        try {
            $factory = $this->factories[$id];
            $this->instances[$id] = $factory($this);
        } catch (Throwable $e) {
            throw new class($id, $e) extends \RuntimeException implements ContainerExceptionInterface {
                public function __construct(string $id, Throwable $previous)
                {
                    parent::__construct(
                        "Error resolving service '$id': " . $previous->getMessage(),
                        0,
                        $previous
                    );
                }
            };
        }

        return $this->instances[$id];
    }

    /**
     * Check if a service is registered in the container.
     *
     * Note: This only checks if a factory is registered.
     * It does not verify if the factory will successfully resolve.
     *
     * @param string $id Service identifier
     * @return bool True if the service is registered, false otherwise
     */
    public function has(string $id): bool
    {
        return isset($this->factories[$id]);
    }
}