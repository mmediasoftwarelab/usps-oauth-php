# Testing Guide

## Running Tests

### Install Dependencies

```bash
composer install
```

### Run All Unit Tests

```bash
composer test
# or
vendor/bin/phpunit
```

### Run Specific Test Suite

```bash
# Unit tests only
vendor/bin/phpunit --testsuite="USPS OAuth PHP Test Suite"

# Integration tests (requires credentials)
vendor/bin/phpunit --testsuite=integration
```

### Run Tests with Coverage

```bash
vendor/bin/phpunit --coverage-html coverage
```

Open `coverage/index.html` in your browser to see the report.

### Run Static Analysis

```bash
# PHPStan - Level 8
composer run phpstan

# PHP CodeSniffer - PSR-12
composer run phpcs
```

---

## Integration Tests

Integration tests make real API calls to USPS sandbox environment.

### Setup

Set environment variables with your USPS API credentials:

**Linux/Mac:**

```bash
export USPS_CLIENT_ID="your-client-id"
export USPS_CLIENT_SECRET="your-client-secret"
```

**Windows (PowerShell):**

```powershell
$env:USPS_CLIENT_ID="your-client-id"
$env:USPS_CLIENT_SECRET="your-client-secret"
```

**Windows (CMD):**

```cmd
set USPS_CLIENT_ID=your-client-id
set USPS_CLIENT_SECRET=your-client-secret
```

### Run Integration Tests

```bash
vendor/bin/phpunit --testsuite=integration
```

**Note**: Integration tests are automatically skipped if credentials are not set.

---

## Writing Tests

### Unit Test Example

```php
<?php

namespace MMedia\USPS\Tests\Unit;

use MMedia\USPS\Client;
use MMedia\USPS\Tests\Mocks\MockHttpClient;
use PHPUnit\Framework\TestCase;

class ExampleTest extends TestCase
{
    public function testSomething(): void
    {
        $httpClient = new MockHttpClient();

        // Mock OAuth response
        $httpClient->addJsonResponse('/oauth2/v3/token', 200, [
            'access_token' => 'test-token',
            'expires_in' => 3600,
        ]);

        // Mock API response
        $httpClient->addJsonResponse('/your-endpoint', 200, [
            'data' => 'value',
        ]);

        $client = new Client(
            clientId: 'test',
            clientSecret: 'test',
            sandbox: true,
            httpClient: $httpClient
        );

        // Your test assertions
        $this->assertInstanceOf(Client::class, $client);
    }
}
```

### Integration Test Example

```php
<?php

namespace MMedia\USPS\Tests\Integration;

use MMedia\USPS\Client;
use PHPUnit\Framework\TestCase;

class ExampleIntegrationTest extends TestCase
{
    protected function setUp(): void
    {
        if (!getenv('USPS_CLIENT_ID')) {
            $this->markTestSkipped('Integration tests require credentials');
        }
    }

    public function testRealApi(): void
    {
        $client = new Client(
            clientId: getenv('USPS_CLIENT_ID'),
            clientSecret: getenv('USPS_CLIENT_SECRET'),
            sandbox: true
        );

        // Make real API call
        $result = $client->request('/endpoint');

        $this->assertNotEmpty($result);
    }
}
```

---

## Mock HTTP Client

The `MockHttpClient` allows testing without real API calls:

```php
use MMedia\USPS\Tests\Mocks\MockHttpClient;

$mock = new MockHttpClient();

// Add response for URL pattern
$mock->addJsonResponse('/oauth2/v3/token', 200, [
    'access_token' => 'test-token',
    'expires_in' => 3600,
]);

// Assert request was made
$this->assertTrue($mock->assertRequestMade('/oauth2/v3/token'));

// Get request count
$count = $mock->countRequests('/oauth2/v3/token');

// Get last request details
$lastRequest = $mock->getLastRequest();
$this->assertEquals('POST', $lastRequest['method']);

// Reset all mocks
$mock->reset();
```

---

## Continuous Integration

Tests run automatically on GitHub Actions for:

- PHP 8.1, 8.2, 8.3
- Ubuntu latest
- Every push and pull request

### CI Pipeline

1. Code validation (composer validate)
2. PHPStan static analysis (level 8)
3. PHP CodeSniffer (PSR-12)
4. Unit tests with coverage
5. Code coverage upload to Codecov
6. Integration tests (if credentials available)
7. Security audit

---

## Test Coverage Goals

- **Unit Tests**: 90%+ code coverage
- **Integration Tests**: Critical user journeys
- **All public APIs**: Must have test coverage
- **Error Paths**: Test exception handling

---

## Debugging Tests

### Run Single Test

```bash
vendor/bin/phpunit --filter testMethodName
```

### Run Single Test File

```bash
vendor/bin/phpunit tests/Unit/ClientTest.php
```

### Stop on Failure

```bash
vendor/bin/phpunit --stop-on-failure
```

### Verbose Output

```bash
vendor/bin/phpunit --verbose
```

---

## Best Practices

1. **One assertion per test** (when possible)
2. **Descriptive test names** (testGetRateValidatesZipCode)
3. **Use type hints** in test methods
4. **Clean up resources** in tearDown()
5. **Mock external dependencies** (HTTP, filesystem, etc.)
6. **Test edge cases** (empty input, null, extreme values)
7. **Test error conditions** (exceptions, validation failures)

---

## Getting Help

- Check existing tests for examples
- Read PHPUnit documentation: https://phpunit.de/
- Open GitHub issue for test-related questions
