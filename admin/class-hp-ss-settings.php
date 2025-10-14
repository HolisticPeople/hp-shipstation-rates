<?php
/**
 * Admin Settings Page
 *
 * Handles the plugin settings interface
 *
 * @package HP_ShipStation_Rates
 * @since 1.0.0
 */

// If this file is called directly, abort.
if ( ! defined( 'WPINC' ) ) {
    die;
}

class HP_SS_Settings {

    /**
     * Initialize the settings
     */
    public static function init() {
        add_action( 'admin_menu', array( __CLASS__, 'add_settings_page' ) );
        add_action( 'admin_init', array( __CLASS__, 'register_settings' ) );
        add_action( 'wp_ajax_hp_ss_test_connection', array( __CLASS__, 'ajax_test_connection' ) );
        add_action( 'admin_enqueue_scripts', array( __CLASS__, 'enqueue_admin_scripts' ) );
    }

    /**
     * Add settings page to admin menu
     */
    public static function add_settings_page() {
        add_submenu_page(
            'woocommerce',
            __( 'HP ShipStation Settings', 'hp-shipstation-rates' ),
            __( 'ShipStation Rates', 'hp-shipstation-rates' ),
            'manage_woocommerce',
            'hp-ss-settings',
            array( __CLASS__, 'render_settings_page' )
        );
    }

    /**
     * Register settings
     */
    public static function register_settings() {
        register_setting( 'hp_ss_settings_group', 'hp_ss_settings', array( __CLASS__, 'sanitize_settings' ) );
    }

    /**
     * Sanitize settings before saving
     *
     * @param array $input Raw input data
     * @return array Sanitized data
     */
    public static function sanitize_settings( $input ) {
        $sanitized = array();

        // API credentials
        $sanitized['api_key'] = isset( $input['api_key'] ) ? sanitize_text_field( $input['api_key'] ) : '';
        $sanitized['api_secret'] = isset( $input['api_secret'] ) ? sanitize_text_field( $input['api_secret'] ) : '';

        // USPS services
        $sanitized['usps_services'] = isset( $input['usps_services'] ) && is_array( $input['usps_services'] ) ? array_map( 'sanitize_text_field', $input['usps_services'] ) : array();

        // UPS services
        $sanitized['ups_services'] = isset( $input['ups_services'] ) && is_array( $input['ups_services'] ) ? array_map( 'sanitize_text_field', $input['ups_services'] ) : array();

        // Default package dimensions
        $sanitized['default_length'] = isset( $input['default_length'] ) && is_numeric( $input['default_length'] ) ? floatval( $input['default_length'] ) : 12;
        $sanitized['default_width'] = isset( $input['default_width'] ) && is_numeric( $input['default_width'] ) ? floatval( $input['default_width'] ) : 12;
        $sanitized['default_height'] = isset( $input['default_height'] ) && is_numeric( $input['default_height'] ) ? floatval( $input['default_height'] ) : 12;
        $sanitized['default_weight'] = isset( $input['default_weight'] ) && is_numeric( $input['default_weight'] ) ? floatval( $input['default_weight'] ) : 1;

        // Debug toggle
        $sanitized['debug_enabled'] = isset( $input['debug_enabled'] ) ? 'yes' : 'no';

        return $sanitized;
    }

