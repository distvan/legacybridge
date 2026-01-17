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

### 3. Rationale

#### 3.1 Problems with Implicit Output Buffering

Many legacy applications rely on PHP's output buffering:

```php
// Legacy pattern: implicit output buffering
ob_start();

echo "<h1>Page Title</h1>";
header('Content-Type: text/html');
echo "<p>Content here</p>";

$output = ob_get_clean();
// Headers sent mixed with output generation
```

Issues with this approach:

- Unpredictable timing - It's unclear when headers are actually sent
- Hidden side effects - Output buffering state is implicit and hard to trace
- Error handling - Errors in output generation can corrupt headers
- Testing difficulty - Hard to unit test without actually sending headers
- Integration problems - Modern code expects clean separation between generation and emission


#### 3.2 Explicit Emission Provides Clarity

By centralizing response emission, LegacyBridge ensures:

- Predictable flow - Response is generated, then emitted in a defined sequence
- Visible side effects - All HTTP operations happen in one place
- Error recovery - Problems during emission are contained and logged
- Testability - Responses can be inspected before emission
- Debuggability - Headers, status, and body are traceable

Example:

```php
<?php
// Modern pattern: Explicit emission
$response = $legacyBridge->handleRequest($request);

// At this point, response is ready but NOT sent
// We can inspect, log, or modify if needed

$emitter = new ResponseEmitter();
$emitter->emit($response);

// Now the response is sent to the client
```

#### 3.3 Handling Legacy Output During Migration

During incremental migration, legacy code may directly output content:

```php
// Legacy code path
echo "Header from legacy code";
$data = get_legacy_data(); // May echo diagnostics
echo $data;

// Modern code path
$response = new Response();
$response->getBody()->write("Content from modern code");
return $response;
```

LegacyBridge must capture and handle this mixed output:

- Output buffering - Capture legacy output temporarily
- Conversion - Convert captured output into PSR-7 response body
- Ordering - Ensure response headers are sent before body
- Cleanup - Restore clean output buffer state for next request

#### 3.4 Response Emission Order Matters

HTTP requires a strict order:

1. Status line - HTTP/1.1 200 OK
2. Headers - Content-Type: text/html
3. Blank line - Separates headers from body
4. Body - Response content

If order is violated, the response is malformed:

```php
<?php
// WRONG: Emitting in wrong order
echo "Body content"; // Body before headers!
header('Content-Type: text/html'); // Too late, headers already sent

// RIGHT: Explicit emission ensures order
$emitter->emit($response);
// Emitter handles: status -> headers -> body
```

#### 3.5 Avoiding Hidden State Pollution

Implicit output buffering can leave PHP in unexpected states:

```php
<?php
// Problem: ob_start() without corresponding ob_end_clean()
ob_start();
echo "Some output";
// What happens if an exception occurs here?
// ob_end_clean() may never be called
// Output buffer left in broken state for next request
```

Explicit emission prevents this:

```php
<?php
try {
    $response = $legacyBridge->handleRequest($request);
} finally {
    $emitter->emit($response);
    // Cleanup is guaranteed, no dangling buffers
}
```

### 4. Response Emission Strategy

#### 4.1 The ResponseEmitter Component

LegacyBridge provides a dedicated ResponseEmitter that:

- Receives a PSR-7 ResponseInterface
- Emits status code and headers safely
- Streams body content efficiently
- Handles edge cases (empty body, large payloads, etc.)

```php
<?php
$emitter = new ResponseEmitter();
$emitter->emit($response);
```

#### 4.2 Emission Process

Step 1: Validate Response

```php
<?php
// Check response is complete and valid
$status = $response->getStatusCode();
if ($status < 100 || $status > 599) {
    throw new InvalidResponseException("Invalid status code: $status");
}
```

Step 2: Emit Status Code

```php
<?php
// Send HTTP status line
$reasonPhrase = $response->getReasonPhrase();
http_response_code($status);
```

Step 3: Emit Headers

```php
<?php
// Send each header in order
foreach ($response->getHeaders() as $name => $values) {
    foreach ($values as $value) {
        header("$name: $value", false);
    }
}
```

Step 4: Emit Body

```php
<?php
// Stream body content efficiently
$body = $response->getBody();
echo $body->getContents();
```

#### 4.3 Handling Legacy Output Capture

```php
<?php
class LegacyBridge {
    public function handleRequest(ServerRequestInterface $request): ResponseInterface
    {
        // Start output buffering before legacy code
        ob_start();
        
        try {
            // Run legacy code - may echo output
            $legacyOutput = include 'legacy/index.php';
            
            // Capture any output
            $output = ob_get_clean();
            
            // Convert to response
            $response = new Response();
            $response->getBody()->write($output);
            return $response;
        } catch (Exception $e) {
            ob_end_clean(); // Clean up on error
            throw $e;
        }
    }
}
```

#### 4.4 Merging Legacy and Modern Responses

```php
<?php
// Combine outputs
$legacyOutput = ... // Captured from legacy code
$modernOutput = ... // From modern controller

$response = new Response();
$response->getBody()->write($legacyOutput);
$response->getBody()->write($modernOutput);

return $response;
```

### 5. Test Driven Approach

Explicit emission enables testing without sending actual HTTP responses:

```php
<?php
// Test: Response object can be inspected
$response = $legacyBridge->handleRequest($request);

$this->assertEquals(200, $response->getStatusCode());
$this->assertEquals('text/html', $response->getHeaderLine('Content-Type'));
$this->assertStringContainsString('Expected content', (string)$response->getBody());

// No HTTP traffic, no side effects, fast and reliable
```

### 6. Performance Considerations
#### 6.1 Outout Buffering Overhead

Output buffering has memory costs:

- Entire response body buffered in memory
- For large responses, can consume significant RAM
- On shared hosting, may hit memory limits

Mitigation:

- Use streaming for large responses when possible
- Monitor output buffer size in production
- Consider response pagination for large datasets

#### 6.2 Header Emission Timing

Emitting headers requires they haven't been sent yet:

```php
<?php
// This fails if ANY output has been sent
header('Content-Type: text/html');
```

Strategy:

- Clear output buffer before emitting headers
- Verify no output has escaped
- Log if headers-already-sent errors occur

### 7. Edge Cases
#### 7.1 Multiple Cals to emit()

```php
<?php
$emitter->emit($response);
$emitter->emit($response); // Headers already sent - ERROR

// Solution: Track emission state
if ($response->isEmitted()) {
    throw new ResponseAlreadyEmittedException();
}
```

#### 7.2 Streaming Responses

```php
<?php
// Large file download
$response = new Response(fopen('/large/file.bin', 'r'));
$emitter->emit($response);

// Body should stream without buffering entire file
```

#### 7.3 Empty Responses

```php
<?php
// 204 No Content
$response = new Response(204);
$emitter->emit($response);

// Should emit status and headers, skip body
```