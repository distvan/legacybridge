<?php

declare(strict_types=1);

namespace LegacyBridge\Http;

use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Message\ServerRequestFactoryInterface;
use Psr\Http\Message\StreamFactoryInterface;
use Psr\Http\Message\UploadedFileFactoryInterface;

/**
 * Factory for creating PSR-7 ServerRequest instances from PHP superglobals.
 *
 * This class converts PHP's superglobals ($_GET, $_POST, $_SERVER, etc.)
 * into a PSR-7 compliant ServerRequestInterface object.
 *
 * This enables legacy PHP applications to work within a PSR-7 request lifecycle
 * without modification of legacy code that depends on superglobals.
 *
 * @see https://www.php-fig.org/psr/psr-7/
 */
final class RequestFactory
{
    /**
     * Constructor
     *
     * @param ServerRequestFactoryInterface $requestFactory Factory for creating ServerRequest objects
     * @param StreamFactoryInterface $streamFactory Factory for creating Stream objects
     * @param UploadedFileFactoryInterface $uploadedFileFactory Factory for creating UploadedFile objects
     */
    public function __construct(
        private ServerRequestFactoryInterface $requestFactory,
        private StreamFactoryInterface $streamFactory,
        private UploadedFileFactoryInterface $uploadedFileFactory
    ) {
    }

    /**
     * Create a PSR-7 ServerRequest from PHP superglobals.
     *
     * This method extracts request information from:
     * - $_SERVER - HTTP method, URI, protocol version, headers
     * - $_GET - Query string parameters
     * - $_POST - Parsed body (for form submissions)
     * - $_COOKIE - Cookie parameters
     * - $_FILES - Uploaded files
     * - php://input - Request body stream
     *
     * The resulting ServerRequest is immutable and can be safely passed
     * to modern controllers and middleware.
     *
     * @return ServerRequestInterface A fully populated PSR-7 ServerRequest
     *
     * @throws \RuntimeException If required superglobals are missing or malformed
     */
    public function fromGlobals(): ServerRequestInterface
    {
        // Extract HTTP method
        $method = $this->getHttpMethod();
        
        // Create URI from superglobals
        $uri = $this->createUriFromGlobals();
        
        // Extract headers from $_SERVER
        $headers = $this->getHeadersFromGlobals();
        
        // Get HTTP protocol version
        $protocol = $this->getProtocolVersion();

        // Create base request with method, URI, and server params
        $request = $this->requestFactory->createServerRequest($method, $uri, $_SERVER)
            ->withProtocolVersion($protocol);

        // Add all headers
        foreach ($headers as $name => $values) {
            foreach ($values as $value) {
                $request = $request->withAddedHeader($name, $value);
            }
        }

        // Add request body stream
        $request = $request->withBody($this->getRequestBody());

        // Add cookie parameters
        if (!empty($_COOKIE)) {
            $request = $request->withCookieParams($_COOKIE);
        }

        // Add query parameters
        if (!empty($_GET)) {
            $request = $request->withQueryParams($_GET);
        }

        // Add parsed body for POST/PUT/PATCH requests
        if ($this->shouldHaveParsedBody($method)) {
            $request = $request->withParsedBody($this->getParsedBody());
        }

        // Add uploaded files
        if (!empty($_FILES)) {
            $uploadedFiles = $this->normalizeFiles($_FILES);
            $request = $request->withUploadedFiles($uploadedFiles);
        }

        return $request;
    }

    /**
     * Extract HTTP method from $_SERVER.
     *
     * @return string HTTP method (GET, POST, PUT, DELETE, etc.)
     */
    private function getHttpMethod(): string
    {
        return strtoupper($_SERVER['REQUEST_METHOD'] ?? 'GET');
    }

    /**
     * Create a URI string from PHP superglobals.
     *
     * Constructs a URI from:
     * - Scheme (HTTP or HTTPS)
     * - Host and port
     * - Request URI and query string
     *
     * @return string Complete URI (e.g., https://example.com:8080/path?query=value)
     */
    private function createUriFromGlobals(): string
    {
        // Determine scheme
        $scheme = $this->getScheme();
        
        // Get host
        $host = $_SERVER['HTTP_HOST'] ?? $_SERVER['SERVER_NAME'] ?? 'localhost';
        
        // Get port if not already in host
        $port = $this->getPort();
        if ($port && !str_contains($host, ':')) {
            $host .= ':' . $port;
        }

        // Get request URI (path + query string)
        $requestUri = $_SERVER['REQUEST_URI'] ?? '/';

        return $scheme . '://' . $host . $requestUri;
    }

