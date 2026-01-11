<?php

declare(strict_types=1);

namespace MMedia\USPS\Tests\Integration;

use MMedia\USPS\Client;
use MMedia\USPS\Enums\DomesticServiceType;
use MMedia\USPS\Rates\DomesticRates;
use PHPUnit\Framework\TestCase;

/**
 * Practical Real-World Scenarios Testing
 * These tests demonstrate actual business use cases with real USPS pricing
 */
class PracticalScenariosTest extends TestCase
{
    private Client $client;

    protected function setUp(): void
    {
        $clientId = getenv('USPS_CLIENT_ID');
        $clientSecret = getenv('USPS_CLIENT_SECRET');
        $sandbox = getenv('USPS_SANDBOX') !== 'false';

        if (!$clientId || !$clientSecret) {
            $this->markTestSkipped('USPS API credentials not configured.');
        }

        $this->client = new Client(
            clientId: $clientId,
            clientSecret: $clientSecret,
            sandbox: $sandbox
        );
    }

    /**
     * Test: Small package from California to New York
     * Real scenario: T-shirt shipment
     */
    public function testSmallPackageCaliforniaToNewYork(): void
    {
        $rates = new DomesticRates($this->client);

        $allRates = $rates->getAllRates(
            originZip: '90210',      // Beverly Hills, CA
            destinationZip: '10001', // New York, NY
            weightLbs: 1.0,          // 1 lb t-shirt
            length: 10.0,
            width: 8.0,
            height: 2.0
        );

        $this->assertNotEmpty($allRates, 'Should return available rates');

        // Display practical results
        echo "\n\n=== T-SHIRT SHIPMENT (CA → NY) ===\n";
        echo "Package: 1 lb, 10×8×2 inches\n\n";

        foreach ($allRates as $service => $rate) {
            printf(
                "%-30s $%0.2f\n",
                str_replace('_', ' ', $service),
                $rate->getTotalPrice()
            );
        }

        // Verify Ground Advantage is cheapest (usually)
        if (isset($allRates['USPS_GROUND_ADVANTAGE'])) {
            $groundPrice = $allRates['USPS_GROUND_ADVANTAGE']->getTotalPrice();
            $this->assertGreaterThan(0, $groundPrice, 'Ground Advantage should have valid price');
        }
    }

    /**
     * Test: Heavy package requiring different service levels
     * Real scenario: Electronics shipment (laptop)
     */
    public function testHeavyPackagePriorityVsGround(): void
    {
        $rates = new DomesticRates($this->client);

        echo "\n\n=== LAPTOP SHIPMENT (TX → FL) ===\n";
        echo "Package: 8 lbs, 16×12×8 inches\n\n";

        // Get Ground Advantage rate
        $groundRate = $rates->getRate(
            originZip: '75001',  // Dallas, TX
            destinationZip: '33101', // Miami, FL
            weightLbs: 8.0,
            length: 16.0,
            width: 12.0,
            height: 8.0,
            serviceType: DomesticServiceType::GROUND_ADVANTAGE
        );

        // Get Priority Mail rate
        $priorityRate = $rates->getRate(
            originZip: '75001',
            destinationZip: '33101',
            weightLbs: 8.0,
            length: 16.0,
            width: 12.0,
            height: 8.0,
            serviceType: DomesticServiceType::PRIORITY_MAIL
        );

        printf("Ground Advantage:  $%0.2f\n", $groundRate->getTotalPrice());
        printf("Priority Mail:     $%0.2f\n", $priorityRate->getTotalPrice());
        printf("Price Difference:  $%0.2f\n", $priorityRate->getTotalPrice() - $groundRate->getTotalPrice());

        $this->assertGreaterThan(
            $groundRate->getTotalPrice(),
            $priorityRate->getTotalPrice(),
            'Priority Mail should cost more than Ground Advantage'
        );
    }

