<?php

declare(strict_types=1);

namespace LegacyBridge\Tests\Container;

use LegacyBridge\Container\LegacyContainer;
use PHPUnit\Framework\TestCase;
use Psr\Container\ContainerExceptionInterface;
use Psr\Container\ContainerInterface;
use Psr\Container\NotFoundExceptionInterface;

/**
 * Tests for LegacyContainer
 *
 * Verifies that:
 * - Services are registered explicitly (no autowiring)
 * - Services are resolved lazily via factories
 * - Services are cached after first resolution
 * - PSR-11 ContainerInterface is properly implemented
 * - Proper exceptions are thrown for missing/failed services
 *
 * @see docs/adr/003-no-autowiring-or-reflection.md
 */
class LegacyContainerTest extends TestCase
{
    private LegacyContainer $container;

    protected function setUp(): void
    {
        $this->container = new LegacyContainer();
    }

    public function testContainerImplementsPsr11Interface(): void
    {
        $this->assertInstanceOf(ContainerInterface::class, $this->container);
    }

    public function testSetRegistersService(): void
    {
        $factory = fn() => 'test_value';
        
        $this->container->set('test_service', $factory);
        
        $this->assertTrue($this->container->has('test_service'));
    }

    public function testGetRetrievesRegisteredService(): void
    {
        $expectedValue = 'test_service_value';
        $factory = fn() => $expectedValue;
        
        $this->container->set('my_service', $factory);
        $result = $this->container->get('my_service');
        
        $this->assertSame($expectedValue, $result);
    }

    public function testFactoryReceivesContainerAsArgument(): void
    {
        $capturedContainer = null;
        $factory = function(ContainerInterface $container) use (&$capturedContainer) {
            $capturedContainer = $container;
            return 'value';
        };
        
        $this->container->set('service', $factory);
        $this->container->get('service');
        
        $this->assertSame($capturedContainer, $this->container);
    }

    public function testServicesAreLazilyResolved(): void
    {
        $resolutionCount = 0;
        $factory = function() use (&$resolutionCount) {
            $resolutionCount++;
            return 'resolved_value';
        };
        
        $this->container->set('lazy_service', $factory);
        
        // Factory not called yet
        $this->assertSame(0, $resolutionCount);
        
        // First get() calls factory
        $this->container->get('lazy_service');
        $this->assertSame(1, $resolutionCount);
    }

    public function testServicesAreCachedAfterFirstResolution(): void
    {
        $resolutionCount = 0;
        $factory = function() use (&$resolutionCount) {
            $resolutionCount++;
            return new \stdClass();
        };
        
        $this->container->set('cached_service', $factory);
        
        $instance1 = $this->container->get('cached_service');
        $instance2 = $this->container->get('cached_service');
        $instance3 = $this->container->get('cached_service');
        
        // Factory called only once
        $this->assertSame(1, $resolutionCount);
        
        // All calls return same instance
        $this->assertSame($instance1, $instance2);
        $this->assertSame($instance2, $instance3);
    }

    public function testSetClearsCachedInstanceWhenReregistering(): void
    {
        $this->container->set('service', fn() => 'first_value');
        $first = $this->container->get('service');
        
        // Re-register with new factory
        $this->container->set('service', fn() => 'second_value');
        $second = $this->container->get('service');
        
        $this->assertSame('first_value', $first);
        $this->assertSame('second_value', $second);
        $this->assertNotSame($first, $second);
    }

    public function testHasReturnsTrueForRegisteredService(): void
    {
        $this->assertFalse($this->container->has('nonexistent'));
        
        $this->container->set('existing', fn() => 'value');
        
        $this->assertTrue($this->container->has('existing'));
    }

    public function testGetThrowsNotFoundExceptionForMissingService(): void
    {
        $this->expectException(NotFoundExceptionInterface::class);
        $this->expectExceptionMessage("Service 'nonexistent' not found in container");
        
        $this->container->get('nonexistent');
    }

    public function testGetThrowsContainerExceptionWhenFactoryThrows(): void
    {
        $factory = function() {
            throw new \RuntimeException('Factory error');
        };
        
        $this->container->set('failing_service', $factory);
        
        $this->expectException(ContainerExceptionInterface::class);
        $this->expectExceptionMessage("Error resolving service 'failing_service'");
        
        $this->container->get('failing_service');
    }

