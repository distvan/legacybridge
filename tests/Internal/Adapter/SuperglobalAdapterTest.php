<?php

declare(strict_types=1);

namespace LegacyBridge\Tests\Internal\Adapter;

use LegacyBridge\Internal\Adapter\SuperglobalAdapter;
use PHPUnit\Framework\TestCase;

/**
 * Tests for SuparglobalAdapter
 *
 * Verifies safe, type-checked access to PHP superglobals
 * with proper defaults and normalization.
 *
 * @internal
 */
class SuperglobalAdapterTest extends TestCase
{
    private array $originalServer;

    protected function setUp(): void
    {
        // Save original $_SERVER with all its keys
        $this->originalServer = $_SERVER;
    }

    protected function tearDown(): void
    {
        // Restore $_SERVER exactly as it was
        $_SERVER = $this->originalServer;
    }

    private function setServer(array $values): void
    {
        // Clear all existing keys first
        foreach (array_keys($_SERVER) as $key) {
            unset($_SERVER[$key]);
        }
        // Set only the values we want
        foreach ($values as $key => $value) {
            $_SERVER[$key] = $value;
        }
    }

    public function testGetHttpMethodDefault(): void
    {
        $this->setServer([]);
        
        $method = SuperglobalAdapter::getHttpMethod();
        $this->assertSame('GET', $method);
    }

    public function testGetHttpMethodFromServer(): void
    {
        $this->setServer(['REQUEST_METHOD' => 'POST']);
        
        $method = SuperglobalAdapter::getHttpMethod();
        $this->assertSame('POST', $method);
    }

    public function testGetHttpMethodNormalizesCase(): void
    {
        $this->setServer(['REQUEST_METHOD' => 'post']);
        
        $method = SuperglobalAdapter::getHttpMethod();
        $this->assertSame('POST', $method);
    }

    public function testGetRequestUriDefault(): void
    {
        $this->setServer([]);
        
        $uri = SuperglobalAdapter::getRequestUri();
        $this->assertSame('/', $uri);
    }

    public function testGetRequestUriFromServer(): void
    {
        $this->setServer(['REQUEST_URI' => '/api/users?page=1']);
        
        $uri = SuperglobalAdapter::getRequestUri();
        $this->assertSame('/api/users?page=1', $uri);
    }

    public function testGetServerNameFromHttpHost(): void
    {
        $this->setServer([
            'HTTP_HOST' => 'example.com',
            'SERVER_NAME' => 'fallback.com',
        ]);
        
        $name = SuperglobalAdapter::getServerName();
        $this->assertSame('example.com', $name);
    }

    public function testGetServerNameFallbackToServerName(): void
    {
        $this->setServer(['SERVER_NAME' => 'fallback.com']);
        
        $name = SuperglobalAdapter::getServerName();
        $this->assertSame('fallback.com', $name);
    }

    public function testGetServerNameDefault(): void
    {
        $this->setServer([]);
        
        $name = SuperglobalAdapter::getServerName();
        $this->assertSame('localhost', $name);
    }

    public function testGetProtocolVersionDefault(): void
    {
        $this->setServer([]);
        
        $version = SuperglobalAdapter::getProtocolVersion();
        $this->assertSame('1.1', $version);
    }

    public function testGetProtocolVersionHttp10(): void
    {
        $this->setServer(['SERVER_PROTOCOL' => 'HTTP/1.0']);
        
        $version = SuperglobalAdapter::getProtocolVersion();
        $this->assertSame('1.0', $version);
    }

    public function testGetProtocolVersionHttp11(): void
    {
        $this->setServer(['SERVER_PROTOCOL' => 'HTTP/1.1']);
        
        $version = SuperglobalAdapter::getProtocolVersion();
        $this->assertSame('1.1', $version);
    }

    public function testGetProtocolVersionHttp2(): void
    {
        $this->setServer(['SERVER_PROTOCOL' => 'HTTP/2.0']);
        
        $version = SuperglobalAdapter::getProtocolVersion();
        $this->assertSame('2.0', $version);
    }

    public function testIsHttpsReturnsFalseByDefault(): void
    {
        $this->setServer([]);
        
        $isHttps = SuperglobalAdapter::isHttps();
        $this->assertFalse($isHttps);
    }

    public function testIsHttpsDetectsDirectHttps(): void
    {
        $this->setServer(['HTTPS' => 'on']);
        
        $isHttps = SuperglobalAdapter::isHttps();
        $this->assertTrue($isHttps);
    }

