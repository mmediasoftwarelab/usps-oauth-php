<?php

declare(strict_types=1);

namespace MMedia\USPS;

use MMedia\USPS\Auth\TokenManager;
use MMedia\USPS\Http\HttpClientInterface;
use MMedia\USPS\Http\CurlHttpClient;
use MMedia\USPS\Exceptions\AuthenticationException;
use MMedia\USPS\Exceptions\ApiException;
use Psr\Log\LoggerInterface;
use Psr\Log\NullLogger;

/**
 * USPS OAuth API Client
 *
 * Main entry point for interacting with the USPS OAuth 2.0 API.
 * Handles authentication, token management, and API requests.
 */
class Client
{
    private const PRODUCTION_BASE_URL = 'https://apis.usps.com';
    private const SANDBOX_BASE_URL = 'https://api-cat.usps.com';

    private readonly string $baseUrl;
    private readonly TokenManager $tokenManager;
    private readonly HttpClientInterface $httpClient;
    private readonly LoggerInterface $logger;

    public function __construct(
        private readonly string $clientId,
        private readonly string $clientSecret,
        private readonly bool $sandbox = false,
        ?HttpClientInterface $httpClient = null,
        ?LoggerInterface $logger = null
    ) {
        $this->baseUrl = $sandbox ? self::SANDBOX_BASE_URL : self::PRODUCTION_BASE_URL;
        $this->httpClient = $httpClient ?? new CurlHttpClient();
        $this->logger = $logger ?? new NullLogger();

        $this->tokenManager = new TokenManager(
            clientId: $this->clientId,
            clientSecret: $this->clientSecret,
            baseUrl: $this->baseUrl,
            httpClient: $this->httpClient,
            logger: $this->logger
        );
    }

    /**
     * Make an authenticated API request
     *
     * @param string $endpoint API endpoint (e.g., '/prices/v3/base-rates/search')
     * @param array<string, mixed> $body Request body
     * @param string $method HTTP method (GET, POST, etc.)
     * @return array<string, mixed> Decoded response body
     * @throws AuthenticationException If authentication fails
     * @throws ApiException If API request fails
     */
    public function request(string $endpoint, array $body = [], string $method = 'POST'): array
    {
        $token = $this->tokenManager->getAccessToken();

        $url = $this->baseUrl . $endpoint;

        $headers = [
            'Authorization' => 'Bearer ' . $token,
            'Content-Type' => 'application/json',
        ];

        $this->logger->info('USPS API request', [
            'method' => $method,
            'endpoint' => $endpoint,
            'sandbox' => $this->sandbox,
        ]);

        $bodyParam = null;
        if (!empty($body)) {
            $encoded = json_encode($body);
            if ($encoded === false) {
                throw new ApiException('Failed to encode request body');
            }
            $bodyParam = $encoded;
        }

        $response = $this->httpClient->request(
            url: $url,
            method: $method,
            headers: $headers,
            body: $bodyParam
        );

        $statusCode = $response->getStatusCode();
        $responseBody = $response->getBody();

        if ($statusCode !== 200) {
            $data = json_decode($responseBody, true);
            $errorMessage = $data['error']['message'] ?? 'API request failed';

            $this->logger->error('USPS API error', [
                'status_code' => $statusCode,
                'error' => $errorMessage,
                'endpoint' => $endpoint,
            ]);

            throw new ApiException($errorMessage, $statusCode);
        }

        $data = json_decode($responseBody, true);

        if (json_last_error() !== JSON_ERROR_NONE) {
            throw new ApiException('Invalid JSON response: ' . json_last_error_msg());
        }

        return $data;
    }

    /**
     * Test API connection
     *
     * @return array{success: bool, message: string, mode: string}
     */
    public function testConnection(): array
    {
        try {
            $token = $this->tokenManager->getAccessToken();

            return [
                'success' => true,
                'message' => 'Successfully connected to USPS API',
                'mode' => $this->sandbox ? 'sandbox' : 'production',
            ];
        } catch (AuthenticationException $e) {
            return [
                'success' => false,
                'message' => $e->getMessage(),
                'mode' => $this->sandbox ? 'sandbox' : 'production',
            ];
        }
    }

    /**
     * Get the base URL being used
     */
    public function getBaseUrl(): string
    {
        return $this->baseUrl;
    }

    /**
     * Check if running in sandbox mode
     */
    public function isSandbox(): bool
    {
        return $this->sandbox;
    }
}
