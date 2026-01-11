<?php

declare(strict_types=1);

namespace MMedia\USPS\Http;

use MMedia\USPS\Exceptions\HttpException;

/**
 * cURL-based HTTP Client
 */
class CurlHttpClient implements HttpClientInterface
{
    public function __construct(
        private readonly int $timeout = 15,
        private readonly int $connectTimeout = 10
    ) {
    }

    public function request(
        string $url,
        string $method = 'GET',
        array $headers = [],
        ?string $body = null
    ): HttpResponse {
        $ch = curl_init();

        if ($ch === false) {
            throw new HttpException('Failed to initialize cURL');
        }

        try {
            // Set URL and method
            curl_setopt($ch, CURLOPT_URL, $url);
            curl_setopt($ch, CURLOPT_CUSTOMREQUEST, $method);

            // Set headers
            $headerLines = [];
            foreach ($headers as $name => $value) {
                $headerLines[] = "{$name}: {$value}";
            }
            if (!empty($headerLines)) {
                curl_setopt($ch, CURLOPT_HTTPHEADER, $headerLines);
            }

            // Set body if provided
            if ($body !== null) {
                curl_setopt($ch, CURLOPT_POSTFIELDS, $body);
            }

            // Configure response handling
            curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
            curl_setopt($ch, CURLOPT_HEADER, true);
            curl_setopt($ch, CURLOPT_TIMEOUT, $this->timeout);
            curl_setopt($ch, CURLOPT_CONNECTTIMEOUT, $this->connectTimeout);

            // SSL verification (should be true in production)
            curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, true);
            curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, 2);

            // Execute request
            $response = curl_exec($ch);

            if ($response === false) {
                $error = curl_error($ch);
                throw new HttpException("cURL error: {$error}");
            }

            // Ensure response is a string
            if (!is_string($response)) {
                throw new HttpException('Unexpected cURL response type');
            }

            // Get response info
            $statusCode = (int) curl_getinfo($ch, CURLINFO_HTTP_CODE);
            $headerSize = (int) curl_getinfo($ch, CURLINFO_HEADER_SIZE);

            // Parse headers and body
            $headerText = substr($response, 0, $headerSize);
            $body = substr($response, $headerSize);

            $headers = $this->parseHeaders($headerText);

            return new HttpResponse($statusCode, $body, $headers);
        } finally {
            curl_close($ch);
        }
    }

    /**
     * Parse HTTP headers from response
     *
     * @return array<string, string>
     */
    private function parseHeaders(string $headerText): array
    {
        $headers = [];
        $lines = explode("\r\n", $headerText);

        foreach ($lines as $line) {
            if (str_contains($line, ':')) {
                [$name, $value] = explode(':', $line, 2);
                $headers[trim($name)] = trim($value);
            }
        }

        return $headers;
    }
}