    public function testContainerExceptionPreservesPreviousException(): void
    {
        $originalException = new \RuntimeException('Original error');
        $factory = function() use ($originalException) {
            throw $originalException;
        };
        
        $this->container->set('service', $factory);
        
        try {
            $this->container->get('service');
        } catch (ContainerExceptionInterface $e) {
            $this->assertSame($originalException, $e->getPrevious());
            return;
        }
        
        $this->fail('Expected ContainerExceptionInterface to be thrown');
    }

    public function testMultipleServicesCanBeRegistered(): void
    {
        $this->container->set('service1', fn() => 'value1');
        $this->container->set('service2', fn() => 'value2');
        $this->container->set('service3', fn() => 'value3');
        
        $this->assertSame('value1', $this->container->get('service1'));
        $this->assertSame('value2', $this->container->get('service2'));
        $this->assertSame('value3', $this->container->get('service3'));
    }

    public function testServiceCanDependOnOtherServices(): void
    {
        // Register a dependency
        $this->container->set('dependency', fn() => new \stdClass());
        
        // Register a service that depends on it
        $factory = function(ContainerInterface $container) {
            $dependency = $container->get('dependency');
            $service = new \stdClass();
            $service->dependency = $dependency;
            return $service;
        };
        
        $this->container->set('service', $factory);
        
        $service = $this->container->get('service');
        $dependency = $this->container->get('dependency');
        
        $this->assertSame($dependency, $service->dependency);
    }

    public function testExplicitServiceRegistrationWithoutReflection(): void
    {
        // This test verifies the core principle of ADR-003:
        // Services must be explicitly registered, no reflection/autowiring
        
        // Explicit registration required
        $this->container->set('test_service', fn() => new TestServiceFixture('my_service'));
        
        $service = $this->container->get('test_service');
        
        $this->assertInstanceOf(TestServiceFixture::class, $service);
        $this->assertSame('my_service', $service->name);
    }

    public function testComplexDependencyGraph(): void
    {
        // Simulate a realistic dependency graph
        
        // Layer 1: Basic dependency
        $this->container->set('config', fn() => ['db' => 'localhost']);
        
        // Layer 2: Service depending on config
        $this->container->set('database', function(ContainerInterface $c) {
            $config = $c->get('config');
            $db = new \stdClass();
            $db->host = $config['db'];
            return $db;
        });
        
        // Layer 3: Service depending on database
        $this->container->set('user_repository', function(ContainerInterface $c) {
            $db = $c->get('database');
            $repo = new \stdClass();
            $repo->db = $db;
            return $repo;
        });
        
        // Layer 4: Service depending on repository
        $this->container->set('user_service', function(ContainerInterface $c) {
            $repo = $c->get('user_repository');
            $service = new \stdClass();
            $service->repo = $repo;
            return $service;
        });
        
        // Resolve the top-level service
        $userService = $this->container->get('user_service');
        
        $this->assertInstanceOf(\stdClass::class, $userService);
        $this->assertInstanceOf(\stdClass::class, $userService->repo);
        $this->assertInstanceOf(\stdClass::class, $userService->repo->db);
        $this->assertSame('localhost', $userService->repo->db->host);
    }

    public function testFactoryCanReturnNull(): void
    {
        $this->container->set('nullable_service', fn() => null);
        
        $result = $this->container->get('nullable_service');
        
        $this->assertNull($result);
    }

    public function testFactoryCanReturnScalarValues(): void
    {
        $this->container->set('string_service', fn() => 'string_value');
        $this->container->set('int_service', fn() => 42);
        $this->container->set('bool_service', fn() => true);
        $this->container->set('array_service', fn() => ['key' => 'value']);
        
        $this->assertSame('string_value', $this->container->get('string_service'));
        $this->assertSame(42, $this->container->get('int_service'));
        $this->assertTrue($this->container->get('bool_service'));
        $this->assertSame(['key' => 'value'], $this->container->get('array_service'));
    }

    public function testNoAutowiringOrReflection(): void
    {
        // This test verifies that autowiring is NOT used
        // Services must be explicitly registered
        
        // Without explicit registration, service should not exist
        $this->assertFalse($this->container->has('UnautoWiredClass'));
        $this->assertFalse($this->container->has('unautowired_class'));
        
        // Exception when trying to get non-registered service
        $this->expectException(NotFoundExceptionInterface::class);
        $this->container->get('UnautoWiredClass');
    }
}

/**
 * Test fixture class
 */
class TestServiceFixture
{
    public function __construct(public string $name = 'test') {}
}
