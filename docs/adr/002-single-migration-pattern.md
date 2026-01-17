# ADR-002: Supporting a Single Migration Pattern in v1.0

Status: Accepted \
Date: 2026-01-03 \
Decision Makers: Istvan Dobrentei \
Context: Incremental modernization of legacy PHP applications

### 1. Context

LegacyBridge PHP exists to enable incremental migration of legacy PHP applications toward modern, PSR-compliant architecture.

A key design question is: Should LegacyBridge support multiple migration patterns (routing styles, controller types, lifecycle hooks), or one constrained pattern?

Common migration patterns observed in the PHP ecosystem include:

- full controller replacement
- framework-style routing integration
- middleware-first pipelines
- service-locator based dispatch
- hybrid approaches with multiple entry points

Each pattern has valid use cases, but also different assumptions and risks.

### 2. Decision

LegacyBridge v1.0 will support **exactly one migration** pattern: A legacy front controller selectively delegating to PSR-7 style controllers that return a PSR-7 Response.

All other patterns are considered **out of scope** for v1.0.

### 3. Migration Pattern Description

The supported pattern has the following characteristics:

- The legacy entry point remains the primary execution path
- Legacy code continues to run unchanged
- Selected requests are delegated to modern controllers
- Controllers receive a ServerRequestInterface
- Controllers return a ResponseInterface
- Control returns to legacy execution if needed

This enables side by side execution of legacy and modern code.

### 4. Rationale

#### 4.1 Predictability Over Flexibility

Legacy systems benefit more from:

- deterministic execution
- visible control flow
- stable mental models

Supporting a single migration pattern reduces:

- cognitive load
- undocumented behavior
- accidental architectural drift

#### 4.2 Production Safety

Each additional supported pattern increases:

- test surface area
- edge cases
- risk of inconsistent behavior

A constrained pattern allows LegacyBridge to:

- reason about behavior
- guarantee stability
- evolve cautiously

#### 4.3 Clear Migration Direction

A single pattern creates:

- a shared team language
- a clear "modern target"
- an implicit end state

Multiple patterns often result in permanent hybridity with no clear completion point

### 5. Future Considerations

Additional patterns may be considered **only** if:

- they can be added without breaking backward compatibility, or
- they are introduced in a new major version with explicit migration guidance

No additional patterns will be added to the v1.x series.

### Appendix: Rejected Alternatives

#### Alternative 1: Multiple Supported Patterns

Pros:

- Appears flexible, mutiple patterns give the impression that LegacyBridge can handle diverse use cases and scenarios.

- Attracts more use cases, by supporting different patterns, the library appeals to a wider range of projects and migration strategies, potentially attracting more users.

- Reduces initial constraints, it removes restrictions on how developers can approach modernization, giving tem more freedom in chosing their migration path.

Cons:

- Increases cognitive load that means developer have to hold more information in their mind at once, making the system harder to understand and use.

- Complicates documentation, because each pattern requires separate documentation, interactions between patterns become unclear and the documentation burder grows exponentially.

- Makes behavior harder to reason about, it's harder to debug issues when multiple execution models exist.

- Weakens stability guarantees, supporting multiple patterns increases the test surface area, more edge cases abd interactions need to be covered and the risk of inconsistent behavior grows. This is especially dangerous for legacy systems where stability is critical.

It's important to note that, the ADR ultimately rejected this alternative because the cons outweight the benefits, especially for legacy systems where **safety and clarity** are more critical than flexibility.

#### Alternative 2: Framework Style Routing Integration

Pros:

- Familiar to framework users
- Powerful abstractions

Cons:

- Imposes architectural decisions, it forces a specific arhitectural approach on projects, rather than letting them choose their own path

- Conflicts with legacy execution models. Legacy systems have their own established execution patterns and request handling. Framework-style routing doesn't align well with how legacy applications already work.

