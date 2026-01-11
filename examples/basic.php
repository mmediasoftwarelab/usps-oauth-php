<?php

/**
 * Basic Usage Example
 * 
 * This example shows basic usage of the USPS OAuth PHP library
 * for getting domestic and international shipping rates.
 */

require __DIR__ . '/../vendor/autoload.php';

use MMedia\USPS\Client;
use MMedia\USPS\Rates\DomesticRates;
use MMedia\USPS\Rates\InternationalRates;
use MMedia\USPS\Enums\DomesticServiceType;
use MMedia\USPS\Enums\InternationalServiceType;
use MMedia\USPS\Exceptions\UspsException;

// Load environment variables (or use your preferred method)
$clientId = getenv('USPS_CLIENT_ID') ?: 'your-client-id';
$clientSecret = getenv('USPS_CLIENT_SECRET') ?: 'your-client-secret';
$sandbox = getenv('USPS_SANDBOX') === 'true';

try {
    // Initialize the USPS client
    $client = new Client(
        clientId: $clientId,
        clientSecret: $clientSecret,
        sandbox: $sandbox
    );

    echo "=== USPS OAuth PHP Library - Basic Example ===\n\n";

    // Test connection
    echo "Testing API connection...\n";
    $testResult = $client->testConnection();
    if ($testResult['success']) {
        echo "✓ Connected to USPS API ({$testResult['mode']} mode)\n\n";
    } else {
        echo "✗ Connection failed: {$testResult['message']}\n";
        exit(1);
    }

    // Example 1: Get a single domestic rate
    echo "Example 1: Single Domestic Rate (Ground Advantage)\n";
    echo "---------------------------------------------------\n";

    $domesticRates = new DomesticRates($client);

    $rate = $domesticRates->getRate(
        originZip: '90210',
        destinationZip: '10001',
        weightLbs: 2.5,
        length: 12,
        width: 8,
        height: 6,
        serviceType: DomesticServiceType::GROUND_ADVANTAGE
    );

    echo "Service: {$rate->getServiceLabel()}\n";
    echo "Base Price: $" . number_format($rate->getBasePrice(), 2) . "\n";
    echo "Total Price: $" . number_format($rate->getTotalPrice(), 2) . "\n\n";

    // Example 2: Get all available domestic rates
    echo "Example 2: All Available Domestic Rates\n";
    echo "----------------------------------------\n";

    $allRates = $domesticRates->getAllRates(
        originZip: '90210',
        destinationZip: '10001',
        weightLbs: 2.5,
        length: 12,
        width: 8,
        height: 6
    );

    foreach ($allRates as $serviceCode => $rate) {
        echo "{$rate->getServiceLabel()}: $" . number_format($rate->getTotalPrice(), 2) . "\n";
    }
    echo "\n";

    // Example 3: Domestic rate with markup and handling fee
    echo "Example 3: Domestic Rate with Markup and Handling Fee\n";
    echo "------------------------------------------------------\n";

    $domesticRates
        ->setRateAdjustment(10.0)  // 10% markup
        ->setHandlingFee(2.50);     // $2.50 handling fee

    $rate = $domesticRates->getRate(
        originZip: '90210',
        destinationZip: '10001',
        weightLbs: 1.0,
        length: 10,
        width: 6,
        height: 4,
        serviceType: DomesticServiceType::PRIORITY_MAIL
    );

    echo "Service: {$rate->getServiceLabel()}\n";
    echo "Base Price: $" . number_format($rate->getBasePrice(), 2) . "\n";
    echo "After 10% markup: $" . number_format($rate->getBasePrice() * 1.1, 2) . "\n";
    echo "After $2.50 handling fee: $" . number_format($rate->getTotalPrice(), 2) . "\n\n";

    // Example 4: International rate
    echo "Example 4: International Rate (to Canada)\n";
    echo "------------------------------------------\n";

    $internationalRates = new InternationalRates($client);

    $intRate = $internationalRates->getRate(
        originZip: '90210',
        destinationCountry: 'CA',  // Canada
        weightLbs: 2.0,
        length: 10,
        width: 8,
        height: 6,
        serviceType: InternationalServiceType::PRIORITY_MAIL_INTERNATIONAL
    );

    echo "Service: {$intRate->getServiceLabel()}\n";
    echo "Destination: Canada\n";
    echo "Total Price: $" . number_format($intRate->getTotalPrice(), 2) . "\n\n";

    // Example 5: Using string service types (backward compatibility)
    echo "Example 5: Using String Service Type\n";
    echo "-------------------------------------\n";

    $rate = $domesticRates->getRate(
        originZip: '90210',
        destinationZip: '10001',
        weightLbs: 1.5,
        length: 12,
        width: 8,
        height: 6,
        serviceType: 'USPS_MEDIA_MAIL'  // String instead of enum
    );

    echo "Service: {$rate->getService()}\n";
    echo "Total Price: $" . number_format($rate->getTotalPrice(), 2) . "\n\n";

    // Example 6: Rate object as array
    echo "Example 6: Rate Object as Array\n";
    echo "--------------------------------\n";

    $rateArray = $rate->toArray();
    echo json_encode($rateArray, JSON_PRETTY_PRINT) . "\n\n";

    echo "All examples completed successfully!\n";
} catch (UspsException $e) {
    echo "Error: {$e->getMessage()}\n";
    echo "Type: " . get_class($e) . "\n";
    exit(1);
}
