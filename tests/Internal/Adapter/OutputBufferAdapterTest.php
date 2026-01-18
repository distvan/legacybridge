<?php

declare(strict_types=1);

namespace LegacyBridge\Tests\Internal\Adapter;

use LegacyBridge\Internal\Adapter\OutputBufferAdapter;
use PHPUnit\Framework\TestCase;

/**
 * Tests for OutputBufferAdapter
 *
 * Verifies that output buffering is managed safely, preventing
 * accidental output leaks and properly capturing legacy output.
 *
 * @internal
 */
class OutputBufferAdapterTest extends TestCase
{
    private ?OutputBufferAdapter $adapter = null;
    private int $bufferLevelBeforeTest = 0;

    protected function setUp(): void
    {
        // Record buffer level before test
        $this->bufferLevelBeforeTest = ob_get_level();
        
        // Create adapter at setUp time (after PHPUnit's buffering starts)
        $this->adapter = new OutputBufferAdapter();
    }

    protected function tearDown(): void
    {
        // Ensure adapter buffers are closed
        if ($this->adapter !== null && $this->adapter->isActive()) {
            $this->adapter->discardAndStop();
        }
        
        // Clean only buffers we created, not PHPUnit's
        $currentLevel = ob_get_level();
        while ($currentLevel > $this->bufferLevelBeforeTest) {
            ob_end_clean();
            $currentLevel = ob_get_level();
        }
    }

    public function testAdapterIsInstantiable(): void
    {
        $this->assertInstanceOf(OutputBufferAdapter::class, $this->adapter);
    }

    public function testStartCaptureCreatesBuffer(): void
    {
        $levelBefore = $this->adapter->getLevel();
        $this->adapter->startCapture();
        $levelAfter = $this->adapter->getLevel();
        $this->adapter->getAndStop();
        
        $this->assertGreaterThan($levelBefore, $levelAfter);
    }

    public function testCapturesSimpleOutput(): void
    {
        $this->adapter->startCapture();
        echo 'Hello, World!';
        $captured = $this->adapter->getAndStop();
        
        $this->assertSame('Hello, World!', $captured);
    }

    public function testCapturesMultipleOutputCalls(): void
    {
        $this->adapter->startCapture();
        echo 'Hello';
        echo ' ';
        echo 'World';
        $captured = $this->adapter->getAndStop();
        
        $this->assertSame('Hello World', $captured);
    }

    public function testCapturesWithSpecialCharacters(): void
    {
        $this->adapter->startCapture();
        echo "Line 1\nLine 2\tTabbed";
        $captured = $this->adapter->getAndStop();
        
        $this->assertSame("Line 1\nLine 2\tTabbed", $captured);
    }

    public function testCapturesUnicodeOutput(): void
    {
        $this->adapter->startCapture();
        echo 'Héllo Wørld! 🚀';
        $captured = $this->adapter->getAndStop();
        
        $this->assertSame('Héllo Wørld! 🚀', $captured);
    }

    public function testGetAndStopStopsCapturing(): void
    {
        $this->adapter->startCapture();
        echo 'captured';
        $captured = $this->adapter->getAndStop();
        
        $this->assertSame('captured', $captured);
    }

    public function testDiscardAndStopClearsBuffer(): void
    {
        $this->adapter->startCapture();
        echo 'should be discarded';
        $this->adapter->discardAndStop();
        
        $this->assertFalse($this->adapter->isActive());
    }

    public function testIsActiveWhenCapturing(): void
    {
        $this->assertFalse($this->adapter->isActive());
        
        $this->adapter->startCapture();
        $this->assertTrue($this->adapter->isActive());
        
        $this->adapter->getAndStop();
        $this->assertFalse($this->adapter->isActive());
    }

    public function testGetLevelReturnsBufferLevel(): void
    {
        $level1 = $this->adapter->getLevel();
        
        $this->adapter->startCapture();
        $level2 = $this->adapter->getLevel();
        $this->adapter->getAndStop();
        
        $this->assertGreaterThan($level1, $level2);
    }

    public function testEmptyBufferReturnsEmptyString(): void
    {
        $this->adapter->startCapture();
        $captured = $this->adapter->getAndStop();
        
        $this->assertSame('', $captured);
    }

    public function testGetAndStopReturnsEmptyWhenNoOutput(): void
    {
        $this->adapter->startCapture();
        $captured = $this->adapter->getAndStop();
        
        $this->assertSame('', $captured);
    }

    public function testDiscardAndStopWithNoOutput(): void
    {
        $this->adapter->startCapture();
        $this->adapter->discardAndStop();
        
        $this->assertFalse($this->adapter->isActive());
    }

    public function testIsActiveAfterGetAndStop(): void
    {
        $this->adapter->startCapture();
        echo 'test';
        $this->adapter->getAndStop();
        
        $this->assertFalse($this->adapter->isActive());
    }

    public function testIsActiveAfterDiscardAndStop(): void
    {
        $this->adapter->startCapture();
        echo 'test';
        $this->adapter->discardAndStop();
        
        $this->assertFalse($this->adapter->isActive());
    }

    public function testLargeOutputCapture(): void
    {
        $this->adapter->startCapture();
        $largeOutput = str_repeat('x', 10000);
        echo $largeOutput;
        $captured = $this->adapter->getAndStop();
        
        $this->assertSame($largeOutput, $captured);
        $this->assertSame(10000, strlen($captured));
    }

    public function testCapturePreservesNewlines(): void
    {
        $this->adapter->startCapture();
        echo "Line 1\n";
        echo "Line 2\n";
        echo "Line 3";
        $captured = $this->adapter->getAndStop();
        
        $this->assertStringContainsString("Line 1\n", $captured);
        $this->assertStringContainsString("Line 2\n", $captured);
        $this->assertStringContainsString("Line 3", $captured);
    }

    public function testMultipleAdaptersIndependent(): void
    {
        // Create first adapter
        $adapter1 = new OutputBufferAdapter();
        $adapter1->startCapture();
        echo 'from adapter1';
        $output1 = $adapter1->getAndStop();
        
        // Create second adapter
        $adapter2 = new OutputBufferAdapter();
        $adapter2->startCapture();
        echo 'from adapter2';
        $output2 = $adapter2->getAndStop();
        
        $this->assertSame('from adapter1', $output1);
        $this->assertSame('from adapter2', $output2);
    }
}
