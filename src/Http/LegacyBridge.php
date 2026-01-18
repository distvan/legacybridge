<?php

declare(strict_types=1);

namespace LegacyBridge\Http;

use LegacyBridge\Container\LegacyContainer;
use LegacyBridge\Internal\Exception\InvalidResponseException;
use Psr\Container\ContainerInterface;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Message\ServerRequestFactoryInterface;
use Psr\Http\Message\StreamFactoryInterface;
use Psr\Http\Message\UploadedFileFactoryInterface;
use Throwable;

/**
 * Entry point for executing a legacy PHP application
 * within a PSR-7 request lifecycle.
 *
 * This class is part of the public API.
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
     * - Starts output buffering
     * - Emits HTTP headers and response body
     * @param callable(ServerRequestInterface, ContainerInterface): ResponseInterface $kernel
     * @param ServerRequestFactoryInterface|null $requestFactory
     * @param StreamFactoryInterface|null $streamFactory
     * @param UploadedFileFactoryInterface|null $uploadedFileFactory
     * @throws InvalidResponseException If kernel does not return ResponseInterface
     */
    public static function run(
        callable $kernel,
        ?ServerRequestFactoryInterface $requestFactory = null,
        ?StreamFactoryInterface $streamFactory = null,
        ?UploadedFileFactoryInterface $uploadedFileFactory = null
    ): void {
        // Create factories with defaults or use provided ones
        $requestFactory = $requestFactory ?? self::createDefaultRequestFactory();
        $streamFactory = $streamFactory ?? self::createDefaultStreamFactory();
        $uploadedFileFactory = $uploadedFileFactory ?? self::createDefaultUploadedFileFactory();

        $factory = new RequestFactory($requestFactory, $streamFactory, $uploadedFileFactory);
        $responseEmitter = new ResponseEmitter();
        $container = new LegacyContainer();

        $request = $factory->fromGlobals();

        // Capture legacy output
        ob_start();

        try {
            $response = $kernel($request, $container);

            if (!$response instanceof ResponseInterface) {
                throw InvalidResponseException::notAResponse($response);
            }
        } catch (Throwable $e) {
            ob_end_clean();
            throw $e;
        }

        $output = ob_get_clean();

        if ($output !== '') {
            $body = $response->getBody();
            $body->write($output);
        }

        $responseEmitter->emit($response);
    }

    /**
     * Create a default ServerRequestFactory.
     * Must be implemented by a concrete PSR-7 implementation.
     *
     * @internal
     */
    private static function createDefaultRequestFactory(): ServerRequestFactoryInterface
    {
        throw new \RuntimeException(
            'No PSR-7 implementation found. Please provide a ServerRequestFactoryInterface'
        );
    }

    /**
     * Create a default StreamFactory.
     * Must be implemented by a concrete PSR-7 implementation.
     *
     * @internal
     */
    private static function createDefaultStreamFactory(): StreamFactoryInterface
    {
        throw new \RuntimeException(
            'No PSR-7 implementation found. Please provide a StreamFactoryInterface'
        );
    }

    /**
     * Create a default UploadedFileFactory.
     * Must be implemented by a concrete PSR-7 implementation.
     *
     * @internal
     */
    private static function createDefaultUploadedFileFactory(): UploadedFileFactoryInterface
    {
        throw new \RuntimeException(
            'No PSR-7 implementation found. Please provide an UploadedFileFactoryInterface'
        );
    }
}
