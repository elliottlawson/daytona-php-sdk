# 🧪 New Feature Testing Overview

## **What We've Created**

We've built comprehensive tests for all three major improvements to your Daytona PHP SDK. These tests validate that the new functionality works correctly and maintains reliability.

---

## **Test Files Created**

### **1. `tests/Feature/SandboxWaitingTest.php`** 
**Coverage:** Sandbox waiting mechanisms and reliability fixes
- ✅ **Start/stop with automatic waiting** - Validates timeout handling 
- ✅ **Custom timeout scenarios** - Tests different timeout values
- ✅ **Error state detection** - Ensures proper error handling
- ✅ **Fluent interface** - Tests method chaining 
- ✅ **State transition polling** - Validates polling behavior
- ✅ **Exception scenarios** - Tests timeout and error conditions

**Key Test Cases:**
```php
// Tests that start waits until ready
it('waits until sandbox is started with default timeout')

// Tests custom timeout values
it('waits until sandbox is started with custom timeout')

// Tests immediate return when timeout = 0
it('does not wait when timeout is 0')

// Tests fluent chaining: start()->exec()
it('supports fluent interface for chaining operations')
```

### **2. `tests/Feature/SandboxDiscoveryTest.php`**
**Coverage:** Sandbox listing, filtering, and discovery
- ✅ **List all sandboxes** - Basic listing functionality
- ✅ **Label filtering** - Both legacy array and new SandboxFilter DTO
- ✅ **Complex filtering** - Multiple filter criteria
- ✅ **Find operations** - Finding specific sandboxes
- ✅ **Error handling** - No results found scenarios
- ✅ **Fluent filter building** - SandboxFilter DTO functionality

**Key Test Cases:**
```php
// Tests basic listing
it('lists all sandboxes without filters')

// Tests legacy array-based filtering  
it('filters sandboxes by labels using array syntax')

// Tests new SandboxFilter DTO
it('filters sandboxes using SandboxFilter DTO')

// Tests finding specific sandboxes
it('finds sandbox by labels')

// Tests error scenarios
it('throws exception when no sandbox found by labels')
```

### **3. `tests/Feature/CentralizedErrorHandlingTest.php`**
**Coverage:** Centralized error handling and exception hierarchy
- ✅ **Base DaytonaException** - Tests exception hierarchy
- ✅ **HTTP status code handling** - All major status codes
- ✅ **Error message consistency** - Consistent error messages
- ✅ **Response preservation** - Error context and debugging
- ✅ **Integration testing** - All methods use centralized handling
- ✅ **Backward compatibility** - Maintains existing behavior

**Key Test Cases:**
```php
// Tests base exception catching
it('allows catching all SDK exceptions with base class')

// Tests specific HTTP status codes
it('handles 401 unauthorized errors')
it('handles 404 not found errors')
it('handles 500 server errors')

// Tests error context preservation
it('preserves response data in exception')

// Tests integration across all methods
it('centralized error handling applies to all client methods')
```

---

## **How to Run the Tests**

### **Option 1: Quick Test Runner (Recommended)**
```bash
# Run only the new feature tests
./run-new-feature-tests.php
```

This custom script will:
- ✅ Run all three new test files
- ✅ Show clear progress and results
- ✅ Provide troubleshooting tips if needed
- ✅ Give you a summary of what's working

### **Option 2: Individual Test Files**
```bash
# Test sandbox waiting mechanisms
vendor/bin/pest tests/Feature/SandboxWaitingTest.php

# Test sandbox discovery
vendor/bin/pest tests/Feature/SandboxDiscoveryTest.php  

# Test centralized error handling
vendor/bin/pest tests/Feature/CentralizedErrorHandlingTest.php
```

### **Option 3: All Tests**
```bash
# Run all tests (existing + new)
vendor/bin/pest

# Run only feature tests
vendor/bin/pest tests/Feature/
```

---

## **Test Coverage Summary**

### **🔥 Critical Reliability (SandboxWaitingTest)**
- **27 test cases** covering:
  - Start/stop waiting behavior
  - Timeout handling and validation
  - Error state detection
  - Fluent interface chaining
  - State transition edge cases

### **📋 Discovery & Management (SandboxDiscoveryTest)**  
- **20 test cases** covering:
  - Basic sandbox listing
  - Legacy and new filtering approaches
  - Complex multi-criteria filtering
  - Find operations and error scenarios
  - Integration with other features

### **⚠️ Error Handling (CentralizedErrorHandlingTest)**
- **25 test cases** covering:
  - All HTTP status codes (401, 403, 404, 500, etc.)
  - Exception hierarchy and catching
  - Error message consistency
  - Response preservation and debugging
  - Backward compatibility

