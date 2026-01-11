<?php

declare(strict_types=1);

namespace MMedia\USPS\Tests\Unit\Rates;

use MMedia\USPS\Client;
use MMedia\USPS\Enums\DomesticServiceType;
use MMedia\USPS\Enums\InternationalServiceType;
use MMedia\USPS\Rates\DomesticRates;
use MMedia\USPS\Rates\InternationalRates;
use MMedia\USPS\Tests\Mocks\MockHttpClient;
use PHPUnit\Framework\TestCase;

/**
 * Tests for rate adjustment (markup/discount) functionality
 */
class RateAdjustmentTest extends TestCase
{
    private MockHttpClient $mockHttpClient;
    private Client $client;

    protected function setUp(): void
    {
        $this->mockHttpClient = new MockHttpClient();
        $this->client = new Client(
            clientId: 'test-client-id',
            clientSecret: 'test-client-secret',
            sandbox: true,
            httpClient: $this->mockHttpClient
        );
    }

    public function testDomesticRateMarkup(): void
    {
        // Mock OAuth token response
        $this->mockHttpClient->addJsonResponse('/oauth2/v3/token', 200, ['access_token' => 'test-token', 'expires_in' => 3600]);

        // Mock rate response with base price of $10.00
        $this->mockHttpClient->addJsonResponse('/prices/v3/base-rates/search', 200, [
            'totalBasePrice' => 10.00,
            'totalPrice' => 10.00,
            'SKU' => 'USPS_GROUND_ADVANTAGE',
            'description' => 'USPS Ground Advantage',
        ]);

        $domesticRates = new DomesticRates($this->client);

        // Apply 20% markup
        $domesticRates->setRateAdjustment(20.0);

        $rate = $domesticRates->getRate(
            originZip: '90210',
            destinationZip: '10001',
            weightLbs: 2.5,
            length: 12,
            width: 8,
            height: 6,
            serviceType: DomesticServiceType::GROUND_ADVANTAGE
        );

        // $10.00 + 20% = $12.00
        $this->assertEquals(12.00, $rate->getTotalPrice());
        $this->assertEquals(10.00, $rate->getBasePrice());
    }

    public function testDomesticRateDiscount(): void
    {
        $this->mockHttpClient->addJsonResponse('/oauth2/v3/token', 200, ['access_token' => 'test-token', 'expires_in' => 3600]);
        $this->mockHttpClient->addJsonResponse('/prices/v3/base-rates/search', 200, [
            'totalBasePrice' => 20.00,
            'totalPrice' => 20.00,
            'SKU' => 'PRIORITY_MAIL',
            'description' => 'Priority Mail',
        ]);

        $domesticRates = new DomesticRates($this->client);

        // Apply 10% discount (negative adjustment)
        $domesticRates->setRateAdjustment(-10.0);

        $rate = $domesticRates->getRate(
            originZip: '90210',
            destinationZip: '10001',
            weightLbs: 5.0,
            length: 14,
            width: 10,
            height: 8,
            serviceType: DomesticServiceType::PRIORITY_MAIL
        );

        // $20.00 - 10% = $18.00
        $this->assertEquals(18.00, $rate->getTotalPrice());
        $this->assertEquals(20.00, $rate->getBasePrice());
    }

    public function testInternationalRateMarkup(): void
    {
        $this->mockHttpClient->addJsonResponse('/oauth2/v3/token', 200, ['access_token' => 'test-token', 'expires_in' => 3600]);
        $this->mockHttpClient->addJsonResponse('/international-prices/v3/base-rates/search', 200, [
            'totalBasePrice' => 35.50,
            'totalPrice' => 35.50,
            'SKU' => 'PRIORITY_MAIL_INTERNATIONAL',
            'description' => 'Priority Mail International',
        ]);

        $internationalRates = new InternationalRates($this->client);

        // Apply 25% markup for international handling
        $internationalRates->setRateAdjustment(25.0);

        $rate = $internationalRates->getRate(
            originZip: '90210',
            destinationCountry: 'CA',
            weightLbs: 3.0,
            length: 12,
            width: 9,
            height: 6,
            serviceType: InternationalServiceType::PRIORITY_MAIL_INTERNATIONAL
        );

        // $35.50 + 25% = $44.375 (rounded to $44.38)
        $this->assertEquals(44.38, round($rate->getTotalPrice(), 2));
        $this->assertEquals(35.50, $rate->getBasePrice());
    }

