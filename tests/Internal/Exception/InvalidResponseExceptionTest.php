<?php

declare(strict_types=1);

namespace LegacyBridge\Tests\Internal\Exception;

use LegacyBridge\Internal\Exception\InvalidResponseException;
use PHPUnit\Framework\TestCase;

/**
 * Tests for InvalidResponseException
 *
 * Verifies that response validation exceptions provide clear error messages
 * for debugging and troubleshooting kernel return values.
 *
 * @internal
 */
class InvalidResponseExceptionTest extends TestCase
{
    public function testExceptionIsRuntimeException(): void
    {
        $exception = InvalidResponseException::notAResponse('string');
        $this->assertInstanceOf(\RuntimeException::class, $exception);
    }

    public function testExceptionIsThrowable(): void
    {
        $exception = InvalidResponseException::notAResponse('string');
        $this->assertInstanceOf(\Throwable::class, $exception);
    }

    public function testNotAResponseWithString(): void
    {
        $exception = InvalidResponseException::notAResponse('some string');
        
        $this->assertStringContainsString('PSR-7 ResponseInterface', $exception->getMessage());
        $this->assertStringContainsString('string', $exception->getMessage());
    }

    public function testNotAResponseWithInteger(): void
    {
        $exception = InvalidResponseException::notAResponse(42);
        
        $this->assertStringContainsString('integer', $exception->getMessage());
    }

    public function testNotAResponseWithArray(): void
    {
        $exception = InvalidResponseException::notAResponse(['key' => 'value']);
        
        $this->assertStringContainsString('array', $exception->getMessage());
    }

    public function testNotAResponseWithObject(): void
    {
        $object = new \stdClass();
        $exception = InvalidResponseException::notAResponse($object);
        
        $this->assertStringContainsString('stdClass', $exception->getMessage());
    }

    public function testNotAResponseWithNull(): void
    {
        $exception = InvalidResponseException::notAResponse(null);
        
        $this->assertStringContainsString('NULL', $exception->getMessage());
    }

    public function testInvalidStatusCodeLow(): void
    {
        $exception = InvalidResponseException::invalidStatusCode(99);
        
        $this->assertStringContainsString('99', $exception->getMessage());
        $this->assertStringContainsString('100 and 599', $exception->getMessage());
    }

    public function testInvalidStatusCodeHigh(): void
    {
        $exception = InvalidResponseException::invalidStatusCode(600);
        
        $this->assertStringContainsString('600', $exception->getMessage());
        $this->assertStringContainsString('100 and 599', $exception->getMessage());
    }

    public function testExceptionCanBeThrown(): void
    {
        $this->expectException(InvalidResponseException::class);
        $this->expectExceptionMessage('PSR-7 ResponseInterface');
        
        throw InvalidResponseException::notAResponse('invalid');
    }

    public function testMultipleExceptionsIndependent(): void
    {
        $exc1 = InvalidResponseException::notAResponse('string');
        $exc2 = InvalidResponseException::invalidStatusCode(999);
        
        $this->assertNotSame($exc1, $exc2);
        $this->assertNotSame($exc1->getMessage(), $exc2->getMessage());
    }
}