    /**
     * Render settings page
     */
    public static function render_settings_page() {
        $settings = get_option( 'hp_ss_settings', array() );
        $api_key = isset( $settings['api_key'] ) ? $settings['api_key'] : '';
        $api_secret = isset( $settings['api_secret'] ) ? $settings['api_secret'] : '';
        $usps_services = isset( $settings['usps_services'] ) ? $settings['usps_services'] : array();
        $ups_services = isset( $settings['ups_services'] ) ? $settings['ups_services'] : array();
        $default_length = isset( $settings['default_length'] ) ? $settings['default_length'] : 12;
        $default_width = isset( $settings['default_width'] ) ? $settings['default_width'] : 12;
        $default_height = isset( $settings['default_height'] ) ? $settings['default_height'] : 12;
        $default_weight = isset( $settings['default_weight'] ) ? $settings['default_weight'] : 1;
        $debug_enabled = isset( $settings['debug_enabled'] ) && $settings['debug_enabled'] === 'yes';

        // Define available services
        $usps_available_services = array(
            'usps_priority_mail' => 'Priority Mail',
            'usps_first_class_mail' => 'First Class Mail',
            'usps_priority_mail_express' => 'Priority Mail Express',
            'usps_media_mail' => 'Media Mail',
            'usps_parcel_select' => 'Parcel Select Ground'
        );

        $ups_available_services = array(
            'ups_ground' => 'Ground',
            'ups_3_day_select' => '3 Day Select',
            'ups_2nd_day_air' => '2nd Day Air',
            'ups_2nd_day_air_am' => '2nd Day Air AM',
            'ups_next_day_air_saver' => 'Next Day Air Saver',
            'ups_next_day_air' => 'Next Day Air',
            'ups_next_day_air_early_am' => 'Next Day Air Early AM'
        );

        ?>
        <div class="wrap">
            <h1><?php esc_html_e( 'HP ShipStation Rates Settings', 'hp-shipstation-rates' ); ?></h1>
            
            <form method="post" action="options.php">
                <?php settings_fields( 'hp_ss_settings_group' ); ?>
                
                <table class="form-table" role="presentation">
                    <!-- API Credentials Section -->
                    <tr>
                        <th colspan="2">
                            <h2><?php esc_html_e( 'API Credentials', 'hp-shipstation-rates' ); ?></h2>
                        </th>
                    </tr>
                    
                    <tr>
                        <th scope="row">
                            <label for="hp_ss_api_key"><?php esc_html_e( 'API Key', 'hp-shipstation-rates' ); ?></label>
                        </th>
                        <td>
                            <input type="text" id="hp_ss_api_key" name="hp_ss_settings[api_key]" value="<?php echo esc_attr( $api_key ); ?>" class="regular-text" />
                            <p class="description"><?php esc_html_e( 'Your ShipStation API Key', 'hp-shipstation-rates' ); ?></p>
                        </td>
                    </tr>
                    
                    <tr>
                        <th scope="row">
                            <label for="hp_ss_api_secret"><?php esc_html_e( 'API Secret', 'hp-shipstation-rates' ); ?></label>
                        </th>
                        <td>
                            <input type="password" id="hp_ss_api_secret" name="hp_ss_settings[api_secret]" value="<?php echo esc_attr( $api_secret ); ?>" class="regular-text" />
                            <p class="description"><?php esc_html_e( 'Your ShipStation API Secret', 'hp-shipstation-rates' ); ?></p>
                        </td>
                    </tr>
                    
                    <tr>
                        <th scope="row"></th>
                        <td>
                            <button type="button" id="hp_ss_test_connection" class="button button-secondary">
                                <?php esc_html_e( 'Test Connection', 'hp-shipstation-rates' ); ?>
                            </button>
                            <span id="hp_ss_test_result" style="margin-left: 10px;"></span>
                        </td>
                    </tr>

                    <!-- USPS Services Section -->
                    <tr>
                        <th colspan="2">
                            <h2><?php esc_html_e( 'USPS Services', 'hp-shipstation-rates' ); ?></h2>
                        </th>
                    </tr>
                    
                    <tr>
                        <th scope="row"><?php esc_html_e( 'Enable USPS Services', 'hp-shipstation-rates' ); ?></th>
                        <td>
                            <fieldset>
                                <?php foreach ( $usps_available_services as $code => $label ) : ?>
                                    <label>
                                        <input type="checkbox" name="hp_ss_settings[usps_services][]" value="<?php echo esc_attr( $code ); ?>" <?php checked( in_array( $code, $usps_services, true ) ); ?> />
                                        <?php echo esc_html( $label ); ?>
                                    </label><br/>
                                <?php endforeach; ?>
                            </fieldset>
                            <p class="description"><?php esc_html_e( 'Select which USPS services to display at checkout', 'hp-shipstation-rates' ); ?></p>
                        </td>
                    </tr>

                    <!-- UPS Services Section -->
                    <tr>
                        <th colspan="2">
                            <h2><?php esc_html_e( 'UPS Services', 'hp-shipstation-rates' ); ?></h2>
                        </th>
                    </tr>
                    
                    <tr>
                        <th scope="row"><?php esc_html_e( 'Enable UPS Services', 'hp-shipstation-rates' ); ?></th>
                        <td>
                            <fieldset>
                                <?php foreach ( $ups_available_services as $code => $label ) : ?>
                                    <label>
                                        <input type="checkbox" name="hp_ss_settings[ups_services][]" value="<?php echo esc_attr( $code ); ?>" <?php checked( in_array( $code, $ups_services, true ) ); ?> />
                                        <?php echo esc_html( $label ); ?>
                                    </label><br/>
                                <?php endforeach; ?>
                            </fieldset>
                            <p class="description"><?php esc_html_e( 'Select which UPS services to display at checkout', 'hp-shipstation-rates' ); ?></p>
                        </td>
                    </tr>

                    <!-- Default Package Settings Section -->
                    <tr>
                        <th colspan="2">
                            <h2><?php esc_html_e( 'Default Package Settings', 'hp-shipstation-rates' ); ?></h2>
                            <p class="description"><?php esc_html_e( 'Used when products do not have dimensions or weight set', 'hp-shipstation-rates' ); ?></p>
                        </th>
                    </tr>
                    
                    <tr>
                        <th scope="row"><?php esc_html_e( 'Default Dimensions (inches)', 'hp-shipstation-rates' ); ?></th>
                        <td>
                            <input type="number" name="hp_ss_settings[default_length]" value="<?php echo esc_attr( $default_length ); ?>" step="0.01" min="1" style="width: 80px;" /> × 
                            <input type="number" name="hp_ss_settings[default_width]" value="<?php echo esc_attr( $default_width ); ?>" step="0.01" min="1" style="width: 80px;" /> × 
                            <input type="number" name="hp_ss_settings[default_height]" value="<?php echo esc_attr( $default_height ); ?>" step="0.01" min="1" style="width: 80px;" />
                            <p class="description"><?php esc_html_e( 'Length × Width × Height', 'hp-shipstation-rates' ); ?></p>
                        </td>
                    </tr>
                    
                    <tr>
                        <th scope="row">
                            <label for="hp_ss_default_weight"><?php esc_html_e( 'Default Weight (lbs)', 'hp-shipstation-rates' ); ?></label>
                        </th>
                        <td>
                            <input type="number" id="hp_ss_default_weight" name="hp_ss_settings[default_weight]" value="<?php echo esc_attr( $default_weight ); ?>" step="0.01" min="0.1" style="width: 80px;" />
                        </td>
                    </tr>

                    <!-- Debug Settings Section -->
                    <tr>
                        <th colspan="2">
                            <h2><?php esc_html_e( 'Debug Settings', 'hp-shipstation-rates' ); ?></h2>
                        </th>
                    </tr>
                    
                    <tr>
                        <th scope="row"><?php esc_html_e( 'Debug Logging', 'hp-shipstation-rates' ); ?></th>
                        <td>
                            <label>
                                <input type="checkbox" name="hp_ss_settings[debug_enabled]" value="1" <?php checked( $debug_enabled ); ?> />
                                <?php esc_html_e( 'Enable debug logging', 'hp-shipstation-rates' ); ?>
                            </label>
                            <p class="description">
                                <?php 
                                printf(
                                    esc_html__( 'Log API requests and responses to %s', 'hp-shipstation-rates' ),
                                    '<code>wp-content/debug.log</code>'
                                ); 
                                ?>
                            </p>
                        </td>
                    </tr>
                </table>

                <?php submit_button(); ?>
            </form>
        </div>
        <?php
    }

