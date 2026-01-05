<?php

declare(strict_types=1);

namespace LegacyBridge\Http;

use Psr\Container\ContainerInterface;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;

/**
 * Entry point for executing a legacy PHP application
 * within a PSR-7 request lifecycle.
 *
 * This class is part of the pubilc API.
 *
 * Stability:
 * - Guaranteed stable in v1.x
 * - No breaking changes without a major version bump
 */
final class LegacyBridge
{
    /**
     * Bootstrap the legacy application inside a PSR-7 request lifecycle.
     *
     * This method is part of the public API and is guaranteed
     * to remain backward compatible within the v1.x series.
     *
     * Side effects:
     * - Reads PHP superglobals
     * - Emits HTTP headers and response body
     * @param callable(ServerRequestInterface, ContainerInterface): ResponseInterface $kernel
     */
    public static function run(callable $kernel): void
    {
        //@todo implemantations
        //create PSR-7 request
        //create container
        //execute legacy kernel
        //emit response
    }
}
