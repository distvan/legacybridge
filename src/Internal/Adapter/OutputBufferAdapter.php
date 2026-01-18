<?php

declare(strict_types=1);

namespace LegacyBridge\Internal\Adapter;

/**
 * Manages PHP output buffering for capturing legacy output.
 *
 * This adapter encapsulates output buffering logic to:
 * - Prevent accidental output before LegacyBridge processing
 * - Capture legacy application output
 * - Merge legacy output with modern PSR-7 responses
 *
 * @internal This class is not part of the public API and may change without notice.
 */
final class OutputBufferAdapter
{
    /**
     * Current buffering level when adapter started.
     */
    private int $initialLevel;

    /**
     * Create a new output buffer adapter.
     *
     * Saves the current output buffer level for cleanup.
     */
    public function __construct()
    {
        $this->initialLevel = ob_get_level();
    }

    /**
     * Start capturing output.
     *
     * @return void
     */
    public function startCapture(): void
    {
        ob_start();
    }

    /**
     * Get captured output and stop buffering.
     *
     * @return string The captured output
     */
    public function getAndStop(): string
    {
        if (ob_get_level() <= $this->initialLevel) {
            return '';
        }

        return ob_get_clean() ?? '';
    }

    /**
     * Discard buffered output and stop buffering.
     *
     * Used when an error occurs during processing.
     *
     * @return void
     */
    public function discardAndStop(): void
    {
        while (ob_get_level() > $this->initialLevel) {
            ob_end_clean();
        }
    }

    /**
     * Check if output buffering is active.
     *
     * @return bool True if buffering is active
     */
    public function isActive(): bool
    {
        return ob_get_level() > $this->initialLevel;
    }

    /**
     * Get current buffer level.
     *
     * @return int The current output buffer level
     */
    public function getLevel(): int
    {
        return ob_get_level();
    }
}
