# Premium Features Licensing Implementation Guide

## Overview

This document outlines the technical implementation for premium/pro features licensing system.

## Architecture

```
┌─────────────────┐         ┌──────────────────┐         ┌─────────────────┐
│  Client App     │────────▶│  License Server  │────────▶│   Database      │
│  (Your Site)    │         │  (licensing API) │         │   (Licenses)    │
└─────────────────┘         └──────────────────┘         └─────────────────┘
```

## License Server Implementation (Laravel)

### 1. Database Schema

```sql
CREATE TABLE licenses (
    id BIGINT PRIMARY KEY AUTO_INCREMENT,
    license_key VARCHAR(64) UNIQUE NOT NULL,
    customer_email VARCHAR(255) NOT NULL,
    product_type ENUM('pro', 'enterprise') NOT NULL,
    domain VARCHAR(255) NOT NULL,
    max_activations INT DEFAULT 1,
    activations INT DEFAULT 0,
    expires_at TIMESTAMP NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    status ENUM('active', 'suspended', 'expired') DEFAULT 'active',
    INDEX idx_license_key (license_key),
    INDEX idx_customer_email (customer_email)
);

CREATE TABLE license_activations (
    id BIGINT PRIMARY KEY AUTO_INCREMENT,
    license_id BIGINT NOT NULL,
    domain VARCHAR(255) NOT NULL,
    ip_address VARCHAR(45),
    user_agent TEXT,
    activated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    last_checked_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (license_id) REFERENCES licenses(id) ON DELETE CASCADE,
    UNIQUE KEY unique_license_domain (license_id, domain)
);

CREATE TABLE license_usage_stats (
    id BIGINT PRIMARY KEY AUTO_INCREMENT,
    license_id BIGINT NOT NULL,
    metric VARCHAR(50) NOT NULL,
    value INT NOT NULL,
    recorded_at DATE NOT NULL,
    FOREIGN KEY (license_id) REFERENCES licenses(id) ON DELETE CASCADE,
    UNIQUE KEY unique_license_metric_date (license_id, metric, recorded_at)
);
```

### 2. API Endpoints

#### Validate License

```http
POST /api/v1/licenses/validate
Content-Type: application/json

{
    "license_key": "USPS-PRO-XXXX-XXXX-XXXX",
    "domain": "example.com",
    "version": "2.0.0"
}

Response 200:
{
    "valid": true,
    "license_type": "pro",
    "expires_at": "2027-01-10T00:00:00Z",
    "features": [
        "label_generation",
        "tracking",
        "webhooks",
        "rate_caching"
    ],
    "limits": {
        "api_calls_per_day": 10000,
        "labels_per_month": 5000
    }
}

Response 403:
{
    "valid": false,
    "error": "License expired",
    "error_code": "LICENSE_EXPIRED"
}
```

#### Activate License

```http
POST /api/v1/licenses/activate
Content-Type: application/json

{
    "license_key": "USPS-PRO-XXXX-XXXX-XXXX",
    "domain": "example.com",
    "ip_address": "192.168.1.1"
}

Response 201:
{
    "activated": true,
    "activation_id": 12345,
    "message": "License activated successfully"
}

Response 429:
{
    "activated": false,
    "error": "Maximum activations reached",
    "max_activations": 1,
    "current_activations": 1
}
```

#### Deactivate License

```http
POST /api/v1/licenses/deactivate
Content-Type: application/json

{
    "license_key": "USPS-PRO-XXXX-XXXX-XXXX",
    "domain": "example.com"
}

Response 200:
{
    "deactivated": true,
    "message": "License deactivated successfully"
}
```

## Client-Side Implementation

### 3. PHP License Client

