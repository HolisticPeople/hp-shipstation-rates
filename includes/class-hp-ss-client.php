<?php
/**
 * ShipStation V1 API Client
 *
 * Handles communication with ShipStation V1 rates endpoint
 * Uses quick mode to prevent ghost orders
 *
 * @package HP_ShipStation_Rates
 * @since 1.0.0
 */

// If this file is called directly, abort.
if ( ! defined( 'WPINC' ) ) {
    die;
}

class HP_SS_Client {

    /**
     * ShipStation V1 API endpoint for rates
     */
    const API_ENDPOINT = 'https://ssapi.shipstation.com/shipments/getrates';

    /**
     * Get shipping rates from ShipStation V1 API
     *
     * @param array $from_address From address array
     * @param array $to_address To address array
     * @param array $package Package data (weight, dimensions)
     * @param string $carrier_code Carrier code ('stamps_com' for USPS, 'ups_walleted' for UPS)
     * @return array|WP_Error Array of rates or WP_Error on failure
     */
    public static function get_rates( $from_address, $to_address, $package, $carrier_code ) {
        // Get API credentials
        $settings = get_option( 'hp_ss_settings', array() );
        $api_key = isset( $settings['api_key'] ) ? $settings['api_key'] : '';
        $api_secret = isset( $settings['api_secret'] ) ? $settings['api_secret'] : '';
        $debug_enabled = isset( $settings['debug_enabled'] ) && $settings['debug_enabled'] === 'yes';

        if ( empty( $api_key ) || empty( $api_secret ) ) {
            return new WP_Error( 'missing_credentials', __( 'ShipStation API credentials not configured.', 'hp-shipstation-rates' ) );
        }

        // Build request body
        $request_body = array(
            'carrierCode' => $carrier_code,
            'serviceCode' => null,
            'packageCode' => 'package',
            'fromPostalCode' => $from_address['postcode'],
            'fromCity' => isset( $from_address['city'] ) ? $from_address['city'] : '',
            'fromState' => isset( $from_address['state'] ) ? $from_address['state'] : '',
            'fromCountry' => $from_address['country'],
            'toCountry' => $to_address['country'],
            'toPostalCode' => $to_address['postcode'],
            'toCity' => isset( $to_address['city'] ) ? $to_address['city'] : '',
            'toState' => isset( $to_address['state'] ) ? $to_address['state'] : '',
            'toStreet1' => isset( $to_address['address_1'] ) ? $to_address['address_1'] : '',
            'toStreet2' => isset( $to_address['address_2'] ) ? $to_address['address_2'] : '',
            'weight' => array(
                'value' => $package['weight'],
                'units' => 'pounds'
            ),
            'dimensions' => array(
                'units' => 'inches',
                'length' => $package['length'],
                'width' => $package['width'],
                'height' => $package['height']
            ),
            'confirmation' => 'none',
            'residential' => true,
            // Quick mode flags to prevent ghost orders
            'rate_options' => array(
                'rate_type' => 'quick'
            ),
            'rateOptions' => array(
                'rateType' => 'quick'
            )
        );

        // Build headers
        $headers = array(
            'Content-Type' => 'application/json',
            'Authorization' => 'Basic ' . base64_encode( $api_key . ':' . $api_secret )
        );

        // Log request if debug enabled
        if ( $debug_enabled ) {
            error_log( sprintf(
                '[HP SS V1] sending carrier=%s to_zip=%s to_country=%s weight=%s quick=true',
                $carrier_code,
                $to_address['postcode'],
                $to_address['country'],
                $package['weight']
            ) );
            error_log( '[HP SS V1] request body: ' . wp_json_encode( $request_body ) );
        }

        // Check transient cache
        $cache_key = self::get_cache_key( $to_address, $package, $carrier_code );
        $cached_rates = get_transient( $cache_key );
        if ( $cached_rates !== false && $debug_enabled ) {
            error_log( '[HP SS V1] returning cached rates for key: ' . $cache_key );
        }
        if ( $cached_rates !== false ) {
            return $cached_rates;
        }

        // Make API request
        $response = wp_remote_post( self::API_ENDPOINT, array(
            'headers' => $headers,
            'body' => wp_json_encode( $request_body ),
            'timeout' => 30
        ) );

        // Check for WP_Error
        if ( is_wp_error( $response ) ) {
            if ( $debug_enabled ) {
                error_log( '[HP SS V1] WP_Error: ' . $response->get_error_message() );
            }
            return $response;
        }

        // Get response code and body
        $response_code = wp_remote_retrieve_response_code( $response );
        $response_body = wp_remote_retrieve_body( $response );

        // Log response if debug enabled
        if ( $debug_enabled ) {
            error_log( sprintf(
                '[HP SS V1] response status=%d carrier=%s body_size=%d',
                $response_code,
                $carrier_code,
                strlen( $response_body )
            ) );
        }

        // Check response code
        if ( $response_code !== 200 ) {
            $error_message = sprintf( __( 'ShipStation API returned error %d', 'hp-shipstation-rates' ), $response_code );
            $decoded = json_decode( $response_body, true );
            if ( is_array( $decoded ) && isset( $decoded['message'] ) ) {
                $error_message .= ': ' . $decoded['message'];
            }
            if ( $debug_enabled ) {
                error_log( '[HP SS V1] error response: ' . $response_body );
            }
            return new WP_Error( 'api_error', $error_message );
        }

        // Decode response
        $rates = json_decode( $response_body, true );

        if ( ! is_array( $rates ) ) {
            if ( $debug_enabled ) {
                error_log( '[HP SS V1] failed to decode response: ' . $response_body );
            }
            return new WP_Error( 'decode_error', __( 'Failed to decode ShipStation response.', 'hp-shipstation-rates' ) );
        }

        // Log rates count
        if ( $debug_enabled ) {
            error_log( sprintf(
                '[HP SS V1] response status=%d carrier=%s rates_count=%d',
                $response_code,
                $carrier_code,
                count( $rates )
            ) );
        }

        // Cache the rates for 90 seconds
        set_transient( $cache_key, $rates, 90 );

        return $rates;
    }

