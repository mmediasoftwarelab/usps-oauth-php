<?php

/**
 * Error Handling Example
 * 
 * This example demonstrates comprehensive error handling
 * when using the USPS OAuth library.
 */

require __DIR__ . '/../vendor/autoload.php';

use MMedia\USPS\Client;
use MMedia\USPS\Rates\DomesticRates;
use MMedia\USPS\Exceptions\AuthenticationException;
use MMedia\USPS\Exceptions\ValidationException;
use MMedia\USPS\Exceptions\RateException;
use MMedia\USPS\Exceptions\ApiException;
use MMedia\USPS\Exceptions\HttpException;
use MMedia\USPS\Exceptions\UspsException;

echo "=== Error Handling Examples ===\n\n";

// Example 1: Authentication Error
echo "Example 1: Authentication Error\n";
echo "--------------------------------\n";
try {
    $client = new Client(
        clientId: 'invalid-client-id',
        clientSecret: 'invalid-secret',
        sandbox: true
    );

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
} catch (AuthenticationException $e) {
    echo "✓ Caught authentication error (expected):\n";
    echo "  Message: {$e->getMessage()}\n";
    echo "  Code: {$e->getCode()}\n\n";
} catch (UspsException $e) {
    echo "Unexpected error: {$e->getMessage()}\n\n";
}

// Example 2: Validation Error
echo "Example 2: Validation Error (Invalid ZIP)\n";
echo "------------------------------------------\n";
try {
    $client = new Client(
        clientId: getenv('USPS_CLIENT_ID') ?: 'test',
        clientSecret: getenv('USPS_CLIENT_SECRET') ?: 'test',
        sandbox: true
    );

    $domesticRates = new DomesticRates($client);

    // Invalid ZIP code (should be 5 digits)
    $rate = $domesticRates->getRate(
        originZip: 'INVALID',
        destinationZip: '10001',
        weightLbs: 2.5,
        length: 12,
        width: 8,
        height: 6,
        serviceType: 'USPS_GROUND_ADVANTAGE'
    );
} catch (ValidationException $e) {
    echo "✓ Caught validation error (expected):\n";
    echo "  Message: {$e->getMessage()}\n\n";
} catch (UspsException $e) {
    echo "Unexpected error: {$e->getMessage()}\n\n";
}

// Example 3: Validation Error (Invalid Weight)
echo "Example 3: Validation Error (Invalid Weight)\n";
echo "---------------------------------------------\n";
try {
    $client = new Client(
        clientId: getenv('USPS_CLIENT_ID') ?: 'test',
        clientSecret: getenv('USPS_CLIENT_SECRET') ?: 'test',
        sandbox: true
    );

    $domesticRates = new DomesticRates($client);

    // Negative weight (invalid)
    $rate = $domesticRates->getRate(
        originZip: '90210',
        destinationZip: '10001',
        weightLbs: -5.0,
        length: 12,
        width: 8,
        height: 6,
        serviceType: 'USPS_GROUND_ADVANTAGE'
    );
} catch (ValidationException $e) {
    echo "✓ Caught validation error (expected):\n";
    echo "  Message: {$e->getMessage()}\n\n";
} catch (UspsException $e) {
    echo "Unexpected error: {$e->getMessage()}\n\n";
}

// Example 4: Comprehensive Error Handling with Retry Logic
echo "Example 4: Error Handling with Retry Logic\n";
echo "-------------------------------------------\n";

function getRateWithRetry(
    Client $client,
    string $originZip,
    string $destinationZip,
    float $weightLbs,
    int $maxRetries = 3
): ?array {
    $attempt = 0;

    while ($attempt < $maxRetries) {
        try {
            $domesticRates = new DomesticRates($client);

            $rate = $domesticRates->getRate(
                originZip: $originZip,
                destinationZip: $destinationZip,
                weightLbs: $weightLbs,
                length: 12,
                width: 8,
                height: 6,
                serviceType: 'USPS_GROUND_ADVANTAGE'
            );

            return [
                'success' => true,
                'rate' => $rate,
            ];
        } catch (ValidationException $e) {
            // Don't retry validation errors
            return [
                'success' => false,
                'error' => 'Validation error: ' . $e->getMessage(),
                'error_type' => 'validation',
            ];
        } catch (AuthenticationException $e) {
            // Don't retry authentication errors
            return [
                'success' => false,
                'error' => 'Authentication failed: ' . $e->getMessage(),
                'error_type' => 'authentication',
            ];
        } catch (RateException $e) {
            // Service might not be available, could retry
            $attempt++;
            if ($attempt >= $maxRetries) {
                return [
                    'success' => false,
                    'error' => 'Rate calculation failed: ' . $e->getMessage(),
                    'error_type' => 'rate',
                    'attempts' => $attempt,
                ];
            }
            sleep(1); // Wait before retry

        } catch (ApiException | HttpException $e) {
            // Network/API issues, retry might help
            $attempt++;
            if ($attempt >= $maxRetries) {
                return [
                    'success' => false,
                    'error' => 'API/Network error: ' . $e->getMessage(),
                    'error_type' => 'api',
                    'attempts' => $attempt,
                ];
            }
            sleep(2); // Longer wait for network issues

        } catch (UspsException $e) {
            // Generic USPS error
            return [
                'success' => false,
                'error' => 'USPS error: ' . $e->getMessage(),
                'error_type' => 'usps',
            ];
        }
    }

    return [
        'success' => false,
        'error' => 'Max retries exceeded',
        'attempts' => $attempt,
    ];
}

// Test with invalid credentials (will fail authentication)
$result = getRateWithRetry(
    new Client(
        clientId: 'test',
        clientSecret: 'test',
        sandbox: true
    ),
    '90210',
    '10001',
    2.5
);

if ($result['success']) {
    echo "Rate calculated: $" . $result['rate']->getTotalPrice() . "\n";
} else {
    echo "✓ Error handled gracefully:\n";
    echo "  Type: {$result['error_type']}\n";
    echo "  Message: {$result['error']}\n";
}

echo "\n=== All error handling examples completed ===\n";
