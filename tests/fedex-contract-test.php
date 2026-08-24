<?php
declare(strict_types=1);

$root = dirname(__DIR__);
$files = [
    'plugin' => file_get_contents($root . '/hp-shipstation-rates.php') ?: '',
    'client' => file_get_contents($root . '/includes/class-hp-ss-client.php') ?: '',
    'method' => file_get_contents($root . '/includes/class-hp-ss-shipping-method.php') ?: '',
    'admin' => file_get_contents($root . '/admin/class-hp-ss-settings.php') ?: '',
];
$requirements = [
    ['plugin', 'function hp_ss_get_enabled_carrier_codes()', 'shared enabled-carrier provider'],
    ['plugin', 'function hp_ss_get_enabled_service_keys()', 'carrier-qualified service provider'],
    ['plugin', "'enable_fedex'", 'FedEx opt-in setting'],
    ['client', 'function get_carriers(', 'connected-carrier discovery'],
    ['method', 'HP_SS_Client::get_rates( $from_address, $to_address, $package_data, $fedex_carrier_code )', 'FedEx live-rate query'],
    ['method', "'service_key' => \$service_key", 'carrier-qualified rate metadata'],
    ['method', "'{{FEDEX}}'", 'FedEx classic-checkout badge token'],
    ['admin', "'fedex' => array()", 'FedEx service discovery bucket'],
    ['admin', "'fedex_carrier_code'", 'resolved FedEx V1 carrier identity'],
    ['admin', 'carrier_controls_semantics', 'versioned positive carrier controls'],
    ['admin', "'enable_usps'", 'positive USPS carrier control'],
    ['admin', "'enable_ups'", 'positive UPS carrier control'],
    ['admin', 'timeout: 120000', 'bounded multi-carrier discovery timeout'],
    ['admin', "textStatus === 'timeout'", 'actionable discovery timeout diagnostic'],
    ['admin', 'wp_handle_upload', 'WordPress uploads-directory badge storage'],
];
foreach ($requirements as [$file, $needle, $label]) {
    if (!str_contains($files[$file], $needle)) {
        fwrite(STDERR, "FAIL: Missing {$label}.\n");
        exit(1);
    }
}
if (str_contains($files['admin'], "esc_html_e( 'Disable USPS'")) {
    fwrite(STDERR, "FAIL: Admin UI still exposes negative USPS carrier semantics.\n");
    exit(1);
}
if (str_contains($files['admin'], "esc_html_e( 'Disable UPS'")) {
    fwrite(STDERR, "FAIL: Admin UI still exposes negative UPS carrier semantics.\n");
    exit(1);
}
if (!is_file($root . '/assets/fedex-badge.svg')) {
    fwrite(STDERR, "FAIL: Missing bundled FedEx badge.\n");
    exit(1);
}
echo "FedEx ShipStation Rates contract passed.\n";