    /**
     * Determine the request scheme (HTTP or HTTPS).
     *
     * Checks:
     * 1. $_SERVER['HTTPS'] (standard)
     * 2. $_SERVER['HTTP_X_FORWARDED_PROTO'] (behind proxy)
     * 3. $_SERVER['REQUEST_SCHEME'] (some servers)
     *
     * @return string Either 'http' or 'https'
     */
    private function getScheme(): string
    {
        // Check HTTPS flag (standard)
        if (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') {
            return 'https';
        }

        // Check X-Forwarded-Proto header (behind reverse proxy)
        if (!empty($_SERVER['HTTP_X_FORWARDED_PROTO'])) {
            return strtolower($_SERVER['HTTP_X_FORWARDED_PROTO']) === 'https' ? 'https' : 'http';
        }

        // Check REQUEST_SCHEME (some servers)
        if (!empty($_SERVER['REQUEST_SCHEME'])) {
            return strtolower($_SERVER['REQUEST_SCHEME']);
        }

        return 'http';
    }

    /**
     * Extract port number from $_SERVER.
     *
     * Handles:
     * 1. Explicit SERVER_PORT
     * 2. X-Forwarded-Port header (behind proxy)
     *
     * @return string|null Port number or null if not explicitly set
     */
    private function getPort(): ?string
    {
        // Check for explicit port in SERVER_PORT
        if (!empty($_SERVER['SERVER_PORT'])) {
            $port = (int)$_SERVER['SERVER_PORT'];
            // Only return if it's not the default for the scheme
            if (($this->getScheme() === 'https' && $port !== 443) ||
                ($this->getScheme() === 'http' && $port !== 80)) {
                return (string)$port;
            }
        }

        // Check for X-Forwarded-Port header (behind proxy)
        if (!empty($_SERVER['HTTP_X_FORWARDED_PORT'])) {
            return (string)$_SERVER['HTTP_X_FORWARDED_PORT'];
        }

        return null;
    }

    /**
     * Extract HTTP protocol version from $_SERVER.
     *
     * Converts $_SERVER['SERVER_PROTOCOL'] (e.g., "HTTP/1.1")
     * to PSR-7 format (e.g., "1.1")
     *
     * @return string Protocol version (e.g., "1.0", "1.1", "2.0")
     */
    private function getProtocolVersion(): string
    {
        $protocol = $_SERVER['SERVER_PROTOCOL'] ?? 'HTTP/1.1';
        return str_replace('HTTP/', '', $protocol);
    }

    /**
     * Get the request body stream.
     *
     * Reads from php://input which contains the raw HTTP request body.
     * This stream can only be read once in PHP, so it must be done carefully.
     *
     * @return \Psr\Http\Message\StreamInterface Request body stream
     */
    private function getRequestBody()
    {
        return $this->streamFactory->createStreamFromFile('php://input', 'r');
    }

    /**
     * Extract HTTP headers from $_SERVER superglobal.
     *
     * PHP stores HTTP headers in $_SERVER with 'HTTP_' prefix.
     * This method extracts and normalizes them to PSR-7 format.
     *
     * Handles:
     * - HTTP_* variables (converted to Header-Name format)
     * - CONTENT_TYPE and CONTENT_LENGTH (special cases)
     *
     * @return array<string, array<int, string>> Headers with format [HeaderName => [value1, value2, ...]]
     */
    private function getHeadersFromGlobals(): array
    {
        $headers = [];
        
        foreach ($_SERVER as $key => $value) {
            if ($this->isHttpHeader($key)) {
                // Remove HTTP_ prefix and convert underscores to dashes
                $header = str_replace('_', '-', substr($key, 5));
                $headers[$header][] = $value;
            } elseif ($this->isSpecialHeader($key)) {
                // Handle CONTENT_TYPE and CONTENT_LENGTH
                $header = str_replace('_', '-', $key);
                $headers[$header][] = $value;
            }
        }
        
        return $headers;
    }

    /**
     * Check if a $_SERVER key represents an HTTP header.
     *
     * @param string $key $_SERVER key
     * @return bool True if key starts with 'HTTP_'
     */
    private function isHttpHeader(string $key): bool
    {
        return str_starts_with($key, 'HTTP_');
    }

