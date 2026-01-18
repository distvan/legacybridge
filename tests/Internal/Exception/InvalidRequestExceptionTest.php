<?php

declare(strict_types=1);

namespace LegacyBridge\Tests\Internal\Exception;

use LegacyBridge\Internal\Exception\InvalidRequestException;
use PHPUnit\Framework\TestCase;

/**
 * Tests for InvalidRequestException
 *
 * Verifies that request creation exceptions provide clear error messages
 * for debugging HTTP method and URI issues.
 *
 * @internal
 */
class InvalidRequestExceptionTest extends TestCase
{
    public function testExceptionIsRuntimeException(): void
    {
        $exception = InvalidRequestException::missingHttpMethod();
        $this->assertInstanceOf(\RuntimeException::class, $exception);
    }

    public function testExceptionIsThrowable(): void
    {
        $exception = InvalidRequestException::missingHttpMethod();
        $this->assertInstanceOf(\Throwable::class, $exception);
    }

    public function testMissingHttpMethodException(): void
    {
        $exception = InvalidRequestException::missingHttpMethod();
        
        $this->assertStringContainsString('HTTP method', $exception->getMessage());
        $this->assertStringContainsString('REQUEST_METHOD', $exception->getMessage());
    }

    public function testInvalidHttpMethodException(): void
    {
        $exception = InvalidRequestException::invalidHttpMethod('TRACE');
        
        $this->assertStringContainsString('TRACE', $exception->getMessage());
        $this->assertStringContainsString('Invalid HTTP method', $exception->getMessage());
    }

    public function testInvalidHttpMethodWithCustomMethod(): void
    {
        $exception = InvalidRequestException::invalidHttpMethod('CUSTOM_METHOD');
        
        $this->assertStringContainsString('CUSTOM_METHOD', $exception->getMessage());
    }

    public function testMalformedUriException(): void
    {
        $exception = InvalidRequestException::malformedUri('Invalid host');
        
        $this->assertStringContainsString('Invalid host', $exception->getMessage());
        $this->assertStringContainsString('Failed to create valid URI', $exception->getMessage());
    }

    public function testMalformedUriWithComplexReason(): void
    {
        $reason = 'Port must be between 1 and 65535, got 99999';
        $exception = InvalidRequestException::malformedUri($reason);
        
        $this->assertStringContainsString($reason, $exception->getMessage());
    }

    public function testExceptionCanBeThrown(): void
    {
        $this->expectException(InvalidRequestException::class);
        $this->expectExceptionMessage('REQUEST_METHOD');
        
        throw InvalidRequestException::missingHttpMethod();
    }

    public function testMultipleExceptionsIndependent(): void
    {
        $exc1 = InvalidRequestException::missingHttpMethod();
        $exc2 = InvalidRequestException::invalidHttpMethod('DELETE');
        
        $this->assertNotSame($exc1, $exc2);
        $this->assertNotSame($exc1->getMessage(), $exc2->getMessage());
    }

    public function testExceptionCodeIsConsistent(): void
    {
        $exc1 = InvalidRequestException::missingHttpMethod();
        $exc2 = InvalidRequestException::invalidHttpMethod('GET');
        
        $this->assertSame(0, $exc1->getCode());
        $this->assertSame(0, $exc2->getCode());
    }
}
