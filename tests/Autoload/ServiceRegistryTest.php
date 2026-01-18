<?php

declare(strict_types=1);

namespace LegacyBridge\Tests\Autoload;

use LegacyBridge\Autoload\ServiceRegistry;
use LegacyBridge\Container\LegacyContainer;
use LegacyBridge\Http\ResponseEmitter;
use LegacyBridge\Http\RequestFactory;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Psr\Container\ContainerInterface;
use Psr\Http\Message\ServerRequestFactoryInterface;
use Psr\Http\Message\StreamFactoryInterface;
use Psr\Http\Message\UploadedFileFactoryInterface;

/**
 * Tests for ServiceRegistry
 *
 * Verifies that ServiceRegistry correctly registers default services
 * into the LegacyContainer following ADR-003 principles.
 *
 * @see LegacyBridge\Autoload\ServiceRegistry
 * @see docs/adr/003-no-autowiring-or-reflection.md
 */
class ServiceRegistryTest extends TestCase
{
    private LegacyContainer $container;

    protected function setUp(): void
    {
        $this->container = new LegacyContainer();
    }

    public function testRegisterDefaultsRegistersResponseEmitter(): void
    {
        ServiceRegistry::registerDefaults($this->container);

        $this->assertTrue($this->container->has('response_emitter'));
    }

    public function testRegisterDefaultsRegistersRequestFactoryBridge(): void
    {
        ServiceRegistry::registerDefaults($this->container);

        $this->assertTrue($this->container->has('request_factory_bridge'));
    }

    public function testRegisterResponseEmitterCreatesResponseEmitterInstance(): void
    {
        ServiceRegistry::registerResponseEmitter($this->container);

        $emitter = $this->container->get('response_emitter');

        $this->assertInstanceOf(ResponseEmitter::class, $emitter);
    }

    public function testRegisterResponseEmitterServiceCachesInstance(): void
    {
        ServiceRegistry::registerResponseEmitter($this->container);

        $emitter1 = $this->container->get('response_emitter');
        $emitter2 = $this->container->get('response_emitter');

        $this->assertSame($emitter1, $emitter2);
    }

    public function testRegisterResponseEmitterServiceIDCorrect(): void
    {
        ServiceRegistry::registerResponseEmitter($this->container);

        $this->assertTrue($this->container->has('response_emitter'));
        $this->assertFalse($this->container->has('emitter'));
        $this->assertFalse($this->container->has('response'));
    }

    public function testRegisterRequestFactoryRequiresPsr7Factories(): void
    {
        ServiceRegistry::registerRequestFactory($this->container);

        // Should throw when trying to resolve without PSR factories
        $this->expectException(\Exception::class);

        $this->container->get('request_factory_bridge');
    }

    public function testRegisterRequestFactoryWithPsr7FactoriesResolvesSuccessfully(): void
    {
        $this->setupPsr7Factories();
        ServiceRegistry::registerRequestFactory($this->container);

        $factory = $this->container->get('request_factory_bridge');

        $this->assertInstanceOf(RequestFactory::class, $factory);
    }

    public function testRegisterRequestFactoryServiceCachesInstance(): void
    {
        $this->setupPsr7Factories();
        ServiceRegistry::registerRequestFactory($this->container);

        $factory1 = $this->container->get('request_factory_bridge');
        $factory2 = $this->container->get('request_factory_bridge');

        $this->assertSame($factory1, $factory2);
    }

    public function testResponseEmitterFactoryMethodReturnsCallable(): void
    {
        $factory = ServiceRegistry::responseEmitter();

        $this->assertTrue(is_callable($factory));
    }

    public function testResponseEmitterFactoryMethodCreatesEmitter(): void
    {
        $factory = ServiceRegistry::responseEmitter();

        $emitter = $factory();

        $this->assertInstanceOf(ResponseEmitter::class, $emitter);
    }

    public function testRequestFactoryFactoryMethodReturnsCallable(): void
    {
        $factory = ServiceRegistry::requestFactory();

        $this->assertTrue(is_callable($factory));
    }

    public function testRequestFactoryFactoryMethodRequiresPsr7Factories(): void
    {
        $factory = ServiceRegistry::requestFactory();

        $this->expectException(\Exception::class);

        // Call factory without PSR implementations in container
        $factory($this->container);
    }

    public function testRequestFactoryFactoryMethodWithPsr7FactoriesCreatesFactory(): void
    {
        $this->setupPsr7Factories();
        $factory = ServiceRegistry::requestFactory();

        $requestFactory = $factory($this->container);

        $this->assertInstanceOf(RequestFactory::class, $requestFactory);
    }

    public function testRegisterDefaultsMultipleTimes(): void
    {
        ServiceRegistry::registerDefaults($this->container);
        ServiceRegistry::registerDefaults($this->container);

        // Should not cause errors, just re-register
        $this->assertTrue($this->container->has('response_emitter'));
        $this->assertTrue($this->container->has('request_factory_bridge'));
    }

    public function testExplicitServiceRegistrationFollowsAdr003(): void
    {
        // ADR-003: No autowiring, explicit registration only
        
        // Verify services must be explicitly registered
        $this->assertFalse($this->container->has('response_emitter'));
        
        // After explicit registration, they're available
        ServiceRegistry::registerResponseEmitter($this->container);
        $this->assertTrue($this->container->has('response_emitter'));
        
        // No magic discovery or autowiring
        $this->assertFalse($this->container->has('ResponseEmitter'));
        $this->assertFalse($this->container->has('emitter'));
    }

    public function testServiceRegistryDoesNotAllowInstantiation(): void
    {
        $reflection = new \ReflectionClass(ServiceRegistry::class);

        $constructor = $reflection->getConstructor();

        $this->assertTrue($constructor->isPrivate(), 'Constructor should be private');
    }

    /**
     * Helper to set up mock PSR-7 factories in container
     */
    private function setupPsr7Factories(): void
    {
        $requestFactory = $this->createMock(ServerRequestFactoryInterface::class);
        $streamFactory = $this->createMock(StreamFactoryInterface::class);
        $uploadedFileFactory = $this->createMock(UploadedFileFactoryInterface::class);

        $this->container->set('request_factory', fn() => $requestFactory);
        $this->container->set('stream_factory', fn() => $streamFactory);
        $this->container->set('uploaded_file_factory', fn() => $uploadedFileFactory);
    }
}