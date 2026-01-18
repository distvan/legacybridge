<?php

declare(strict_types=1);

namespace LegacyBridge\Autoload;

use LegacyBridge\Container\LegacyContainer;
use LegacyBridge\Http\RequestFactory;
use LegacyBridge\Http\ResponseEmitter;
use Psr\Container\ContainerInterface;

/**
 * Registry for default service definitions in the LegacyContainer.
 *
 * This class provides methods to register commonly-used services into a container.
 * Each registration is explicit and documented, following ADR-003 principles.
 *
 * Services registered:
 * - request_factory: PSR-7 ServerRequestFactory (requires PSR implementation)
 * - stream_factory: PSR-7 StreamFactory (requires PSR implementation)
 * - uploaded_file_factory: PSR-7 UploadedFileFactory (requires PSR implementation)
 * - request_factory_bridge: LegacyBridge RequestFactory wrapper
 * - response_emitter: Explicit HTTP response emitter
 *
 * Usage:
 * ```php
 * $container = new LegacyContainer();
 * ServiceRegistry::registerDefaults($container);
 * 
 * // Or register individual services
 * $container->set('response_emitter', ServiceRegistry::responseEmitter());
 * ```
 *
 * @see LegacyBridge\Container\LegacyContainer
 * @see docs/adr/003-no-autowiring-or-reflection.md
 */
final class ServiceRegistry
{
    /**
     * Register all default services into a container.
     *
     * This method registers:
     * - response_emitter: ResponseEmitter instance
     * - request_factory_bridge: RequestFactory wrapper
     *
     * Note: PSR-7 factories (request_factory, stream_factory, uploaded_file_factory)
     * must be registered separately as they depend on external PSR-7 implementation.
     *
     * @param LegacyContainer $container The container to register services into
     * @return void
     */
    public static function registerDefaults(LegacyContainer $container): void
    {
        self::registerResponseEmitter($container);
        self::registerRequestFactory($container);
    }

    /**
     * Register the ResponseEmitter service.
     *
     * The ResponseEmitter handles explicit HTTP response emission.
     * This aligns with ADR-004 (Explicit Response Emission).
     *
     * Service ID: 'response_emitter'
     *
     * @param LegacyContainer $container The container to register into
     * @return void
     */
    public static function registerResponseEmitter(LegacyContainer $container): void
    {
        $container->set('response_emitter', fn() => new ResponseEmitter());
    }

    /**
     * Register the RequestFactory service.
     *
     * The RequestFactory converts PHP superglobals into PSR-7 ServerRequests.
     *
     * Note: This requires PSR-7 factories to be registered first:
     * - request_factory (PSR-7 ServerRequestFactory)
     * - stream_factory (PSR-7 StreamFactory)
     * - uploaded_file_factory (PSR-7 UploadedFileFactory)
     *
     * Service ID: 'request_factory_bridge'
     *
     * @param LegacyContainer $container The container to register into
     * @return void
     *
     * @throws \Psr\Container\NotFoundExceptionInterface If PSR factories not registered
     */
    public static function registerRequestFactory(LegacyContainer $container): void
    {
        $container->set(
            'request_factory_bridge',
            function(ContainerInterface $c) {
                return new RequestFactory(
                    $c->get('request_factory'),
                    $c->get('stream_factory'),
                    $c->get('uploaded_file_factory')
                );
            }
        );
    }

    /**
     * Factory method to create a ResponseEmitter.
     *
     * Can be used standalone or passed to container registration.
     *
     * Usage:
     * ```php
     * $container->set('response_emitter', ServiceRegistry::responseEmitter());
     * ```
     *
     * @return callable Factory function that returns ResponseEmitter
     */
    public static function responseEmitter(): callable
    {
        return fn() => new ResponseEmitter();
    }

    /**
     * Factory method to create a RequestFactory.
     *
     * Requires PSR-7 factories to be available in the container.
     *
     * Usage:
     * ```php
     * $container->set('request_factory_bridge', ServiceRegistry::requestFactory());
     * ```
     *
     * @return callable Factory function that returns RequestFactory
     */
    public static function requestFactory(): callable
    {
        return function(ContainerInterface $c) {
            return new RequestFactory(
                $c->get('request_factory'),
                $c->get('stream_factory'),
                $c->get('uploaded_file_factory')
            );
        };
    }

    /**
     * Private constructor to prevent instantiation.
     *
     * This is a registry of static methods, not meant to be instantiated.
     */
    private function __construct()
    {
    }
}