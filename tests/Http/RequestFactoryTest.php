<?php

declare(strict_types=1);

namespace LegacyBridge\Tests\Http;

use LegacyBridge\Http\RequestFactory;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Psr\Http\Message\ServerRequestFactoryInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Message\StreamFactoryInterface;
use Psr\Http\Message\StreamInterface;
use Psr\Http\Message\UploadedFileFactoryInterface;
use Psr\Http\Message\UploadedFileInterface;

/**
 * Tests for RequestFactory
 *
 * Verifies that RequestFactory correctly converts PHP superglobals
 * into PSR-7 ServerRequest objects.
 *
 * @see LegacyBridge\Http\RequestFactory
 */
class RequestFactoryTest extends TestCase
{
    /** @var ServerRequestFactoryInterface&MockObject */
    private ServerRequestFactoryInterface $requestFactory;
    
    /** @var StreamFactoryInterface&MockObject */
    private StreamFactoryInterface $streamFactory;
    
    /** @var UploadedFileFactoryInterface&MockObject */
    private UploadedFileFactoryInterface $uploadedFileFactory;
    
    private RequestFactory $factory;
    
    /** @var array|null Original $_SERVER */
    private $originalServer;
    
    /** @var array|null Original $_GET */
    private $originalGet;
    
    /** @var array|null Original $_POST */
    private $originalPost;
    
    /** @var array|null Original $_COOKIE */
    private $originalCookie;
    
    /** @var array|null Original $_FILES */
    private $originalFiles;

    protected function setUp(): void
    {
        // Backup superglobals - using null coalescing to handle undefined indices
        $this->originalServer = $_SERVER ?? [];
        $this->originalGet = $_GET ?? [];
        $this->originalPost = $_POST ?? [];
        $this->originalCookie = $_COOKIE ?? [];
        $this->originalFiles = $_FILES ?? [];

        $this->requestFactory = $this->createMock(ServerRequestFactoryInterface::class);
        $this->streamFactory = $this->createMock(StreamFactoryInterface::class);
        $this->uploadedFileFactory = $this->createMock(UploadedFileFactoryInterface::class);
        
        $this->factory = new RequestFactory(
            $this->requestFactory,
            $this->streamFactory,
            $this->uploadedFileFactory
        );
    }

    protected function tearDown(): void
    {
        // Restore superglobals
        $_SERVER = $this->originalServer;
        $_GET = $this->originalGet;
        $_POST = $this->originalPost;
        $_COOKIE = $this->originalCookie;
        $_FILES = $this->originalFiles;
    }

    public function testFromGlobalsCreatesRequestInstance(): void
    {
        // Setup minimal superglobals
        $_SERVER = [
            'REQUEST_METHOD' => 'GET',
            'REQUEST_URI' => '/',
            'SERVER_PROTOCOL' => 'HTTP/1.1',
            'HTTP_HOST' => 'example.com',
        ];
        $_GET = [];
        $_POST = [];
        $_COOKIE = [];
        $_FILES = [];

        // Setup mocks with flexible expectations
        $stream = $this->createMock(StreamInterface::class);
        $this->streamFactory->method('createStreamFromFile')
            ->willReturn($stream);

        $mockRequest = $this->createFluentMockRequest();
        $this->requestFactory->method('createServerRequest')
            ->willReturn($mockRequest);

        $result = $this->factory->fromGlobals();

        $this->assertInstanceOf(ServerRequestInterface::class, $result);
    }

    public function testFromGlobalsHandlesGetRequest(): void
    {
        $_SERVER = [
            'REQUEST_METHOD' => 'GET',
            'REQUEST_URI' => '/test?foo=bar',
            'SERVER_PROTOCOL' => 'HTTP/1.1',
            'HTTP_HOST' => 'example.com',
            'HTTP_USER_AGENT' => 'TestAgent/1.0',
        ];
        $_GET = ['foo' => 'bar'];
        $_POST = [];
        $_COOKIE = [];
        $_FILES = [];

        $stream = $this->createMock(StreamInterface::class);
        $this->streamFactory->method('createStreamFromFile')->willReturn($stream);

        $mockRequest = $this->createFluentMockRequest();
        $this->requestFactory->method('createServerRequest')->willReturn($mockRequest);

        $result = $this->factory->fromGlobals();

        $this->assertInstanceOf(ServerRequestInterface::class, $result);
    }

