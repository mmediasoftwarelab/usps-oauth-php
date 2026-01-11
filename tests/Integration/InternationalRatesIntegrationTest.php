<?php

declare(strict_types=1);

namespace MMedia\USPS\Tests\Integration;

use MMedia\USPS\Client;
use MMedia\USPS\Enums\InternationalServiceType;
use MMedia\USPS\Rates\InternationalRates;
use PHPUnit\Framework\TestCase;

/**
 * Integration tests for international rate calculations
 * Requires USPS API credentials in .env file
 */
class InternationalRatesIntegrationTest extends TestCase
{
    private Client $client;

    protected function setUp(): void
    {
        $clientId = getenv('USPS_CLIENT_ID');
        $clientSecret = getenv('USPS_CLIENT_SECRET');
        $sandbox = getenv('USPS_SANDBOX') !== 'false';

        if (!$clientId || !$clientSecret) {
            $this->markTestSkipped('USPS API credentials not configured. Set USPS_CLIENT_ID and USPS_CLIENT_SECRET.');
        }

        $this->client = new Client(
            clientId: $clientId,
            clientSecret: $clientSecret,
            sandbox: $sandbox
        );
    }

    public function testRealApiGetInternationalRate(): void
    {
        $internationalRates = new InternationalRates($this->client);

        $rate = $internationalRates->getRate(
            originZip: '90210',
            destinationCountry: 'CA', // Canada
            weightLbs: 2.5,
            length: 12.0,
            width: 8.0,
            height: 6.0,
            serviceType: InternationalServiceType::PRIORITY_MAIL_INTERNATIONAL
        );

        $this->assertNotNull($rate);
        $this->assertGreaterThan(0, $rate->getTotalPrice());
        $this->assertEquals('PRIORITY_MAIL_INTERNATIONAL', $rate->getService());
    }

    public function testRealApiGetAllInternationalRates(): void
    {
        $internationalRates = new InternationalRates($this->client);

        $rates = $internationalRates->getAllRates(
            originZip: '90210',
            destinationCountry: 'CA', // Canada
            weightLbs: 2.5,
            length: 12.0,
            width: 8.0,
            height: 6.0
        );

        $this->assertNotEmpty($rates);
        $this->assertIsArray($rates);

        foreach ($rates as $serviceType => $rate) {
            $this->assertIsString($serviceType);
            $this->assertInstanceOf(\MMedia\USPS\Models\Rate::class, $rate);
            $this->assertGreaterThan(0, $rate->getTotalPrice());
        }
    }

    public function testRealApiWithRateAdjustment(): void
    {
        $internationalRates = new InternationalRates($this->client);

        // Apply 15% markup
        $internationalRates->setRateAdjustment(15.0);

        $rate = $internationalRates->getRate(
            originZip: '90210',
            destinationCountry: 'GB', // United Kingdom
            weightLbs: 1.5,
            length: 10.0,
            width: 7.0,
            height: 5.0,
            serviceType: InternationalServiceType::PRIORITY_MAIL_INTERNATIONAL
        );

        $this->assertNotNull($rate);
        $this->assertGreaterThan(0, $rate->getTotalPrice());

        // Verify markup was applied (total should be higher than base)
        $this->assertGreaterThan($rate->getBasePrice(), $rate->getTotalPrice());
    }
}
