<?php

/**
 * WordPress/WooCommerce Adapter
 * 
 * This adapter shows how to use the standalone USPS OAuth library
 * with WordPress and WooCommerce.
 */

use MMedia\USPS\Client;
use MMedia\USPS\Rates\DomesticRates;
use MMedia\USPS\Rates\InternationalRates;
use MMedia\USPS\Exceptions\UspsException;
use Psr\Log\AbstractLogger;

/**
 * WordPress Logger adapter for PSR-3 compatibility
 */
class WP_USPS_Logger extends AbstractLogger
{
    public function log($level, $message, array $context = []): void
    {
        if (defined('WP_DEBUG') && WP_DEBUG && defined('WP_DEBUG_LOG') && WP_DEBUG_LOG) {
            $contextStr = !empty($context) ? ' ' . json_encode($context) : '';
            error_log("[USPS {$level}] {$message}{$contextStr}");
        }
    }
}

/**
 * WooCommerce Shipping Method using USPS OAuth library
 */
class WC_USPS_OAuth_Shipping_Method extends WC_Shipping_Method
{
    private Client $client;
    private DomesticRates $domesticRates;
    private InternationalRates $internationalRates;

    public function __construct($instance_id = 0)
    {
        $this->id = 'usps_oauth';
        $this->instance_id = absint($instance_id);
        $this->method_title = __('USPS OAuth Shipping', 'usps-oauth');
        $this->method_description = __('Real-time USPS shipping rates using OAuth 2.0 API', 'usps-oauth');
        $this->supports = ['shipping-zones', 'instance-settings'];

        $this->init();

        // Initialize USPS client
        $this->init_usps_client();
    }

    /**
     * Initialize settings
     */
    public function init()
    {
        $this->instance_form_fields = [
            'title' => [
                'title' => __('Title', 'usps-oauth'),
                'type' => 'text',
                'description' => __('This controls the title which the user sees during checkout.', 'usps-oauth'),
                'default' => __('USPS', 'usps-oauth'),
                'desc_tip' => true,
            ],
            'client_id' => [
                'title' => __('Client ID', 'usps-oauth'),
                'type' => 'text',
                'description' => __('Enter your USPS API Client ID', 'usps-oauth'),
                'default' => '',
            ],
            'client_secret' => [
                'title' => __('Client Secret', 'usps-oauth'),
                'type' => 'password',
                'description' => __('Enter your USPS API Client Secret', 'usps-oauth'),
                'default' => '',
            ],
            'sandbox' => [
                'title' => __('Sandbox Mode', 'usps-oauth'),
                'type' => 'checkbox',
                'label' => __('Enable sandbox mode', 'usps-oauth'),
                'default' => 'yes',
            ],
            'rate_adjustment' => [
                'title' => __('Rate Adjustment (%)', 'usps-oauth'),
                'type' => 'number',
                'description' => __('Percentage to adjust rates (positive for markup, negative for discount)', 'usps-oauth'),
                'default' => '0',
                'custom_attributes' => [
                    'step' => '0.1',
                ],
            ],
            'handling_fee' => [
                'title' => __('Handling Fee ($)', 'usps-oauth'),
                'type' => 'number',
                'description' => __('Fixed handling fee to add to all rates', 'usps-oauth'),
                'default' => '0',
                'custom_attributes' => [
                    'step' => '0.01',
                ],
            ],
        ];

        $this->init_settings();
        $this->title = $this->get_option('title');

        // Save settings
        add_action('woocommerce_update_options_shipping_' . $this->id, [$this, 'process_admin_options']);
    }

    /**
     * Initialize USPS client
     */
    private function init_usps_client()
    {
        $clientId = $this->get_option('client_id');
        $clientSecret = $this->get_option('client_secret');
        $sandbox = $this->get_option('sandbox') === 'yes';

        if (empty($clientId) || empty($clientSecret)) {
            return;
        }

        try {
            $this->client = new Client(
                clientId: $clientId,
                clientSecret: $clientSecret,
                sandbox: $sandbox,
                logger: new WP_USPS_Logger()
            );

            $rateAdjustment = (float) $this->get_option('rate_adjustment', 0);
            $handlingFee = (float) $this->get_option('handling_fee', 0);

            $this->domesticRates = new DomesticRates($this->client);
            $this->domesticRates
                ->setRateAdjustment($rateAdjustment)
                ->setHandlingFee($handlingFee);

            $this->internationalRates = new InternationalRates($this->client);
            $this->internationalRates
                ->setRateAdjustment($rateAdjustment)
                ->setHandlingFee($handlingFee);
        } catch (\Exception $e) {
            error_log('USPS OAuth initialization error: ' . $e->getMessage());
        }
    }

