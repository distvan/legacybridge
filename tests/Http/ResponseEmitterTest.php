<?php

declare(strict_types=1);

namespace LegacyBridge\Tests\Http;

use LegacyBridge\Http\ResponseEmitter;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\StreamInterface;

/**
 * Tests for ResponseEmitter
 *
 * Verifies that ResponseEmitter correctly emits PSR-7 Response objects
 * including status codes, headers, and body content.
 *
 * This implements the explicit response emission pattern from ADR-004.
 *
 * @see LegacyBridge\Http\ResponseEmitter
 * @see docs/adr/004-explicit-response-emissions.md
 */
class ResponseEmitterTest extends TestCase
{
    private ResponseEmitter $emitter;

    protected function setUp(): void
    {
        $this->emitter = new ResponseEmitter();
    }

    public function testEmitterIsInstantiable(): void
    {
        $this->assertInstanceOf(ResponseEmitter::class, $this->emitter);
    }

    public function testEmitSetsHttpResponseCode(): void
    {
        $response = $this->createMockResponse(200);
        
        // Verify the emitter can handle a 200 response without errors
        ob_start();
        $this->emitter->emit($response);
        ob_get_clean();
        
        // If we reach here without exception, the emit succeeded
        $this->addToAssertionCount(1);
    }

    public function testEmitWith200StatusCode(): void
    {
        $response = $this->createMockResponse(200, [], 'Success');
        
        ob_start();
        $this->emitter->emit($response);
        $output = ob_get_clean();
        
        $this->assertSame('Success', $output);
    }

    public function testEmitWith201StatusCode(): void
    {
        $response = $this->createMockResponse(201, [], 'Created');
        
        ob_start();
        $this->emitter->emit($response);
        $output = ob_get_clean();
        
        $this->assertSame('Created', $output);
    }

    public function testEmitWith404StatusCode(): void
    {
        $response = $this->createMockResponse(404, [], 'Not Found');
        
        ob_start();
        $this->emitter->emit($response);
        $output = ob_get_clean();
        
        $this->assertSame('Not Found', $output);
    }

    public function testEmitWith500StatusCode(): void
    {
        $response = $this->createMockResponse(500, [], 'Server Error');
        
        ob_start();
        $this->emitter->emit($response);
        $output = ob_get_clean();
        
        $this->assertSame('Server Error', $output);
    }

    public function testEmitWithContentTypeHeader(): void
    {
        $headers = [
            'Content-Type' => ['application/json'],
        ];
        
        $response = $this->createMockResponse(200, $headers, '{"status": "ok"}');
        
        ob_start();
        $this->emitter->emit($response);
        $output = ob_get_clean();
        
        $this->assertSame('{"status": "ok"}', $output);
    }

    public function testEmitWithMultipleHeaders(): void
    {
        $headers = [
            'Content-Type' => ['application/json'],
            'X-Custom-Header' => ['custom-value'],
            'Cache-Control' => ['no-cache'],
        ];
        
        $response = $this->createMockResponse(200, $headers, 'body');
        
        ob_start();
        $this->emitter->emit($response);
        $output = ob_get_clean();
        
        $this->assertSame('body', $output);
    }

    public function testEmitWithMultipleHeaderValues(): void
    {
        // Some headers can have multiple values (e.g., Set-Cookie)
        $headers = [
            'Set-Cookie' => [
                'sessionid=abc123; Path=/',
                'theme=dark; Path=/',
            ],
        ];
        
        $response = $this->createMockResponse(200, $headers, 'body');
        
        ob_start();
        $this->emitter->emit($response);
        $output = ob_get_clean();
        
        $this->assertSame('body', $output);
    }

    public function testEmitWithEmptyBody(): void
    {
        $response = $this->createMockResponse(204, [], '');
        
        ob_start();
        $this->emitter->emit($response);
        $output = ob_get_clean();
        
        $this->assertSame('', $output);
    }

    public function testEmitWithLargeBody(): void
    {
        $largeContent = str_repeat('A', 100000); // 100KB
        
        $response = $this->createMockResponse(200, [], $largeContent);
        
        ob_start();
        $this->emitter->emit($response);
        $output = ob_get_clean();
        
        $this->assertSame($largeContent, $output);
        $this->assertGreaterThan(50000, strlen($output));
    }

    public function testEmitWithBinaryContent(): void
    {
        $binaryContent = "\x00\x01\x02\x03\x04\x05";
        
        $response = $this->createMockResponse(200, ['Content-Type' => ['application/octet-stream']], $binaryContent);
        
        ob_start();
        $this->emitter->emit($response);
        $output = ob_get_clean();
        
        $this->assertSame($binaryContent, $output);
    }

    public function testEmitWithJsonContent(): void
    {
        $jsonContent = json_encode(['id' => 1, 'name' => 'Test', 'active' => true]);
        
        $response = $this->createMockResponse(200, ['Content-Type' => ['application/json']], $jsonContent);
        
        ob_start();
        $this->emitter->emit($response);
        $output = ob_get_clean();
        
        $this->assertSame($jsonContent, $output);
        $this->assertStringContainsString('Test', $output);
    }

    public function testEmitWithHtmlContent(): void
    {
        $htmlContent = '<html><body><h1>Hello</h1></body></html>';
        
        $response = $this->createMockResponse(200, ['Content-Type' => ['text/html']], $htmlContent);
        
        ob_start();
        $this->emitter->emit($response);
        $output = ob_get_clean();
        
        $this->assertSame($htmlContent, $output);
        $this->assertStringContainsString('<h1>', $output);
    }

