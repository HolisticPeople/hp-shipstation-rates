<?php
/**
 * Package Builder and Unit Converter
 *
 * Handles converting WooCommerce cart contents into ShipStation-compatible package data
 * Converts weights to pounds and dimensions to inches
 *
 * @package HP_ShipStation_Rates
 * @since 1.0.0
 */

// If this file is called directly, abort.
if ( ! defined( 'WPINC' ) ) {
    die;
}

class HP_SS_Packager {

    /**
     * Build package data from WooCommerce cart contents
     *
     * @param array $contents WooCommerce package contents
     * @return array Package data with weight and dimensions in ShipStation units (pounds, inches)
     */
    public static function build_package( $contents ) {
        $settings = get_option( 'hp_ss_settings', array() );
        
        // Default dimensions from settings or fallback
        $default_length = isset( $settings['default_length'] ) && $settings['default_length'] > 0 ? floatval( $settings['default_length'] ) : 12;
        $default_width = isset( $settings['default_width'] ) && $settings['default_width'] > 0 ? floatval( $settings['default_width'] ) : 12;
        $default_height = isset( $settings['default_height'] ) && $settings['default_height'] > 0 ? floatval( $settings['default_height'] ) : 12;
        $default_weight = isset( $settings['default_weight'] ) && $settings['default_weight'] > 0 ? floatval( $settings['default_weight'] ) : 1;

        $total_weight = 0;
        $max_length = 0;
        $max_width = 0;
        $max_height = 0;
        $has_dimensions = false;

        // Loop through cart items
        foreach ( $contents as $item ) {
            if ( ! isset( $item['data'] ) ) {
                continue;
            }

            $product = $item['data'];
            $quantity = isset( $item['quantity'] ) ? intval( $item['quantity'] ) : 1;

            // Skip if product doesn't need shipping
            if ( ! $product->needs_shipping() ) {
                continue;
            }

            // Get product weight
            $product_weight = $product->get_weight();
            if ( $product_weight !== '' && is_numeric( $product_weight ) && $product_weight > 0 ) {
                // Convert to pounds
                $weight_in_lbs = wc_get_weight( floatval( $product_weight ), 'lbs' );
                $total_weight += $weight_in_lbs * $quantity;
            }

            // Get product dimensions
            $length = $product->get_length();
            $width = $product->get_width();
            $height = $product->get_height();

            if ( $length !== '' && is_numeric( $length ) && $length > 0 &&
                 $width !== '' && is_numeric( $width ) && $width > 0 &&
                 $height !== '' && is_numeric( $height ) && $height > 0 ) {
                
                $has_dimensions = true;
                
                // Convert to inches and track maximum dimensions
                $length_in_inches = wc_get_dimension( floatval( $length ), 'in' );
                $width_in_inches = wc_get_dimension( floatval( $width ), 'in' );
                $height_in_inches = wc_get_dimension( floatval( $height ), 'in' );

                $max_length = max( $max_length, $length_in_inches );
                $max_width = max( $max_width, $width_in_inches );
                $max_height = max( $max_height, $height_in_inches );
            }
        }

        // Use defaults if no weight/dimensions found
        if ( $total_weight <= 0 ) {
            $total_weight = $default_weight;
        }

        if ( ! $has_dimensions ) {
            $max_length = $default_length;
            $max_width = $default_width;
            $max_height = $default_height;
        }

        // Round to 2 decimal places and ensure minimum of 1
        return array(
            'weight' => max( 0.1, round( $total_weight, 2 ) ),
            'length' => max( 1, round( $max_length, 2 ) ),
            'width' => max( 1, round( $max_width, 2 ) ),
            'height' => max( 1, round( $max_height, 2 ) )
        );
    }

    /**
     * Build from address from WooCommerce store settings
     *
     * @return array From address data
     */
    public static function get_from_address() {
        return array(
            'postcode' => get_option( 'woocommerce_store_postcode', '' ),
            'city' => get_option( 'woocommerce_store_city', '' ),
            'state' => WC()->countries && method_exists( WC()->countries, 'get_base_state' ) ? WC()->countries->get_base_state() : '',
            'country' => WC()->countries && method_exists( WC()->countries, 'get_base_country' ) ? WC()->countries->get_base_country() : 'US'
        );
    }

    /**
     * Build to address from WooCommerce package destination
     *
     * @param array $destination WooCommerce package destination
     * @return array To address data
     */
    public static function get_to_address( $destination ) {
        return array(
            'postcode' => isset( $destination['postcode'] ) ? $destination['postcode'] : '',
            'city' => isset( $destination['city'] ) ? $destination['city'] : '',
            'state' => isset( $destination['state'] ) ? $destination['state'] : '',
            'country' => isset( $destination['country'] ) ? $destination['country'] : '',
            'address_1' => isset( $destination['address'] ) ? $destination['address'] : '',
            'address_2' => isset( $destination['address_2'] ) ? $destination['address_2'] : ''
        );
    }
}

