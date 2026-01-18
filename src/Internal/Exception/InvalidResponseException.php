<?php

declare(strict_types=1);

namespace LegacyBridge\Internal\Exception;

/**
 * Thrown when a kernel returns an invalid response.
 *
 * @internal This class is not part of the public API and may change without notice.
 */
final class InvalidResponseException extends \RuntimeException
{
    /**
     * Create exception for non-ResponseInterface return value.
     *
     * @param mixed $received The actual value received
     * @return self
     */
    public static function notAResponse($received): self
    {
        $type = gettype($received);
        if (is_object($received)) {
            $type = get_class($received);
        }

        return new self(
            "Legacy kernel must return a PSR-7 ResponseInterface, got $type"
        );
    }

    /**
     * Create exception for invalid status code.
     *
     * @param int $statusCode The invalid status code
     * @return self
     */
    public static function invalidStatusCode(int $statusCode): self
    {
        return new self(
            "Invalid HTTP status code: $statusCode. Must be between 100 and 599."
        );
    }
}
