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

LegacyBridge v1.0 will support exactly one migration pattern: A legacy front controller selectively delegating to PSR-7 style controllers that return a PSR-7 Response.

This pattern is:

- explicitly documented
- officially supported
- covered by stability guarantees

All other patterns are considered **out of scope** for v1.0.