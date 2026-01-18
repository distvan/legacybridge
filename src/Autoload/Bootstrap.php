<?php

declare(strict_types=1);

namespace LegacyBridge\Autoload;

use LegacyBridge\Container\LegacyContainer;
use LegacyBridge\Http\LegacyBridge;
use LegacyBridge\Internal\Exception\InvalidRequestException;
use Psr\Http\Message\ServerRequestFactoryInterface;
use Psr\Http\Message\StreamFactoryInterface;
use Psr\Http\Message\UploadedFileFactoryInterface;

/**
 * Bootstrap helper for setting up LegacyBridge in a legacy application.
 *
 * This class simplifies the initialization of LegacyBridge by providing
 * a fluent interface for configuration and startup.
 *
 * The Bootstrap class embodies ADR-001 principles:
 * - Framework-agnostic: Works with any PSR-7 implementation
 * - Explicit: All configuration is visible and intentional
 * - Minimal: No magic, just straightforward setup
 *
 * Usage:
 * ```php
 * // Minimal setup
 * Bootstrap::new()
 *     ->withPsr7Factories($requestFactory, $streamFactory, $uploadedFileFactory)
 *     ->run($kernel);
 *
 * // Advanced setup with custom services
 * $bootstrap = Bootstrap::new();
 * $bootstrap->container()->set('my_service', $factory);
 * $bootstrap
 *     ->withPsr7Factories($rf, $sf, $uf)
 *     ->run($kernel);
 * ```
 *
 * @see LegacyBridge\Http\LegacyBridge
 * @see docs/adr/001-legacybridge-not-a-framework.md
 */
final class Bootstrap
{
    /**
     * The service container for the application.
     */
    private LegacyContainer $container;

    /**
     * PSR-7 ServerRequestFactory implementation.
     */
    private ?ServerRequestFactoryInterface $requestFactory = null;

    /**
     * PSR-7 StreamFactory implementation.
     */
    private ?StreamFactoryInterface $streamFactory = null;

    /**
     * PSR-7 UploadedFileFactory implementation.
     */
    private ?UploadedFileFactoryInterface $uploadedFileFactory = null;

    /**
     * Private constructor - use Bootstrap::new() instead.
     */
    private function __construct()
    {
        $this->container = ContainerFactory::create();
        ServiceRegistry::registerDefaults($this->container);
    }

    /**
     * Create a new Bootstrap instance.
     *
     * @return self A new Bootstrap instance with defaults configured
     */
    public static function new(): self
    {
        return new self();
    }

    /**
     * Get the service container.
     *
     * Allows direct access for custom service registration.
     *
     * @return LegacyContainer The application container
     */
    public function container(): LegacyContainer
    {
        return $this->container;
    }

    /**
     * Set PSR-7 factory implementations.
     *
     * These factories are required by LegacyBridge to convert superglobals
     * into PSR-7 requests and to emit PSR-7 responses.
     *
     * @param ServerRequestFactoryInterface $requestFactory PSR-17 ServerRequestFactory
     * @param StreamFactoryInterface $streamFactory PSR-17 StreamFactory
     * @param UploadedFileFactoryInterface $uploadedFileFactory PSR-17 UploadedFileFactory
     * @return self Fluent interface for method chaining
     */
    public function withPsr7Factories(
        ServerRequestFactoryInterface $requestFactory,
        StreamFactoryInterface $streamFactory,
        UploadedFileFactoryInterface $uploadedFileFactory
    ): self {
        $this->requestFactory = $requestFactory;
        $this->streamFactory = $streamFactory;
        $this->uploadedFileFactory = $uploadedFileFactory;

        // Register PSR-7 factories in container
        $this->container->set('request_factory', fn() => $requestFactory);
        $this->container->set('stream_factory', fn() => $streamFactory);
        $this->container->set('uploaded_file_factory', fn() => $uploadedFileFactory);

        return $this;
    }

    /**
     * Execute the LegacyBridge with the given kernel.
     *
     * This is the final step that actually starts request processing.
     *
     * The kernel is a callable that receives:
     * - ServerRequestInterface: The PSR-7 request
     * - ContainerInterface: The service container
     *
     * And must return a ResponseInterface.
     *
     * @param callable $kernel Application kernel
     * @return void
     *
     * @throws InvalidRequestException If PSR-7 factories not configured
     */
    public function run(callable $kernel): void
    {
        if ($this->requestFactory === null || $this->streamFactory === null || $this->uploadedFileFactory === null) {
            throw new \RuntimeException('PSR-7 factories must be configured before calling run(). Use withPsr7Factories() to set them.');
        }

        LegacyBridge::run(
            $kernel,
            $this->requestFactory,
            $this->streamFactory,
            $this->uploadedFileFactory
        );
    }
}
