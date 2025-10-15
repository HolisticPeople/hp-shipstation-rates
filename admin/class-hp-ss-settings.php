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
        add_action( 'wp_ajax_hp_ss_fetch_services', array( __CLASS__, 'ajax_fetch_services' ) );
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
        error_log( '[HP SS Settings] sanitize_settings called with input: ' . print_r( $input, true ) );
        $sanitized = array();

        // API credentials
        $sanitized['api_key'] = isset( $input['api_key'] ) ? sanitize_text_field( $input['api_key'] ) : '';
        $sanitized['api_secret'] = isset( $input['api_secret'] ) ? sanitize_text_field( $input['api_secret'] ) : '';

        // Service configurations (enabled services with custom display names)
        // Format: array( 'service_code' => array( 'enabled' => true, 'name' => 'Custom Name' ) )
        $sanitized['service_config'] = array();
        if ( isset( $input['service_config'] ) && is_array( $input['service_config'] ) ) {
            foreach ( $input['service_config'] as $service_code => $config ) {
                $sanitized['service_config'][ sanitize_text_field( $service_code ) ] = array(
                    'enabled' => isset( $config['enabled'] ) && $config['enabled'] === 'yes',
                    'name' => isset( $config['name'] ) ? sanitize_text_field( $config['name'] ) : ''
                );
            }
        }

        // Legacy support: keep old format for backward compatibility
        $sanitized['usps_services'] = isset( $input['usps_services'] ) && is_array( $input['usps_services'] ) ? array_map( 'sanitize_text_field', $input['usps_services'] ) : array();
        $sanitized['ups_services'] = isset( $input['ups_services'] ) && is_array( $input['ups_services'] ) ? array_map( 'sanitize_text_field', $input['ups_services'] ) : array();

        // Default package dimensions
        $sanitized['default_length'] = isset( $input['default_length'] ) && is_numeric( $input['default_length'] ) ? floatval( $input['default_length'] ) : 12;
        $sanitized['default_width'] = isset( $input['default_width'] ) && is_numeric( $input['default_width'] ) ? floatval( $input['default_width'] ) : 12;
        $sanitized['default_height'] = isset( $input['default_height'] ) && is_numeric( $input['default_height'] ) ? floatval( $input['default_height'] ) : 12;
        $sanitized['default_weight'] = isset( $input['default_weight'] ) && is_numeric( $input['default_weight'] ) ? floatval( $input['default_weight'] ) : 1;

        // Debug toggle
        $sanitized['debug_enabled'] = isset( $input['debug_enabled'] ) ? 'yes' : 'no';
        
        // Performance: disable carriers
        $sanitized['disable_usps'] = isset( $input['disable_usps'] ) ? 'yes' : 'no';
        $sanitized['disable_ups'] = isset( $input['disable_ups'] ) ? 'yes' : 'no';
        
        // Badge display settings
        $sanitized['show_badges'] = isset( $input['show_badges'] ) ? 'yes' : 'no';
        
        // Handle badge file uploads
        if ( ! empty( $_FILES['usps_badge']['name'] ) ) {
            $sanitized['usps_badge'] = self::handle_badge_upload( 'usps_badge' );
        } elseif ( isset( $sanitized['usps_badge'] ) ) {
            // Keep existing badge
            $old_settings = get_option( 'hp_ss_settings', array() );
            $sanitized['usps_badge'] = isset( $old_settings['usps_badge'] ) ? $old_settings['usps_badge'] : '';
        }
        
        if ( ! empty( $_FILES['ups_badge']['name'] ) ) {
            $sanitized['ups_badge'] = self::handle_badge_upload( 'ups_badge' );
        } elseif ( isset( $sanitized['ups_badge'] ) ) {
            // Keep existing badge
            $old_settings = get_option( 'hp_ss_settings', array() );
            $sanitized['ups_badge'] = isset( $old_settings['ups_badge'] ) ? $old_settings['ups_badge'] : '';
        }

        return $sanitized;
    }

    /**
     * Render settings page
     */
    public static function render_settings_page() {
        error_log( '[HP SS Settings] Settings page render called' );
        $settings = get_option( 'hp_ss_settings', array() );
        error_log( '[HP SS Settings] Current settings: ' . print_r( $settings, true ) );
        $api_key = isset( $settings['api_key'] ) ? $settings['api_key'] : '';
        $api_secret = isset( $settings['api_secret'] ) ? $settings['api_secret'] : '';
        $usps_services = isset( $settings['usps_services'] ) ? $settings['usps_services'] : array();
        $ups_services = isset( $settings['ups_services'] ) ? $settings['ups_services'] : array();
        $default_length = isset( $settings['default_length'] ) ? $settings['default_length'] : 12;
        $default_width = isset( $settings['default_width'] ) ? $settings['default_width'] : 12;
        $default_height = isset( $settings['default_height'] ) ? $settings['default_height'] : 12;
        $default_weight = isset( $settings['default_weight'] ) ? $settings['default_weight'] : 1;
        $debug_enabled = isset( $settings['debug_enabled'] ) && $settings['debug_enabled'] === 'yes';
        
        // Get service configuration
        $service_config = isset( $settings['service_config'] ) ? $settings['service_config'] : array();
        
        // Get discovered services (stored separately)
        $discovered_services = get_option( 'hp_ss_discovered_services', array(
            'usps' => array(),
            'ups' => array()
        ) );

        ?>
        <div class="wrap">
            <h1><?php esc_html_e( 'HP ShipStation Rates Settings', 'hp-shipstation-rates' ); ?> <small style="color: #666; font-weight: normal;">v<?php echo esc_html( HP_SS_VERSION ); ?></small></h1>
            
            <form method="post" action="options.php" enctype="multipart/form-data">
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

                    <!-- Service Discovery Section (must fetch services first) -->
                    <tr>
                        <th colspan="2">
                            <h2><?php esc_html_e( 'Service Discovery', 'hp-shipstation-rates' ); ?></h2>
                        </th>
                    </tr>
                    
                    <tr>
                        <th scope="row"><?php esc_html_e( 'Fetch Services', 'hp-shipstation-rates' ); ?></th>
                        <td>
                            <button type="button" id="hp_ss_fetch_services" class="button button-secondary">
                                <?php esc_html_e( 'Fetch Available Services from ShipStation', 'hp-shipstation-rates' ); ?>
                            </button>
                            <p class="description">
                                <?php esc_html_e( 'Click to query ShipStation and discover all available shipping services (domestic + international).', 'hp-shipstation-rates' ); ?>
                            </p>
                            <div id="hp_ss_services_result" style="margin-top: 10px;"></div>
                        </td>
                    </tr>

                    <!-- Service Configuration Section (populated after fetching) -->
                    <tr id="hp_ss_services_config_row" style="<?php echo empty( $discovered_services['usps'] ) && empty( $discovered_services['ups'] ) ? 'display: none;' : ''; ?>">
                        <th scope="row"><?php esc_html_e( 'Configure Services', 'hp-shipstation-rates' ); ?></th>
                        <td>
                            <div id="hp_ss_services_config">
                                <?php if ( ! empty( $discovered_services['usps'] ) || ! empty( $discovered_services['ups'] ) ) : ?>
                                    <p class="description" style="margin-bottom: 15px;">
                                        <?php esc_html_e( 'Enable services and customize their display names. Leave name blank to use ShipStation\'s default name.', 'hp-shipstation-rates' ); ?>
                                    </p>
                                    
                                    <?php if ( ! empty( $discovered_services['usps'] ) ) : ?>
                                        <h4>USPS Services:</h4>
                                        <table class="widefat" style="margin-bottom: 20px;">
                                            <thead>
                                                <tr>
                                                    <th style="width: 50px;">Enable</th>
                                                    <th style="width: 35%;">Service Code</th>
                                                    <th style="width: 30%;">ShipStation Name</th>
                                                    <th>Custom Display Name (optional)</th>
                                                </tr>
                                            </thead>
                                            <tbody>
                                                <?php foreach ( $discovered_services['usps'] as $code => $name ) : 
                                                    $is_enabled = isset( $service_config[ $code ]['enabled'] ) && $service_config[ $code ]['enabled'];
                                                    $custom_name = isset( $service_config[ $code ]['name'] ) && ! empty( $service_config[ $code ]['name'] ) ? $service_config[ $code ]['name'] : $name;
                                                ?>
                                                    <tr>
                                                        <td style="text-align: center;">
                                                            <input type="checkbox" 
                                                                   name="hp_ss_settings[service_config][<?php echo esc_attr( $code ); ?>][enabled]" 
                                                                   value="yes" 
                                                                   <?php checked( $is_enabled ); ?> />
                                                        </td>
                                                        <td><code><?php echo esc_html( $code ); ?></code></td>
                                                        <td><?php echo esc_html( $name ); ?></td>
                                                        <td>
                                                            <input type="text" 
                                                                   name="hp_ss_settings[service_config][<?php echo esc_attr( $code ); ?>][name]" 
                                                                   value="<?php echo esc_attr( $custom_name ); ?>" 
                                                                   class="regular-text" />
                                                        </td>
                                                    </tr>
                                                <?php endforeach; ?>
                                            </tbody>
                                        </table>
                                    <?php endif; ?>
                                    
                                    <?php if ( ! empty( $discovered_services['ups'] ) ) : ?>
                                        <h4>UPS Services:</h4>
                                        <table class="widefat" style="margin-bottom: 20px;">
                                            <thead>
                                                <tr>
                                                    <th style="width: 50px;">Enable</th>
                                                    <th style="width: 35%;">Service Code</th>
                                                    <th style="width: 30%;">ShipStation Name</th>
                                                    <th>Custom Display Name (optional)</th>
                                                </tr>
                                            </thead>
                                            <tbody>
                                                <?php foreach ( $discovered_services['ups'] as $code => $name ) : 
                                                    $is_enabled = isset( $service_config[ $code ]['enabled'] ) && $service_config[ $code ]['enabled'];
                                                    $custom_name = isset( $service_config[ $code ]['name'] ) && ! empty( $service_config[ $code ]['name'] ) ? $service_config[ $code ]['name'] : $name;
                                                ?>
                                                    <tr>
                                                        <td style="text-align: center;">
                                                            <input type="checkbox" 
                                                                   name="hp_ss_settings[service_config][<?php echo esc_attr( $code ); ?>][enabled]" 
                                                                   value="yes" 
                                                                   <?php checked( $is_enabled ); ?> />
                                                        </td>
                                                        <td><code><?php echo esc_html( $code ); ?></code></td>
                                                        <td><?php echo esc_html( $name ); ?></td>
                                                        <td>
                                                            <input type="text" 
                                                                   name="hp_ss_settings[service_config][<?php echo esc_attr( $code ); ?>][name]" 
                                                                   value="<?php echo esc_attr( $custom_name ); ?>" 
                                                                   class="regular-text" />
                                                        </td>
                                                    </tr>
                                                <?php endforeach; ?>
                                            </tbody>
                                        </table>
                                    <?php endif; ?>
                                <?php else : ?>
                                    <p class="description"><?php esc_html_e( 'No services discovered yet. Click "Fetch Available Services" above.', 'hp-shipstation-rates' ); ?></p>
                                <?php endif; ?>
                            </div>
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
                    
                    <!-- Badge Display Settings Section -->
                    <tr>
                        <th colspan="2">
                            <h2><?php esc_html_e( 'Carrier Badges', 'hp-shipstation-rates' ); ?></h2>
                        </th>
                    </tr>
                    
                    <tr>
                        <th scope="row"><?php esc_html_e( 'Show Carrier Badges', 'hp-shipstation-rates' ); ?></th>
                        <td>
                            <?php $show_badges = isset( $settings['show_badges'] ) && $settings['show_badges'] === 'yes'; ?>
                            <label>
                                <input type="checkbox" name="hp_ss_settings[show_badges]" value="1" <?php checked( $show_badges ); ?> />
                                <?php esc_html_e( 'Display carrier badges next to shipping methods', 'hp-shipstation-rates' ); ?>
                            </label>
                            <p class="description">
                                <?php esc_html_e( 'Shows USPS/UPS logos before the shipping method names on checkout.', 'hp-shipstation-rates' ); ?>
                            </p>
                        </td>
                    </tr>
                    
                    <tr>
                        <th scope="row"><?php esc_html_e( 'USPS Badge', 'hp-shipstation-rates' ); ?></th>
                        <td>
                            <?php 
                            $usps_badge = isset( $settings['usps_badge'] ) ? $settings['usps_badge'] : '';
                            if ( $usps_badge ) {
                                echo '<p><img src="' . esc_url( $usps_badge ) . '" alt="USPS Badge" style="max-height: 40px; background: #fff; padding: 5px; border: 1px solid #ddd;" /></p>';
                            } else {
                                // Check for default badge in assets folder
                                $default_badge = HP_SS_PLUGIN_URL . 'assets/usps-badge.png';
                                if ( file_exists( HP_SS_PLUGIN_DIR . 'assets/usps-badge.png' ) ) {
                                    echo '<p><img src="' . esc_url( $default_badge ) . '" alt="USPS Badge" style="max-height: 40px; background: #fff; padding: 5px; border: 1px solid #ddd;" /></p>';
                                    echo '<p class="description">' . esc_html__( 'Using default USPS badge', 'hp-shipstation-rates' ) . '</p>';
                                }
                            }
                            ?>
                            <input type="file" name="usps_badge" accept="image/*" />
                            <p class="description">
                                <?php esc_html_e( 'Upload a custom USPS badge (PNG, JPG, SVG, or WebP). Recommended: 40-60px wide × 18-24px tall.', 'hp-shipstation-rates' ); ?>
                            </p>
                        </td>
                    </tr>
                    
                    <tr>
                        <th scope="row"><?php esc_html_e( 'UPS Badge', 'hp-shipstation-rates' ); ?></th>
                        <td>
                            <?php 
                            $ups_badge = isset( $settings['ups_badge'] ) ? $settings['ups_badge'] : '';
                            if ( $ups_badge ) {
                                echo '<p><img src="' . esc_url( $ups_badge ) . '" alt="UPS Badge" style="max-height: 40px; background: #fff; padding: 5px; border: 1px solid #ddd;" /></p>';
                            } else {
                                // Check for default badge in assets folder
                                $default_badge = HP_SS_PLUGIN_URL . 'assets/ups-badge.png';
                                if ( file_exists( HP_SS_PLUGIN_DIR . 'assets/ups-badge.png' ) ) {
                                    echo '<p><img src="' . esc_url( $default_badge ) . '" alt="UPS Badge" style="max-height: 40px; background: #fff; padding: 5px; border: 1px solid #ddd;" /></p>';
                                    echo '<p class="description">' . esc_html__( 'Using default UPS badge', 'hp-shipstation-rates' ) . '</p>';
                                }
                            }
                            ?>
                            <input type="file" name="ups_badge" accept="image/*" />
                            <p class="description">
                                <?php esc_html_e( 'Upload a custom UPS badge (PNG, JPG, SVG, or WebP). Recommended: 40-60px wide × 18-24px tall.', 'hp-shipstation-rates' ); ?>
                            </p>
                        </td>
                    </tr>
                </table>

                <?php submit_button(); ?>
            </form>
        </div>
        
        <script type="text/javascript">
        jQuery(document).ready(function($) {
            var hpSsNonce = <?php echo wp_json_encode( wp_create_nonce( 'hp-ss-test-connection' ) ); ?>;
            
            $('#hp_ss_test_connection').on('click', function(e) {
                e.preventDefault();
                
                var $button = $(this);
                var $result = $('#hp_ss_test_result');
                var apiKey = $('#hp_ss_api_key').val();
                var apiSecret = $('#hp_ss_api_secret').val();
                
                if (!apiKey || !apiSecret) {
                    $result.html('<span style="color: #dc3232;">❌ Please enter both API Key and Secret</span>');
                    return;
                }
                
                $button.prop('disabled', true).text('Testing...');
                $result.html('<span style="color: #666;">⏳ Testing connection...</span>');
                
                $.ajax({
                    url: ajaxurl,
                    type: 'POST',
                    data: {
                        action: 'hp_ss_test_connection',
                        nonce: hpSsNonce,
                        api_key: apiKey,
                        api_secret: apiSecret
                    },
                    success: function(response) {
                        if (response.success) {
                            $result.html('<span style="color: #46b450;">✓ ' + response.data.message + '</span>');
                        } else {
                            $result.html('<span style="color: #dc3232;">❌ ' + response.data.message + '</span>');
                        }
                    },
                    error: function() {
                        $result.html('<span style="color: #dc3232;">❌ Connection test failed. Please try again.</span>');
                    },
                    complete: function() {
                        $button.prop('disabled', false).text('Test Connection');
                    }
                });
            });
            
            // Fetch Services handler
            $('#hp_ss_fetch_services').on('click', function(e) {
                e.preventDefault();
                
                var $button = $(this);
                var $result = $('#hp_ss_services_result');
                var apiKey = $('#hp_ss_api_key').val();
                var apiSecret = $('#hp_ss_api_secret').val();
                
                if (!apiKey || !apiSecret) {
                    $result.html('<span style="color: #dc3232;">❌ Please enter and save your API credentials first</span>');
                    return;
                }
                
                $button.prop('disabled', true).text('Fetching services...');
                $result.html('<span style="color: #666;">⏳ Querying ShipStation for available services (this may take 10-15 seconds)...</span>');
                
                $.ajax({
                    url: ajaxurl,
                    type: 'POST',
                    timeout: 30000,
                    data: {
                        action: 'hp_ss_fetch_services',
                        nonce: hpSsNonce,
                        api_key: apiKey,
                        api_secret: apiSecret
                    },
                    success: function(response) {
                        if (response.success) {
                            $result.html('<span style="color: #46b450;">✓ ' + response.data.message + ' - Reloading page to show configuration...</span>');
                            
                            // Reload page to show the new service configuration UI
                            setTimeout(function() {
                                location.reload();
                            }, 1500);
                        } else {
                            $result.html('<span style="color: #dc3232;">❌ ' + response.data.message + '</span>');
                        }
                    },
                    error: function() {
                        $result.html('<span style="color: #dc3232;">❌ Failed to fetch services. Please try again.</span>');
                    },
                    complete: function() {
                        $button.prop('disabled', false).text('Fetch Available Services from ShipStation');
                    }
                });
            });
            
            // Make custom name fields fully editable and add visual feedback
            $('.widefat tbody tr').each(function() {
                var $row = $(this);
                var $checkbox = $row.find('input[type="checkbox"]');
                var $nameInput = $row.find('input[type="text"]');
                
                // Ensure name input is always enabled and fully interactive
                $nameInput.prop('disabled', false)
                         .prop('readonly', false)
                         .css({
                             'pointer-events': 'auto',
                             'user-select': 'text',
                             '-webkit-user-select': 'text',
                             '-moz-user-select': 'text',
                             '-ms-user-select': 'text',
                             'cursor': 'text'
                         })
                         .attr('tabindex', '0');
                
                // Enable triple-click to select all
                $nameInput.on('click', function(e) {
                    if (e.detail === 3) { // Triple click
                        this.select();
                    }
                });
                
                // Enable Ctrl+A to select all
                $nameInput.on('keydown', function(e) {
                    if (e.ctrlKey && e.key === 'a') {
                        e.preventDefault();
                        this.select();
                    }
                });
                
                // Select all on focus for easier editing
                $nameInput.on('focus', function() {
                    var $this = $(this);
                    // Delay selection slightly to ensure it works
                    setTimeout(function() {
                        $this[0].select();
                    }, 50);
                });
                
                // Add visual feedback when checkbox changes
                $checkbox.on('change', function() {
                    if ($(this).is(':checked')) {
                        $row.css('background-color', '#f0f9ff');
                        $nameInput.css('border-color', '#0073aa');
                    } else {
                        $row.css('background-color', '');
                        $nameInput.css('border-color', '');
                    }
                });
                
                // Apply initial styling
                $checkbox.trigger('change');
            });
        });
        </script>
        
        <style>
        .widefat tbody tr {
            transition: background-color 0.2s ease;
        }
        .widefat tbody input[type="text"] {
            transition: border-color 0.2s ease;
            pointer-events: auto !important;
            user-select: text !important;
            cursor: text !important;
            background-color: #fff !important;
        }
        .widefat tbody input[type="text"]:focus {
            outline: 2px solid #0073aa;
            outline-offset: 0;
        }
        .widefat thead th {
            background-color: #f0f0f1;
            font-weight: 600;
        }
        </style>
        <?php
    }

    /**
     * AJAX handler for testing API connection
     */
    public static function ajax_test_connection() {
        error_log( '[HP SS AJAX] Test connection handler called' );
        check_ajax_referer( 'hp-ss-test-connection', 'nonce' );

        if ( ! current_user_can( 'manage_woocommerce' ) ) {
            wp_send_json_error( array( 'message' => __( 'Insufficient permissions', 'hp-shipstation-rates' ) ) );
        }

        $api_key = isset( $_POST['api_key'] ) ? sanitize_text_field( $_POST['api_key'] ) : '';
        $api_secret = isset( $_POST['api_secret'] ) ? sanitize_text_field( $_POST['api_secret'] ) : '';

        $result = HP_SS_Client::test_credentials( $api_key, $api_secret );

        if ( $result['success'] ) {
            // Save credentials on successful test
            $settings = get_option( 'hp_ss_settings', array() );
            $settings['api_key'] = $api_key;
            $settings['api_secret'] = $api_secret;
            update_option( 'hp_ss_settings', $settings );
            
            $result['message'] .= ' (Credentials saved)';
            wp_send_json_success( $result );
        } else {
            wp_send_json_error( $result );
        }
    }

    /**
     * AJAX handler for fetching available services from ShipStation
     */
    public static function ajax_fetch_services() {
        error_log( '[HP SS AJAX] Fetch services handler called' );
        check_ajax_referer( 'hp-ss-test-connection', 'nonce' );

        if ( ! current_user_can( 'manage_woocommerce' ) ) {
            wp_send_json_error( array( 'message' => __( 'Insufficient permissions', 'hp-shipstation-rates' ) ) );
        }

        $api_key = isset( $_POST['api_key'] ) ? sanitize_text_field( $_POST['api_key'] ) : '';
        $api_secret = isset( $_POST['api_secret'] ) ? sanitize_text_field( $_POST['api_secret'] ) : '';

        if ( empty( $api_key ) || empty( $api_secret ) ) {
            wp_send_json_error( array( 'message' => __( 'API credentials required', 'hp-shipstation-rates' ) ) );
        }

        // Make test rate requests to get actual service codes
        $from_address = HP_SS_Packager::get_from_address();
        $test_package = array(
            'weight' => 5,
            'length' => 12,
            'width' => 12,
            'height' => 6
        );

        $services = array(
            'usps' => array(),
            'ups' => array()
        );

        // Temporarily update settings to use provided credentials
        $original_settings = get_option( 'hp_ss_settings', array() );
        update_option( 'hp_ss_settings', array(
            'api_key' => $api_key,
            'api_secret' => $api_secret,
            'debug_enabled' => 'no'
        ) );

        // Test destinations: US domestic + International
        $test_destinations = array(
            array( 'postcode' => '90210', 'city' => 'Beverly Hills', 'state' => 'CA', 'country' => 'US', 'address_1' => '123 Test St', 'address_2' => '' ),
            array( 'postcode' => '2015500', 'city' => 'Yaad', 'state' => '', 'country' => 'IL', 'address_1' => 'Test St', 'address_2' => '' ),
            array( 'postcode' => 'SW1A 1AA', 'city' => 'London', 'state' => '', 'country' => 'GB', 'address_1' => 'Test St', 'address_2' => '' )
        );

        // Fetch services from multiple destinations to get comprehensive list
        foreach ( $test_destinations as $to_address ) {
            // Fetch USPS services
            $usps_rates = HP_SS_Client::get_rates( $from_address, $to_address, $test_package, 'stamps_com' );
            if ( ! is_wp_error( $usps_rates ) && is_array( $usps_rates ) ) {
                foreach ( $usps_rates as $rate ) {
                    if ( isset( $rate['serviceCode'] ) && isset( $rate['serviceName'] ) ) {
                        $services['usps'][ $rate['serviceCode'] ] = $rate['serviceName'];
                    }
                }
            }

            // Fetch UPS services
            $ups_rates = HP_SS_Client::get_rates( $from_address, $to_address, $test_package, 'ups_walleted' );
            if ( ! is_wp_error( $ups_rates ) && is_array( $ups_rates ) ) {
                foreach ( $ups_rates as $rate ) {
                    if ( isset( $rate['serviceCode'] ) && isset( $rate['serviceName'] ) ) {
                        $services['ups'][ $rate['serviceCode'] ] = $rate['serviceName'];
                    }
                }
            }
        }

        // Restore original settings
        update_option( 'hp_ss_settings', $original_settings );

        if ( empty( $services['usps'] ) && empty( $services['ups'] ) ) {
            wp_send_json_error( array( 'message' => __( 'No services found. Please check your credentials.', 'hp-shipstation-rates' ) ) );
        }

        // Store discovered services for the UI
        update_option( 'hp_ss_discovered_services', $services );

        wp_send_json_success( array(
            'message' => sprintf( __( 'Found %d USPS and %d UPS services (domestic + international)', 'hp-shipstation-rates' ), count( $services['usps'] ), count( $services['ups'] ) ),
            'services' => $services,
            'reload' => true  // Tell frontend to reload the page to show configuration UI
        ) );
    }

    /**
     * Enqueue admin scripts
     */
    public static function enqueue_admin_scripts( $hook ) {
        // JavaScript is now inline in the settings page, no external file needed
        // Keeping this function for potential future use
        return;
    }
    
    /**
     * Handle badge file upload
     *
     * @param string $file_key The $_FILES array key
     * @return string The uploaded file URL or empty string on failure
     */
    private static function handle_badge_upload( $file_key ) {
        if ( ! function_exists( 'wp_handle_upload' ) ) {
            require_once( ABSPATH . 'wp-admin/includes/file.php' );
        }
        
        $uploadedfile = $_FILES[ $file_key ];
        $upload_overrides = array(
            'test_form' => false,
            'mimes' => array(
                'jpg|jpeg|jpe' => 'image/jpeg',
                'png' => 'image/png',
                'svg' => 'image/svg+xml',
                'webp' => 'image/webp'
            )
        );
        
        // Move uploaded file to plugin assets directory
        $plugin_dir = HP_SS_PLUGIN_DIR . 'assets/';
        $filename = $file_key . '.' . pathinfo( $uploadedfile['name'], PATHINFO_EXTENSION );
        $target_file = $plugin_dir . $filename;
        
        // Ensure directory exists
        if ( ! file_exists( $plugin_dir ) ) {
            wp_mkdir_p( $plugin_dir );
        }
        
        // Move the file
        if ( move_uploaded_file( $uploadedfile['tmp_name'], $target_file ) ) {
            // Return the URL to the uploaded file
            return HP_SS_PLUGIN_URL . 'assets/' . $filename;
        }
        
        return '';
    }
}

