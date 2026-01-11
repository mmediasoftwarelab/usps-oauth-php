<?php

/**
 * Laravel Integration Example
 * 
 * This example shows how to integrate the USPS OAuth library
 * into a Laravel application.
 */

namespace App\Services;

use MMedia\USPS\Client;
use MMedia\USPS\Rates\DomesticRates;
use MMedia\USPS\Rates\InternationalRates;
use MMedia\USPS\Models\Rate;
use MMedia\USPS\Exceptions\UspsException;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Cache;

/**
 * USPS Shipping Service for Laravel
 */
class UspsShippingService
{
    private Client $client;
    private DomesticRates $domesticRates;
    private InternationalRates $internationalRates;

    public function __construct()
    {
        // Initialize client with config values
        $this->client = new Client(
            clientId: config('services.usps.client_id'),
            clientSecret: config('services.usps.client_secret'),
            sandbox: config('services.usps.sandbox', true),
            logger: Log::channel('usps')
        );

        $this->domesticRates = new DomesticRates($this->client);
        $this->internationalRates = new InternationalRates($this->client);

        // Apply configured adjustments
        $rateAdjustment = config('services.usps.rate_adjustment', 0);
        $handlingFee = config('services.usps.handling_fee', 0);

        $this->domesticRates
            ->setRateAdjustment($rateAdjustment)
            ->setHandlingFee($handlingFee);

        $this->internationalRates
            ->setRateAdjustment($rateAdjustment)
            ->setHandlingFee($handlingFee);
    }

    /**
     * Calculate shipping rates for a cart
     *
     * @param array $items Cart items with weight and dimensions
     * @param string $destinationZip Destination ZIP code
     * @param string|null $destinationCountry Destination country (null for domestic)
     * @return array Array of available shipping rates
     */
    public function calculateShippingRates(
        array $items,
        string $destinationZip,
        ?string $destinationCountry = null
    ): array {
        try {
            // Calculate total weight and dimensions
            $package = $this->calculatePackageDimensions($items);

            $originZip = config('services.usps.origin_zip');

            // Determine if domestic or international
            $isDomestic = empty($destinationCountry) || $destinationCountry === 'US';

            if ($isDomestic) {
                return $this->getDomesticRates($originZip, $destinationZip, $package);
            } else {
                return $this->getInternationalRates(
                    $originZip,
                    $destinationCountry,
                    $package,
                    $destinationZip
                );
            }
        } catch (UspsException $e) {
            Log::error('USPS rate calculation failed', [
                'error' => $e->getMessage(),
                'destination_zip' => $destinationZip,
                'destination_country' => $destinationCountry,
            ]);

            // Return fallback rates if configured
            return $this->getFallbackRates();
        }
    }

    /**
     * Get domestic shipping rates with caching
     */
    private function getDomesticRates(string $originZip, string $destinationZip, array $package): array
    {
        $cacheKey = "usps_domestic_{$originZip}_{$destinationZip}_{$package['weight']}";

        return Cache::remember($cacheKey, now()->addHours(1), function () use ($originZip, $destinationZip, $package) {
            $rates = $this->domesticRates->getAllRates(
                originZip: $originZip,
                destinationZip: $destinationZip,
                weightLbs: $package['weight'],
                length: $package['length'],
                width: $package['width'],
                height: $package['height']
            );

            return $this->formatRatesForCart($rates);
        });
    }

    /**
     * Get international shipping rates with caching
     */
    private function getInternationalRates(
        string $originZip,
        string $destinationCountry,
        array $package,
        ?string $destinationPostalCode = null
    ): array {
        $cacheKey = "usps_intl_{$originZip}_{$destinationCountry}_{$package['weight']}";

        return Cache::remember($cacheKey, now()->addHours(1), function () use (
            $originZip,
            $destinationCountry,
            $package,
            $destinationPostalCode
        ) {
            $rates = $this->internationalRates->getAllRates(
                originZip: $originZip,
                destinationCountry: $destinationCountry,
                weightLbs: $package['weight'],
                length: $package['length'],
                width: $package['width'],
                height: $package['height'],
                destinationPostalCode: $destinationPostalCode
            );

            return $this->formatRatesForCart($rates);
        });
    }