    public function testFromGlobalsHandlesPostRequest(): void
    {
        $_SERVER = [
            'REQUEST_METHOD' => 'POST',
            'REQUEST_URI' => '/submit',
            'SERVER_PROTOCOL' => 'HTTP/1.1',
            'HTTP_HOST' => 'example.com',
            'CONTENT_TYPE' => 'application/x-www-form-urlencoded',
        ];
        $_GET = [];
        $_POST = ['username' => 'testuser', 'email' => 'test@example.com'];
        $_COOKIE = [];
        $_FILES = [];

        $stream = $this->createMock(StreamInterface::class);
        $this->streamFactory->method('createStreamFromFile')->willReturn($stream);

        $mockRequest = $this->createFluentMockRequest();
        $this->requestFactory->method('createServerRequest')->willReturn($mockRequest);

        $result = $this->factory->fromGlobals();

        $this->assertInstanceOf(ServerRequestInterface::class, $result);
    }

    public function testFromGlobalsHandlesPutRequest(): void
    {
        $_SERVER = [
            'REQUEST_METHOD' => 'PUT',
            'REQUEST_URI' => '/resource/123',
            'SERVER_PROTOCOL' => 'HTTP/1.1',
            'HTTP_HOST' => 'api.example.com',
            'CONTENT_TYPE' => 'application/json',
        ];
        $_GET = [];
        $_POST = [];
        $_COOKIE = [];
        $_FILES = [];

        $stream = $this->createMock(StreamInterface::class);
        $this->streamFactory->method('createStreamFromFile')->willReturn($stream);

        $mockRequest = $this->createFluentMockRequest();
        $this->requestFactory->method('createServerRequest')->willReturn($mockRequest);

        $result = $this->factory->fromGlobals();

        $this->assertInstanceOf(ServerRequestInterface::class, $result);
    }

    public function testFromGlobalsHandlesPatchRequest(): void
    {
        $_SERVER = [
            'REQUEST_METHOD' => 'PATCH',
            'REQUEST_URI' => '/resource/123',
            'SERVER_PROTOCOL' => 'HTTP/1.1',
            'HTTP_HOST' => 'api.example.com',
        ];
        $_GET = [];
        $_POST = [];
        $_COOKIE = [];
        $_FILES = [];

        $stream = $this->createMock(StreamInterface::class);
        $this->streamFactory->method('createStreamFromFile')->willReturn($stream);

        $mockRequest = $this->createFluentMockRequest();
        $this->requestFactory->method('createServerRequest')->willReturn($mockRequest);

        $result = $this->factory->fromGlobals();

        $this->assertInstanceOf(ServerRequestInterface::class, $result);
    }

    public function testFromGlobalsHandlesDeleteRequest(): void
    {
        $_SERVER = [
            'REQUEST_METHOD' => 'DELETE',
            'REQUEST_URI' => '/resource/123',
            'SERVER_PROTOCOL' => 'HTTP/1.1',
            'HTTP_HOST' => 'api.example.com',
        ];
        $_GET = [];
        $_POST = [];
        $_COOKIE = [];
        $_FILES = [];

        $stream = $this->createMock(StreamInterface::class);
        $this->streamFactory->method('createStreamFromFile')->willReturn($stream);

        $mockRequest = $this->createFluentMockRequest();
        $this->requestFactory->method('createServerRequest')->willReturn($mockRequest);

        $result = $this->factory->fromGlobals();

        $this->assertInstanceOf(ServerRequestInterface::class, $result);
    }

    public function testFromGlobalsHandlesHttpsScheme(): void
    {
        $_SERVER = [
            'REQUEST_METHOD' => 'GET',
            'REQUEST_URI' => '/',
            'SERVER_PROTOCOL' => 'HTTP/1.1',
            'HTTP_HOST' => 'example.com',
            'HTTPS' => 'on',
        ];
        $_GET = [];
        $_POST = [];
        $_COOKIE = [];
        $_FILES = [];

        $stream = $this->createMock(StreamInterface::class);
        $this->streamFactory->method('createStreamFromFile')->willReturn($stream);

        $mockRequest = $this->createFluentMockRequest();
        $this->requestFactory->method('createServerRequest')->willReturn($mockRequest);

        $result = $this->factory->fromGlobals();

        $this->assertInstanceOf(ServerRequestInterface::class, $result);
    }

