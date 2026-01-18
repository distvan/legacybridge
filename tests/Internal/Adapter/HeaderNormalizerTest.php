<?php

declare(strict_types=1);

namespace LegacyBridge\Tests\Internal\Adapter;

use LegacyBridge\Internal\Adapter\HeaderNormalizer;
use PHPUnit\Framework\TestCase;

/**
 * Tests for HeaderNormalizer
 *
 * Verifies that PHP $_SERVER headers are correctly normalized
 * to PSR-7 format for HTTP request building.
 *
 * @internal
 */
class HeaderNormalizerTest extends TestCase
{
    public function testNormalizesHttpPrefix(): void
    {
        $server = ['HTTP_CONTENT_TYPE' => 'application/json'];
        
        $headers = HeaderNormalizer::normalize($server);
        
        $this->assertArrayHasKey('Content-Type', $headers);
        $this->assertSame(['application/json'], $headers['Content-Type']);
    }

    public function testNormalizesMultipleHeaders(): void
    {
        $server = [
            'HTTP_CONTENT_TYPE' => 'application/json',
            'HTTP_ACCEPT' => 'application/json',
            'HTTP_HOST' => 'example.com',
        ];
        
        $headers = HeaderNormalizer::normalize($server);
        
        $this->assertArrayHasKey('Content-Type', $headers);
        $this->assertArrayHasKey('Accept', $headers);
        $this->assertArrayHasKey('Host', $headers);
    }

    public function testNormalizesXHeaders(): void
    {
        $server = ['HTTP_X_FORWARDED_FOR' => '192.168.1.1'];
        
        $headers = HeaderNormalizer::normalize($server);
        
        $this->assertArrayHasKey('X-Forwarded-For', $headers);
        $this->assertSame(['192.168.1.1'], $headers['X-Forwarded-For']);
    }

    public function testNormalizesContentLength(): void
    {
        $server = ['CONTENT_LENGTH' => '1024'];
        
        $headers = HeaderNormalizer::normalize($server);
        
        $this->assertArrayHasKey('Content-Length', $headers);
        $this->assertSame(['1024'], $headers['Content-Length']);
    }

    public function testNormalizesContentType(): void
    {
        $server = ['CONTENT_TYPE' => 'application/x-www-form-urlencoded'];
        
        $headers = HeaderNormalizer::normalize($server);
        
        $this->assertArrayHasKey('Content-Type', $headers);
        $this->assertSame(['application/x-www-form-urlencoded'], $headers['Content-Type']);
    }

    public function testIgnoresNonHeaderKeys(): void
    {
        $server = [
            'REQUEST_METHOD' => 'GET',
            'REQUEST_URI' => '/path',
            'SCRIPT_NAME' => '/index.php',
            'SERVER_NAME' => 'localhost',
            'HTTP_HOST' => 'example.com',
        ];
        
        $headers = HeaderNormalizer::normalize($server);
        
        $this->assertArrayNotHasKey('REQUEST_METHOD', $headers);
        $this->assertArrayNotHasKey('REQUEST_URI', $headers);
        $this->assertArrayNotHasKey('SCRIPT_NAME', $headers);
        $this->assertArrayNotHasKey('SERVER_NAME', $headers);
        $this->assertArrayHasKey('Host', $headers);
    }

    public function testNormalizesHeaderNameCase(): void
    {
        $server = ['HTTP_ACCEPT_LANGUAGE' => 'en-US'];
        
        $headers = HeaderNormalizer::normalize($server);
        
        $this->assertArrayHasKey('Accept-Language', $headers);
    }

    public function testNormalizesMultipleUnderscores(): void
    {
        $server = ['HTTP_X_CUSTOM_HEADER_NAME' => 'value'];
        
        $headers = HeaderNormalizer::normalize($server);
        
        $this->assertArrayHasKey('X-Custom-Header-Name', $headers);
    }

    public function testPreservesHeaderValueCase(): void
    {
        $server = ['HTTP_ACCEPT' => 'Application/JSON; charset=UTF-8'];
        
        $headers = HeaderNormalizer::normalize($server);
        
        $this->assertSame(['Application/JSON; charset=UTF-8'], $headers['Accept']);
    }

    public function testMultipleHeaderValuesAsArray(): void
    {
        $server = [
            'HTTP_ACCEPT' => 'application/json',
            'HTTP_AUTHORIZATION' => 'Bearer token123',
        ];
        
        $headers = HeaderNormalizer::normalize($server);
        
        $this->assertIsArray($headers['Accept']);
        $this->assertIsArray($headers['Authorization']);
    }

    public function testHandlesEmptyServer(): void
    {
        $server = [];
        
        $headers = HeaderNormalizer::normalize($server);
        
        $this->assertIsArray($headers);
        $this->assertCount(0, $headers);
    }

    public function testHandlesNonHttpHeaders(): void
    {
        $server = [
            'PATH' => '/usr/bin',
            'HOME' => '/home/user',
            'HTTP_HOST' => 'example.com',
        ];
        
        $headers = HeaderNormalizer::normalize($server);
        
        $this->assertArrayNotHasKey('PATH', $headers);
        $this->assertArrayNotHasKey('HOME', $headers);
        $this->assertArrayHasKey('Host', $headers);
    }

    public function testShouldSendHeaderValidatesValue(): void
    {
        $this->assertTrue(HeaderNormalizer::shouldSendHeader('value'));
        $this->assertTrue(HeaderNormalizer::shouldSendHeader('Content-Type'));
        $this->assertFalse(HeaderNormalizer::shouldSendHeader(''));
        $this->assertFalse(HeaderNormalizer::shouldSendHeader('   '));
    }

    public function testNormalizesCommonHeaders(): void
    {
        $server = [
            'HTTP_USER_AGENT' => 'Mozilla/5.0',
            'HTTP_REFERER' => 'https://example.com',
            'HTTP_COOKIE' => 'session=abc123',
            'HTTP_ACCEPT_ENCODING' => 'gzip, deflate',
        ];
        
        $headers = HeaderNormalizer::normalize($server);
        
        $this->assertArrayHasKey('User-Agent', $headers);
        $this->assertArrayHasKey('Referer', $headers);
        $this->assertArrayHasKey('Cookie', $headers);
        $this->assertArrayHasKey('Accept-Encoding', $headers);
    }
}