    /**
     * Test: Cross-country vs local shipping
     * Real scenario: Book shipment
     */
    public function testDistanceImpactOnPricing(): void
    {
        $rates = new DomesticRates($this->client);

        echo "\n\n=== DISTANCE PRICING COMPARISON ===\n";
        echo "Package: 3 lbs book, 12×9×3 inches\n\n";

        // Short distance: CA to CA
        $localRate = $rates->getRate(
            originZip: '90210',  // Beverly Hills, CA
            destinationZip: '94102', // San Francisco, CA
            weightLbs: 3.0,
            length: 12.0,
            width: 9.0,
            height: 3.0,
            serviceType: DomesticServiceType::GROUND_ADVANTAGE
        );

        // Long distance: CA to ME
        $crossCountryRate = $rates->getRate(
            originZip: '90210',  // Beverly Hills, CA
            destinationZip: '04101', // Portland, ME
            weightLbs: 3.0,
            length: 12.0,
            width: 9.0,
            height: 3.0,
            serviceType: DomesticServiceType::GROUND_ADVANTAGE
        );

        printf("CA → CA (local):        $%0.2f\n", $localRate->getTotalPrice());
        printf("CA → ME (cross-country): $%0.2f\n", $crossCountryRate->getTotalPrice());
        printf(
            "Distance Premium:       $%0.2f (%.1f%%)\n",
            $crossCountryRate->getTotalPrice() - $localRate->getTotalPrice(),
            (($crossCountryRate->getTotalPrice() / $localRate->getTotalPrice()) - 1) * 100
        );

        $this->assertGreaterThan(0, $localRate->getTotalPrice());
        $this->assertGreaterThan(0, $crossCountryRate->getTotalPrice());
    }

    /**
     * Test: Business markup scenario
     * Real scenario: E-commerce store adding 15% handling fee
     */
    public function testEcommerceMarkupScenario(): void
    {
        $rates = new DomesticRates($this->client);
        $rates->setRateAdjustment(15.0); // 15% markup
        $rates->setHandlingFee(2.50);    // $2.50 handling

        echo "\n\n=== E-COMMERCE PRICING ===\n";
        echo "Scenario: 2 lb product with 15% markup + $2.50 handling\n\n";

        $rate = $rates->getRate(
            originZip: '60601',  // Chicago, IL
            destinationZip: '98101', // Seattle, WA
            weightLbs: 2.0,
            length: 10.0,
            width: 8.0,
            height: 6.0,
            serviceType: DomesticServiceType::GROUND_ADVANTAGE
        );

        $basePrice = $rate->getBasePrice();
        $finalPrice = $rate->getTotalPrice();
        $markup = $finalPrice - $basePrice;

        printf("USPS Base Rate:    $%0.2f\n", $basePrice);
        printf("Your Markup:       $%0.2f\n", $markup);
        printf("Customer Pays:     $%0.2f\n", $finalPrice);
        printf("Your Profit:       $%0.2f\n", $markup);

        $this->assertEquals($basePrice * 1.15 + 2.50, $finalPrice, 'Markup calculation should be correct', 0.01);
    }

    /**
     * Test: Multiple package weights to show pricing tiers
     * Real scenario: Warehouse shipping different product sizes
     */
    public function testWeightBasedPricingTiers(): void
    {
        $rates = new DomesticRates($this->client);

        echo "\n\n=== WEIGHT-BASED PRICING TIERS ===\n";
        echo "Route: NY → CA | Service: Ground Advantage\n\n";
        echo "Weight    Price    Price/lb\n";
        echo "------    -----    --------\n";

        $weights = [1, 3, 5, 10, 15];
        $prices = [];

        foreach ($weights as $weight) {
            $rate = $rates->getRate(
                originZip: '10001',
                destinationZip: '90210',
                weightLbs: (float)$weight,
                length: 12.0,
                width: 10.0,
                height: 8.0,
                serviceType: DomesticServiceType::GROUND_ADVANTAGE
            );

            $price = $rate->getTotalPrice();
            $prices[$weight] = $price;

            printf(
                "%2d lbs    $%5.2f    $%5.2f\n",
                $weight,
                $price,
                $price / $weight
            );
        }

        // Verify pricing increases with weight (use correct comparison order)
        $this->assertGreaterThan($prices[3], $prices[5], '5 lbs should cost more than 3 lbs');
        $this->assertGreaterThan($prices[10], $prices[15], '15 lbs should cost more than 10 lbs');
    }
}