    /**
     * Generate cache key for rates
     *
     * @param array $to_address Destination address
     * @param array $package Package data
     * @param string $carrier_code Carrier code
     * @return string Cache key
     */
    private static function get_cache_key( $to_address, $package, $carrier_code ) {
        $key_data = array(
            'to_postcode' => $to_address['postcode'],
            'to_country' => $to_address['country'],
            'weight' => $package['weight'],
            'carrier' => $carrier_code
        );
        return 'hp_ss_rates_' . md5( wp_json_encode( $key_data ) );
    }

    /**
     * Test ShipStation API credentials
     *
     * @param string $api_key API Key
     * @param string $api_secret API Secret
     * @return array Result array with 'success' and 'message' keys
     */
    public static function test_credentials( $api_key, $api_secret ) {
        if ( empty( $api_key ) || empty( $api_secret ) ) {
            return array(
                'success' => false,
                'message' => __( 'API Key and Secret are required.', 'hp-shipstation-rates' )
            );
        }

        // Test endpoint: list carriers
        $test_url = 'https://ssapi.shipstation.com/carriers';

        $headers = array(
            'Content-Type' => 'application/json',
            'Authorization' => 'Basic ' . base64_encode( $api_key . ':' . $api_secret )
        );

        $response = wp_remote_get( $test_url, array(
            'headers' => $headers,
            'timeout' => 15
        ) );

        if ( is_wp_error( $response ) ) {
            return array(
                'success' => false,
                'message' => sprintf( __( 'Connection failed: %s', 'hp-shipstation-rates' ), $response->get_error_message() )
            );
        }

        $response_code = wp_remote_retrieve_response_code( $response );

        if ( $response_code === 200 ) {
            $body = wp_remote_retrieve_body( $response );
            $carriers = json_decode( $body, true );
            $carriers_count = is_array( $carriers ) ? count( $carriers ) : 0;
            
            return array(
                'success' => true,
                'message' => sprintf( __( 'Connection successful! Found %d carriers.', 'hp-shipstation-rates' ), $carriers_count )
            );
        } elseif ( $response_code === 401 ) {
            return array(
                'success' => false,
                'message' => __( 'Authentication failed. Please check your API Key and Secret.', 'hp-shipstation-rates' )
            );
        } else {
            return array(
                'success' => false,
                'message' => sprintf( __( 'API returned error %d', 'hp-shipstation-rates' ), $response_code )
            );
        }
    }
}

