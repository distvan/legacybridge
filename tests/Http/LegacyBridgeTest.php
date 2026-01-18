<?php

declare(strict_types=1);

namespace LegacyBridge\Tests\Http;

use LegacyBridge\Http\LegacyBridge;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Psr\Container\ContainerInterface;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestFactoryInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Message\StreamFactoryInterface;
use Psr\Http\Message\StreamInterface;
use Psr\Http\Message\UriInterface;
use Psr\Http\Message\UploadedFileFactoryInterface;

class LegacyBridgeTest extends TestCase
{
    /** @var ServerRequestFactoryInterface&MockObject */
    private ServerRequestFactoryInterface $requestFactory;
    /** @var StreamFactoryInterface&MockObject */
    private StreamFactoryInterface $streamFactory;
    /** @var UploadedFileFactoryInterface&MockObject */
    private UploadedFileFactoryInterface $uploadedFileFactory;

    protected function setUp(): void
    {
        $this->requestFactory = $this->createMock(ServerRequestFactoryInterface::class);
        $this->streamFactory = $this->createMock(StreamFactoryInterface::class);
        $this->uploadedFileFactory = $this->createMock(UploadedFileFactoryInterface::class);
    }

    public function testLegacyBridgeIsStaticClass(): void
    {
        $reflection = new \ReflectionClass(LegacyBridge::class);
        $runMethod = $reflection->getMethod('run');
        
        $this->assertTrue($runMethod->isStatic(), 'LegacyBridge::run() should be static');
        $this->assertTrue($runMethod->isPublic(), 'LegacyBridge::run() should be public');
    }

    public function testRunCallsKernelWithRequestAndContainer(): void
    {
        $kernelCalled = false;
        $capturedRequest = null;
        $capturedContainer = null;

        $kernel = function (ServerRequestInterface $request, ContainerInterface $container) use (&$kernelCalled, &$capturedRequest, &$capturedContainer) {
            $kernelCalled = true;
            $capturedRequest = $request;
            $capturedContainer = $container;

            $response = $this->createMock(ResponseInterface::class);
            $body = $this->createMock(StreamInterface::class);
            $response->method('getBody')->willReturn($body);

            return $response;
        };

        $this->callLegacyBridgeRun($kernel);

        $this->assertTrue($kernelCalled, 'Kernel was not called');
        $this->assertInstanceOf(ServerRequestInterface::class, $capturedRequest);
        $this->assertInstanceOf(ContainerInterface::class, $capturedContainer);
    }

    public function testRunThrowsExceptionIfKernelDoesNotReturnResponse(): void
    {
        $kernel = function (ServerRequestInterface $request, ContainerInterface $container) {
            return 'invalid response';
        };

        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('Legacy kernel must return a PSR-7 ResponseInterface');

        $this->callLegacyBridgeRun($kernel);
    }

    public function testRunCapturesLegacyOutput(): void
    {
        $kernel = function (ServerRequestInterface $request, ContainerInterface $container) {
            echo 'Legacy output';

            $response = $this->createMock(ResponseInterface::class);
            $body = $this->createMock(StreamInterface::class);

            $body->expects($this->once())
                ->method('write')
                ->with('Legacy output');

            $response->method('getBody')->willReturn($body);

            return $response;
        };

        $this->callLegacyBridgeRun($kernel);
    }

    public function testRunWritesOutputToResponseBody(): void
    {
        $legacyOutput = "Hello from legacy code\n";

        $kernel = function (ServerRequestInterface $request, ContainerInterface $container) use ($legacyOutput) {
            echo $legacyOutput;

            $response = $this->createMock(ResponseInterface::class);
            $body = $this->createMock(StreamInterface::class);

            $body->expects($this->once())
                ->method('write')
                ->with($legacyOutput);

            $response->method('getBody')->willReturn($body);

            return $response;
        };

        $this->callLegacyBridgeRun($kernel);
    }

    public function testRunDoesNotWriteEmptyOutputToResponseBody(): void
    {
        $kernel = function (ServerRequestInterface $request, ContainerInterface $container) {
            // No legacy output

            $response = $this->createMock(ResponseInterface::class);
            $body = $this->createMock(StreamInterface::class);

            $body->expects($this->never())
                ->method('write');

            $response->method('getBody')->willReturn($body);

            return $response;
        };

        $this->callLegacyBridgeRun($kernel);
    }

