<?php

declare(strict_types=1);

namespace MMedia\USPS\Tests\Unit\Auth;

use MMedia\USPS\Auth\TokenManager;
use MMedia\USPS\Exceptions\AuthenticationException;
use MMedia\USPS\Tests\Mocks\MockHttpClient;
use PHPUnit\Framework\TestCase;

class TokenManagerTest extends TestCase
{
    private MockHttpClient $httpClient;

    protected function setUp(): void
    {
        $this->httpClient = new MockHttpClient();
    }

    public function testGetAccessTokenRequestsNewToken(): void
    {
        $this->httpClient->addJsonResponse('/oauth2/v3/token', 200, [
            'access_token' => 'test-token-abc123',
            'token_type' => 'Bearer',
            'expires_in' => 3600,
        ]);

        $tokenManager = new TokenManager(
            clientId: 'test-client-id',
            clientSecret: 'test-client-secret',
            baseUrl: 'https://api-cat.usps.com',
            httpClient: $this->httpClient
        );

        $token = $tokenManager->getAccessToken();

        $this->assertEquals('test-token-abc123', $token);
        $this->assertEquals(1, $this->httpClient->countRequests('/oauth2/v3/token'));
    }

    public function testGetAccessTokenReusesValidToken(): void
    {
        $this->httpClient->addJsonResponse('/oauth2/v3/token', 200, [
            'access_token' => 'test-token-abc123',
            'token_type' => 'Bearer',
            'expires_in' => 3600, // Valid for 1 hour
        ]);

        $tokenManager = new TokenManager(
            clientId: 'test-client-id',
            clientSecret: 'test-client-secret',
            baseUrl: 'https://api-cat.usps.com',
            httpClient: $this->httpClient
        );

        // First call should request token
        $token1 = $tokenManager->getAccessToken();

        // Second call should reuse token
        $token2 = $tokenManager->getAccessToken();

        $this->assertEquals($token1, $token2);
        // Should only make ONE request since token is still valid
        $this->assertEquals(1, $this->httpClient->countRequests('/oauth2/v3/token'));
    }

    public function testGetAccessTokenRefreshesExpiredToken(): void
    {
        // First token with very short expiry
        $this->httpClient->addJsonResponse('/oauth2/v3/token', 200, [
            'access_token' => 'expired-token',
            'token_type' => 'Bearer',
            'expires_in' => 1, // Expires in 1 second
        ]);

        $tokenManager = new TokenManager(
            clientId: 'test-client-id',
            clientSecret: 'test-client-secret',
            baseUrl: 'https://api-cat.usps.com',
            httpClient: $this->httpClient
        );

        $token1 = $tokenManager->getAccessToken();

        // Wait for token to expire (accounting for 5-minute buffer)
        sleep(2);

        // Add new token response
        $this->httpClient->reset();
        $this->httpClient->addJsonResponse('/oauth2/v3/token', 200, [
            'access_token' => 'new-refreshed-token',
            'token_type' => 'Bearer',
            'expires_in' => 3600,
        ]);

        $token2 = $tokenManager->getAccessToken();

        // Should get new token after expiry
        $this->assertEquals('new-refreshed-token', $token2);
    }

    public function testGetAccessTokenThrowsExceptionOnAuthFailure(): void
    {
        $this->httpClient->addJsonResponse('/oauth2/v3/token', 401, [
            'error' => 'invalid_client',
            'error_description' => 'Client authentication failed',
        ]);

        $tokenManager = new TokenManager(
            clientId: 'invalid-client-id',
            clientSecret: 'invalid-secret',
            baseUrl: 'https://api-cat.usps.com',
            httpClient: $this->httpClient
        );

        $this->expectException(AuthenticationException::class);
        $this->expectExceptionMessage('Client authentication failed');
        $this->expectExceptionCode(401);

        $tokenManager->getAccessToken();
    }

    public function testTokenRequestIncludesCorrectHeaders(): void
    {
        $this->httpClient->addJsonResponse('/oauth2/v3/token', 200, [
            'access_token' => 'test-token',
            'token_type' => 'Bearer',
            'expires_in' => 3600,
        ]);

        $tokenManager = new TokenManager(
            clientId: 'test-client-id',
            clientSecret: 'test-client-secret',
            baseUrl: 'https://api-cat.usps.com',
            httpClient: $this->httpClient
        );

        $tokenManager->getAccessToken();

        $lastRequest = $this->httpClient->getLastRequest();

        $this->assertNotNull($lastRequest);
        $this->assertEquals('POST', $lastRequest['method']);
        $this->assertArrayHasKey('Content-Type', $lastRequest['headers']);
        $this->assertEquals('application/x-www-form-urlencoded', $lastRequest['headers']['Content-Type']);
    }

    public function testTokenRequestIncludesCredentials(): void
    {
        $this->httpClient->addJsonResponse('/oauth2/v3/token', 200, [
            'access_token' => 'test-token',
            'token_type' => 'Bearer',
            'expires_in' => 3600,
        ]);

        $tokenManager = new TokenManager(
            clientId: 'my-client-id',
            clientSecret: 'my-client-secret',
            baseUrl: 'https://api-cat.usps.com',
            httpClient: $this->httpClient
        );

        $tokenManager->getAccessToken();

        $lastRequest = $this->httpClient->getLastRequest();
        $body = $lastRequest['body'];

        $this->assertNotNull($body);
        $this->assertStringContainsString('grant_type=client_credentials', $body);
        $this->assertStringContainsString('client_id=my-client-id', $body);
        $this->assertStringContainsString('client_secret=my-client-secret', $body);
    }
}
