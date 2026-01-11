# Changelog

All notable changes to this project will be documented in this file.

The format is based on [Keep a Changelog](https://keepachangelog.com/en/1.0.0/),
and this project adheres to [Semantic Versioning](https://semver.org/spec/v2.0.0.html).

## [Unreleased]

## [1.0.0] - 2026-01-10

### Added

- Initial release of USPS OAuth PHP library
- OAuth 2.0 authentication with automatic token refresh and caching
- Domestic shipping rate calculations with 10+ service types
- International shipping rate calculations
- Support for all major USPS services (Ground Advantage, Priority Mail, Media Mail, etc.)
- PSR-4 autoloading
- PSR-3 logging support (optional)
- Modern PHP 8.1+ with strict type safety
- Comprehensive exception handling (Authentication, API, HTTP, Rate, Validation)
- Rate adjustment and handling fee support (markup/discount)
- Framework-agnostic HTTP client interface
- cURL-based HTTP client implementation with proper error handling
- Service type enums for type safety (DomesticServiceType, InternationalServiceType)
- Rich Rate model with helper methods (getTotalPrice, getBasePrice, getService, etc.)
- Laravel integration example
- WordPress/WooCommerce adapter example
- Comprehensive documentation (README, TESTING, QUICKSTART, MONETIZATION, etc.)
- MIT license for maximum flexibility

### Testing

- 33 comprehensive tests (31 unit + 2 integration)
- 93 test assertions covering all major functionality
- MockHttpClient for reliable unit testing without API calls
- Real API integration tests for production validation
- PHPStan level 8 static analysis (strictest type checking)
- PSR-12 code style compliance
- GitHub Actions CI/CD pipeline for PHP 8.1, 8.2, 8.3
- .env-based credential management for integration tests
- Windows SSL certificate configuration documentation

### Code Quality

- 100% PSR-12 compliant code formatting
- Zero PHPStan errors at level 8
- Comprehensive inline documentation
- Type hints on all methods and properties
- Immutable service objects
- Fluent interfaces for ease of use

### Features

- **Client**: Main entry point with OAuth authentication
- **DomesticRates**: Calculate rates for US domestic shipments
- **InternationalRates**: Calculate rates for international shipments
- **TokenManager**: Automatic OAuth token management and refresh
- **HttpClient**: Pluggable HTTP client interface with cURL implementation
- **Exceptions**: Specific exception types for different error scenarios
- **Enums**: Type-safe service type definitions
- **Models**: Clean rate objects with helper methods

[Unreleased]: https://github.com/mmediasoftwarelab/usps-oauth-php/compare/v1.0.0...HEAD
[1.0.0]: https://github.com/mmediasoftwarelab/usps-oauth-php/releases/tag/v1.0.0
