# ADR-001: Choosing LegacyBridge as a Modernization Bridge Instead of a Framework

Status: Accepted \
Date: 2026-01-03 \
Decision Makers: Istvan Dobrentei \
Context: Legacy PHP applications in production need modernization

### 1. Context

Many PHP applications in production are:

- tightly coupled to global state ($GLOBALS, require/include)
- non-PSR compliant
- long-lived and business critical
- impossible or too risky to rewrite in a single step

Teams need to introduce modern PHP practices (PSR-7, PSR-11, testable controllers, dependency injection) while keeping legacy code operational.

Existing approaches:

1. Full rewrite - High risk, often not feasible.
2. Framework adoption (Laravel/Symfony) - Forces architectural decisions, adds heavy dependencies, breaks legacy code.
3. Patchwork adapters - Unreliable, non standard, no guarantees.

We need a solution that respects legacy constraints while allowing incremental modernization. Modernization timelines for multinational production systems are measured in quarters or years, not sprints. This reality demands an approach that reduces risk incrementally rather than betting on large upfront architectural changes.

### 2. Decision

We will implement LegacyBridge PHP, a framework-agnostic bridge that:

- Adapts legacy entry points into a PSR-7 request lifecycle
- Exposes legacy globals through a PSR-11 container
- Supports side by side execution of legacy and modern code
- Provides a documented migration pattern
- Guarantees stability of public API within v1.x
- Separates internal helpers under an Internal namespace

We will not build a framework. We will not refactor legacy code automatically.

### 3. Consequences

Positive:

- Incremental modernization is possible
- Stable API allows adoption in production without risk
- Individual modern components can be rolled back if needed (reversibility)
- Public and internal APIs are explicitly separated
- Clear, teachable pattern for migration
- Framework-agnostic design prevents vendor lock-in even during modernization
- Allows teams to mix modern libraries and frameworks later without constraints
- Testability as a first-class outcome: modern controllers can be unit-tested in isolation while legacy code continues untested but functional
- Natural observability boundary: logging, metrics, and tracing can be added to modern components without retrofitting the legacy codebase
- Team velocity preserved: business features continue to be delivered during modernization. Unlike big-bang rewrites, the bridge approach does not freeze feature development
- Reflects a professional, experience driven approach: risk reduction, long term maintainability

Negative/Trade offs:

- Limited functionality in v1.0
- Only supports one migration pattern initially
- Users must understand the bridge concept
- Not a full framework or "magic solution"
- Success requires organizational discipline: incrementalism is slower than wholesale rewrites, but much safer

### 4. Rationale

Why not a framework?

- Frameworks impose architectural constraints that legacy apps cannot follow.
- Framework adoption is a breaking change, often impossible for multinational production systems.
- LegacyBridge is intentionally minimal: it enforces no structure, it adapts rather than replaces.
- Reversibility is critical: teams must be able to roll back or replace modern components without wholesale rewrites. Frameworks lock teams into ecosystem-specific choices; bridges permit substitution.
- Framework-agnostic design ensures that modernization paths are not predetermined. Teams choose their own logging, testing, database access, and other concerns incrementally.

Why one migration pattern?

- Predictability is critical for production systems.
- Supporting multiple patterns increases complexity and risks inconsistencies.
- The chosen pattern (legacy front controller delegating to PSR-7 controllers) is simple, explicit, and reversible. 

Why internal namespace?

- Protects public API stability
- Signals clearly to users what they may rely on
- Prevents accidental dependencies on non-guaranteed behavior
- Reflects a principle of minimal guarantees: LegacyBridge commits only to PSR-7/PSR-11 interop and the documented migration pattern. Beyond that, teams retain freedom to choose their own architecture, testing approach, and tooling.

Documentation as contract:

- These ADRs are part of the public contract.
- Users can rely on documented behavior, not just code interfaces.
- Undocumented behavior is explicitly not guaranteed, even if it appears to work.