    public function testHandlingFee(): void
    {
        $this->mockHttpClient->addJsonResponse('/oauth2/v3/token', 200, ['access_token' => 'test-token', 'expires_in' => 3600]);
        $this->mockHttpClient->addJsonResponse('/prices/v3/base-rates/search', 200, [
            'totalBasePrice' => 15.00,
            'totalPrice' => 15.00,
            'SKU' => 'USPS_GROUND_ADVANTAGE',
            'description' => 'USPS Ground Advantage',
        ]);

        $domesticRates = new DomesticRates($this->client);

        // Apply $2.50 flat handling fee
        $domesticRates->setHandlingFee(2.50);

        $rate = $domesticRates->getRate(
            originZip: '90210',
            destinationZip: '10001',
            weightLbs: 3.0,
            length: 12,
            width: 8,
            height: 6,
            serviceType: DomesticServiceType::GROUND_ADVANTAGE
        );

        // $15.00 + $2.50 = $17.50
        $this->assertEquals(17.50, $rate->getTotalPrice());
        $this->assertEquals(15.00, $rate->getBasePrice());
    }

    public function testCombinedMarkupAndHandlingFee(): void
    {
        $this->mockHttpClient->addJsonResponse('/oauth2/v3/token', 200, ['access_token' => 'test-token', 'expires_in' => 3600]);
        $this->mockHttpClient->addJsonResponse('/prices/v3/base-rates/search', 200, [
            'totalBasePrice' => 10.00,
            'totalPrice' => 10.00,
            'SKU' => 'USPS_GROUND_ADVANTAGE',
            'description' => 'USPS Ground Advantage',
        ]);

        $domesticRates = new DomesticRates($this->client);

        // Apply 15% markup + $3.00 handling fee
        $domesticRates->setRateAdjustment(15.0);
        $domesticRates->setHandlingFee(3.00);

        $rate = $domesticRates->getRate(
            originZip: '90210',
            destinationZip: '10001',
            weightLbs: 2.0,
            length: 10,
            width: 7,
            height: 5,
            serviceType: DomesticServiceType::GROUND_ADVANTAGE
        );

        // ($10.00 * 1.15) + $3.00 = $11.50 + $3.00 = $14.50
        $this->assertEquals(14.50, $rate->getTotalPrice());
        $this->assertEquals(10.00, $rate->getBasePrice());
    }

    public function testFluentInterface(): void
    {
        $this->mockHttpClient->addJsonResponse('/oauth2/v3/token', 200, ['access_token' => 'test-token', 'expires_in' => 3600]);
        $this->mockHttpClient->addJsonResponse('/prices/v3/base-rates/search', 200, [
            'totalBasePrice' => 12.00,
            'totalPrice' => 12.00,
            'SKU' => 'PRIORITY_MAIL',
            'description' => 'Priority Mail',
        ]);

        $rate = (new DomesticRates($this->client))
            ->setRateAdjustment(10.0)
            ->setHandlingFee(2.00)
            ->getRate(
                originZip: '90210',
                destinationZip: '10001',
                weightLbs: 1.5,
                length: 8,
                width: 6,
                height: 4,
                serviceType: DomesticServiceType::PRIORITY_MAIL
            );

        // ($12.00 * 1.10) + $2.00 = $13.20 + $2.00 = $15.20
        $this->assertEquals(15.20, round($rate->getTotalPrice(), 2));
    }

    public function testZeroAdjustment(): void
    {
        $this->mockHttpClient->addJsonResponse('/oauth2/v3/token', 200, ['access_token' => 'test-token', 'expires_in' => 3600]);
        $this->mockHttpClient->addJsonResponse('/prices/v3/base-rates/search', 200, [
            'totalBasePrice' => 8.50,
            'totalPrice' => 8.50,
            'SKU' => 'USPS_GROUND_ADVANTAGE',
            'description' => 'USPS Ground Advantage',
        ]);

        $domesticRates = new DomesticRates($this->client);
        $domesticRates->setRateAdjustment(0.0);

        $rate = $domesticRates->getRate(
            originZip: '90210',
            destinationZip: '10001',
            weightLbs: 1.0,
            length: 8,
            width: 6,
            height: 4,
            serviceType: DomesticServiceType::GROUND_ADVANTAGE
        );

        // No adjustment: $8.50
        $this->assertEquals(8.50, $rate->getTotalPrice());
        $this->assertEquals(8.50, $rate->getBasePrice());
    }
}