    public function testFromGlobalsHandlesHttpsOff(): void
    {
        $_SERVER = [
            'REQUEST_METHOD' => 'GET',
            'REQUEST_URI' => '/',
            'SERVER_PROTOCOL' => 'HTTP/1.1',
            'HTTP_HOST' => 'example.com',
            'HTTPS' => 'off',
        ];
        $_GET = [];
        $_POST = [];
        $_COOKIE = [];
        $_FILES = [];

        $stream = $this->createMock(StreamInterface::class);
        $this->streamFactory->method('createStreamFromFile')->willReturn($stream);

        $mockRequest = $this->createFluentMockRequest();
        $this->requestFactory->method('createServerRequest')->willReturn($mockRequest);

        $result = $this->factory->fromGlobals();

        $this->assertInstanceOf(ServerRequestInterface::class, $result);
    }

    public function testFromGlobalsHandlesForwardedProto(): void
    {
        $_SERVER = [
            'REQUEST_METHOD' => 'GET',
            'REQUEST_URI' => '/',
            'SERVER_PROTOCOL' => 'HTTP/1.1',
            'HTTP_HOST' => 'example.com',
            'HTTP_X_FORWARDED_PROTO' => 'https',
        ];
        $_GET = [];
        $_POST = [];
        $_COOKIE = [];
        $_FILES = [];

        $stream = $this->createMock(StreamInterface::class);
        $this->streamFactory->method('createStreamFromFile')->willReturn($stream);

        $mockRequest = $this->createFluentMockRequest();
        $this->requestFactory->method('createServerRequest')->willReturn($mockRequest);

        $result = $this->factory->fromGlobals();

        $this->assertInstanceOf(ServerRequestInterface::class, $result);
    }

    public function testFromGlobalsHandlesCookies(): void
    {
        $_SERVER = [
            'REQUEST_METHOD' => 'GET',
            'REQUEST_URI' => '/',
            'SERVER_PROTOCOL' => 'HTTP/1.1',
            'HTTP_HOST' => 'example.com',
        ];
        $_GET = [];
        $_POST = [];
        $_COOKIE = ['sessionid' => 'abc123', 'theme' => 'dark'];
        $_FILES = [];

        $stream = $this->createMock(StreamInterface::class);
        $this->streamFactory->method('createStreamFromFile')->willReturn($stream);

        $mockRequest = $this->createFluentMockRequest();
        $this->requestFactory->method('createServerRequest')->willReturn($mockRequest);

        $result = $this->factory->fromGlobals();

        $this->assertInstanceOf(ServerRequestInterface::class, $result);
    }

    public function testFromGlobalsHandlesHeaders(): void
    {
        $_SERVER = [
            'REQUEST_METHOD' => 'GET',
            'REQUEST_URI' => '/',
            'SERVER_PROTOCOL' => 'HTTP/1.1',
            'HTTP_HOST' => 'example.com',
            'HTTP_USER_AGENT' => 'Mozilla/5.0',
            'HTTP_ACCEPT' => 'application/json',
            'CONTENT_TYPE' => 'application/json',
        ];
        $_GET = [];
        $_POST = [];
        $_COOKIE = [];
        $_FILES = [];

        $stream = $this->createMock(StreamInterface::class);
        $this->streamFactory->method('createStreamFromFile')->willReturn($stream);

        $mockRequest = $this->createFluentMockRequest();
        $this->requestFactory->method('createServerRequest')->willReturn($mockRequest);

        $result = $this->factory->fromGlobals();

        $this->assertInstanceOf(ServerRequestInterface::class, $result);
    }

    public function testFromGlobalsHandlesQueryParameters(): void
    {
        $_SERVER = [
            'REQUEST_METHOD' => 'GET',
            'REQUEST_URI' => '/?page=1&sort=asc',
            'SERVER_PROTOCOL' => 'HTTP/1.1',
            'HTTP_HOST' => 'example.com',
        ];
        $_GET = ['page' => '1', 'sort' => 'asc'];
        $_POST = [];
        $_COOKIE = [];
        $_FILES = [];

        $stream = $this->createMock(StreamInterface::class);
        $this->streamFactory->method('createStreamFromFile')->willReturn($stream);

        $mockRequest = $this->createFluentMockRequest();
        $this->requestFactory->method('createServerRequest')->willReturn($mockRequest);

        $result = $this->factory->fromGlobals();

        $this->assertInstanceOf(ServerRequestInterface::class, $result);
    }

