<?php

declare(strict_types=1);

namespace MMedia\USPS\Rates;

use MMedia\USPS\Client;
use MMedia\USPS\Models\Rate;
use MMedia\USPS\Enums\InternationalServiceType;
use MMedia\USPS\Exceptions\RateException;
use MMedia\USPS\Exceptions\ValidationException;

/**
 * International Shipping Rates
 *
 * Get shipping rates for international shipments using USPS International Prices API v3.
 */
class InternationalRates
{
    private const API_ENDPOINT = '/international-prices/v3/base-rates/search';

    private float $rateAdjustmentPercent = 0.0;
    private float $handlingFee = 0.0;

    public function __construct(
        private readonly Client $client
    ) {
    }

    /**
     * Get international shipping rate
     *
     * @param string $originZip 5-digit origin ZIP code
     * @param string $destinationCountry 2-letter country code (ISO 3166-1 alpha-2)
     * @param float $weightLbs Package weight in pounds
     * @param float $length Package length in inches
     * @param float $width Package width in inches
     * @param float $height Package height in inches
     * @param string|InternationalServiceType $serviceType Service type
     * @param string|null $destinationPostalCode Optional destination postal code
     * @return Rate Rate object with pricing information
     * @throws ValidationException If input validation fails
     * @throws RateException If rate calculation fails
     */
    public function getRate(
        string $originZip,
        string $destinationCountry,
        float $weightLbs,
        float $length,
        float $width,
        float $height,
        string|InternationalServiceType $serviceType,
        ?string $destinationPostalCode = null
    ): Rate {
        $this->validateInputs($originZip, $destinationCountry, $weightLbs, $length, $width, $height);

        $serviceCode = $serviceType instanceof InternationalServiceType
            ? $serviceType->value
            : $serviceType;

        $request = $this->buildRequest(
            $originZip,
            $destinationCountry,
            $weightLbs,
            $length,
            $width,
            $height,
            $serviceCode,
            $destinationPostalCode
        );

        $response = $this->client->request(self::API_ENDPOINT, $request, 'POST');

        return $this->parseResponse($response, $serviceCode, $serviceType);
    }

    /**
     * Get rates for all available international services
     *
     * @return array<string, Rate> Array of rates keyed by service type
     */
    public function getAllRates(
        string $originZip,
        string $destinationCountry,
        float $weightLbs,
        float $length,
        float $width,
        float $height,
        ?string $destinationPostalCode = null
    ): array {
        $rates = [];

        foreach (InternationalServiceType::cases() as $serviceType) {
            try {
                $rate = $this->getRate(
                    $originZip,
                    $destinationCountry,
                    $weightLbs,
                    $length,
                    $width,
                    $height,
                    $serviceType,
                    $destinationPostalCode
                );
                $rates[$serviceType->value] = $rate;
            } catch (RateException $e) {
                // Skip services that fail
                continue;
            }
        }

        return $rates;
    }

    /**
     * Set rate adjustment percentage (markup/discount)
     */
    public function setRateAdjustment(float $percent): self
    {
        $this->rateAdjustmentPercent = $percent;
        return $this;
    }

    /**
     * Set handling fee (fixed amount added to all rates)
     */
    public function setHandlingFee(float $fee): self
    {
        $this->handlingFee = max(0, $fee);
        return $this;
    }

    /**
     * Build API request body
     *
     * @return array<string, mixed>
     */
    private function buildRequest(
        string $originZip,
        string $destinationCountry,
        float $weightLbs,
        float $length,
        float $width,
        float $height,
        string $serviceCode,
        ?string $destinationPostalCode
    ): array {
        return [
            'originZIPCode' => $originZip,
            'foreignPostalCode' => $destinationPostalCode ?? '',
            'destinationCountryCode' => strtoupper($destinationCountry),
            'weight' => $weightLbs,
            'length' => $length,
            'width' => $width,
            'height' => $height,
            'mailClass' => $serviceCode,
            'processingCategory' => 'NON_MACHINABLE',
            'rateIndicator' => 'SP',
            'destinationEntryFacilityType' => 'NONE',
            'priceType' => 'COMMERCIAL',
        ];
    }

    /**
     * Parse API response into Rate object
     *
     * @param array<string, mixed> $response
     * @throws RateException
     */
    private function parseResponse(
        array $response,
        string $serviceCode,
        string|InternationalServiceType $serviceType
    ): Rate {
        if (empty($response['totalBasePrice'])) {
            throw new RateException('No rate found in API response for service: ' . $serviceCode);
        }

        $basePrice = (float) $response['totalBasePrice'];

        // Apply rate adjustment
        $adjustedPrice = $basePrice * (1 + ($this->rateAdjustmentPercent / 100));

        // Add handling fee
        $totalPrice = $adjustedPrice + $this->handlingFee;

        $serviceLabel = $serviceType instanceof InternationalServiceType
            ? $serviceType->getLabel()
            : $serviceCode;

        return new Rate(
            service: $serviceCode,
            serviceLabel: $serviceLabel,
            basePrice: $basePrice,
            totalPrice: $totalPrice,
            metadata: [
                'rate_adjustment_percent' => $this->rateAdjustmentPercent,
                'handling_fee' => $this->handlingFee,
                'api_response' => $response,
            ]
        );
    }

    /**
     * Validate input parameters
     *
     * @throws ValidationException
     */
    private function validateInputs(
        string $originZip,
        string $destinationCountry,
        float $weightLbs,
        float $length,
        float $width,
        float $height
    ): void {
        if (!preg_match('/^\d{5}$/', $originZip)) {
            throw new ValidationException('Invalid origin ZIP code. Must be 5 digits.');
        }

        if (!preg_match('/^[A-Z]{2}$/i', $destinationCountry)) {
            throw new ValidationException('Invalid destination country code. Must be 2 letters (ISO 3166-1 alpha-2).');
        }

        if ($weightLbs <= 0) {
            throw new ValidationException('Weight must be greater than 0');
        }

        if ($length <= 0 || $width <= 0 || $height <= 0) {
            throw new ValidationException('Dimensions must be greater than 0');
        }
    }
}
