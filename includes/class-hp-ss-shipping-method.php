<?php
/**
 * WooCommerce Shipping Method
 *
 * Integrates with WooCommerce to provide ShipStation rates at checkout
 *
 * @package HP_ShipStation_Rates
 * @since 1.0.0
 */

// If this file is called directly, abort.
if ( ! defined( 'WPINC' ) ) {
    die;
}

class HP_SS_Shipping_Method extends WC_Shipping_Method {

    /**
     * Constructor
     *
     * @param int $instance_id Shipping zone instance ID
     */
    public function __construct( $instance_id = 0 ) {
        $this->id = 'hp_shipstation';
        $this->instance_id = absint( $instance_id );
        $this->method_title = __( 'HP ShipStation Rates', 'hp-shipstation-rates' );
        $this->method_description = __( 'Get real-time USPS and UPS shipping rates from ShipStation.', 'hp-shipstation-rates' );
        $this->supports = array(
            'shipping-zones',
            'instance-settings',
        );

        $this->init();
    }

    /**
     * Initialize settings
     */
    private function init() {
        // Load the settings
        $this->init_form_fields();
        $this->init_settings();

        // Define user set variables
        $this->enabled = $this->get_option( 'enabled' );
        $this->title = $this->get_option( 'title' );

        // Save settings
        add_action( 'woocommerce_update_options_shipping_' . $this->id, array( $this, 'process_admin_options' ) );
    }

    /**
     * Define settings form fields
     */
    public function init_form_fields() {
        $this->instance_form_fields = array(
            'enabled' => array(
                'title' => __( 'Enable/Disable', 'hp-shipstation-rates' ),
                'type' => 'checkbox',
                'label' => __( 'Enable this shipping method', 'hp-shipstation-rates' ),
                'default' => 'yes'
            ),
            'title' => array(
                'title' => __( 'Method Title', 'hp-shipstation-rates' ),
                'type' => 'text',
                'description' => __( 'This controls the title which the user sees during checkout.', 'hp-shipstation-rates' ),
                'default' => __( 'ShipStation Rates', 'hp-shipstation-rates' ),
                'desc_tip' => true
            ),
            'global_settings_link' => array(
                'title' => __( 'Global Settings', 'hp-shipstation-rates' ),
                'type' => 'title',
                'description' => sprintf(
                    __( 'Configure API credentials and service filters in <a href="%s">HP ShipStation Settings</a>.', 'hp-shipstation-rates' ),
                    admin_url( 'admin.php?page=hp-ss-settings' )
                )
            )
        );
    }

    /**
     * Calculate shipping rates
     *
     * @param array $package WooCommerce package data
     */
    public function calculate_shipping( $package = array() ) {
        // Get global settings
        $settings = get_option( 'hp_ss_settings', array() );
        $debug_enabled = isset( $settings['debug_enabled'] ) && $settings['debug_enabled'] === 'yes';

        if ( $debug_enabled ) {
            error_log( '[HP SS Method] calculate_shipping called for package with ' . count( $package['contents'] ) . ' items' );
        }

        // Check if credentials are configured
        $api_key = isset( $settings['api_key'] ) ? $settings['api_key'] : '';
        $api_secret = isset( $settings['api_secret'] ) ? $settings['api_secret'] : '';

        if ( empty( $api_key ) || empty( $api_secret ) ) {
            if ( $debug_enabled ) {
                error_log( '[HP SS Method] API credentials not configured' );
            }
            return;
        }

        // Build addresses and package data
        $from_address = HP_SS_Packager::get_from_address();
        $to_address = HP_SS_Packager::get_to_address( $package['destination'] );
        $package_data = HP_SS_Packager::build_package( $package['contents'] );

        if ( $debug_enabled ) {
            error_log( '[HP SS Method] Package data: ' . wp_json_encode( $package_data ) );
            error_log( '[HP SS Method] To address: ' . wp_json_encode( $to_address ) );
        }

        // Validate essential data
        if ( empty( $to_address['postcode'] ) || empty( $to_address['country'] ) ) {
            if ( $debug_enabled ) {
                error_log( '[HP SS Method] Missing destination postal code or country' );
            }
            return;
        }

        // Get allowed services from settings
        $usps_services = isset( $settings['usps_services'] ) && is_array( $settings['usps_services'] ) ? $settings['usps_services'] : array();
        $ups_services = isset( $settings['ups_services'] ) && is_array( $settings['ups_services'] ) ? $settings['ups_services'] : array();

        $all_rates = array();

        // Get USPS rates if any USPS services are enabled
        if ( ! empty( $usps_services ) ) {
            $usps_rates = HP_SS_Client::get_rates( $from_address, $to_address, $package_data, 'stamps_com' );
            if ( ! is_wp_error( $usps_rates ) && is_array( $usps_rates ) ) {
                $all_rates = array_merge( $all_rates, $this->filter_rates( $usps_rates, $usps_services, 'USPS' ) );
            } elseif ( is_wp_error( $usps_rates ) && $debug_enabled ) {
                error_log( '[HP SS Method] USPS rates error: ' . $usps_rates->get_error_message() );
            }
        }

        // Get UPS rates if any UPS services are enabled
        if ( ! empty( $ups_services ) ) {
            $ups_rates = HP_SS_Client::get_rates( $from_address, $to_address, $package_data, 'ups_walleted' );
            if ( ! is_wp_error( $ups_rates ) && is_array( $ups_rates ) ) {
                $all_rates = array_merge( $all_rates, $this->filter_rates( $ups_rates, $ups_services, 'UPS' ) );
            } elseif ( is_wp_error( $ups_rates ) && $debug_enabled ) {
                error_log( '[HP SS Method] UPS rates error: ' . $ups_rates->get_error_message() );
            }
        }

        // Add rates to WooCommerce
        if ( ! empty( $all_rates ) ) {
            foreach ( $all_rates as $rate ) {
                $this->add_rate( $rate );
            }
            if ( $debug_enabled ) {
                error_log( '[HP SS Method] Added ' . count( $all_rates ) . ' rates to checkout' );
            }
        } elseif ( $debug_enabled ) {
            error_log( '[HP SS Method] No rates returned from ShipStation' );
        }
    }

    /**
     * Filter rates based on allowed services
     *
     * @param array $rates Array of rates from ShipStation
     * @param array $allowed_services Array of allowed service codes
     * @param string $carrier_name Carrier name for display
     * @return array Filtered rates in WooCommerce format
     */
    private function filter_rates( $rates, $allowed_services, $carrier_name ) {
        $filtered_rates = array();

        foreach ( $rates as $rate ) {
            if ( ! isset( $rate['serviceCode'] ) || ! isset( $rate['serviceName'] ) || ! isset( $rate['shipmentCost'] ) ) {
                continue;
            }

            $service_code = $rate['serviceCode'];
            $service_name = $rate['serviceName'];
            $cost = floatval( $rate['shipmentCost'] );

            // Check if this service is in the allowed list
            if ( in_array( $service_code, $allowed_services, true ) ) {
                $filtered_rates[] = array(
                    'id' => 'hp_ss_' . sanitize_title( $service_code ),
                    'label' => $carrier_name . ' ' . $service_name,
                    'cost' => $cost,
                    'meta_data' => array(
                        'carrier' => $carrier_name,
                        'service_code' => $service_code
                    )
                );
            }
        }

        return $filtered_rates;
    }
}

