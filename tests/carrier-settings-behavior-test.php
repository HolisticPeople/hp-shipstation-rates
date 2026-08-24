<?php
declare(strict_types=1);

if (PHP_VERSION_ID < 80500) {
    fwrite(STDERR, "PHP 8.5 or newer is required.\n");
    exit(1);
}

define('WPINC', 'wp-includes');
define('ABSPATH', dirname(__DIR__) . '/');
$GLOBALS['hp_ss_test_options'] = [];
$_FILES = [];

function add_action(...$args): void {}
function add_filter(...$args): void {}
function plugin_dir_path(string $file): string { return dirname($file) . '/'; }
function plugin_dir_url(string $file): string { return 'https://example.test/plugins/hp-shipstation-rates/'; }
function plugin_basename(string $file): string { return basename($file); }
function get_option(string $key, mixed $default = false): mixed { return $GLOBALS['hp_ss_test_options'][$key] ?? $default; }
function update_option(string $key, mixed $value): bool { $GLOBALS['hp_ss_test_options'][$key] = $value; return true; }
function sanitize_text_field(mixed $value): string { return trim(strip_tags((string) $value)); }
function sanitize_key(mixed $value): string { return strtolower((string) preg_replace('/[^a-z0-9_-]/', '', (string) $value)); }
function esc_url_raw(string $value): string { return $value; }
function apply_filters(string $hook, mixed $value): mixed { return $value; }

function carrier_settings_assert_same(mixed $expected, mixed $actual, string $message): void {
    if ($expected !== $actual) {
        fwrite(STDERR, "FAIL: {$message}; expected " . var_export($expected, true) . ', got ' . var_export($actual, true) . "\n");
        exit(1);
    }
}

require_once dirname(__DIR__) . '/hp-shipstation-rates.php';
require_once dirname(__DIR__) . '/admin/class-hp-ss-settings.php';

carrier_settings_assert_same(['stamps_com', 'ups_walleted'], hp_ss_get_enabled_carrier_codes(), 'FedEx remains opt-in on an unconfigured install');

$GLOBALS['hp_ss_test_options']['hp_ss_settings'] = [
    'disable_ups' => 'yes',
    'enable_fedex' => 'yes',
    'fedex_carrier_code' => 'fedex_walleted',
    'service_config' => [
        'usps:priority_mail' => ['enabled' => true, 'name' => 'Priority'],
        'fedex:fedex_international_priority' => ['enabled' => 'yes', 'name' => 'FedEx International Priority'],
        'ups:ups_ground' => ['enabled' => false, 'name' => 'Ground'],
    ],
];
carrier_settings_assert_same(['stamps_com', 'fedex_walleted'], hp_ss_get_enabled_carrier_codes(), 'enabled carrier helper honors incumbent disables and discovered FedEx code');
carrier_settings_assert_same(
    ['usps:priority_mail', 'fedex:fedex_international_priority'],
    hp_ss_get_enabled_service_keys(),
    'carrier-qualified enabled services remain distinct'
);

$sanitized = HP_SS_Settings::sanitize_settings([
    'default_length' => '12',
    'default_width' => '10',
    'default_height' => '8',
    'default_weight' => '2',
    'carrier_controls_semantics' => 'positive-v1',
    'enable_usps' => '1',
    'enable_ups' => '1',
    'enable_fedex' => '1',
    'fedex_carrier_code' => 'fedex_walleted',
    'service_config' => [
        'fedex:fedex_international_priority' => ['enabled' => 'yes', 'name' => '<b>International Priority</b>'],
    ],
]);
carrier_settings_assert_same('yes', $sanitized['enable_fedex'], 'full settings save preserves explicit FedEx enablement');
carrier_settings_assert_same('no', $sanitized['disable_usps'], 'positive USPS control translates to the legacy enabled state');
carrier_settings_assert_same('no', $sanitized['disable_ups'], 'positive UPS control translates to the legacy enabled state');
carrier_settings_assert_same('fedex_walleted', $sanitized['fedex_carrier_code'], 'settings save preserves allowlisted FedEx V1 code');
carrier_settings_assert_same(
    ['enabled' => true, 'name' => 'International Priority'],
    $sanitized['service_config']['fedex:fedex_international_priority'],
    'settings save preserves and sanitizes the carrier-qualified service entry'
);

$positiveDisabled = HP_SS_Settings::sanitize_settings([
    'default_length' => '12',
    'carrier_controls_semantics' => 'positive-v1',
    'enable_ups' => '1',
]);
carrier_settings_assert_same('yes', $positiveDisabled['disable_usps'], 'unchecked positive USPS control disables USPS');
carrier_settings_assert_same('no', $positiveDisabled['disable_ups'], 'checked positive UPS control enables UPS');
carrier_settings_assert_same('no', $positiveDisabled['enable_fedex'], 'unchecked positive FedEx control disables FedEx');

$legacyForm = HP_SS_Settings::sanitize_settings([
    'default_length' => '12',
    'disable_usps' => '1',
]);
carrier_settings_assert_same('yes', $legacyForm['disable_usps'], 'cached legacy negative USPS form remains compatible');
carrier_settings_assert_same('no', $legacyForm['disable_ups'], 'cached legacy negative UPS form remains compatible');

$GLOBALS['hp_ss_test_options']['hp_ss_settings'] = [
    'disable_usps' => 'no',
    'disable_ups' => 'yes',
    'enable_fedex' => 'yes',
    'service_config' => [
        'usps:usps_priority_mail' => ['enabled' => true, 'name' => 'Priority Mail'],
        'fedex:fedex_international_economy' => ['enabled' => true, 'name' => 'International Economy'],
    ],
];
$partial = HP_SS_Settings::sanitize_settings([]);
carrier_settings_assert_same('no', $partial['disable_usps'], 'partial update preserves USPS carrier state');
carrier_settings_assert_same('yes', $partial['disable_ups'], 'partial update preserves UPS carrier state');
carrier_settings_assert_same('yes', $partial['enable_fedex'], 'partial update preserves FedEx carrier state');
carrier_settings_assert_same(
    $GLOBALS['hp_ss_test_options']['hp_ss_settings']['service_config'],
    $partial['service_config'],
    'partial update preserves service selections'
);

fwrite(STDOUT, "Carrier settings behavior passed.\n");
