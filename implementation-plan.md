# Daytona PHP SDK Implementation Plan

## **Phase 1: Critical Reliability Fixes** 🔥

### 1. **Sandbox Waiting Mechanisms** (HIGHEST PRIORITY)
**Files to modify:**
- `src/DaytonaClient.php` - Add waiting logic to start/stop methods
- `src/Sandbox.php` - Update start/stop to use new signatures
- `src/Exceptions/SandboxException.php` - Add timeout/state error methods

**New functionality:**
- `waitUntilSandboxStarted()` - Poll until sandbox is ready
- `waitUntilSandboxStopped()` - Poll until sandbox is stopped  
- Enhanced `startSandbox($id, $timeout)` - Add timeout parameter
- Enhanced `stopSandbox($id, $timeout)` - Add timeout parameter

### 2. **Sandbox Discovery & Filtering** (HIGH PRIORITY)
**Files to modify:**
- `src/DaytonaClient.php` - Add list/find methods
- `src/DTOs/` - Create SandboxFilter DTO

**New functionality:**
- `listSandboxes($labels = null)` - List with optional filtering
- `findSandboxByLabels($labels)` - Find first matching sandbox

### 3. **Centralized Error Handling** (HIGH PRIORITY)  
**Files to modify:**
- `src/DaytonaClient.php` - Enhance client() method with centralized handler
- `src/Exceptions/` - Add base DaytonaException

**New functionality:**
- Centralized error interception in HTTP client
- Base `DaytonaException` for easier catching
- Better timeout detection

## **Phase 2: Enhanced Management Features** 🟡

### 4. **Label Management**
- `setLabels()` method for sandboxes
- Label-based operations

### 5. **Auto-Configuration Methods**
- `setAutoStopInterval()` 
- `setAutoArchiveInterval()`
- `setAutoDeleteInterval()`

### 6. **JWT Authentication Support**
- Enhance Config DTO
- Update client authentication

## **Implementation Order**

1. ✅ **Sandbox waiting mechanisms** - Start here (most critical)
2. ✅ **Sandbox listing/filtering** - Essential for management  
3. ✅ **Centralized error handling** - Reduce code duplication
4. 🟡 **Label management** - Nice to have
5. 🟡 **Auto-configuration** - Advanced features
6. 🟡 **JWT authentication** - Enterprise features

---

## **Detailed Change List**

### **Change 1: Sandbox Waiting Mechanisms**
```php
// DaytonaClient.php additions:
- waitUntilSandboxStarted(string $sandboxId, int $timeout): void
- waitUntilSandboxStopped(string $sandboxId, int $timeout): void  
- waitUntilSandboxState(string $sandboxId, array $targetStates, array $errorStates, int $timeout): void

// Method signature changes:
- startSandbox(string $sandboxId, ?int $timeout = 60): void
- stopSandbox(string $sandboxId, ?int $timeout = 60): void

// Sandbox.php changes:
- start(?int $timeout = 60): self
- stop(?int $timeout = 60): self
- waitUntilStarted(?int $timeout = 60): self
- waitUntilStopped(?int $timeout = 60): self
```

### **Change 2: Sandbox Discovery**
```php
// DaytonaClient.php additions:
- listSandboxes(?array $labels = null): array
- findSandboxByLabels(array $labels): Sandbox

// New DTOs:
- DTOs/SandboxFilter.php
```

### **Change 3: Error Handling**
```php
// DaytonaClient.php changes:
- Enhanced client() method with ->throw() callback
- handleApiError() method for centralized processing

// New exceptions:
- Exceptions/DaytonaException.php (base class)
- Enhanced SandboxException with timeout methods
```

Ready to start implementing! 🚀