<?php

declare(strict_types=1);

namespace LegacyBridge\Internal\Buffer;

use Psr\Http\Message\StreamInterface;

/**
 * Manages internal stream buffering for response body construction.
 *
 * This manager helps combine output from legacy and modern code
 * into a single PSR-7 response body.
 *
 * @internal This class is not part of the public API and may change without notice.
 */
final class StreamBufferManager
{
    /**
     * The buffered content.
     */
    private string $buffer = '';

    /**
     * Append content to the buffer.
     *
     * @param string $content Content to append
     * @return void
     */
    public function append(string $content): void
    {
        $this->buffer .= $content;
    }

    /**
     * Append content from a stream to the buffer.
     *
     * @param StreamInterface $stream Stream to read from
     * @return void
     */
    public function appendStream(StreamInterface $stream): void
    {
        if ($stream->isSeekable()) {
            $stream->rewind();
        }

        $this->buffer .= $stream->getContents();
    }

    /**
     * Get the buffered content.
     *
     * @return string The buffered content
     */
    public function getContent(): string
    {
        return $this->buffer;
    }

    /**
     * Get the size of buffered content.
     *
     * @return int Size in bytes
     */
    public function getSize(): int
    {
        return strlen($this->buffer);
    }

    /**
     * Clear the buffer.
     *
     * @return void
     */
    public function clear(): void
    {
        $this->buffer = '';
    }

    /**
     * Check if buffer is empty.
     *
     * @return bool True if buffer is empty
     */
    public function isEmpty(): bool
    {
        return $this->buffer === '';
    }

    /**
     * Write buffer content to a stream.
     *
     * @param StreamInterface $stream Stream to write to
     * @return void
     */
    public function writeToStream(StreamInterface $stream): void
    {
        if (!$this->isEmpty()) {
            $stream->write($this->buffer);
        }
    }
}
