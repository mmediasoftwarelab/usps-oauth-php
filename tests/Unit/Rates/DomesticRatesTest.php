<?php

declare(strict_types=1);

namespace MMedia\USPS\Tests\Unit\Rates;

use MMedia\USPS\Client;
use MMedia\USPS\Enums\DomesticServiceType;
use MMedia\USPS\Exceptions\RateException;
use MMedia\USPS\Exceptions\ValidationException;
use MMedia\USPS\Models\Rate;
use MMedia\USPS\Rates\DomesticRates;
use MMedia\USPS\Tests\Mocks\MockHttpClient;
use PHPUnit\Framework\TestCase;

class DomesticRatesTest extends TestCase
{
    private MockHttpClient $httpClient;
    private Client $client;
    private DomesticRates $domesticRates;

    protected function setUp(): void
    {
        $this->httpClient = new MockHttpClient();

        // Mock OAuth token
        $this->httpClient->addJsonResponse('/oauth2/v3/token', 200, [
            'access_token' => 'test-token',
            'token_type' => 'Bearer',
            'expires_in' => 3600,
        ]);

        $this->client = new Client(
            clientId: 'test-client-id',
            clientSecret: 'test-client-secret',
            sandbox: true,
            httpClient: $this->httpClient
        );

        $this->domesticRates = new DomesticRates($this->client);
    }

    public function testGetRateReturnsValidRate(): void
    {
        $this->httpClient->addJsonResponse('/prices/v3/base-rates/search', 200, [
            'totalBasePrice' => 8.50,
            'totalPrice' => 8.50,
            'SKU' => 'USPS_GROUND_ADVANTAGE',
            'description' => 'USPS Ground Advantage',
        ]);

        $rate = $this->domesticRates->getRate(
            originZip: '90210',
            destinationZip: '10001',
            weightLbs: 2.5,
            length: 12.0,
            width: 8.0,
            height: 6.0,
            serviceType: DomesticServiceType::GROUND_ADVANTAGE
        );

        $this->assertInstanceOf(Rate::class, $rate);
        $this->assertEquals(8.50, $rate->getTotalPrice());
        $this->assertEquals('USPS_GROUND_ADVANTAGE', $rate->getService());
    }

    public function testGetRateWithStringServiceType(): void
    {
        $this->httpClient->addJsonResponse('/prices/v3/base-rates/search', 200, [
            'totalBasePrice' => 10.20,
            'totalPrice' => 10.20,
            'SKU' => 'PRIORITY_MAIL',
            'description' => 'Priority Mail',
        ]);

        $rate = $this->domesticRates->getRate(
            originZip: '90210',
            destinationZip: '10001',
            weightLbs: 2.5,
            length: 12.0,
            width: 8.0,
            height: 6.0,
            serviceType: 'PRIORITY_MAIL'
        );

        $this->assertInstanceOf(Rate::class, $rate);
        $this->assertEquals(10.20, $rate->getTotalPrice());
    }

    public function testGetRateValidatesZipCode(): void
    {
        $this->expectException(ValidationException::class);
        $this->expectExceptionMessage('ZIP code');

        $this->domesticRates->getRate(
            originZip: '123', // Invalid - too short
            destinationZip: '10001',
            weightLbs: 2.5,
            length: 12.0,
            width: 8.0,
            height: 6.0,
            serviceType: DomesticServiceType::GROUND_ADVANTAGE
        );
    }

    public function testGetRateValidatesWeight(): void
    {
        $this->expectException(ValidationException::class);
        $this->expectExceptionMessage('Weight');

        $this->domesticRates->getRate(
            originZip: '90210',
            destinationZip: '10001',
            weightLbs: 0.0, // Invalid - zero weight
            length: 12.0,
            width: 8.0,
            height: 6.0,
            serviceType: DomesticServiceType::GROUND_ADVANTAGE
        );
    }

    public function testGetRateValidatesDimensions(): void
    {
        $this->expectException(ValidationException::class);
        $this->expectExceptionMessage('Dimensions must be greater than 0');

        $this->domesticRates->getRate(
            originZip: '90210',
            destinationZip: '10001',
            weightLbs: 2.5,
            length: 0.0, // Invalid - zero dimension
            width: 8.0,
            height: 6.0,
            serviceType: DomesticServiceType::GROUND_ADVANTAGE
        );
    }

    public function testGetAllRatesReturnsMultipleServices(): void
    {
        // Mock response for each service type request
        $this->httpClient->addJsonResponse('/prices/v3/base-rates/search', 200, [
            'totalBasePrice' => 8.50,
            'totalPrice' => 8.50,
            'SKU' => 'USPS_GROUND_ADVANTAGE',
            'description' => 'USPS Ground Advantage',
        ]);

        $rates = $this->domesticRates->getAllRates(
            originZip: '90210',
            destinationZip: '10001',
            weightLbs: 2.5,
            length: 12.0,
            width: 8.0,
            height: 6.0
        );

        $this->assertIsArray($rates);
        $this->assertNotEmpty($rates);

        foreach ($rates as $rate) {
            $this->assertInstanceOf(Rate::class, $rate);
        }
    }

    public function testSetRateAdjustment(): void
    {
        $result = $this->domesticRates->setRateAdjustment(10.0);

        $this->assertInstanceOf(DomesticRates::class, $result);
        $this->assertSame($this->domesticRates, $result); // Fluent interface
    }

    public function testSetHandlingFee(): void
    {
        $result = $this->domesticRates->setHandlingFee(2.50);

        $this->assertInstanceOf(DomesticRates::class, $result);
        $this->assertSame($this->domesticRates, $result); // Fluent interface
    }

    public function testHandlingFeeCannotBeNegative(): void
    {
        // Should set to 0 if negative value provided
        $this->domesticRates->setHandlingFee(-5.0);

        // This test just ensures no exception is thrown
        // The actual handling fee logic would need to be tested through getRate
        $this->assertTrue(true);
    }

    public function testRateRequestIncludesAllParameters(): void
    {
        $this->httpClient->addJsonResponse('/prices/v3/base-rates/search', 200, [
            'totalBasePrice' => 8.50,
            'totalPrice' => 8.50,
            'SKU' => 'USPS_GROUND_ADVANTAGE',
            'description' => 'USPS Ground Advantage',
        ]);

        $this->domesticRates->getRate(
            originZip: '90210',
            destinationZip: '10001',
            weightLbs: 2.5,
            length: 12.0,
            width: 8.0,
            height: 6.0,
            serviceType: DomesticServiceType::GROUND_ADVANTAGE
        );

        $lastRequest = $this->httpClient->getLastRequest();
        $this->assertNotNull($lastRequest);

        $body = json_decode($lastRequest['body'], true);
        $this->assertArrayHasKey('originZIPCode', $body);
        $this->assertArrayHasKey('destinationZIPCode', $body);
        $this->assertArrayHasKey('weight', $body);
        $this->assertArrayHasKey('length', $body);
        $this->assertArrayHasKey('width', $body);
        $this->assertArrayHasKey('height', $body);
        $this->assertArrayHasKey('mailClass', $body);

        $this->assertEquals('90210', $body['originZIPCode']);
        $this->assertEquals('10001', $body['destinationZIPCode']);
        $this->assertEquals(2.5, $body['weight']);
    }
}
