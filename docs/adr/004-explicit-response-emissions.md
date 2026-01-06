# ADR-004: Explicit HTTP Response Emission

Status: Accepted \
Date: 2026-01-03 \
Decision Makers: Istvan Dobrentei \
Context: HTTP response handling in legacy PHP environments

### 1. Context

Legacy PHP applications commonly:

- write output directly using echo or print
- modify headers imperatively using header()
- rely on output buffering implicitly
- mix response generation and emission

Modern PHP applications typically:

- return immutable PSR-7 ResponseInterface objects
- separate response creation from response emission
- control output flow explicitly

When modern and legacy code coexists, response handling becomes a critical integration point.

### 1. Decision

LegacyBridge PHP will emit HTTP responses explicitly using a dedicated response emitter.

LegacyBridge will:

- convert legacy output into a PSR-7 response
- emit headers, status code, and body in a defined order
- centralize response emission logic
- avoid implicit output flushing

LegacyBridge will not:

- rely on implicit PHP output buffering behavior
- allow uncontrolled output emission
- hide response side effects