    /**
     * AJAX handler for testing API connection
     */
    public static function ajax_test_connection() {
        check_ajax_referer( 'hp-ss-test-connection', 'nonce' );

        if ( ! current_user_can( 'manage_woocommerce' ) ) {
            wp_send_json_error( array( 'message' => __( 'Insufficient permissions', 'hp-shipstation-rates' ) ) );
        }

        $api_key = isset( $_POST['api_key'] ) ? sanitize_text_field( $_POST['api_key'] ) : '';
        $api_secret = isset( $_POST['api_secret'] ) ? sanitize_text_field( $_POST['api_secret'] ) : '';

        $result = HP_SS_Client::test_credentials( $api_key, $api_secret );

        if ( $result['success'] ) {
            wp_send_json_success( $result );
        } else {
            wp_send_json_error( $result );
        }
    }

    /**
     * Enqueue admin scripts
     */
    public static function enqueue_admin_scripts( $hook ) {
        if ( $hook !== 'woocommerce_page_hp-ss-settings' ) {
            return;
        }

        wp_enqueue_script( 'hp-ss-admin', HP_SS_PLUGIN_URL . 'admin/hp-ss-admin.js', array( 'jquery' ), HP_SS_VERSION, true );
        wp_localize_script( 'hp-ss-admin', 'hpSsAdmin', array(
            'ajax_url' => admin_url( 'admin-ajax.php' ),
            'nonce' => wp_create_nonce( 'hp-ss-test-connection' ),
            'testing_text' => __( 'Testing...', 'hp-shipstation-rates' ),
            'test_connection_text' => __( 'Test Connection', 'hp-shipstation-rates' )
        ) );
    }
}

