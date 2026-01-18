<?php

declare(strict_types=1);

namespace LegacyBridge\Internal\Adapter;

/**
 * Normalizes PHP $_SERVER headers to PSR-7 format.
 *
 * PHP stores HTTP headers in $_SERVER with 'HTTP_' prefix and underscores.
 * This adapter converts them to proper HTTP header format.
 *
 * Examples:
 * - HTTP_CONTENT_TYPE → Content-Type
 * - HTTP_X_FORWARDED_FOR → X-Forwarded-For
 * - CONTENT_LENGTH → Content-Length
 *
 * @internal This class is not part of the public API and may change without notice.
 */
final class HeaderNormalizer
{
    /**
     * Extract and normalize headers from $_SERVER.
     *
     * @param array $server $_SERVER superglobal
     * @return array<string, array<string>> Normalized headers
     */
    public static function normalize(array $server): array
    {
        $headers = [];

        foreach ($server as $key => $value) {
            if (!self::isHttpHeader($key) && !self::isSpecialHeader($key)) {
                continue;
            }

            $headerName = self::normalizeHeaderName($key);
            $headers[$headerName][] = $value;
        }

        return $headers;
    }

    /**
     * Check if $_SERVER key is an HTTP header (HTTP_* prefix).
     *
     * @param string $key $_SERVER key
     * @return bool True if it's an HTTP header
     */
    private static function isHttpHeader(string $key): bool
    {
        return str_starts_with($key, 'HTTP_');
    }

    /**
     * Check if $_SERVER key is a special header (CONTENT_TYPE, CONTENT_LENGTH).
     *
     * @param string $key $_SERVER key
     * @return bool True if it's a special header
     */
    private static function isSpecialHeader(string $key): bool
    {
        return in_array($key, ['CONTENT_TYPE', 'CONTENT_LENGTH'], true);
    }

    /**
     * Normalize a $_SERVER header key to PSR-7 header name.
     *
     * Converts:
     * - HTTP_CONTENT_TYPE → Content-Type
     * - CONTENT_TYPE → Content-Type
     * - HTTP_X_CUSTOM_HEADER → X-Custom-Header
     *
     * @param string $key $_SERVER key
     * @return string Normalized header name
     */
    private static function normalizeHeaderName(string $key): string
    {
        // Remove HTTP_ prefix if present
        if (str_starts_with($key, 'HTTP_')) {
            $key = substr($key, 5);
        }

        // Convert underscores to dashes and apply title case
        $parts = explode('_', $key);
        $parts = array_map(fn($part) => ucfirst(strtolower($part)), $parts);

        return implode('-', $parts);
    }

    /**
     * Check if a header should be sent.
     *
     * Some $_SERVER values should not be treated as headers.
     *
     * @param string $value The header value
     * @return bool True if header should be sent
     */
    public static function shouldSendHeader(string $value): bool
    {
        return trim($value) !== '';
    }
}
