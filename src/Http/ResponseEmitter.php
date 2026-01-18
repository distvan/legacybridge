<?php

declare(strict_types=1);

namespace LegacyBridge\Http;

use Psr\Http\Message\ResponseInterface;

/**
 * Emits a PSR-7 Response to the client.
 *
 * This class handles explicit HTTP response emission by:
 * - Setting the HTTP status code
 * - Sending headers in the correct order
 * - Writing the response body
 *
 * This implements the explicit response emission pattern from ADR-004.
 *
 * @see docs/adr/004-explicit-response-emissions.md
 * @see https://www.php-fig.org/psr/psr-7/
 */
final class ResponseEmitter
{
    /**
     * Emit a PSR-7 Response to the client.
     *
     * This method:
     * 1. Sets the HTTP status code
     * 2. Sends all headers
     * 3. Outputs the response body
     *
     * Headers must not have been sent prior to calling this method.
     *
     * @param ResponseInterface $response The response to emit
     * @return void
     *
     * @throws \RuntimeException If headers have already been sent
     */
    public function emit(ResponseInterface $response): void
    {
        // Check if headers have already been sent
        if (headers_sent($file, $line)) {
            throw new \RuntimeException(
                "Headers already sent. Cannot emit response. Headers sent in file '$file' on line $line."
            );
        }

        // Set HTTP status code
        $statusCode = $response->getStatusCode();
        http_response_code($statusCode);

        // Emit headers
        $this->emitHeaders($response);

        // Emit body
        $this->emitBody($response);
    }

    /**
     * Emit all headers from the response.
     *
     * @param ResponseInterface $response The response
     * @return void
     */
    private function emitHeaders(ResponseInterface $response): void
    {
        // Iterate through all headers and send them
        foreach ($response->getHeaders() as $name => $values) {
            // For each header, send all values
            // Use false as second parameter after first header to append, not replace
            $first = true;
            foreach ($values as $value) {
                // Only pass status code on first header for this name
                if (PHP_SAPI === 'cli' || PHP_SAPI === 'phpdbg') {
                    // In CLI/test environments, header() is a no-op but we still need to handle it
                    header(
                        sprintf('%s: %s', $name, $value),
                        $first
                    );
                } else {
                    // In web environments, include response code
                    header(
                        sprintf('%s: %s', $name, $value),
                        $first,
                        $response->getStatusCode()
                    );
                }
                $first = false;
            }
        }
    }

    /**
     * Emit the response body.
     *
     * @param ResponseInterface $response The response
     * @return void
     */
    private function emitBody(ResponseInterface $response): void
    {
        $body = $response->getBody();

        // If body is seekable, rewind it to the beginning
        if ($body->isSeekable()) {
            $body->rewind();
        }

        // Stream body content efficiently
        echo $body->getContents();
    }
}
