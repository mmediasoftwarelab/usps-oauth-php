<?php

declare(strict_types=1);

namespace MMedia\USPS\Http;

/**
 * HTTP Client Interface
 */
interface HttpClientInterface
{
    /**
     * Make an HTTP request
     *
     * @param string $url Full URL to request
     * @param string $method HTTP method (GET, POST, etc.)
     * @param array<string, string> $headers Request headers
     * @param string|null $body Request body
     * @return HttpResponse Response object
     */
    public function request(
        string $url,
        string $method = 'GET',
        array $headers = [],
        ?string $body = null
    ): HttpResponse;
}
