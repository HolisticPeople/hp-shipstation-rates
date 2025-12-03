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
     * Modify rates to add carrier badges
     */
    public static function init_hooks() {
        add_filter( 'woocommerce_package_rates', array( __CLASS__, 'add_carrier_marker' ), 10, 2 );
        add_action( 'wp_footer', array( __CLASS__, 'add_badge_script' ), 999 );
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

        // Smart session cache: Only recalculate when ZIP code or cart actually changes
        // Build minimal hashes for efficient comparison
        
        // Cart hash: Only product IDs and quantities (ignore other package data)
        $cart_items = array();
        foreach ( $package['contents'] as $item ) {
            $cart_items[] = array(
                'id' => isset( $item['product_id'] ) ? $item['product_id'] : 0,
                'qty' => isset( $item['quantity'] ) ? $item['quantity'] : 1
            );
        }
        $cart_hash = md5( wp_json_encode( $cart_items ) );
        
        // Destination hash: ONLY zip code and country (street/city/state don't affect rates)
        $dest_key = array(
            'zip' => isset( $package['destination']['postcode'] ) ? $package['destination']['postcode'] : '',
            'country' => isset( $package['destination']['country'] ) ? $package['destination']['country'] : ''
        );
        $dest_hash = md5( wp_json_encode( $dest_key ) );
        
        $session_key = 'hp_ss_session_' . $dest_hash . '_' . $cart_hash;
        $rates_cache_key = 'hp_ss_rates_cache_' . $dest_hash . '_' . $cart_hash;
        
        // Check if we have cached rates for this exact ZIP + cart combination
        $cached_rates = get_transient( $rates_cache_key );
        
        if ( $cached_rates !== false && is_array( $cached_rates ) ) {
            if ( $debug_enabled ) {
                error_log( '[HP SS Method] Using cached rates - found ' . count( $cached_rates ) . ' rates (ZIP: ' . $dest_key['zip'] . ', cart unchanged)' );
            }
            
            // Add cached rates directly to WooCommerce
            foreach ( $cached_rates as $rate ) {
                $this->add_rate( $rate );
            }
            
            // Update session timestamp to prevent recalculation
            set_transient( $session_key, time(), 120 );
            return;
        }
        
        // Mark that we're calculating now (prevent concurrent calculations)
        $calc_lock = get_transient( $session_key );
        if ( $calc_lock !== false ) {
            $seconds_ago = time() - $calc_lock;
            if ( $seconds_ago < 10 ) {
                error_log( '[HP SS Method] CALCULATION IN PROGRESS - skipping (started ' . $seconds_ago . ' seconds ago)' );
                return;
            }
        }
        
        set_transient( $session_key, time(), 120 );

        $start_time = microtime( true );
        
        if ( $debug_enabled ) {
            error_log( '[HP SS Method] ===== CALCULATE_SHIPPING START =====' );
            error_log( '[HP SS Method] Package items: ' . count( $package['contents'] ) );
            error_log( '[HP SS Method] Full package data: ' . print_r( $package, true ) );
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

        // Get service configuration (new format with custom names)
        $service_config = isset( $settings['service_config'] ) && is_array( $settings['service_config'] ) ? $settings['service_config'] : array();
        
        // Legacy support: get old format services
        $usps_services_legacy = isset( $settings['usps_services'] ) && is_array( $settings['usps_services'] ) ? $settings['usps_services'] : array();
        $ups_services_legacy = isset( $settings['ups_services'] ) && is_array( $settings['ups_services'] ) ? $settings['ups_services'] : array();
        
        // Check if carriers are disabled for performance
        $disable_usps = isset( $settings['disable_usps'] ) && $settings['disable_usps'] === 'yes';
        $disable_ups = isset( $settings['disable_ups'] ) && $settings['disable_ups'] === 'yes';

        $all_rates = array();

        // Get USPS rates if any USPS services are enabled and not disabled
        $has_usps_services = ! empty( $service_config ) || ! empty( $usps_services_legacy );
        if ( $has_usps_services && ! $disable_usps ) {
            $usps_rates = HP_SS_Client::get_rates( $from_address, $to_address, $package_data, 'stamps_com' );
            if ( ! is_wp_error( $usps_rates ) && is_array( $usps_rates ) ) {
                $all_rates = array_merge( $all_rates, $this->filter_rates( $usps_rates, $service_config, 'USPS', $usps_services_legacy ) );
            } elseif ( is_wp_error( $usps_rates ) && $debug_enabled ) {
                error_log( '[HP SS Method] USPS rates error: ' . $usps_rates->get_error_message() );
            }
        }

        // Get UPS rates if any UPS services are enabled and not disabled
        $has_ups_services = ! empty( $service_config ) || ! empty( $ups_services_legacy );
        if ( $has_ups_services && ! $disable_ups ) {
            $ups_rates = HP_SS_Client::get_rates( $from_address, $to_address, $package_data, 'ups_walleted' );
            if ( ! is_wp_error( $ups_rates ) && is_array( $ups_rates ) ) {
                $all_rates = array_merge( $all_rates, $this->filter_rates( $ups_rates, $service_config, 'UPS', $ups_services_legacy ) );
            } elseif ( is_wp_error( $ups_rates ) && $debug_enabled ) {
                error_log( '[HP SS Method] UPS rates error: ' . $ups_rates->get_error_message() );
            }
        }

        // Sort rates by price (lowest first) before adding to WooCommerce
        if ( ! empty( $all_rates ) ) {
            usort( $all_rates, function( $a, $b ) {
                return $a['cost'] <=> $b['cost'];
            } );
            
            // Store rates in cache for reuse (2 minutes)
            set_transient( $rates_cache_key, $all_rates, 120 );
            
            foreach ( $all_rates as $rate ) {
                if ( $debug_enabled ) {
                    error_log( '[HP SS Method] Adding rate: ' . print_r( $rate, true ) );
                }
                $this->add_rate( $rate );
            }
            
            if ( $debug_enabled ) {
                error_log( '[HP SS Method] Added ' . count( $all_rates ) . ' rates to checkout (sorted by price, cached for reuse)' );
            }
        } else {
            if ( $debug_enabled ) {
                error_log( '[HP SS Method] No rates returned from ShipStation' );
            }
        }

        if ( $debug_enabled ) {
            $end_time = microtime( true );
            $duration = round( ( $end_time - $start_time ) * 1000, 2 );
            error_log( '[HP SS Method] ===== CALCULATE_SHIPPING END (took ' . $duration . 'ms) =====' );
        }
    }

    /**
     * Filter rates based on service configuration
     *
     * @param array $rates Array of rates from ShipStation
     * @param array $service_config Service configuration with enabled status and custom names
     * @param string $carrier_name Carrier name for display
     * @param array $legacy_services Legacy format (array of service codes) for backward compatibility
     * @return array Filtered rates in WooCommerce format
     */
    private function filter_rates( $rates, $service_config, $carrier_name, $legacy_services = array() ) {
        $filtered_rates = array();
        $settings = get_option( 'hp_ss_settings', array() );
        $debug_enabled = isset( $settings['debug_enabled'] ) && $settings['debug_enabled'] === 'yes';

        foreach ( $rates as $rate ) {
            if ( ! isset( $rate['serviceCode'] ) || ! isset( $rate['serviceName'] ) || ! isset( $rate['shipmentCost'] ) ) {
                continue;
            }

            $service_code = $rate['serviceCode'];
            $service_name = $rate['serviceName'];
            // ShipStation returns the base postage in shipmentCost and any surcharges
            // (fuel, remote area, additional handling, etc.) in otherCost.
            // To quote what ShipStation will actually bill, we need to add both.
            $shipment_cost = floatval( $rate['shipmentCost'] );
            $other_cost    = isset( $rate['otherCost'] ) ? floatval( $rate['otherCost'] ) : 0.0;
            $cost          = $shipment_cost + $other_cost;

            // Check new format first (service_config)
            $is_enabled = false;
            $custom_name = '';
            
            if ( isset( $service_config[ $service_code ] ) ) {
                $is_enabled = $service_config[ $service_code ]['enabled'];
                $custom_name = $service_config[ $service_code ]['name'];
            } elseif ( ! empty( $legacy_services ) && in_array( $service_code, $legacy_services, true ) ) {
                // Fall back to legacy format
                $is_enabled = true;
            }

            if ( $is_enabled ) {
                // Use custom name if provided, otherwise use ShipStation's name (which already includes carrier)
                $display_name = ! empty( $custom_name ) ? $custom_name : $service_name;
                
                $filtered_rates[] = array(
                    'id' => 'hp_ss_' . sanitize_title( $service_code ),
                    'label' => $display_name,
                    'cost' => $cost,
                    'meta_data' => array(
                        'carrier' => $carrier_name,
                        'service_code' => $service_code,
                        'original_name' => $service_name,
                        // Store breakdown for debugging / future display if needed.
                        'shipment_cost' => $shipment_cost,
                        'other_cost' => $other_cost,
                    )
                );
                if ( $debug_enabled ) {
                    error_log( '[HP SS Method] ✓ ACCEPTED: ' . $service_code . ' (' . $service_name . ') - $' . $cost . ' - Display: ' . $display_name );
                }
            } else {
                // Log rejected services to help identify available service codes
                error_log( '[HP SS Method] ✗ REJECTED: ' . $service_code . ' (' . $service_name . ') - $' . $cost . ' - not enabled' );
            }
        }

        return $filtered_rates;
    }

    /**
     * Add carrier marker to rate labels
     *
     * @param array $rates Array of shipping rates
     * @param array $package Package data
     * @return array Modified rates
     */
    public static function add_carrier_marker( $rates, $package ) {
        // Check if badges are enabled
        $settings = get_option( 'hp_ss_settings', array() );
        $show_badges = isset( $settings['show_badges'] ) ? $settings['show_badges'] === 'yes' : true; // Default to true
        
        if ( ! $show_badges ) {
            return $rates; // Don't add markers if badges are disabled
        }
        
        foreach ( $rates as $rate_key => $rate ) {
            // Only modify our shipping method's rates
            if ( strpos( $rate->get_method_id(), 'hp_shipstation' ) === false ) {
                continue;
            }
            
            // Get carrier from meta data
            $meta_data = $rate->get_meta_data();
            if ( isset( $meta_data['carrier'] ) ) {
                $carrier = strtoupper( $meta_data['carrier'] );
                $current_label = $rate->get_label();
                // Add a data marker that JavaScript can detect and replace
                $rate->set_label( '{{' . $carrier . '}} ' . $current_label );
            }
        }
        
        return $rates;
    }
    
    /**
     * Add JavaScript to convert markers to badge images
     */
    public static function add_badge_script() {
        if ( ! is_checkout() && ! is_cart() ) {
            return;
        }
        
        // Check if badges are enabled
        $settings = get_option( 'hp_ss_settings', array() );
        $show_badges = isset( $settings['show_badges'] ) && $settings['show_badges'] === 'yes';
        
        if ( ! $show_badges ) {
            return; // Badges disabled, don't output script
        }
        
        // Get badge URLs (custom or default)
        $usps_badge_url = isset( $settings['usps_badge'] ) && ! empty( $settings['usps_badge'] ) 
            ? $settings['usps_badge'] 
            : HP_SS_PLUGIN_URL . 'assets/usps-badge.png';
        
        $ups_badge_url = isset( $settings['ups_badge'] ) && ! empty( $settings['ups_badge'] ) 
            ? $settings['ups_badge'] 
            : HP_SS_PLUGIN_URL . 'assets/ups-badge.png';
        
        ?>
        <style type="text/css">
            /* Force left alignment for shipping method labels with badges */
            .woocommerce-shipping-methods li label,
            #shipping_method li label,
            [name="shipping_method"] label,
            .shipping-methods li label,
            label[for^="shipping_method"] {
                text-align: left !important;
                justify-content: space-between !important;
                display: flex !important;
                align-items: center !important;
            }
            .hp-ss-badge {
                flex-shrink: 0;
            }
            /* Keep price on the right */
            .woocommerce-shipping-methods li label .woocommerce-Price-amount,
            #shipping_method li label .woocommerce-Price-amount,
            [name="shipping_method"] label .woocommerce-Price-amount {
                margin-left: auto !important;
            }
        </style>
        <script type="text/javascript">
        (function($) {
            'use strict';
            
            if (typeof $ === 'undefined' || !$) {
                return; // jQuery not loaded, exit safely
            }
            
            function addCarrierBadges() {
                try {
                    // Try multiple selectors to find the shipping method labels
                    var selectors = [
                        '.woocommerce-shipping-methods li label',
                        '#shipping_method li label',
                        '[name="shipping_method"] label',
                        '.shipping-methods li label',
                        'label[for^="shipping_method"]'
                    ];
                    
                    selectors.forEach(function(selector) {
                        try {
                            $(selector).each(function() {
                                var $label = $(this);
                                var html = $label.html();
                                
                                if (!html || typeof html !== 'string') return;
                                
                                // Check for USPS marker and replace with actual badge image
                                if (html.indexOf('{{USPS}}') !== -1) {
                                    var badge = '<img src="<?php echo esc_url( $usps_badge_url ); ?>" alt="USPS" class="hp-ss-badge hp-ss-usps" style="display:inline-block;height:24px;width:auto;vertical-align:middle;margin-right:8px;" />';
                                    $label.html(html.replace(/\{\{USPS\}\}/g, badge));
                                }
                                // Check for UPS marker and replace with actual badge image
                                if (html.indexOf('{{UPS}}') !== -1) {
                                    var badge = '<img src="<?php echo esc_url( $ups_badge_url ); ?>" alt="UPS" class="hp-ss-badge hp-ss-ups" style="display:inline-block;height:24px;width:auto;vertical-align:middle;margin-right:8px;" />';
                                    $label.html(html.replace(/\{\{UPS\}\}/g, badge));
                                }
                            });
                        } catch (e) {
                            // Silently fail for individual selectors
                        }
                    });
                } catch (e) {
                    // Silently fail if there's any error
                }
            }
            
            // Run on page load with delay
            $(document).ready(function() {
                setTimeout(function() {
                    try { addCarrierBadges(); } catch(e) {}
                }, 500);
            });
            
            // Run after page fully loaded
            $(window).on('load', function() {
                setTimeout(function() {
                    try { addCarrierBadges(); } catch(e) {}
                }, 100);
            });
            
            // Run after AJAX updates
            $(document.body).on('updated_checkout updated_shipping_method update_checkout', function() {
                setTimeout(function() {
                    try { addCarrierBadges(); } catch(e) {}
                }, 200);
            });
            
            // For CheckoutWC specifically
            $(document).on('cfw-updated-checkout', function() {
                setTimeout(function() {
                    try { addCarrierBadges(); } catch(e) {}
                }, 200);
            });
        })(jQuery);
        </script>
        <?php
    }
}

