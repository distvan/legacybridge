<?php

declare(strict_types=1);

namespace LegacyBridge\Internal\Exception;

/**
 * Thrown when request creation fails from superglobals.
 *
 * @internal This class is not part of the public API and may change without notice.
 */
final class InvalidRequestException extends \RuntimeException
{
    /**
     * Create exception for missing HTTP method.
     *
     * @return self
     */
    public static function missingHttpMethod(): self
    {
        return new self(
            'HTTP method not found in $_SERVER[REQUEST_METHOD]'
        );
    }

    /**
     * Create exception for invalid HTTP method.
     *
     * @param string $method
     * @return self
     */
    public static function invalidHttpMethod(string $method): self
    {
        return new self(
            "Invalid HTTP method: $method"
        );
    }

    /**
     * Create exception for malformed URI.
     *
     * @param string $reason
     * @return self
     */
    public static function malformedUri(string $reason): self
    {
        return new self(
            "Failed to create valid URI: $reason"
        );
    }
}
