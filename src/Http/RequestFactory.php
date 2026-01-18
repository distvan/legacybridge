<?php

declare(strict_types=1);

namespace LegacyBridge\Http;

use LegacyBridge\Internal\Adapter\HeaderNormalizer;
use LegacyBridge\Internal\Exception\InvalidRequestException;
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
     * @throws InvalidRequestException If required superglobals are missing or malformed
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
     * @throws InvalidRequestException If REQUEST_METHOD is missing
     */
    private function getHttpMethod(): string
    {
        // Get method with fallback to GET
        $method = strtoupper($_SERVER['REQUEST_METHOD'] ?? 'GET');

        // Validate HTTP method
        $validMethods = ['GET', 'POST', 'PUT', 'PATCH', 'DELETE', 'HEAD', 'OPTIONS', 'TRACE', 'CONNECT'];
        if (!in_array($method, $validMethods, true)) {
            throw InvalidRequestException::invalidHttpMethod($method);
        }

        return $method;
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
     * @throws InvalidRequestException If URI cannot be created
     */
    private function createUriFromGlobals(): string
    {
        try {
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

            $uri = $scheme . '://' . $host . $requestUri;
            
            // Basic URI validation
            if (!filter_var($uri, FILTER_VALIDATE_URL)) {
                throw InvalidRequestException::malformedUri('Invalid URI format: ' . $uri);
            }

            return $uri;
        } catch (InvalidRequestException $e) {
            throw $e;
        } catch (\Throwable $e) {
            throw InvalidRequestException::malformedUri($e->getMessage());
        }
    }

    /**
     * Determine the request scheme (HTTP or HTTPS).
     *
     * Checks multiple sources in order of preference:
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
        $proto = strtolower($_SERVER['HTTP_X_FORWARDED_PROTO'] ?? '');
        if ($proto === 'https') {
            return 'https';
        }

        // Check REQUEST_SCHEME or default to http
        $scheme = strtolower($_SERVER['REQUEST_SCHEME'] ?? 'http');
        return ($scheme === 'https') ? 'https' : 'http';
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
     * Uses HeaderNormalizer to convert PHP's $_SERVER header format
     * to PSR-7 compatible format.
     *
     * Handles:
     * - HTTP_* variables (converted to Header-Name format)
     * - CONTENT_TYPE and CONTENT_LENGTH (special cases)
     *
     * @return array<string, array<int, string>> Headers with format [HeaderName => [value1, value2, ...]]
     */
    private function getHeadersFromGlobals(): array
    {
        return HeaderNormalizer::normalize($_SERVER);
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
     * Get the parsed request body.
     *
     * Determines the appropriate parsing based on Content-Type header:
     * - application/x-www-form-urlencoded: $_POST array
     * - application/json: Parsed JSON object/array
     * - Other types: Returns $_POST (fallback)
     *
     * @return mixed Parsed body (array or object)
     */
    private function getParsedBody()
    {
        $contentType = $_SERVER['CONTENT_TYPE'] ?? '';
        
        if (str_contains($contentType, 'application/json')) {
            $body = file_get_contents('php://input');
            return json_decode($body, true) ?? [];
        }

        return $_POST;
    }

    /**
     * Normalize $_FILES array to PSR-7 UploadedFile objects.
     *
     * Converts PHP's $_FILES structure to PSR-7 UploadedFileInterface objects
     * for proper file upload handling.
     *
     * @param array $files The $_FILES superglobal
     * @return array Normalized uploaded files
     */
    private function normalizeFiles(array $files): array
    {
        $normalized = [];

        foreach ($files as $key => $file) {
            $normalized[$key] = $this->normalizeFileArray($file);
        }

        return $normalized;
    }

    /**
     * Recursively normalize file array entries.
     *
     * Handles both single file uploads and arrays of file uploads.
     *
     * @param array $file File entry from $_FILES
     * @return mixed Normalized UploadedFile or array of UploadedFiles
     */
    private function normalizeFileArray(array $file)
    {
        // Handle multiple files (array of uploads)
        if (is_array($file['tmp_name'])) {
            $normalized = [];
            foreach ($file['tmp_name'] as $index => $tmpName) {
                $normalized[$index] = $this->uploadedFileFactory->createUploadedFile(
                    $this->streamFactory->createStreamFromFile($tmpName),
                    (int)($file['size'][$index] ?? 0),
                    (int)($file['error'][$index] ?? UPLOAD_ERR_NO_FILE),
                    $file['name'][$index] ?? null,
                    $file['type'][$index] ?? null
                );
            }
            return $normalized;
        }

        // Handle single file
        return $this->uploadedFileFactory->createUploadedFile(
            $this->streamFactory->createStreamFromFile($file['tmp_name']),
            (int)($file['size'] ?? 0),
            (int)($file['error'] ?? UPLOAD_ERR_NO_FILE),
            $file['name'] ?? null,
            $file['type'] ?? null
        );
    }
}