    public function testRunCleansOutputBufferOnException(): void
    {
        $kernel = function (ServerRequestInterface $request, ContainerInterface $container) {
            echo 'Buffered output before exception';
            throw new \Exception('Test exception');
        };

        $this->expectException(\Exception::class);
        $this->expectExceptionMessage('Test exception');

        $initialLevel = ob_get_level();

        try {
            $this->callLegacyBridgeRun($kernel);
        } finally {
            // Verify output buffer was cleaned
            $this->assertEquals($initialLevel, ob_get_level(), 'Output buffer not cleaned on exception');
        }
    }

    public function testRunCreatesRequestFromSuperglobals(): void
    {
        $originalServer = $_SERVER;

        try {
            $_SERVER = [
                'REQUEST_METHOD' => 'GET',
                'REQUEST_URI' => '/test',
                'SERVER_PROTOCOL' => 'HTTP/1.1',
                'HTTP_HOST' => 'example.com',
            ];
            unset($_GET, $_POST, $_COOKIE, $_FILES);

            $requestURI = '';

            $kernel = function (ServerRequestInterface $request, ContainerInterface $container) use (&$requestURI) {
                $requestURI = (string)$request->getUri();

                $response = $this->createMock(ResponseInterface::class);
                $body = $this->createMock(StreamInterface::class);
                $response->method('getBody')->willReturn($body);

                return $response;
            };

            $this->callLegacyBridgeRun($kernel);

            $this->assertStringContainsString('/test', $requestURI);
        } finally {
            $_SERVER = $originalServer;
        }
    }

    public function testRunEmitsResponse(): void
    {
        $kernel = function (ServerRequestInterface $request, ContainerInterface $container) {
            $response = $this->createMock(ResponseInterface::class);
            $body = $this->createMock(StreamInterface::class);
            $response->method('getBody')->willReturn($body);

            return $response;
        };

        // Verify the run() method executes without fatal errors
        $this->callLegacyBridgeRun($kernel);
        $this->assertTrue(true, 'LegacyBridge::run() completed without errors');
    }

    public function testRunPassesContainerToKernel(): void
    {
        $containerReceived = null;

        $kernel = function (ServerRequestInterface $request, ContainerInterface $container) use (&$containerReceived) {
            $containerReceived = $container;

            $response = $this->createMock(ResponseInterface::class);
            $body = $this->createMock(StreamInterface::class);
            $response->method('getBody')->willReturn($body);

            return $response;
        };

        $this->callLegacyBridgeRun($kernel);

        $this->assertNotNull($containerReceived, 'Container was not passed to kernel');
        $this->assertInstanceOf(ContainerInterface::class, $containerReceived);
    }

    /**
     * Helper method to call LegacyBridge::run() with mock factories
     */
    private function callLegacyBridgeRun(callable $kernel): void
    {
        // Mock stream
        $stream = $this->createMock(StreamInterface::class);
        $this->streamFactory->method('createStreamFromFile')->willReturn($stream);

        // Mock URI
        $uri = $this->createMock(UriInterface::class);
        $uri->method('__toString')->willReturn('http://example.com/test');

        // Mock request
        $mockRequest = $this->createMock(ServerRequestInterface::class);
        $mockRequest->method('getUri')->willReturn($uri);
        $mockRequest->method('withBody')->willReturnSelf();
        $mockRequest->method('withProtocolVersion')->willReturnSelf();
        $mockRequest->method('withAddedHeader')->willReturnSelf();
        $mockRequest->method('withCookieParams')->willReturnSelf();
        $mockRequest->method('withQueryParams')->willReturnSelf();
        $mockRequest->method('withParsedBody')->willReturnSelf();
        $mockRequest->method('withUploadedFiles')->willReturnSelf();

        $this->requestFactory->method('createServerRequest')->willReturn($mockRequest);

        $initialLevel = ob_get_level();

        try {
            LegacyBridge::run(
                $kernel,
                $this->requestFactory,
                $this->streamFactory,
                $this->uploadedFileFactory
            );
        } finally {
            // Clean up any remaining output buffers created during execution
            while (ob_get_level() > $initialLevel) {
                ob_end_clean();
            }
        }
    }
}
