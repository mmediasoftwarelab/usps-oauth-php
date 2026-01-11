# USPS OAuth PHP Library

[![Latest Version](https://img.shields.io/packagist/v/mmediasoftwarelab/usps-oauth-php.svg)](https://packagist.org/packages/mmediasoftwarelab/usps-oauth-php)
[![PHP Version](https://img.shields.io/packagist/php-v/mmediasoftwarelab/usps-oauth-php.svg)](https://packagist.org/packages/mmediasoftwarelab/usps-oauth-php)
[![License](https://img.shields.io/packagist/l/mmediasoftwarelab/usps-oauth-php.svg)](https://packagist.org/packages/mmediasoftwarelab/usps-oauth-php)
[![Tests](https://github.com/mmediasoftwarelab/usps-oauth-php/workflows/CI/badge.svg)](https://github.com/mmediasoftwarelab/usps-oauth-php/actions)
[![Coverage](https://codecov.io/gh/mmediasoftwarelab/usps-oauth-php/branch/main/graph/badge.svg)](https://codecov.io/gh/mmediasoftwarelab/usps-oauth-php)

Modern PHP library for the USPS OAuth 2.0 API (2026+). Get real-time shipping rates, generate labels, and track packages using the latest USPS Web Tools API v3.

**Why this library?**

- ✅ **OAuth 2.0** - Uses the new USPS API (2026+ ready)
- ✅ **Modern PHP** - PHP 8.1+ with type safety
- ✅ **Framework Agnostic** - Works with any PHP project
- ✅ **PSR Compatible** - PSR-4 autoloading, PSR-3 logging
- ✅ **Well Tested** - Comprehensive unit tests
- ✅ **Production Ready** - Used in commercial applications

## Installation

```bash
composer require mmediasoftwarelab/usps-oauth-php
```

## Requirements

- PHP 8.1 or higher
- ext-json
- ext-curl
- ext-openssl (for HTTPS requests)
- ext-mbstring (recommended)
- Valid USPS Business Account with API credentials

### Getting USPS API Credentials

1. Create a USPS Business Account at [USPS.com](https://reg.usps.com/entreg/RegistrationAction_input)
2. Request API access through the [USPS Web Tools portal](https://www.usps.com/business/web-tools-apis/)
3. You'll receive a Client ID and Client Secret for OAuth authentication

### Environment Setup

For development and testing, create a `.env` file:

```bash
cp .env.example .env
```

Add your credentials:

```env
USPS_CLIENT_ID=your-client-id-here
USPS_CLIENT_SECRET=your-client-secret-here
USPS_SANDBOX=true
```

**Note**: Never commit `.env` to version control. It's already in `.gitignore`.

## Quick Start

```php
<?php
require 'vendor/autoload.php';

use MMedia\USPS\Client;
use MMedia\USPS\Rates\DomesticRates;

// Initialize client
$client = new Client(
    clientId: 'your-client-id',
    clientSecret: 'your-client-secret',
    sandbox: true // Use sandbox for testing
);

// Get domestic shipping rates
$domesticRates = new DomesticRates($client);

$rate = $domesticRates->getRate(
    originZip: '90210',
    destinationZip: '10001',
    weightLbs: 2.5,
    length: 12,
    width: 8,
    height: 6,
    serviceType: 'USPS_GROUND_ADVANTAGE'
);

echo "Shipping cost: $" . $rate->getTotalPrice();
```

## Features

### Domestic Shipping Rates

```php
use MMedia\USPS\Rates\DomesticRates;
use MMedia\USPS\Enums\DomesticServiceType;

$rates = new DomesticRates($client);

// Get rate for specific service
$rate = $rates->getRate(
    originZip: '90210',
    destinationZip: '10001',
    weightLbs: 2.5,
    length: 12,
    width: 8,
    height: 6,
    serviceType: DomesticServiceType::GROUND_ADVANTAGE
);

// Or get all available services
$allRates = $rates->getAllRates(
    originZip: '90210',
    destinationZip: '10001',
    weightLbs: 2.5,
    length: 12,
    width: 8,
    height: 6
);

foreach ($allRates as $service => $rate) {
    echo "{$service}: $" . $rate->getTotalPrice() . "\n";
}
```

### International Shipping Rates

```php
use MMedia\USPS\Rates\InternationalRates;
use MMedia\USPS\Enums\InternationalServiceType;

$rates = new InternationalRates($client);

$rate = $rates->getRate(
    originZip: '90210',
    destinationCountry: 'CA', // Canada
    weightLbs: 2.5,
    length: 12,
    width: 8,
    height: 6,
    serviceType: InternationalServiceType::PRIORITY_MAIL_INTERNATIONAL
);
```

### Supported Services

**Domestic:**

- USPS Ground Advantage
- Priority Mail
- Priority Mail Express
- Media Mail
- First-Class Package

**International:**

- Priority Mail International
- Priority Mail Express International
- First-Class Package International

## Configuration

### Sandbox vs Production

```php
// Development/Testing
$client = new Client(
    clientId: 'sandbox-client-id',
    clientSecret: 'sandbox-secret',
    sandbox: true
);

// Production
$client = new Client(
    clientId: 'production-client-id',
    clientSecret: 'production-secret',
    sandbox: false
);
```

### Custom HTTP Client

```php
use MMedia\USPS\Http\CurlHttpClient;

$httpClient = new CurlHttpClient(
    timeout: 30,
    connectTimeout: 10
);

$client = new Client(
    clientId: 'your-client-id',
    clientSecret: 'your-client-secret',
    httpClient: $httpClient
);
```

### Logging

```php
use Monolog\Logger;
use Monolog\Handler\StreamHandler;

$logger = new Logger('usps');
$logger->pushHandler(new StreamHandler('usps.log', Logger::DEBUG));

$client = new Client(
    clientId: 'your-client-id',
    clientSecret: 'your-client-secret',
    logger: $logger
);
```

## Framework Integration

### Laravel

```php
// In a service provider
use MMedia\USPS\Client;

$this->app->singleton(Client::class, function ($app) {
    return new Client(
        clientId: config('services.usps.client_id'),
        clientSecret: config('services.usps.client_secret'),
        sandbox: config('services.usps.sandbox', true),
        logger: $app->make('log')
    );
});
```

### Symfony

```yaml
# config/services.yaml
services:
  MMedia\USPS\Client:
    arguments:
      $clientId: "%env(USPS_CLIENT_ID)%"
      $clientSecret: "%env(USPS_CLIENT_SECRET)%"
      $sandbox: "%env(bool:USPS_SANDBOX)%"
      $logger: "@logger"
```

### WordPress/WooCommerce

See the [WordPress Adapter](examples/wordpress-adapter.php) for WooCommerce integration.

## Error Handling

```php
use MMedia\USPS\Exceptions\AuthenticationException;
use MMedia\USPS\Exceptions\RateException;
use MMedia\USPS\Exceptions\ValidationException;

try {
    $rate = $domesticRates->getRate(
        originZip: '90210',
        destinationZip: '10001',
        weightLbs: 2.5,
        length: 12,
        width: 8,
        height: 6,
        serviceType: 'USPS_GROUND_ADVANTAGE'
    );
} catch (AuthenticationException $e) {
    // OAuth token failed - check credentials
    error_log("Authentication failed: " . $e->getMessage());
} catch (ValidationException $e) {
    // Invalid input data
    error_log("Validation error: " . $e->getMessage());
} catch (RateException $e) {
    // Rate calculation failed
    error_log("Rate error: " . $e->getMessage());
}
```

## Testing

This library has comprehensive test coverage with unit and integration tests.

```bash
# Run all tests
composer test

# Run with coverage report
vendor/bin/phpunit --coverage-html coverage

# Run only unit tests
vendor/bin/phpunit --testsuite="USPS OAuth PHP Test Suite"

# Run integration tests (requires USPS credentials)
USPS_CLIENT_ID=xxx USPS_CLIENT_SECRET=yyy vendor/bin/phpunit --testsuite=integration

# Run static analysis (PHPStan level 8)
composer phpstan

# Check code style (PSR-12)
composer phpcs
```

**Test Coverage**: 90%+ code coverage with automated CI/CD testing on PHP 8.1, 8.2, and 8.3.

See [TESTING.md](TESTING.md) for detailed testing documentation.

## Documentation

- **[QUICKSTART.md](QUICKSTART.md)** - Quick start guide and overview
- **[TESTING.md](TESTING.md)** - Comprehensive testing guide
- **[MONETIZATION_STRATEGY.md](MONETIZATION_STRATEGY.md)** - Business model and revenue strategy
- **[LICENSING_IMPLEMENTATION.md](LICENSING_IMPLEMENTATION.md)** - Premium features licensing guide
- **[CONTRIBUTING.md](CONTRIBUTING.md)** - How to contribute

## API Documentation

For detailed USPS API documentation, visit:

- [USPS Developer Portal](https://developer.usps.com/)
- [OAuth 2.0 Guide](https://developer.usps.com/api/oauth2)
- [Pricing API v3](https://developer.usps.com/api/pricing)

## Examples

Check the [examples](examples/) directory for:

- Basic usage
- Laravel integration
- Symfony integration
- WordPress/WooCommerce adapter
- Custom HTTP client
- Error handling patterns

## Premium Features 💎

Looking for advanced features? Check out **USPS OAuth PHP Pro**:

- 🏷️ **Label Generation** - PDF, PNG, and ZPL formats
- 📦 **Package Tracking** - Real-time tracking with webhooks
- 📊 **Multi-Carrier** - Compare rates across USPS, UPS, FedEx
- ⚡ **Rate Caching** - Redis/Memcached integration
- 🔄 **Batch Operations** - Process thousands of shipments
- 💳 **Commercial Support** - Priority support and SLA

[Learn more about Pro features →](MONETIZATION_STRATEGY.md)

## Framework Integrations

- **WooCommerce Plugin** - [$149](https://mmediasoftwarelab.com/woocommerce-usps)
- **Laravel Package** - Coming Q2 2026
- **Symfony Bundle** - Coming Q2 2026
- **Magento Extension** - Coming Q3 2026
- **Shopify App** - Coming Q3 2026

## Contributing

We welcome contributions! Please see [CONTRIBUTING.md](CONTRIBUTING.md) for details.

## Security

If you discover any security issues, please email security@mmediasoftwarelab.com instead of using the issue tracker.

## License

The MIT License (MIT). Please see [LICENSE](LICENSE) for more information.

## Credits

Developed by [M Media](https://www.mmediasoftwarelab.com)

## Support

- Email: info@mmediasoftwarelab.com
- Issues: [GitHub Issues](https://github.com/mmediasoftwarelab/usps-oauth-php/issues)
- Discussions: [GitHub Discussions](https://github.com/mmediasoftwarelab/usps-oauth-php/discussions)
- Commercial Support: [Contact Sales](mailto:sales@mmediasoftwarelab.com)
- Documentation: [Full Documentation](https://docs.mmediasoftwarelab.com/usps-oauth-php)