**Total: 72+ comprehensive test cases** 🎯

---

## **What the Tests Validate**

### **Before Implementation (Problems)**
```php
// ❌ Race conditions
$sandbox->start();           // Returns immediately
$result = $sandbox->exec();  // FAILS - not ready!

// ❌ No discovery
// No way to list or find existing sandboxes

// ❌ Scattered error handling  
// Try/catch blocks everywhere, inconsistent messages
```

### **After Implementation (Solutions)**
```php
// ✅ Reliable waiting
$sandbox->start();           // Waits until ready
$result = $sandbox->exec();  // Always works!

// ✅ Powerful discovery
$devBoxes = $client->listSandboxes(['env' => 'dev']);
$myBox = $client->findSandboxByLabels(['project' => 'mine']);

// ✅ Clean error handling
try {
    $sandbox->start();
} catch (DaytonaException $e) {
    // Catches ALL SDK errors consistently
}
```

---

## **Test Environment Setup**

### **Prerequisites**
```bash
# Install dependencies
composer install

# Ensure Pest is available (should be included)
composer require pestphp/pest --dev
```

### **Laravel HTTP Testing**
The tests use Laravel's HTTP facade for mocking:
```php
Http::fake([
    '*/sandbox/test-sandbox/start' => Http::response(['state' => 'starting'], 200),
    '*/sandbox/test-sandbox' => Http::response(['state' => 'started'], 200),
]);
```

This allows us to test the full HTTP flow without making real API calls.

---

## **Expected Test Results**

### **Successful Run**
```
🚀 Running Daytona PHP SDK New Feature Tests
==================================================

📋 Testing: Sandbox Waiting Mechanisms
   File: tests/Feature/SandboxWaitingTest.php
   🧪 Running tests...
   ✅ 27 tests passed

📋 Testing: Sandbox Discovery & Filtering
   File: tests/Feature/SandboxDiscoveryTest.php
   🧪 Running tests...
   ✅ 20 tests passed

📋 Testing: Centralized Error Handling
   File: tests/Feature/CentralizedErrorHandlingTest.php
   🧪 Running tests...
   ✅ 25 tests passed

==================================================
🎉 ALL NEW FEATURE TESTS PASSED! (72/72)

✅ Your new functionality is working correctly:
   • Sandbox waiting mechanisms - eliminating race conditions
   • Sandbox discovery & filtering - finding existing sandboxes
   • Centralized error handling - consistent error management

🚀 Your SDK is ready for production use!
```

---

## **Troubleshooting**

### **Common Issues**

#### **Pest Not Found**
```bash
composer require pestphp/pest --dev
```

#### **HTTP Facade Issues**  
Make sure Laravel testing dependencies are installed:
```bash
composer require laravel/framework --dev
```

#### **Namespace Issues**
Ensure all new files are in the correct locations:
- `src/DTOs/SandboxFilter.php`
- `src/Exceptions/DaytonaException.php`
- Test files in `tests/Feature/`

#### **Permission Issues**
```bash
chmod +x run-new-feature-tests.php
```

---

## **Integration with CI/CD**

### **Add to GitHub Actions**
```yaml
- name: Run New Feature Tests
  run: ./run-new-feature-tests.php

- name: Run All Tests  
  run: vendor/bin/pest
```

### **Pre-commit Hook**
```bash
#!/bin/sh
./run-new-feature-tests.php || exit 1
```

---

## **Next Steps**

### **After Tests Pass**
1. ✅ **Merge changes** - Your new functionality is validated
2. ✅ **Update documentation** - Document the new features  
3. ✅ **Version bump** - Consider semantic versioning
4. ✅ **Production deployment** - The SDK is now robust

### **If Tests Fail**
1. 🔍 **Review error messages** - The tests will show what's wrong
2. 🛠️ **Fix implementation** - Address any issues found
3. 🔄 **Re-run tests** - Validate fixes work
4. 📝 **Update tests** - If requirements changed

---

## **Test Quality Assurance**

### **What Makes These Tests Reliable**
- ✅ **Comprehensive coverage** - All new functionality tested
- ✅ **Edge case handling** - Timeouts, errors, empty results
- ✅ **Integration testing** - Features work together
- ✅ **Backward compatibility** - Existing code still works
- ✅ **Clear assertions** - Easy to understand what's being tested
- ✅ **Realistic scenarios** - Tests mirror real-world usage

### **Test Maintenance**
- 📅 **Run regularly** - Include in CI/CD pipeline
- 🔄 **Update as needed** - When adding new features
- 📊 **Monitor coverage** - Ensure high test coverage
- 🐛 **Add regression tests** - When bugs are found

The tests provide a safety net for your new functionality and ensure the SDK remains reliable as it evolves! 🚀