<?php

declare(strict_types=1);

namespace MMedia\USPS\Rates;

use MMedia\USPS\Client;
use MMedia\USPS\Models\Rate;
use MMedia\USPS\Enums\DomesticServiceType;
use MMedia\USPS\Exceptions\ApiException;
use MMedia\USPS\Exceptions\RateException;
use MMedia\USPS\Exceptions\ValidationException;

/**
 * Domestic Shipping Rates
 *
 * Get shipping rates for domestic (US) shipments using USPS Prices API v3.
 */
class DomesticRates
{
    private const API_ENDPOINT = '/prices/v3/base-rates/search';

    private float $rateAdjustmentPercent = 0.0;
    private float $handlingFee = 0.0;

    public function __construct(
        private readonly Client $client
    ) {}

    /**
     * Get shipping rate for a specific service
     *
     * @param string $originZip 5-digit origin ZIP code
     * @param string $destinationZip 5-digit destination ZIP code
     * @param float $weightLbs Package weight in pounds
     * @param float $length Package length in inches
     * @param float $width Package width in inches
     * @param float $height Package height in inches
     * @param string|DomesticServiceType $serviceType Service type
     * @return Rate Rate object with pricing information
     * @throws ValidationException If input validation fails
     * @throws RateException If rate calculation fails
     */
    public function getRate(
        string $originZip,
        string $destinationZip,
        float $weightLbs,
        float $length,
        float $width,
        float $height,
        string|DomesticServiceType $serviceType
    ): Rate {
        $this->validateInputs($originZip, $destinationZip, $weightLbs, $length, $width, $height);

        $serviceCode = $serviceType instanceof DomesticServiceType
            ? $serviceType->value
            : $serviceType;

        $request = $this->buildRequest(
            $originZip,
            $destinationZip,
            $weightLbs,
            $length,
            $width,
            $height,
            $serviceCode
        );

        $response = $this->client->request(self::API_ENDPOINT, $request, 'POST');

        return $this->parseResponse($response, $serviceCode, $serviceType);
    }

    /**
     * Get rates for all available services
     *
     * @return array<string, Rate> Array of rates keyed by service type
     */
    public function getAllRates(
        string $originZip,
        string $destinationZip,
        float $weightLbs,
        float $length,
        float $width,
        float $height
    ): array {
        $rates = [];

        foreach (DomesticServiceType::cases() as $serviceType) {
            try {
                $rate = $this->getRate(
                    $originZip,
                    $destinationZip,
                    $weightLbs,
                    $length,
                    $width,
                    $height,
                    $serviceType
                );
                $rates[$serviceType->value] = $rate;
            } catch (RateException | ApiException $e) {
                // Skip services that fail (not available for this shipment)
                continue;
            }
        }

        return $rates;
    }

    /**
     * Set rate adjustment percentage (markup/discount)
     *
     * @param float $percent Percentage adjustment (positive for markup, negative for discount)
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
        string $destinationZip,
        float $weightLbs,
        float $length,
        float $width,
        float $height,
        string $serviceCode
    ): array {
        return [
            'originZIPCode' => $originZip,
            'destinationZIPCode' => $destinationZip,
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
     * @return Rate
     * @throws RateException
     */
    private function parseResponse(
        array $response,
        string $serviceCode,
        string|DomesticServiceType $serviceType
    ): Rate {
        if (empty($response['totalBasePrice'])) {
            throw new RateException('No rate found in API response for service: ' . $serviceCode);
        }

        $basePrice = (float) $response['totalBasePrice'];

        // Apply rate adjustment
        $adjustedPrice = $basePrice * (1 + ($this->rateAdjustmentPercent / 100));

        // Add handling fee
        $totalPrice = $adjustedPrice + $this->handlingFee;

        $serviceLabel = $serviceType instanceof DomesticServiceType
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
        string $destinationZip,
        float $weightLbs,
        float $length,
        float $width,
        float $height
    ): void {
        if (!preg_match('/^\d{5}$/', $originZip)) {
            throw new ValidationException('Invalid origin ZIP code. Must be 5 digits.');
        }

        if (!preg_match('/^\d{5}$/', $destinationZip)) {
            throw new ValidationException('Invalid destination ZIP code. Must be 5 digits.');
        }

        if ($weightLbs <= 0) {
            throw new ValidationException('Weight must be greater than 0');
        }

        if ($length <= 0 || $width <= 0 || $height <= 0) {
            throw new ValidationException('Dimensions must be greater than 0');
        }
    }
}
