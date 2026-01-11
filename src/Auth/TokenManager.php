<?php

declare(strict_types=1);

namespace MMedia\USPS\Auth;

use MMedia\USPS\Http\HttpClientInterface;
use MMedia\USPS\Exceptions\AuthenticationException;
use Psr\Log\LoggerInterface;
use Psr\Log\NullLogger;

/**
 * OAuth 2.0 Token Manager
 *
 * Handles OAuth token acquisition, storage, and automatic refresh.
 */
class TokenManager
{
    private const TOKEN_EXPIRY_BUFFER = 300; // Refresh 5 minutes before expiry

    private ?string $accessToken = null;
    private ?int $tokenExpiry = null;

    public function __construct(
        private readonly string $clientId,
        private readonly string $clientSecret,
        private readonly string $baseUrl,
        private readonly HttpClientInterface $httpClient,
        private readonly LoggerInterface $logger = new NullLogger()
    ) {
    }

    /**
     * Get a valid access token (refreshes if needed)
     *
     * @throws AuthenticationException If token acquisition fails
     */
    public function getAccessToken(): string
    {
        if ($this->isTokenValid()) {
            assert($this->accessToken !== null);
            return $this->accessToken;
        }

        return $this->refreshToken();
    }

    /**
     * Check if current token is still valid
     */
    private function isTokenValid(): bool
    {
        if ($this->accessToken === null || $this->tokenExpiry === null) {
            return false;
        }

        return time() < ($this->tokenExpiry - self::TOKEN_EXPIRY_BUFFER);
    }

    /**
     * Request a new access token from USPS
     *
     * @throws AuthenticationException If authentication fails
     */
    private function refreshToken(): string
    {
        $tokenUrl = $this->baseUrl . '/oauth2/v3/token';

        $this->logger->info('Requesting new OAuth token', [
            'client_id' => substr($this->clientId, 0, 10) . '...',
        ]);

        $body = http_build_query([
            'grant_type' => 'client_credentials',
            'client_id' => $this->clientId,
            'client_secret' => $this->clientSecret,
        ]);

        try {
            $response = $this->httpClient->request(
                url: $tokenUrl,
                method: 'POST',
                headers: ['Content-Type' => 'application/x-www-form-urlencoded'],
                body: $body
            );

            $statusCode = $response->getStatusCode();
            $responseBody = $response->getBody();

            if ($statusCode !== 200) {
                $data = json_decode($responseBody, true);
                $errorMessage = $data['error_description'] ?? 'Token request failed';

                $this->logger->error('OAuth token request failed', [
                    'status_code' => $statusCode,
                    'error' => $errorMessage,
                ]);

                throw new AuthenticationException($errorMessage, $statusCode);
            }

            $data = json_decode($responseBody, true);

            if (empty($data['access_token'])) {
                throw new AuthenticationException('No access token in response');
            }

            $this->accessToken = $data['access_token'];
            $expiresIn = $data['expires_in'] ?? 3600;
            $this->tokenExpiry = time() + (int) $expiresIn;

            $this->logger->info('OAuth token acquired successfully', [
                'expires_in' => $expiresIn,
            ]);

            return $this->accessToken;
        } catch (\Exception $e) {
            if ($e instanceof AuthenticationException) {
                throw $e;
            }

            $this->logger->error('Token request exception', [
                'error' => $e->getMessage(),
            ]);

            throw new AuthenticationException(
                'Failed to acquire access token: ' . $e->getMessage(),
                0,
                $e
            );
        }
    }

    /**
     * Clear stored token (force refresh on next request)
     */
    public function clearToken(): void
    {
        $this->accessToken = null;
        $this->tokenExpiry = null;
    }
}