    public function testIsHttpsIgnoresOffHttps(): void
    {
        $this->setServer(['HTTPS' => 'off']);
        
        $isHttps = SuperglobalAdapter::isHttps();
        $this->assertFalse($isHttps);
    }

    public function testIsHttpsDetectsXForwardedProto(): void
    {
        $this->setServer(['HTTP_X_FORWARDED_PROTO' => 'https']);
        
        $isHttps = SuperglobalAdapter::isHttps();
        $this->assertTrue($isHttps);
    }

    public function testIsHttpsIgnoresCaseInXForwardedProto(): void
    {
        $this->setServer(['HTTP_X_FORWARDED_PROTO' => 'HTTPS']);
        
        $isHttps = SuperglobalAdapter::isHttps();
        $this->assertTrue($isHttps);
    }

    public function testIsHttpsIgnoresHttpInXForwardedProto(): void
    {
        $this->setServer(['HTTP_X_FORWARDED_PROTO' => 'http']);
        
        $isHttps = SuperglobalAdapter::isHttps();
        $this->assertFalse($isHttps);
    }

    public function testIsHttpsDetectsRequestScheme(): void
    {
        $this->setServer(['REQUEST_SCHEME' => 'https']);
        
        $isHttps = SuperglobalAdapter::isHttps();
        $this->assertTrue($isHttps);
    }

    public function testIsHttpsIgnoresHttpRequestScheme(): void
    {
        $this->setServer(['REQUEST_SCHEME' => 'http']);
        
        $isHttps = SuperglobalAdapter::isHttps();
        $this->assertFalse($isHttps);
    }

    public function testGetPortDefault(): void
    {
        $this->setServer([]);
        
        $port = SuperglobalAdapter::getPort();
        $this->assertSame(80, $port);
    }

    public function testGetPortHttpsDefault(): void
    {
        $this->setServer(['HTTPS' => 'on']);
        
        $port = SuperglobalAdapter::getPort();
        $this->assertSame(443, $port);
    }

    public function testGetPortFromServer(): void
    {
        $this->setServer(['SERVER_PORT' => '8080']);
        
        $port = SuperglobalAdapter::getPort();
        $this->assertSame(8080, $port);
    }

    public function testGetPortFromXForwardedPort(): void
    {
        $this->setServer(['HTTP_X_FORWARDED_PORT' => '443']);
        
        $port = SuperglobalAdapter::getPort();
        $this->assertSame(443, $port);
    }

    public function testGetPortPrefersServerPort(): void
    {
        $this->setServer([
            'SERVER_PORT' => '8080',
            'HTTP_X_FORWARDED_PORT' => '443',
        ]);
        
        $port = SuperglobalAdapter::getPort();
        $this->assertSame(8080, $port);
    }

    public function testGetSuperglobalValue(): void
    {
        $array = ['key' => 'value', 'other' => 'data'];
        
        $value = SuperglobalAdapter::get($array, 'key');
        $this->assertSame('value', $value);
    }

    public function testGetSuperglobalDefault(): void
    {
        $array = ['key' => 'value'];
        
        $value = SuperglobalAdapter::get($array, 'missing', 'default');
        $this->assertSame('default', $value);
    }

    public function testGetSuperglobalReturnsMixedTypes(): void
    {
        $array = ['string' => 'value', 'int' => 42, 'array' => ['a' => 'b']];
        
        $this->assertSame('value', SuperglobalAdapter::get($array, 'string'));
        $this->assertSame(42, SuperglobalAdapter::get($array, 'int'));
        $this->assertSame(['a' => 'b'], SuperglobalAdapter::get($array, 'array'));
    }

    public function testHasSuperglobalKey(): void
    {
        $array = ['key' => 'value'];
        
        $this->assertTrue(SuperglobalAdapter::has($array, 'key'));
        $this->assertFalse(SuperglobalAdapter::has($array, 'missing'));
    }

    public function testHasSuperglobalRejectsMissingKey(): void
    {
        $array = [];
        
        $this->assertFalse(SuperglobalAdapter::has($array, 'missing'));
    }

    public function testHasSuperglobalRejectsEmptyValue(): void
    {
        $array = ['empty' => ''];
        
        $this->assertFalse(SuperglobalAdapter::has($array, 'empty'));
    }

    public function testHasSuperglobalRejectsZero(): void
    {
        $array = ['zero' => 0];
        
        $this->assertFalse(SuperglobalAdapter::has($array, 'zero'));
    }

    public function testHasSuperglobalAcceptsNonEmptyValue(): void
    {
        $array = ['value' => 'something'];
        
        $this->assertTrue(SuperglobalAdapter::has($array, 'value'));
    }
}
