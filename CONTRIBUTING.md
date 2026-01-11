# Contributing to USPS OAuth PHP Library

Thank you for considering contributing to the USPS OAuth PHP library! We welcome contributions from the community.

## How to Contribute

### Reporting Bugs

If you find a bug, please create an issue on GitHub with:

- A clear title and description
- Steps to reproduce the issue
- Expected vs actual behavior
- PHP version and environment details
- Code samples if applicable

### Suggesting Features

Feature requests are welcome! Please:

- Check existing issues first to avoid duplicates
- Clearly describe the use case and benefits
- Be open to discussion about implementation

### Pull Requests

1. **Fork the repository** and create your branch from `main`
2. **Write tests** for any new functionality
3. **Follow PSR-12** coding standards
4. **Update documentation** if you change functionality
5. **Ensure tests pass** by running `composer test`
6. **Run static analysis** with `composer phpstan`
7. **Check code style** with `composer phpcs`

### Development Setup

```bash
# Clone your fork
git clone https://github.com/your-username/usps-oauth-php.git
cd usps-oauth-php

# Install dependencies
composer install

# Run tests
composer test

# Run static analysis
composer phpstan

# Check code style
composer phpcs
```

### Coding Standards

- Follow PSR-12 coding style
- Use PHP 8.1+ features (type hints, readonly properties, enums, etc.)
- Write descriptive docblocks for all public methods
- Keep methods focused and single-responsibility
- Use dependency injection where appropriate

### Commit Messages

- Use clear, descriptive commit messages
- Reference issue numbers when applicable
- Use present tense ("Add feature" not "Added feature")

Example:

```
Add support for package insurance calculation

- Implement insurance fee calculation
- Add tests for insurance scenarios
- Update documentation

Fixes #123
```

### Testing

All new code should include tests:

```php
namespace MMedia\USPS\Tests;

use PHPUnit\Framework\TestCase;
use MMedia\USPS\Client;

class ClientTest extends TestCase
{
    public function testClientInitialization(): void
    {
        $client = new Client(
            clientId: 'test-id',
            clientSecret: 'test-secret',
            sandbox: true
        );

        $this->assertTrue($client->isSandbox());
    }
}
```

### Code Review Process

1. All submissions require review
2. We aim to review PRs within 48 hours
3. Changes may be requested for quality/consistency
4. Once approved, maintainers will merge

## License

By contributing, you agree that your contributions will be licensed under the MIT License.

## Questions?

Feel free to open an issue for any questions about contributing.