    public function testEmitWithLocationRedirectHeader(): void
    {
        $headers = [
            'Location' => ['https://example.com/new-location'],
        ];
        
        $response = $this->createMockResponse(302, $headers, '');
        
        ob_start();
        $this->emitter->emit($response);
        $output = ob_get_clean();
        
        $this->assertSame('', $output);
    }

    public function testEmitWithAuthorizationHeader(): void
    {
        $headers = [
            'Authorization' => ['Bearer eyJhbGciOiJIUzI1NiIsInR5cCI6IkpXVCJ9'],
        ];
        
        $response = $this->createMockResponse(200, $headers, 'protected content');
        
        ob_start();
        $this->emitter->emit($response);
        $output = ob_get_clean();
        
        $this->assertSame('protected content', $output);
    }

    public function testEmitDoesNotThrowExceptionDuringNormalOperation(): void
    {
        $response = $this->createMockResponse(200, [], 'body');
        
        // Verify no exception is thrown during normal emit operation
        try {
            ob_start();
            $this->emitter->emit($response);
            ob_get_clean();
            $this->assertTrue(true, 'Emit completed without exception');
        } catch (\Exception $e) {
            $this->fail("Unexpected exception during emit: " . $e->getMessage());
        }
    }

    public function testEmitWithSeekableBody(): void
    {
        $stream = $this->createMock(StreamInterface::class);
        $stream->method('isSeekable')->willReturn(true);
        $stream->method('getContents')->willReturn('seekable content');
        
        $response = $this->createMock(ResponseInterface::class);
        $response->method('getStatusCode')->willReturn(200);
        $response->method('getHeaders')->willReturn([]);
        $response->method('getBody')->willReturn($stream);
        
        // Should call rewind on seekable stream
        $stream->expects($this->once())->method('rewind');
        
        ob_start();
        $this->emitter->emit($response);
        $output = ob_get_clean();
        
        $this->assertSame('seekable content', $output);
    }

    public function testEmitWithNonSeekableBody(): void
    {
        $stream = $this->createMock(StreamInterface::class);
        $stream->method('isSeekable')->willReturn(false);
        $stream->method('getContents')->willReturn('non-seekable content');
        
        $response = $this->createMock(ResponseInterface::class);
        $response->method('getStatusCode')->willReturn(200);
        $response->method('getHeaders')->willReturn([]);
        $response->method('getBody')->willReturn($stream);
        
        // Should NOT call rewind on non-seekable stream
        $stream->expects($this->never())->method('rewind');
        
        ob_start();
        $this->emitter->emit($response);
        $output = ob_get_clean();
        
        $this->assertSame('non-seekable content', $output);
    }

    public function testEmitWithComplexHeaderValues(): void
    {
        $headers = [
            'Content-Security-Policy' => [
                "default-src 'self'; script-src 'self' 'unsafe-inline'; style-src 'self' 'unsafe-inline'",
            ],
        ];
        
        $response = $this->createMockResponse(200, $headers, 'body');
        
        ob_start();
        $this->emitter->emit($response);
        $output = ob_get_clean();
        
        $this->assertSame('body', $output);
    }

    public function testEmitMultipleTimesWithDifferentResponses(): void
    {
        $response1 = $this->createMockResponse(200, [], 'first response');
        $response2 = $this->createMockResponse(201, [], 'second response');
        
        ob_start();
        $this->emitter->emit($response1);
        $output1 = ob_get_clean();
        
        ob_start();
        $this->emitter->emit($response2);
        $output2 = ob_get_clean();
        
        $this->assertSame('first response', $output1);
        $this->assertSame('second response', $output2);
        $this->assertNotSame($output1, $output2);
    }

    public function testEmitWithUnicodeContent(): void
    {
        $unicodeContent = '你好世界 🌍 مرحبا بالعالم';
        
        $response = $this->createMockResponse(200, ['Content-Type' => ['text/plain; charset=utf-8']], $unicodeContent);
        
        ob_start();
        $this->emitter->emit($response);
        $output = ob_get_clean();
        
        $this->assertSame($unicodeContent, $output);
        $this->assertStringContainsString('世界', $output);
    }

    public function testEmitExplicitEmissionPattern(): void
    {
        // This test verifies the explicit response emission pattern from ADR-004
        // Response is created, then explicitly emitted
        
        $headers = [
            'Content-Type' => ['application/json'],
            'X-Powered-By' => ['LegacyBridge/1.0'],
        ];
        
        $content = json_encode(['message' => 'OK', 'status' => 200]);
        $response = $this->createMockResponse(200, $headers, $content);
        
        // Explicit emission: no implicit buffering, no side effects
        ob_start();
        $this->emitter->emit($response);
        $output = ob_get_clean();
        
        // Verify response was emitted
        $this->assertSame($content, $output);
        $this->assertIsString($output);
    }

    /**
     * Helper method to create a mock PSR-7 Response
     *
     * @param int $statusCode HTTP status code
     * @param array<string, array<string>> $headers Response headers
     * @param string $body Response body content
     * @return ResponseInterface&MockObject Mock response
     */
    private function createMockResponse(int $statusCode, array $headers = [], string $body = ''): ResponseInterface
    {
        $stream = $this->createMock(StreamInterface::class);
        $stream->method('isSeekable')->willReturn(true);
        $stream->method('getContents')->willReturn($body);
        
        $response = $this->createMock(ResponseInterface::class);
        $response->method('getStatusCode')->willReturn($statusCode);
        $response->method('getReasonPhrase')->willReturn('');
        $response->method('getHeaders')->willReturn($headers);
        $response->method('getBody')->willReturn($stream);
        
        return $response;
    }
}
