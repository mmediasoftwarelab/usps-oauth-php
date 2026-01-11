<?php

declare(strict_types=1);

namespace MMedia\USPS\Tests\Unit;

use MMedia\USPS\Client;
use MMedia\USPS\Exceptions\ApiException;
use MMedia\USPS\Exceptions\AuthenticationException;
use MMedia\USPS\Tests\Mocks\MockHttpClient;
use PHPUnit\Framework\TestCase;

class ClientTest extends TestCase
{
    private MockHttpClient $httpClient;
    private Client $client;

    protected function setUp(): void
    {
        $this->httpClient = new MockHttpClient();

        // Mock successful OAuth token response
        $this->httpClient->addJsonResponse('/oauth2/v3/token', 200, [
            'access_token' => 'test-access-token-12345',
            'token_type' => 'Bearer',
            'expires_in' => 3600,
        ]);

        $this->client = new Client(
            clientId: 'test-client-id',
            clientSecret: 'test-client-secret',
            sandbox: true,
            httpClient: $this->httpClient
        );
    }

    public function testClientInitializesWithSandboxUrl(): void
    {
        $client = new Client(
            clientId: 'test-id',
            clientSecret: 'test-secret',
            sandbox: true,
            httpClient: $this->httpClient
        );

        $this->assertInstanceOf(Client::class, $client);
    }

    public function testClientInitializesWithProductionUrl(): void
    {
        $client = new Client(
            clientId: 'test-id',
            clientSecret: 'test-secret',
            sandbox: false,
            httpClient: $this->httpClient
        );

        $this->assertInstanceOf(Client::class, $client);
    }

    public function testRequestMakesAuthenticatedApiCall(): void
    {
        $this->httpClient->addJsonResponse('/prices/v3/base-rates/search', 200, [
            'rateOptions' => [
                [
                    'totalBasePrice' => 8.50,
                    'totalPrice' => 8.50,
                    'rates' => [],
                ],
            ],
        ]);

        $response = $this->client->request('/prices/v3/base-rates/search', [
            'originZIPCode' => '90210',
            'destinationZIPCode' => '10001',
        ]);

        $this->assertIsArray($response);
        $this->assertArrayHasKey('rateOptions', $response);

        // Verify OAuth token was requested
        $this->assertTrue($this->httpClient->assertRequestMade('/oauth2/v3/token'));

        // Verify API request was made
        $this->assertTrue($this->httpClient->assertRequestMade('/prices/v3/base-rates/search'));

        // Verify authorization header was sent
        $lastRequest = $this->httpClient->getLastRequest();
        $this->assertNotNull($lastRequest);
        $this->assertArrayHasKey('Authorization', $lastRequest['headers']);
        $this->assertStringStartsWith('Bearer ', $lastRequest['headers']['Authorization']);
    }

    public function testRequestThrowsApiExceptionOn400Error(): void
    {
        $this->httpClient->addJsonResponse('/prices/v3/base-rates/search', 400, [
            'error' => [
                'message' => 'Invalid ZIP code format',
            ],
        ]);

        $this->expectException(ApiException::class);
        $this->expectExceptionMessage('Invalid ZIP code format');

        $this->client->request('/prices/v3/base-rates/search', [
            'originZIPCode' => 'invalid',
        ]);
    }

    public function testRequestThrowsApiExceptionOn500Error(): void
    {
        $this->httpClient->addJsonResponse('/prices/v3/base-rates/search', 500, [
            'error' => [
                'message' => 'Internal server error',
            ],
        ]);

        $this->expectException(ApiException::class);
        $this->expectExceptionCode(500);

        $this->client->request('/prices/v3/base-rates/search');
    }

    public function testGetMethodRequest(): void
    {
        $this->httpClient->addJsonResponse('/tracking/v3/123456', 200, [
            'trackingNumber' => '123456',
            'status' => 'Delivered',
        ]);

        $response = $this->client->request('/tracking/v3/123456', [], 'GET');

        $this->assertIsArray($response);
        $this->assertArrayHasKey('trackingNumber', $response);

        $lastRequest = $this->httpClient->getLastRequest();
        $this->assertEquals('GET', $lastRequest['method']);
    }
}
