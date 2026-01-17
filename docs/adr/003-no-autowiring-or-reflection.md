# ADR-003: Avoiding Auto-Wiring and Reflection Based Dependency Resolution

Status: Accepted \
Date: 2026-01-03 \
Decision Makers: Istvan Dobrentei \
Context: dependency management in legacy PHP modernization

### 1. Context

Modern PHP frameworks often rely on:

- reflection based auto wiring
- implicit dependency resolution
- convention over configuration

While these approaches improve developer convenience in greenfield projects, they introduce **implicit behavior** and **hidden coupling**.

Legacy PHP application typically:

- depend on global state
- initialize dependencies procedurally
- contain side effects during bootstrap
- lack explicit dependency graphs

Introducing auto wiring into such environments can create non-deterministic behavior and make migration riskier.

### 2. Decision

LegacyBridge PHP will not support auto wiring or reflection based dependency resolution.

Instead,

- requires explicit service registration
- resolves dependencies lazily via defined factories
- exposes legacy dependencies intentionally

The container is designed as a minimal PSR-11 wrapper, not a full featured dependency injection framework.

### 3. Rationale

#### 3.1 Predictability in Legacy Environments

Auto-wiring uses runtime reflection to inspect class constructors and dependencies, automatically resolving them based on type hints. This works well in controlled greenfield projects but fails in legacy systems because:

- Legacy code often lacks type hints entirely
- Global state and static methods make dependency graphs non-deterministic
- Bootstrap side effects (database connections, cache initialization) can interfere with auto-wiring
- Constructor dependencies may conflict with global initialization order

**Explicit registration ensures:**
- Every dependency relationship is visible in code
- No hidden assumptions about bootstrap order
- Developers can reason about what will be injected

#### 3.2 Avoiding Hidden Coupling

Reflection-based dependency resolution obscures the relationship between components:

- Code that auto-wires dependencies creates implicit contracts
- Developers don't see where dependencies come from
- Changing a class name or constructor accidentally breaks dependencies at runtime
- Type hints become implicit requirements rather than explicit contracts

**Explicit factories preserve clarity:**
- Dependencies are defined where they're registered, not discovered at runtime
- Coupling is visible and intentional
- Refactoring is safer because all usages are in code you can search

#### 3.3 Performance and Startup Concerns

Reflection-based auto-wiring has measurable costs:

- Runtime reflection happens on every request (or is cached, adding complexity)
- Bootstrap side effects from implicit resolution can delay application startup
- Debugging performance issues becomes harder when behavior is hidden

**Explicit registration is faster:**
- No reflection overhead per request
- Dependencies are resolved exactly as defined
- Startup behavior is predictable and auditable

#### 3.4 Legacy Dependencies Are Not Autowireable

Legacy PHP applications typically have:

- Global variables and constants (`$GLOBALS`, `define()`)
- Static factory methods (`Database::getInstance()`)
- Configuration files (`require 'config.php'`)
- Singletons initialized at bootstrap (`$db = new DatabaseConnection()`)

These cannot be reliably auto-wired. They require explicit, intentional wrapping and exposure through the container.

### 4. Approach: Explicit Service Registration

#### 4.1 PSR-11 Wrapper Pattern

LegacyBridge provides a minimal PSR-11 `ContainerInterface` implementation that:

- Stores service definitions as closures (factories)
- Resolves services on-demand (lazy initialization)
- Exposes both modern PSR services and wrapped legacy dependencies
- Does not use reflection

Example:

```php
$container = new LegacyContainer();

// Register a modern service
$container->set('response_factory', function() {
    return new ResponseFactory();
});

// Wrap a legacy global
$container->set('legacy_db', function() {
    global $database;
    return $database;
});

// Resolve when needed
$db = $container->get('legacy_db');
```

#### 4.2 Explicit Dependency Declaration

Controllers and modern components declare dependencies explicitly:

- Through constructor injection (type hints, no auto-wiring)
- Through factory functions passed by the application
- Through explicit container access in delegation points

This ensures:

- Dependencies are visible in the code
- Migration seams are clear
- Legacy and modern code boundaries are explicit

#### 4.3 No Convention Over Configuration

LegacyBridge does not:

- Scan directories for controllers
- Auto-discover routes from annotations
- Resolve class names to services by convention
- Wire dependencies based on parameter names

All wiring is explicit:

```php
<?php
// Explicit: We choose which modern controller to delegate to
if ($request->getPath() === '/api/users') {
    $controller = $container->get('user_controller');
    return $controller->handleRequest($request);
}
```

### 5. Trade offs

#### 5.1 Verbosity vs. Safety

Explicit registration requires more boilerplate code compared to auto-wiring. However:

In legacy environments, safety and visibility are more valuable than brevity and developers can see exactly what's happening and migration risk is reduced and debugging is easier.

#### 5.2 Framework Familiarity

Developers familiar with Laravel, Symfony, or other frameworks may expect auto-wiring. However:

- LegacyBridge is not a framework
- The explicit approach is intentional and deliberate
- The migration path is the priority, not framework convenience

### 6. Appendix: Risks of auto-wiring in legacy system

#### Risk 1: Non deterministic bootstrap order

```php
<?php
// In a legacy system:
$db = new Database(); // Global initialization

// If auto-wiring tries to resolve dependencies before this:
// The database connection may not exist yet
class UserController {
    public function __construct(Database $db) {}
}
```

#### Risk 2: Type hint mismatches

```php
<?php
// Legacy code: no type hint
public function __construct($config) {
    $this->config = $config; // Could be array, object, or string
}

// Auto-wiring can't determine what to inject
```

#### Risk 3: Hidden global state

```php
<?php
class LegacyService {
    public function getData() {
        return $GLOBALS['cache']; // Hidden coupling
    }
}

// Auto-wiring doesn't know about this dependency
// The service breaks if the global isn't initialized first
```

#### Risk 4: Side effects during resolution

```php
<?php
class LegacyComponent {
    public function __construct() {
        // Side effect: database query during instantiation
        $this->data = database_query("SELECT ...");
        // Side effect: log initialization
        file_put_contents('log.txt', 'Initialized at ' . time());
    }
}

// Auto-wiring triggers these side effects unexpectedly
```