```php
<?php

namespace MMedia\USPS\Pro;

use MMedia\USPS\Http\HttpClientInterface;
use MMedia\USPS\Http\CurlHttpClient;
use MMedia\USPS\Exceptions\LicenseException;

class License
{
    private const LICENSE_SERVER = 'https://licensing.mmediasoftwarelab.com';
    private const CACHE_TTL = 86400; // 24 hours

    private ?array $licenseData = null;

    public function __construct(
        private readonly string $licenseKey,
        private readonly string $domain,
        private readonly ?HttpClientInterface $httpClient = null,
        private readonly ?string $cacheDir = null
    ) {
        $this->httpClient = $httpClient ?? new CurlHttpClient();
        $this->cacheDir = $cacheDir ?? sys_get_temp_dir();
    }

    /**
     * Validate license (uses cache when possible)
     *
     * @throws LicenseException
     */
    public function validate(): bool
    {
        // Try cache first
        if ($this->licenseData = $this->getFromCache()) {
            return true;
        }

        // Validate with server
        $response = $this->httpClient->request(
            url: self::LICENSE_SERVER . '/api/v1/licenses/validate',
            method: 'POST',
            headers: ['Content-Type' => 'application/json'],
            body: json_encode([
                'license_key' => $this->licenseKey,
                'domain' => $this->domain,
                'version' => $this->getLibraryVersion(),
            ])
        );

        $data = json_decode($response->getBody(), true);

        if ($response->getStatusCode() !== 200 || !($data['valid'] ?? false)) {
            throw new LicenseException(
                $data['error'] ?? 'Invalid license',
                $data['error_code'] ?? 'INVALID_LICENSE'
            );
        }

        $this->licenseData = $data;
        $this->saveToCache($data);

        return true;
    }

    /**
     * Check if specific feature is enabled
     */
    public function hasFeature(string $feature): bool
    {
        if (!$this->licenseData) {
            $this->validate();
        }

        return in_array($feature, $this->licenseData['features'] ?? [], true);
    }

    /**
     * Get license type (pro, enterprise, etc.)
     */
    public function getType(): string
    {
        if (!$this->licenseData) {
            $this->validate();
        }

        return $this->licenseData['license_type'] ?? 'unknown';
    }

    /**
     * Activate license on this domain
     */
    public function activate(): bool
    {
        $response = $this->httpClient->request(
            url: self::LICENSE_SERVER . '/api/v1/licenses/activate',
            method: 'POST',
            headers: ['Content-Type' => 'application/json'],
            body: json_encode([
                'license_key' => $this->licenseKey,
                'domain' => $this->domain,
                'ip_address' => $_SERVER['SERVER_ADDR'] ?? null,
            ])
        );

        $data = json_decode($response->getBody(), true);

        if ($response->getStatusCode() !== 201) {
            throw new LicenseException(
                $data['error'] ?? 'Activation failed'
            );
        }

        return true;
    }

    /**
     * Cache license data locally
     */
    private function saveToCache(array $data): void
    {
        $cacheFile = $this->getCacheFilePath();
        file_put_contents($cacheFile, json_encode([
            'data' => $data,
            'expires_at' => time() + self::CACHE_TTL,
        ]));
    }

    /**
     * Get cached license data
     */
    private function getFromCache(): ?array
    {
        $cacheFile = $this->getCacheFilePath();

        if (!file_exists($cacheFile)) {
            return null;
        }

        $cached = json_decode(file_get_contents($cacheFile), true);

        if (!$cached || time() > ($cached['expires_at'] ?? 0)) {
            return null; // Expired
        }

        return $cached['data'];
    }

    private function getCacheFilePath(): string
    {
        return $this->cacheDir . '/usps_license_' . md5($this->licenseKey . $this->domain) . '.json';
    }

    private function getLibraryVersion(): string
    {
        // Read from composer.json or constant
        return '2.0.0';
    }
}
```

### 4. Usage in Premium Features

```php
<?php

namespace MMedia\USPS\Pro;

use MMedia\USPS\Client;
use MMedia\USPS\Exceptions\LicenseException;

class LabelGenerator
{
    public function __construct(
        private readonly Client $client,
        private readonly License $license
    ) {
        // Validate license has label generation feature
        if (!$this->license->hasFeature('label_generation')) {
            throw new LicenseException(
                'Your license does not include label generation. Upgrade to Pro to unlock this feature.'
            );
        }
    }

    /**
     * Generate shipping label
     */
    public function generateLabel(
        string $trackingNumber,
        string $format = 'PDF'
    ): string {
        // License is already validated in constructor

        $response = $this->client->request('/labels/v3/label', [
            'trackingNumber' => $trackingNumber,
            'labelType' => 'SHIPPING',
            'imageType' => $format,
        ], 'POST');

        return $response['labelImage']; // Base64 encoded
    }
}
```

### 5. Feature Gating Example

```php
<?php

use MMedia\USPS\Client;
use MMedia\USPS\Pro\License;
use MMedia\USPS\Pro\LabelGenerator;

// Initialize
$client = new Client(
    clientId: 'your-client-id',
    clientSecret: 'your-client-secret'
);

// Check license
try {
    $license = new License(
        licenseKey: 'USPS-PRO-XXXX-XXXX-XXXX',
        domain: $_SERVER['HTTP_HOST']
    );

    $license->validate();

    // Use Pro features
    $labelGen = new LabelGenerator($client, $license);
    $label = $labelGen->generateLabel('9400123456789012345678');

    echo "Label generated successfully!";

} catch (LicenseException $e) {
    echo "License error: " . $e->getMessage();
    echo "\nUpgrade to Pro: https://mmediasoftwarelab.com/usps-php-pro";
}
```