    /**
     * Calculate package dimensions from cart items
     */
    private function calculatePackageDimensions(array $items): array
    {
        $totalWeight = 0;
        $maxLength = 0;
        $maxWidth = 0;
        $maxHeight = 0;

        foreach ($items as $item) {
            $totalWeight += $item['weight'] * $item['quantity'];
            $maxLength = max($maxLength, $item['length'] ?? 0);
            $maxWidth = max($maxWidth, $item['width'] ?? 0);
            $maxHeight = max($maxHeight, $item['height'] ?? 0);
        }

        // Use default box dimensions if product dimensions not available
        if ($maxLength === 0 || $maxWidth === 0 || $maxHeight === 0) {
            $maxLength = config('services.usps.default_length', 12);
            $maxWidth = config('services.usps.default_width', 8);
            $maxHeight = config('services.usps.default_height', 6);
        }

        return [
            'weight' => max(0.1, $totalWeight), // Minimum 0.1 lbs
            'length' => $maxLength,
            'width' => $maxWidth,
            'height' => $maxHeight,
        ];
    }

    /**
     * Format rates for cart display
     *
     * @param Rate[] $rates
     * @return array
     */
    private function formatRatesForCart(array $rates): array
    {
        return array_map(function (Rate $rate) {
            return [
                'id' => $rate->getService(),
                'label' => $rate->getServiceLabel(),
                'cost' => $rate->getTotalPrice(),
                'formatted_cost' => '$' . number_format($rate->getTotalPrice(), 2),
            ];
        }, $rates);
    }

    /**
     * Get fallback rates when API is unavailable
     */
    private function getFallbackRates(): array
    {
        return config('services.usps.fallback_rates', []);
    }
}

/*
 * Configuration file (config/services.php):
 * 
 * return [
 *     'usps' => [
 *         'client_id' => env('USPS_CLIENT_ID'),
 *         'client_secret' => env('USPS_CLIENT_SECRET'),
 *         'sandbox' => env('USPS_SANDBOX', true),
 *         'origin_zip' => env('USPS_ORIGIN_ZIP', '90210'),
 *         'rate_adjustment' => env('USPS_RATE_ADJUSTMENT', 0),
 *         'handling_fee' => env('USPS_HANDLING_FEE', 0),
 *         'default_length' => 12,
 *         'default_width' => 8,
 *         'default_height' => 6,
 *         'fallback_rates' => [
 *             [
 *                 'id' => 'ground',
 *                 'label' => 'USPS Ground (Estimated)',
 *                 'cost' => 8.99,
 *             ],
 *         ],
 *     ],
 * ];
 * 
 * Service Provider (app/Providers/AppServiceProvider.php):
 * 
 * use MMedia\USPS\Client;
 * 
 * public function register()
 * {
 *     $this->app->singleton(Client::class, function ($app) {
 *         return new Client(
 *             clientId: config('services.usps.client_id'),
 *             clientSecret: config('services.usps.client_secret'),
 *             sandbox: config('services.usps.sandbox'),
 *             logger: $app->make('log')
 *         );
 *     });
 * }
 * 
 * Controller Example:
 * 
 * use App\Services\UspsShippingService;
 * 
 * class CheckoutController extends Controller
 * {
 *     public function getShippingRates(Request $request, UspsShippingService $usps)
 *     {
 *         $items = $request->input('items');
 *         $zip = $request->input('zip');
 *         $country = $request->input('country');
 *         
 *         $rates = $usps->calculateShippingRates($items, $zip, $country);
 *         
 *         return response()->json(['rates' => $rates]);
 *     }
 * }
 */