    /**
     * Calculate shipping rates for package
     */
    public function calculate_shipping($package = [])
    {
        if (!isset($this->client)) {
            return;
        }

        try {
            $originZip = $this->get_origin_zip();
            $destZip = $package['destination']['postcode'];
            $destCountry = $package['destination']['country'];

            // Calculate package weight and dimensions
            $weight = $this->calculate_package_weight($package);
            $dimensions = $this->calculate_package_dimensions($package);

            // Determine if domestic or international
            $isDomestic = $destCountry === 'US';

            if ($isDomestic) {
                $rates = $this->domesticRates->getAllRates(
                    originZip: $originZip,
                    destinationZip: $destZip,
                    weightLbs: $weight,
                    length: $dimensions['length'],
                    width: $dimensions['width'],
                    height: $dimensions['height']
                );
            } else {
                $rates = $this->internationalRates->getAllRates(
                    originZip: $originZip,
                    destinationCountry: $destCountry,
                    weightLbs: $weight,
                    length: $dimensions['length'],
                    width: $dimensions['width'],
                    height: $dimensions['height']
                );
            }

            // Add rates to WooCommerce
            foreach ($rates as $rate) {
                $this->add_rate([
                    'id' => $this->id . '_' . $rate->getService(),
                    'label' => $rate->getServiceLabel(),
                    'cost' => $rate->getTotalPrice(),
                    'meta_data' => [
                        'service' => $rate->getService(),
                        'base_price' => $rate->getBasePrice(),
                    ],
                ]);
            }
        } catch (UspsException $e) {
            error_log('USPS rate calculation error: ' . $e->getMessage());
        }
    }

    /**
     * Get origin ZIP code
     */
    private function get_origin_zip(): string
    {
        return get_option('woocommerce_store_postcode', '');
    }

    /**
     * Calculate package weight in pounds
     */
    private function calculate_package_weight($package): float
    {
        $weight = 0;

        foreach ($package['contents'] as $item) {
            $product = $item['data'];
            $itemWeight = (float) $product->get_weight();

            // Convert to pounds if needed
            $weightUnit = get_option('woocommerce_weight_unit');
            if ($weightUnit === 'kg') {
                $itemWeight = $itemWeight * 2.20462;
            } elseif ($weightUnit === 'g') {
                $itemWeight = $itemWeight * 0.00220462;
            } elseif ($weightUnit === 'oz') {
                $itemWeight = $itemWeight / 16;
            }

            $weight += $itemWeight * $item['quantity'];
        }

        return max(0.1, $weight);
    }

    /**
     * Calculate package dimensions in inches
     */
    private function calculate_package_dimensions($package): array
    {
        $length = 0;
        $width = 0;
        $height = 0;

        foreach ($package['contents'] as $item) {
            $product = $item['data'];
            $length = max($length, (float) $product->get_length());
            $width = max($width, (float) $product->get_width());
            $height = max($height, (float) $product->get_height());
        }

        // Use defaults if no dimensions
        if ($length === 0 || $width === 0 || $height === 0) {
            $length = 12;
            $width = 8;
            $height = 6;
        }

        // Convert to inches if needed
        $dimensionUnit = get_option('woocommerce_dimension_unit');
        if ($dimensionUnit === 'cm') {
            $length = $length * 0.393701;
            $width = $width * 0.393701;
            $height = $height * 0.393701;
        } elseif ($dimensionUnit === 'm') {
            $length = $length * 39.3701;
            $width = $width * 39.3701;
            $height = $height * 39.3701;
        }

        return [
            'length' => $length,
            'width' => $width,
            'height' => $height,
        ];
    }
}

/**
 * Register shipping method with WooCommerce
 */
function register_usps_oauth_shipping_method($methods)
{
    $methods['usps_oauth'] = 'WC_USPS_OAuth_Shipping_Method';
    return $methods;
}
add_filter('woocommerce_shipping_methods', 'register_usps_oauth_shipping_method');

/*
 * Note: This adapter requires:
 * 1. The USPS OAuth library installed via Composer in your WordPress plugin
 * 2. Composer autoloader included in your plugin's main file
 * 3. WooCommerce installed and activated
 * 
 * In your plugin's main file:
 * 
 * require_once __DIR__ . '/vendor/autoload.php';
 * require_once __DIR__ . '/includes/class-wc-usps-oauth-shipping.php';
 */
