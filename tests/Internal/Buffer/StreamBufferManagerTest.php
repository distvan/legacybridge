<?php

declare(strict_types=1);

namespace LegacyBridge\Tests\Internal\Buffer;

use LegacyBridge\Internal\Buffer\StreamBufferManager;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Psr\Http\Message\StreamInterface;

/**
 * Tests for StreamBufferManager
 *
 * Verifies that stream buffering correctly combines output
 * from legacy and modern code into a single response body.
 *
 * @internal
 */
class StreamBufferManagerTest extends TestCase
{
    private StreamBufferManager $manager;

    protected function setUp(): void
    {
        $this->manager = new StreamBufferManager();
    }

    public function testManagerIsInstantiable(): void
    {
        $this->assertInstanceOf(StreamBufferManager::class, $this->manager);
    }

    public function testBufferStartsEmpty(): void
    {
        $this->assertTrue($this->manager->isEmpty());
        $this->assertSame('', $this->manager->getContent());
        $this->assertSame(0, $this->manager->getSize());
    }

    public function testAppendSingleContent(): void
    {
        $this->manager->append('Hello');
        
        $this->assertSame('Hello', $this->manager->getContent());
        $this->assertSame(5, $this->manager->getSize());
    }

    public function testAppendMultipleContent(): void
    {
        $this->manager->append('Hello');
        $this->manager->append(' ');
        $this->manager->append('World');
        
        $this->assertSame('Hello World', $this->manager->getContent());
        $this->assertSame(11, $this->manager->getSize());
    }

    public function testAppendEmptyContent(): void
    {
        $this->manager->append('Hello');
        $this->manager->append('');
        
        $this->assertSame('Hello', $this->manager->getContent());
    }

    public function testAppendSpecialCharacters(): void
    {
        $this->manager->append("Line 1\nLine 2\tTabbed");
        
        $this->assertSame("Line 1\nLine 2\tTabbed", $this->manager->getContent());
    }

    public function testAppendUnicodeContent(): void
    {
        $this->manager->append('Héllo Wørld! 🚀');
        
        $this->assertSame('Héllo Wørld! 🚀', $this->manager->getContent());
    }

    public function testAppendStreamSeekable(): void
    {
        $stream = $this->createMockStream('Stream content', true);
        
        $this->manager->append('Before: ');
        $this->manager->appendStream($stream);
        
        $this->assertStringContainsString('Before: ', $this->manager->getContent());
        $this->assertStringContainsString('Stream content', $this->manager->getContent());
    }

    public function testAppendStreamNonSeekable(): void
    {
        $stream = $this->createMockStream('Stream content', false);
        
        $this->manager->append('Before: ');
        $this->manager->appendStream($stream);
        
        $this->assertStringContainsString('Before: ', $this->manager->getContent());
        $this->assertStringContainsString('Stream content', $this->manager->getContent());
    }

    public function testClear(): void
    {
        $this->manager->append('Some content');
        $this->assertFalse($this->manager->isEmpty());
        
        $this->manager->clear();
        
        $this->assertTrue($this->manager->isEmpty());
        $this->assertSame('', $this->manager->getContent());
        $this->assertSame(0, $this->manager->getSize());
    }

    public function testWriteToStream(): void
    {
        $this->manager->append('Buffer content');
        
        $stream = $this->createMock(StreamInterface::class);
        $stream->expects($this->once())->method('write')->with('Buffer content');
        
        $this->manager->writeToStream($stream);
    }

    public function testWriteToStreamWhenEmpty(): void
    {
        $stream = $this->createMock(StreamInterface::class);
        $stream->expects($this->never())->method('write');
        
        $this->manager->writeToStream($stream);
    }

    public function testMultipleManagersIndependent(): void
    {
        $manager1 = new StreamBufferManager();
        $manager2 = new StreamBufferManager();
        
        $manager1->append('Content 1');
        $manager2->append('Content 2');
        
        $this->assertSame('Content 1', $manager1->getContent());
        $this->assertSame('Content 2', $manager2->getContent());
    }

    /**
     * Create a mock stream that returns specific content.
     *
     * @param string $content The content to return
     * @param bool $seekable Whether stream is seekable
     * @return MockObject|StreamInterface
     */
    private function createMockStream(string $content, bool $seekable)
    {
        $stream = $this->createMock(StreamInterface::class);
        $stream->method('isSeekable')->willReturn($seekable);
        $stream->method('getContents')->willReturn($content);
        
        if ($seekable) {
            $stream->expects($this->once())->method('rewind');
        }
        
        return $stream;
    }
}