    /**
     * Check if a $_SERVER key is a special header (CONTENT_TYPE, CONTENT_LENGTH).
     *
     * @param string $key $_SERVER key
     * @return bool True if key is a special header
     */
    private function isSpecialHeader(string $key): bool
    {
        return in_array($key, ['CONTENT_TYPE', 'CONTENT_LENGTH'], true);
    }

    /**
     * Determine if request should have a parsed body.
     *
     * GET and HEAD requests typically don't have bodies.
     * Other methods (POST, PUT, PATCH, DELETE) may have bodies.
     *
     * @param string $method HTTP method
     * @return bool True if method typically includes a body
     */
    private function shouldHaveParsedBody(string $method): bool
    {
        return in_array($method, ['POST', 'PUT', 'PATCH', 'DELETE'], true);
    }

    /**
     * Get parsed body from $_POST or raw input.
     *
     * Attempts to parse the request body based on Content-Type:
     * - application/x-www-form-urlencoded: Uses $_POST
     * - application/json: Parses raw JSON
     * - multipart/form-data: Uses $_POST
     *
     * @return array|null Parsed body or null if not available
     */
    private function getParsedBody(): ?array
    {
        // If $_POST is populated, use it (form data or multipart)
        if (!empty($_POST)) {
            return $_POST;
        }

        // Check Content-Type for JSON
        $contentType = $_SERVER['CONTENT_TYPE'] ?? '';
        if (str_contains($contentType, 'application/json')) {
            $input = file_get_contents('php://input');
            if ($input) {
                $decoded = json_decode($input, true);
                return is_array($decoded) ? $decoded : null;
            }
        }

        return null;
    }

    /**
     * Normalize the $_FILES array into UploadedFile instances.
     *
     * PHP's $_FILES has an unintuitive structure for multiple file uploads.
     * This method normalizes it to a more logical structure and creates
     * PSR-7 UploadedFile instances.
     *
     * Handles:
     * - Single file: <input type="file" name="file">
     * - Multiple files: <input type="file" name="files[]">
     * - Nested files: <input type="file" name="data[files][]">
     *
     * @param array<string, mixed> $files $_FILES superglobal
     * @return array<string, mixed> Normalized array of UploadedFile instances
     */
    private function normalizeFiles(array $files): array
    {
        $normalized = [];

        foreach ($files as $key => $file) {
            if (!is_array($file) || !isset($file['name'])) {
                continue;
            }

            // Check if this is a multi-file upload (name is array)
            if (is_array($file['name'])) {
                // Recursively process multiple files
                $normalized[$key] = $this->normalizeMultipleFiles($file);
            } else {
                // Single file upload
                $normalized[$key] = $this->createUploadedFile($file);
            }
        }

        return $normalized;
    }

    /**
     * Normalize multiple file uploads.
     *
     * Converts PHP's "files array within an array" structure to individual UploadedFile instances.
     *
     * @param array<string, array> $files File data array (already verified to be multi-file)
     * @return array Array of UploadedFile instances
     */
    private function normalizeMultipleFiles(array $files): array
    {
        $normalized = [];
        $count = count($files['name'] ?? []);

        for ($i = 0; $i < $count; $i++) {
            $file = [
                'name' => $files['name'][$i] ?? null,
                'type' => $files['type'][$i] ?? null,
                'tmp_name' => $files['tmp_name'][$i] ?? null,
                'error' => $files['error'][$i] ?? UPLOAD_ERR_NO_FILE,
                'size' => $files['size'][$i] ?? 0,
            ];

            // Check if this is a nested array (e.g., input with name="data[files][]")
            if (is_array($file['name'])) {
                $normalized[] = $this->normalizeMultipleFiles($file);
            } else {
                $normalized[] = $this->createUploadedFile($file);
            }
        }

        return $normalized;
    }

    /**
     * Create a single UploadedFile instance.
     *
     * @param array<string, mixed> $file File data: name, type, tmp_name, error, size
     * @return \Psr\Http\Message\UploadedFileInterface Uploaded file instance
     */
    private function createUploadedFile(array $file)
    {
        $stream = $this->streamFactory->createStreamFromFile(
            $file['tmp_name'] ?? '',
            'r'
        );

        return $this->uploadedFileFactory->createUploadedFile(
            $stream,
            (int)($file['size'] ?? 0),
            (int)($file['error'] ?? UPLOAD_ERR_NO_FILE),
            $file['name'] ?? null,
            $file['type'] ?? null
        );
    }
}
