<?php

declare(strict_types=1);

namespace MMedia\USPS\Tests\Integration;

use MMedia\USPS\Client;
use MMedia\USPS\Enums\DomesticServiceType;
use MMedia\USPS\Rates\DomesticRates;
use PHPUnit\Framework\TestCase;

/**
 * Integration Tests
 * 
 * These tests require valid USPS API credentials set in environment variables:
 * - USPS_CLIENT_ID
 * - USPS_CLIENT_SECRET
 * 
 * Run with: USPS_CLIENT_ID=xxx USPS_CLIENT_SECRET=yyy vendor/bin/phpunit --testsuite integration
 */
class DomesticRatesIntegrationTest extends TestCase
{
    private ?Client $client = null;

    protected function setUp(): void
    {
        $clientId = getenv('USPS_CLIENT_ID');
        $clientSecret = getenv('USPS_CLIENT_SECRET');

        if (!$clientId || !$clientSecret) {
            $this->markTestSkipped(
                'Integration tests require USPS_CLIENT_ID and USPS_CLIENT_SECRET environment variables'
            );
        }

        $sandbox = getenv('USPS_SANDBOX');
        $useSandbox = $sandbox === 'true' || $sandbox === '1';

        $this->client = new Client(
            clientId: $clientId,
            clientSecret: $clientSecret,
            sandbox: $useSandbox
        );
    }

    public function testRealApiGetDomesticRate(): void
    {
        $domesticRates = new DomesticRates($this->client);

        $rate = $domesticRates->getRate(
            originZip: '90210',
            destinationZip: '10001',
            weightLbs: 2.5,
            length: 12.0,
            width: 8.0,
            height: 6.0,
            serviceType: DomesticServiceType::GROUND_ADVANTAGE
        );

        $this->assertNotNull($rate);
        $this->assertGreaterThan(0, $rate->getTotalPrice());
        $this->assertEquals('USPS_GROUND_ADVANTAGE', $rate->getService());
    }

    public function testRealApiGetAllDomesticRates(): void
    {
        $domesticRates = new DomesticRates($this->client);

        $rates = $domesticRates->getAllRates(
            originZip: '90210',
            destinationZip: '10001',
            weightLbs: 2.5,
            length: 12.0,
            width: 8.0,
            height: 6.0
        );

        $this->assertNotEmpty($rates);
        $this->assertIsArray($rates);

        foreach ($rates as $serviceType => $rate) {
            $this->assertIsString($serviceType);
            $this->assertGreaterThan(0, $rate->getTotalPrice());
        }
    }
}
