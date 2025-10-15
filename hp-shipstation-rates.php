<?php
/**
 * Plugin Name: HP ShipStation Rates
 * Plugin URI: https://holisticpeople.com/
 * Description: Minimal WooCommerce shipping method that fetches real-time USPS and UPS quotes from ShipStation V1 API (with quick mode to prevent ghost orders).
 * Version: 2.4.3
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
define( 'HP_SS_VERSION', '2.4.3' );
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