- Moves LegacyBridge toward framework territory. This would transform LegacyBridge from bridge/adapter library into something that acts more like a framework. **According to ADR-001**, LegacyBridge is explicitly not a framework, so this violates the project's core philosophy.

LegacyBridge is designed to be a minimal, explicit tool for incrementally modernizing legacy code, not to impose a framework's architectural vision on top of existing systems.


#### Alternative 3: Middleware First Architecture

A middleware-first architecture structures the application as a PSR-15 middleware pipeline, with all request handling flow through middleware layers befor reaching the controller.

Pros:

- Modern and expressive
- Clean separation of concerns
- Familiar to PSR-15 users

Cons:

- Requires deep restructuring
- Difficult to adopt incrementally
- Risky for legacy systems

Middleware-first architectures expect to control how requests flow through the system. But legacy systems already have their own established request handling and control flow. This creates a conflict.

Adding a middleware pipeline on top of legacy code adds another layer of indirection and execution flow that developers need to understand.

With middleware pipelines, requests pass through multiple layers before reaching controllers. It becomes harder to see where the actual business logic is happening and trace the execution path.

Middleware-first assumes the final modern system should also use middleware pipelines. This locks in an architectural decision before you've fully migrated the system.

**LegacyBridge wants to be transparent** about what's happening. With a middleware-first approach, you lose visibility into the request flow. Instead, LegacyBridge favors explicit delegation where you can clearly see: "Legacy code runs here, then delegates to modern code here." This keeps the migration path visible and understandable at every step.

Middleware-first architectures assume ownership of the request lifecycle, which legacy systems already control. Introducing middleware as the primary integration mechanism increases complexity, hides control flow, and prematurely commits the system to a specific architectural end state.
LegacyBridge v1.0 favors **explicit delegation and visible migration seams** over indirect execution models.

#### Alternative 4: Service Locator Based Approach

Legacy code resolves modern components via a central registry or container, typically using string identifiers.

With a service locator pattern dependencies are resolved through a central registry using string identifiers. This makes it unclear which components depend on what, hiding the actual dependencies behind indirect lookups.

Developers can't see the path from legacy to modern code. Dependencies are hidden in a registry, so there's no visible seam showing where legacy code ends and modern code begins.

LegacyBridge is built on the principle of explicit delegation. The library wants developers to see exactly how requests flow and which components are being used. Service locators do the opposite, they use implicit resolution where you can't easily trace where a dependency came from.

Therefore the core problem is LegacyBridge is built on the principle of explicit delegation. The library wants developers to see exactly how requests flow and which components are being used. Service locators do the opposite, they use implicit resolution whereyou can't easily trace where a dependency came from.

This approach obscures architectural boundaries and does not create a clear migration direction. It contradicts LegacyBridge's goal of **making dependencies explicit**.

#### Alternative 5: Hybrid Approach with Multiple Entry Points

Legacy and modern systems run under separate entry points, often split by routing rules or subpaths.

LegacyBridge wants all requests to flow through one consistent path. This makes it easier to understand what's happening at each step.

When everything goes through one entry point, developers can trace request flow more easily. With multiple entry points, you have to figure out which path a request took, making debugging harder.

A single path means a single mental model. Developers don't need to understand multiple different execution flows depending on routing rules or subpaths.

The goal of LegacyBridge is to gradually migrate legacy code to modern code until you reach a fully modern system. Multiple entry points create permanent separation between legacy and modern systems, preventing convergence toward a unified, modern codebase.

If legacy and modern systems run under separate entry points (e.g., /legacy/* routes to old code, /modern/* routes to new code), you end up with two parallel systems that never merge. This creates technical debt and makes the final migration goal unclear. LegacyBridge's philosophy is to have one request path where legacy and modern code coexist and gradually transition toward all-modern code.

LegacyBridge prioritizes a **single, unified request lifecycle** to simplify debugging, reasoning, and longterm convergence.