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
