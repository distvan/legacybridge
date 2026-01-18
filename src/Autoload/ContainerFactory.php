<?php

declare(strict_types=1);

namespace LegacyBridge\Autoload;

use LegacyBridge\Container\LegacyContainer;
use Psr\Container\ContainerInterface;

/**
 * Factory for creating a configured LegacyContainer instance.
 *
 * This factory provides a convenient way to instantiate a LegacyContainer
 * with sensible defaults, reducing boilerplate in application bootstrap code.
 *
 * This component is part of the Autoload namespace and supports the
 * explicit service registration pattern from ADR-003.
 *
 * Usage:
 * ```php
 * $container = ContainerFactory::create();
 * // Container is ready to use with explicit service registration
 * ```
 *
 * @see LegacyBridge\Container\LegacyContainer
 * @see docs/adr/003-no-autowiring-or-reflection.md
 */
final class ContainerFactory
{
    /**
     * Create a new LegacyContainer instance.
     *
     * @return LegacyContainer A fresh container ready for service registration
     */
    public static function create(): LegacyContainer
    {
        return new LegacyContainer();
    }

    /**
     * Create a LegacyContainer and register default services.
     *
     * This convenience method creates a container and registers common services
     * in one call, suitable for basic applications that don't need customization.
     *
     * @return LegacyContainer Container with default services registered
     */
    public static function createWithDefaults(): LegacyContainer
    {
        $container = self::create();
        ServiceRegistry::registerDefaults($container);
        return $container;
    }

    /**
     * Private constructor to prevent instantiation.
     *
     * This is a static factory, not meant to be instantiated.
     */
    private function __construct()
    {
    }
}