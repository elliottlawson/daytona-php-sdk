# Sandbox Start/Stop API Behavior Analysis

## **TL;DR - API Behavior**

The sandbox start/stop APIs **return immediately** - they do NOT hang until completion. The TypeScript SDK implements waiting as a **convenience layer** with polling.

---

## **How The API Actually Works**

### **API Response Pattern**
```bash
POST /sandbox/{id}/start
# Returns: 200 OK immediately with updated sandbox data
# The sandbox might still be in "starting" state

GET /sandbox/{id}  
# Returns: Current state (starting -> started -> running)
```

### **State Transitions**
```
start() called -> "starting" -> "started" -> "running"
stop() called  -> "stopping" -> "stopped"
```

The API call returns **as soon as the transition begins**, not when it completes.

---

## **Current PHP SDK vs TypeScript SDK**

### **Your Current Implementation** (Returns Immediately)
```php
// DaytonaClient.php
public function startSandbox(string $sandboxId): void
{
    $response = $this->client()->post("sandbox/{$sandboxId}/start");
    // ✅ API call returns immediately
    // ❌ No waiting for actual completion
}

// Sandbox.php  
public function start(): void
{
    $this->client->startSandbox($this->id);
    // ❌ Returns immediately, sandbox might not be ready
}
```

### **TypeScript SDK Implementation** (Waits Until Ready)
```typescript
public async start(timeout = 60): Promise<void> {
    // 1. Make the API call (returns immediately)
    const response = await this.sandboxApi.startSandbox(this.id, undefined, { timeout: timeout * 1000 })
    
    // 2. Update local state with response
    this.processSandboxDto(response.data)
    
    // 3. Wait until actually started (convenience layer)
    await this.waitUntilStarted(timeout)
}

// The waiting mechanism (polling-based)
public async waitUntilStarted(timeout = 60) {
    const checkInterval = 100 // Poll every 100ms
    
    while (this.state !== 'started') {
        await this.refreshData()  // GET /sandbox/{id}
        
        if (this.state === 'started') return
        if (this.state === 'error') throw new Error(...)
        if (timeout exceeded) throw new Error(...)
        
        await sleep(100ms)
    }
}
```

---

## **The Problem With Your Current Approach**

### **Race Condition Example**
```php
$sandbox = $client->createSandbox($params);
$sandbox->start();  // Returns immediately
$sandbox->exec('echo "hello"');  // ❌ FAILS - sandbox not ready yet!
```

### **User Experience Issues**
- Users don't know when sandbox is actually ready
- No feedback during startup process  
- Commands fail unpredictably
- No timeout handling

---

## **What You Need to Implement**

### **Option 1: Add Waiting to Existing Methods** (Recommended)
```php
// DaytonaClient.php
public function startSandbox(string $sandboxId, ?int $timeout = 60): void
{
    $response = $this->client()->post("sandbox/{$sandboxId}/start");
    
    if (!$response->successful()) {
        throw ApiException::fromResponse($response, 'start sandbox');
    }
    
    // Wait until actually started
    $this->waitUntilSandboxStarted($sandboxId, $timeout);
}

private function waitUntilSandboxStarted(string $sandboxId, int $timeout): void
{
    $startTime = time();
    $checkInterval = 0.1; // 100ms
    
    while (true) {
        $sandbox = $this->getSandbox($sandboxId);
        
        if ($sandbox->state === 'started') {
            return; // ✅ Ready!
        }
        
        if ($sandbox->state === 'error') {
            throw SandboxException::startFailed($sandboxId, $sandbox->errorReason);
        }
        
        if ($timeout > 0 && (time() - $startTime) > $timeout) {
            throw SandboxException::startTimeout($sandboxId, $timeout);
        }
        
        usleep($checkInterval * 1000000); // Convert to microseconds
    }
}
```

### **Option 2: Separate Methods** (More Flexible)
```php
// Keep existing immediate methods
public function startSandbox(string $sandboxId): void { /* existing */ }

// Add new waiting methods  
public function startSandboxAndWait(string $sandboxId, int $timeout = 60): void
{
    $this->startSandbox($sandboxId);
    $this->waitUntilSandboxStarted($sandboxId, $timeout);
}

public function waitUntilSandboxStarted(string $sandboxId, int $timeout = 60): void
{
    // Implementation above
}
```

### **Option 3: Enhanced Sandbox Class** (Best UX)
```php
// Sandbox.php
public function start(?int $timeout = 60): self
{
    $this->client->startSandbox($this->id, $timeout);
    $this->refresh(); // Update local state
    return $this;
}

public function waitUntilStarted(?int $timeout = 60): self
{
    // Dedicated waiting method
    $this->client->waitUntilSandboxStarted($this->id, $timeout);
    $this->refresh();
    return $this;
}

// Fluent interface
$sandbox->start()->exec('echo "hello"');  // ✅ Works reliably
```

---

## **Implementation Details**

### **State Checking Logic**
```php
private function waitUntilSandboxState(string $sandboxId, array $targetStates, array $errorStates, int $timeout): void
{
    $startTime = time();
    
    while (true) {
        $sandbox = $this->getSandbox($sandboxId);
        
        if (in_array($sandbox->state, $targetStates)) {
            return; // Success!
        }
        
        if (in_array($sandbox->state, $errorStates)) {
            throw SandboxException::stateError($sandboxId, $sandbox->state, $sandbox->errorReason);
        }
        
        if ($timeout > 0 && (time() - $startTime) > $timeout) {
            throw SandboxException::timeout($sandboxId, $targetStates, $timeout);
        }
        
        usleep(100000); // 100ms
    }
}

// Usage:
$this->waitUntilSandboxState($id, ['started'], ['error', 'failed'], 60);
$this->waitUntilSandboxState($id, ['stopped'], ['error'], 60);
```

### **Enhanced Error Handling**
```php
class SandboxException extends Exception
{
    public static function startTimeout(string $sandboxId, int $timeout): self
    {
        return new self("Sandbox {$sandboxId} failed to start within {$timeout} seconds");
    }
    
    public static function stateError(string $sandboxId, string $state, ?string $reason): self
    {
        $message = "Sandbox {$sandboxId} entered error state: {$state}";
        if ($reason) $message .= " - {$reason}";
        return new self($message);
    }
}
```

---

## **Recommended Implementation**

### **Phase 1: Fix Current Methods**
1. **Add timeout parameter** to `startSandbox()`/`stopSandbox()`
2. **Add waiting logic** to these methods
3. **Update Sandbox class** to use new signatures

### **Phase 2: Add Convenience Methods**  
4. **Add dedicated waiting methods** for flexibility
5. **Add state checking utilities**
6. **Enhance error messages**

### **Example Usage After Implementation**
```php
// Simple case (waits automatically)
$sandbox = $client->createSandbox($params);
$sandbox->start(); // Waits until ready
$result = $sandbox->exec('echo "hello"'); // ✅ Works reliably

// Advanced case (custom timeout)
$sandbox->start(120); // Wait up to 2 minutes

// Separate waiting (for complex flows)
$sandbox->startAsync(); // Returns immediately
// ... do other work ...
$sandbox->waitUntilStarted(60); // Wait when ready
```

---

## **Why This Matters**

### **Reliability**
- Eliminates race conditions
- Predictable behavior
- Better error handling

### **User Experience**  
- Clear feedback on readiness
- Configurable timeouts
- Intuitive API

### **Debugging**
- Clear error states
- Timeout information
- Better logging

The TypeScript SDK's approach is **much more reliable** because it ensures sandboxes are actually ready before returning control to the user. Your current approach creates unpredictable race conditions.