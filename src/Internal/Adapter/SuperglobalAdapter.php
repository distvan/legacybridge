<?php

declare(strict_types=1);

namespace LegacyBridge\Internal\Adapter;

/**
 * Safely accesses PHP superglobals.
 *
 * This adapter provides safe, type-checked access to superglobals
 * and normalizes their values for PSR-7 compatibility.
 *
 * @internal This class is not part of the public API and may change without notice.
 */
final class SuperglobalAdapter
{
    /**
     * Get HTTP method from $_SERVER, with fallback.
     *
     * @return string HTTP method (default: GET)
     */
    public static function getHttpMethod(): string
    {
        $method = $_SERVER['REQUEST_METHOD'] ?? 'GET';
        return strtoupper($method);
    }

    /**
     * Get request URI from $_SERVER, with fallback.
     *
     * @return string Request URI (default: /)
     */
    public static function getRequestUri(): string
    {
        return $_SERVER['REQUEST_URI'] ?? '/';
    }

    /**
     * Get server name or host.
     *
     * @return string Server name (default: localhost)
     */
    public static function getServerName(): string
    {
        return $_SERVER['HTTP_HOST']
            ?? $_SERVER['SERVER_NAME']
            ?? 'localhost';
    }

    /**
     * Get server protocol version.
     *
     * @return string Protocol version (default: 1.1)
     */
    public static function getProtocolVersion(): string
    {
        $protocol = $_SERVER['SERVER_PROTOCOL'] ?? 'HTTP/1.1';
        return str_replace('HTTP/', '', $protocol);
    }

    /**
     * Check if connection is HTTPS.
     *
     * Checks multiple sources for HTTPS detection (direct, proxy, etc).
     *
     * @return bool True if HTTPS
     */
    public static function isHttps(): bool
    {
        // Direct HTTPS check
        if (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') {
            return true;
        }

        // Check X-Forwarded-Proto (behind reverse proxy)
        if (!empty($_SERVER['HTTP_X_FORWARDED_PROTO'])) {
            return strtolower($_SERVER['HTTP_X_FORWARDED_PROTO']) === 'https';
        }

        // Check REQUEST_SCHEME
        if (!empty($_SERVER['REQUEST_SCHEME'])) {
            return strtolower($_SERVER['REQUEST_SCHEME']) === 'https';
        }

        return false;
    }

    /**
     * Get server port, with scheme-aware defaults.
     *
     * @return int Server port
     */
    public static function getPort(): int
    {
        if (!empty($_SERVER['SERVER_PORT'])) {
            return (int)$_SERVER['SERVER_PORT'];
        }

        if (!empty($_SERVER['HTTP_X_FORWARDED_PORT'])) {
            return (int)$_SERVER['HTTP_X_FORWARDED_PORT'];
        }

        return self::isHttps() ? 443 : 80;
    }

    /**
     * Get superglobal with type safety.
     *
     * @param array $array The superglobal array
     * @param string $key The key to retrieve
     * @param mixed $default Default value if key not found
     * @return mixed The value or default
     */
    public static function get(array $array, string $key, $default = null)
    {
        return $array[$key] ?? $default;
    }

    /**
     * Check if superglobal key exists.
     *
     * @param array $array The superglobal array
     * @param string $key The key to check
     * @return bool True if key exists
     */
    public static function has(array $array, string $key): bool
    {
        return isset($array[$key]) && !empty($array[$key]);
    }
}
