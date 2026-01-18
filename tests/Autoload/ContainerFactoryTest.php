<?php

declare(strict_types=1);

namespace LegacyBridge\Tests\Autoload;

use LegacyBridge\Autoload\ContainerFactory;
use LegacyBridge\Container\LegacyContainer;
use PHPUnit\Framework\TestCase;

/**
 * Tests for ContainerFactory
 *
 * Verifies that ContainerFactory correctly creates and configures
 * LegacyContainer instances with sensible defaults.
 *
 * @see LegacyBridge\Autoload\ContainerFactory
 */
class ContainerFactoryTest extends TestCase
{
    public function testCreateReturnsLegacyContainerInstance(): void
    {
        $container = ContainerFactory::create();

        $this->assertInstanceOf(LegacyContainer::class, $container);
    }

    public function testCreateReturnsNewInstanceEachTime(): void
    {
        $container1 = ContainerFactory::create();
        $container2 = ContainerFactory::create();

        $this->assertNotSame($container1, $container2);
    }

    public function testCreateReturnsFreshContainer(): void
    {
        $container = ContainerFactory::create();

        // Should be empty initially
        $this->assertFalse($container->has('test_service'));
    }

    public function testCreateWithDefaultsReturnsConfiguredContainer(): void
    {
        $container = ContainerFactory::createWithDefaults();

        $this->assertInstanceOf(LegacyContainer::class, $container);
    }

    public function testCreateWithDefaultsRegistersResponseEmitter(): void
    {
        $container = ContainerFactory::createWithDefaults();

        $this->assertTrue($container->has('response_emitter'));
    }

    public function testCreateWithDefaultsRegistersRequestFactoryBridge(): void
    {
        $container = ContainerFactory::createWithDefaults();

        $this->assertTrue($container->has('request_factory_bridge'));
    }

    public function testCreateWithDefaultsCanResolveResponseEmitter(): void
    {
        $container = ContainerFactory::createWithDefaults();

        $emitter = $container->get('response_emitter');

        $this->assertNotNull($emitter);
    }

    public function testEachCallToCreateWithDefaultsReturnsDifferentInstances(): void
    {
        $container1 = ContainerFactory::createWithDefaults();
        $container2 = ContainerFactory::createWithDefaults();

        $this->assertNotSame($container1, $container2);
    }

    public function testCreatedContainerSupportsServiceRegistration(): void
    {
        $container = ContainerFactory::create();

        $container->set('custom_service', fn() => 'custom_value');

        $this->assertTrue($container->has('custom_service'));
        $this->assertSame('custom_value', $container->get('custom_service'));
    }

    public function testCreateWithDefaultsContainerSupportsCustomServices(): void
    {
        $container = ContainerFactory::createWithDefaults();

        $container->set('user_service', fn() => new \stdClass());

        $this->assertTrue($container->has('user_service'));
        $this->assertInstanceOf(\stdClass::class, $container->get('user_service'));
    }

    public function testFactoryDoesNotAllowInstantiation(): void
    {
        $reflection = new \ReflectionClass(ContainerFactory::class);

        $constructor = $reflection->getConstructor();

        $this->assertTrue($constructor->isPrivate(), 'Constructor should be private');
    }
}