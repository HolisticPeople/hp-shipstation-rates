<?php
/**
 * Plugin Name: HP ShipStation Rates
 * Plugin URI: https://holisticpeople.com/
 * Description: Minimal WooCommerce shipping method that fetches real-time USPS and UPS quotes from ShipStation V1 API (with quick mode to prevent ghost orders).
 * Version: 2.4.6
 * Author: Holistic People
 * Author URI: https://holisticpeople.com/
 * Text Domain: hp-shipstation-rates
 * Domain Path: /languages
 * Requires at least: 5.8
 * Requires PHP: 7.4
 * WC requires at least: 6.0
 * WC tested up to: 8.5
 * License: GPL v2 or later
 * License URI: https://www.gnu.org/licenses/gpl-2.0.html
 */

// If this file is called directly, abort.
if ( ! defined( 'WPINC' ) ) {
    die;
}

// Define plugin constants
define( 'HP_SS_VERSION', '2.4.6' );
define( 'HP_SS_PLUGIN_FILE', __FILE__ );
define( 'HP_SS_PLUGIN_DIR', plugin_dir_path( __FILE__ ) );
define( 'HP_SS_PLUGIN_URL', plugin_dir_url( __FILE__ ) );
define( 'HP_SS_PLUGIN_BASENAME', plugin_basename( __FILE__ ) );

/**
 * Check if WooCommerce is active
 */
function hp_ss_is_woocommerce_active() {
    return in_array( 'woocommerce/woocommerce.php', apply_filters( 'active_plugins', get_option( 'active_plugins' ) ) );
}

/**
 * Admin notice if WooCommerce is not active
 */
function hp_ss_woocommerce_missing_notice() {
    ?>
    <div class="error">
        <p><?php esc_html_e( 'HP ShipStation Rates requires WooCommerce to be installed and active.', 'hp-shipstation-rates' ); ?></p>
    </div>
    <?php
}

/**
 * Initialize the plugin
 */
function hp_ss_init() {
    // Check if WooCommerce is active
    if ( ! hp_ss_is_woocommerce_active() ) {
        add_action( 'admin_notices', 'hp_ss_woocommerce_missing_notice' );
        return;
    }

    // Load plugin textdomain
    load_plugin_textdomain( 'hp-shipstation-rates', false, dirname( HP_SS_PLUGIN_BASENAME ) . '/languages' );

    // Include required files
    require_once HP_SS_PLUGIN_DIR . 'includes/class-hp-ss-client.php';
    require_once HP_SS_PLUGIN_DIR . 'includes/class-hp-ss-packager.php';
    require_once HP_SS_PLUGIN_DIR . 'includes/class-hp-ss-shipping-method.php';
    require_once HP_SS_PLUGIN_DIR . 'admin/class-hp-ss-settings.php';

    // Initialize settings page
    HP_SS_Settings::init();
}
add_action( 'plugins_loaded', 'hp_ss_init' );

/**
 * Register the shipping method with WooCommerce
 */
function hp_ss_register_shipping_method( $methods ) {
    $methods['hp_shipstation'] = 'HP_SS_Shipping_Method';
    return $methods;
}
add_filter( 'woocommerce_shipping_methods', 'hp_ss_register_shipping_method' );

/**
 * ShipStation export compatibility:
 * Exclude any WooCommerce order line that does not have a SKU from being
 * exported as a product to ShipStation.
 *
 * The official WooCommerce ShipStation integration exposes the
 * 'woocommerce_shipstation_export_line_item' filter. Returning false from
 * this filter will prevent a line from being exported.
 *
 * We use this to hide:
 * - Discount/upsell fee lines (which are WC_Order_Item_Fee entries)
 * - Any other non-product rows that do not have a SKU
 *
 * This makes the behavior future‑proof for additional funnels: as long as
 * funnel‑specific lines do not carry a SKU, they will not appear as separate
 * products in ShipStation. Only real products with SKUs will be exported.
 */
if ( ! function_exists( 'hp_ss_filter_shipstation_export_line_item' ) ) {
    function hp_ss_filter_shipstation_export_line_item( $line_item, $item, $order ) {
        // Product line: only export if the underlying product has a non-empty SKU.
        if ( $item instanceof WC_Order_Item_Product ) {
            $product = $item->get_product();
            if ( ! $product ) {
                return false;
            }
            $sku = trim( (string) $product->get_sku() );
            return $sku === '' ? false : $line_item;
        }

        // For all non-product lines (fees, shipping, etc.), skip export so they
        // don't appear as pseudo-products in ShipStation.
        return false;
    }
    add_filter( 'woocommerce_shipstation_export_line_item', 'hp_ss_filter_shipstation_export_line_item', 10, 3 );
}

// Initialize shipping method hooks
add_action( 'woocommerce_init', array( 'HP_SS_Shipping_Method', 'init_hooks' ) );

/**
 * Add settings link on plugin page
 */
function hp_ss_plugin_action_links( $links ) {
    $settings_link = '<a href="' . admin_url( 'admin.php?page=hp-ss-settings' ) . '">' . __( 'Settings', 'hp-shipstation-rates' ) . '</a>';
    array_unshift( $links, $settings_link );
    return $links;
}
add_filter( 'plugin_action_links_' . HP_SS_PLUGIN_BASENAME, 'hp_ss_plugin_action_links' );

/**
 * Helper: Return enabled ShipStation service codes as configured in this plugin.
 * - Single source of truth for other plugins (e.g., HP Funnel Bridge)
 * - Reads hp_ss_settings['service_config'] (preferred) where each entry is:
 *     [service_code] => ['enabled' => bool, 'name' => 'Custom Name']
 * - For legacy installs, also considers 'usps_services' and 'ups_services' arrays
 *
 * @return string[] Lowercase service codes, e.g. ['usps_priority_mail','usps_ground_advantage']
 */
function hp_ss_get_enabled_service_codes(): array {
    $settings = get_option( 'hp_ss_settings', array() );
    $codes = array();

    // Preferred: service_config structure
    if ( isset( $settings['service_config'] ) && is_array( $settings['service_config'] ) ) {
        foreach ( $settings['service_config'] as $service_code => $config ) {
            $enabled = isset( $config['enabled'] ) && ( $config['enabled'] === true || $config['enabled'] === 'yes' || $config['enabled'] === 1 || $config['enabled'] === '1' );
            if ( $enabled ) {
                $codes[] = strtolower( trim( (string) $service_code ) );
            }
        }
    }

    // Legacy (back-compat) – prior settings used flat arrays
    if ( empty( $codes ) ) {
        if ( ! empty( $settings['usps_services'] ) && is_array( $settings['usps_services'] ) ) {
            foreach ( $settings['usps_services'] as $code ) {
                $codes[] = strtolower( trim( (string) $code ) );
            }
        }
        if ( ! empty( $settings['ups_services'] ) && is_array( $settings['ups_services'] ) ) {
            foreach ( $settings['ups_services'] as $code ) {
                $codes[] = strtolower( trim( (string) $code ) );
            }
        }
    }

    // Normalize and return unique list
    $codes = array_values( array_unique( array_filter( $codes, function( $c ) { return $c !== ''; } ) ) );
    return $codes;
}