    public function testFromGlobalsHandlesSingleUploadedFile(): void
    {
        $_SERVER = [
            'REQUEST_METHOD' => 'POST',
            'REQUEST_URI' => '/upload',
            'SERVER_PROTOCOL' => 'HTTP/1.1',
            'HTTP_HOST' => 'example.com',
        ];
        $_GET = [];
        $_POST = [];
        $_COOKIE = [];
        $_FILES = [
            'avatar' => [
                'name' => 'profile.jpg',
                'type' => 'image/jpeg',
                'tmp_name' => '/tmp/php123abc',
                'error' => 0,
                'size' => 12345,
            ],
        ];

        $stream = $this->createMock(StreamInterface::class);
        $this->streamFactory->method('createStreamFromFile')->willReturn($stream);

        $uploadedFile = $this->createMock(UploadedFileInterface::class);
        $this->uploadedFileFactory->method('createUploadedFile')->willReturn($uploadedFile);

        $mockRequest = $this->createFluentMockRequest();
        $this->requestFactory->method('createServerRequest')->willReturn($mockRequest);

        $result = $this->factory->fromGlobals();

        $this->assertInstanceOf(ServerRequestInterface::class, $result);
    }

    public function testFromGlobalsHandlesMultipleUploadedFiles(): void
    {
        $_SERVER = [
            'REQUEST_METHOD' => 'POST',
            'REQUEST_URI' => '/upload',
            'SERVER_PROTOCOL' => 'HTTP/1.1',
            'HTTP_HOST' => 'example.com',
        ];
        $_GET = [];
        $_POST = [];
        $_COOKIE = [];
        $_FILES = [
            'documents' => [
                'name' => ['doc1.pdf', 'doc2.pdf'],
                'type' => ['application/pdf', 'application/pdf'],
                'tmp_name' => ['/tmp/php123abc', '/tmp/php123def'],
                'error' => [0, 0],
                'size' => [50000, 60000],
            ],
        ];

        $stream = $this->createMock(StreamInterface::class);
        $this->streamFactory->method('createStreamFromFile')->willReturn($stream);

        $uploadedFile = $this->createMock(UploadedFileInterface::class);
        $this->uploadedFileFactory->method('createUploadedFile')->willReturn($uploadedFile);

        $mockRequest = $this->createFluentMockRequest();
        $this->requestFactory->method('createServerRequest')->willReturn($mockRequest);

        $result = $this->factory->fromGlobals();

        $this->assertInstanceOf(ServerRequestInterface::class, $result);
    }

    public function testFromGlobalsHandlesCustomPort(): void
    {
        $_SERVER = [
            'REQUEST_METHOD' => 'GET',
            'REQUEST_URI' => '/',
            'SERVER_PROTOCOL' => 'HTTP/1.1',
            'HTTP_HOST' => 'example.com',
            'SERVER_PORT' => '8080',
        ];
        $_GET = [];
        $_POST = [];
        $_COOKIE = [];
        $_FILES = [];

        $stream = $this->createMock(StreamInterface::class);
        $this->streamFactory->method('createStreamFromFile')->willReturn($stream);

        $mockRequest = $this->createFluentMockRequest();
        $this->requestFactory->method('createServerRequest')->willReturn($mockRequest);

        $result = $this->factory->fromGlobals();

        $this->assertInstanceOf(ServerRequestInterface::class, $result);
    }

    public function testFromGlobalsHandlesDefaultValues(): void
    {
        $_SERVER = [];
        $_GET = [];
        $_POST = [];
        $_COOKIE = [];
        $_FILES = [];

        $stream = $this->createMock(StreamInterface::class);
        $this->streamFactory->method('createStreamFromFile')->willReturn($stream);

        $mockRequest = $this->createFluentMockRequest();
        $this->requestFactory->method('createServerRequest')->willReturn($mockRequest);

        $result = $this->factory->fromGlobals();

        $this->assertInstanceOf(ServerRequestInterface::class, $result);
    }

    /**
     * Helper to create a mock ServerRequest with fluent interface
     * 
     * All with* methods return $this to allow method chaining
     * This is required because RequestFactory uses fluent interface
     */
    private function createFluentMockRequest(): ServerRequestInterface
    {
        $request = $this->createMock(ServerRequestInterface::class);
        
        // Configure all methods to return self for fluent interface
        $request->method('withBody')->willReturnSelf();
        $request->method('withProtocolVersion')->willReturnSelf();
        $request->method('withAddedHeader')->willReturnSelf();
        $request->method('withCookieParams')->willReturnSelf();
        $request->method('withQueryParams')->willReturnSelf();
        $request->method('withParsedBody')->willReturnSelf();
        $request->method('withUploadedFiles')->willReturnSelf();
        
        return $request;
    }
}
