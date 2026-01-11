# Changelog

All notable changes to this project will be documented in this file.

The format is based on [Keep a Changelog](https://keepachangelog.com/en/1.0.0/),
and this project adheres to [Semantic Versioning](https://semver.org/spec/v2.0.0.html).

## [Unreleased]

## [1.0.0] - 2026-01-10

### Added

- Initial release of USPS OAuth PHP library
- OAuth 2.0 authentication with automatic token refresh
- Domestic shipping rate calculations
- International shipping rate calculations
- Support for all major USPS services (Ground Advantage, Priority Mail, Media Mail, etc.)
- PSR-4 autoloading
- PSR-3 logging support
- Modern PHP 8.1+ with type safety
- Comprehensive exception handling
- Rate adjustment and handling fee support
- Framework-agnostic HTTP client interface
- cURL-based HTTP client implementation
- Service type enums for type safety
- Rate model with helper methods
- Laravel integration example
- WordPress/WooCommerce adapter example
- Comprehensive documentation
- MIT license

### Features

- **Client**: Main entry point with OAuth authentication
- **DomesticRates**: Calculate rates for US domestic shipments
- **InternationalRates**: Calculate rates for international shipments
- **TokenManager**: Automatic OAuth token management and refresh
- **HttpClient**: Pluggable HTTP client interface with cURL implementation
- **Exceptions**: Specific exception types for different error scenarios
- **Enums**: Type-safe service type definitions
- **Models**: Clean rate objects with helper methods

[Unreleased]: https://github.com/mmedia/usps-oauth-php/compare/v1.0.0...HEAD
[1.0.0]: https://github.com/mmedia/usps-oauth-php/releases/tag/v1.0.0