## WooCommerce Integration

### 6. Plugin License Settings

```php
<?php

// In your WooCommerce plugin admin settings

add_action('woocommerce_admin_field_usps_license', function($value) {
    $license_key = get_option('usps_oauth_license_key');
    $license_status = get_option('usps_oauth_license_status');

    ?>
    <tr valign="top">
        <th scope="row" class="titledesc">
            <label>License Key</label>
        </th>
        <td class="forminp">
            <input
                type="text"
                name="usps_oauth_license_key"
                value="<?php echo esc_attr($license_key); ?>"
                placeholder="USPS-PRO-XXXX-XXXX-XXXX"
                class="regular-text"
            />

            <?php if ($license_status === 'valid'): ?>
                <span class="dashicons dashicons-yes" style="color: green;"></span>
                <span style="color: green;">Valid License</span>
            <?php else: ?>
                <span class="dashicons dashicons-no" style="color: red;"></span>
                <span style="color: red;">Invalid or Expired</span>
            <?php endif; ?>

            <p class="description">
                Enter your Pro license key to unlock premium features.
                <a href="https://mmediasoftwarelab.com/purchase" target="_blank">Purchase a license</a>
            </p>
        </td>
    </tr>
    <?php
});

// Validate license on save
add_action('woocommerce_update_options', function() {
    $license_key = $_POST['usps_oauth_license_key'] ?? '';

    if (empty($license_key)) {
        return;
    }

    try {
        $license = new \MMedia\USPS\Pro\License(
            $license_key,
            $_SERVER['HTTP_HOST']
        );

        $license->activate(); // Activate on this domain
        $license->validate();

        update_option('usps_oauth_license_status', 'valid');
        update_option('usps_oauth_license_features', $license->getType());

    } catch (\Exception $e) {
        update_option('usps_oauth_license_status', 'invalid');
        add_settings_error(
            'usps_oauth_license',
            'license_error',
            'License validation failed: ' . $e->getMessage()
        );
    }
});
```

## Security Considerations

### 7. Best Practices

1. **License Key Format**: Use cryptographically secure random generation

   ```php
   $key = 'USPS-PRO-' . strtoupper(bin2hex(random_bytes(12)));
   // Example: USPS-PRO-A1B2C3D4E5F6G7H8I9J0K1L2
   ```

2. **Rate Limiting**: Limit validation requests to prevent abuse

   ```php
   // Max 10 validation requests per hour per license
   if ($recentValidations > 10) {
       throw new LicenseException('Too many validation requests');
   }
   ```

3. **Domain Validation**: Normalize domains before comparison

   ```php
   function normalizeDomain(string $domain): string {
       return strtolower(preg_replace('/^www\./', '', $domain));
   }
   ```

4. **Encrypted Communication**: Always use HTTPS for license server
5. **Local Caching**: Cache valid licenses to reduce server load
6. **Graceful Degradation**: If license server is down, use cached validation

## Testing License System

```php
<?php

namespace MMedia\USPS\Tests\Unit\Pro;

use MMedia\USPS\Pro\License;
use MMedia\USPS\Exceptions\LicenseException;
use MMedia\USPS\Tests\Mocks\MockHttpClient;
use PHPUnit\Framework\TestCase;

class LicenseTest extends TestCase
{
    public function testValidLicense(): void
    {
        $httpClient = new MockHttpClient();
        $httpClient->addJsonResponse('/api/v1/licenses/validate', 200, [
            'valid' => true,
            'license_type' => 'pro',
            'features' => ['label_generation', 'tracking'],
        ]);

        $license = new License(
            'TEST-KEY',
            'example.com',
            $httpClient
        );

        $this->assertTrue($license->validate());
        $this->assertTrue($license->hasFeature('label_generation'));
    }

    public function testInvalidLicenseThrowsException(): void
    {
        $httpClient = new MockHttpClient();
        $httpClient->addJsonResponse('/api/v1/licenses/validate', 403, [
            'valid' => false,
            'error' => 'License expired',
        ]);

        $license = new License('INVALID-KEY', 'example.com', $httpClient);

        $this->expectException(LicenseException::class);
        $license->validate();
    }
}
```

---

## Next Steps

1. [ ] Build Laravel licensing server
2. [ ] Implement license generation admin panel
3. [ ] Create Stripe/PayPal integration for purchases
4. [ ] Build customer portal for license management
5. [ ] Implement automatic license renewal system
6. [ ] Create affiliate tracking system
7. [ ] Build usage analytics dashboard

---

**Last Updated**: January 10, 2026
