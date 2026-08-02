<?php

declare( strict_types=1 );

if ( PHP_VERSION_ID < 80500 ) {
    fwrite( STDERR, "PHP 8.5 or newer is required.\n" );
    exit( 1 );
}

define( 'WPINC', 'wp-includes' );

class WP_Error {
    public string $code;
    public string $message;

    public function __construct( string $code, string $message ) {
        $this->code = $code;
        $this->message = $message;
    }

    public function get_error_message(): string {
        return $this->message;
    }
}

function is_wp_error( $value ): bool {
    return $value instanceof WP_Error;
}

function __( string $text, string $domain = '' ): string {
    return $text;
}

function wp_json_encode( $value ): string {
    return json_encode( $value, JSON_UNESCAPED_SLASHES );
}

function get_option( string $key, $default = false ) {
    if ( 'hp_core_shipstation_settings' === $key ) {
        return array( 'api_key' => 'key', 'api_secret' => 'secret' );
    }
    return $default;
}

function get_transient( string $key ) {
    return $GLOBALS['hp_test_transients'][$key] ?? false;
}

function set_transient( string $key, $value, int $expiration = 0 ): bool {
    $GLOBALS['hp_test_transients'][$key] = $value;
    return true;
}

function wp_remote_post( string $url, array $args = array() ) {
    $GLOBALS['hp_test_remote_posts'][] = array( $url, $args );
    return array( 'response' => array( 'code' => 200 ), 'body' => '[{"inline":true}]' );
}

function wp_remote_retrieve_response_code( array $response ): int {
    return (int) $response['response']['code'];
}

function wp_remote_retrieve_body( array $response ): string {
    return $response['body'];
}

function assert_true( bool $condition, string $message ): void {
    if ( ! $condition ) {
        throw new RuntimeException( $message );
    }
}

function fixture_call() {
    return HP_SS_Client::get_rates(
        array( 'postcode' => '10001', 'city' => 'New York', 'state' => 'NY', 'country' => 'US' ),
        array( 'postcode' => '90210', 'city' => 'Beverly Hills', 'state' => 'CA', 'country' => 'US', 'address_1' => '123 Main St', 'address_2' => 'Apt 4' ),
        array( 'weight' => 2.5, 'length' => 10, 'width' => 8, 'height' => 4 ),
        'stamps_com'
    );
}

if ( ! isset( $argv[1] ) ) {
    foreach ( array( 'delegated-success', 'delegated-error', 'inline-fallback' ) as $case ) {
        passthru( escapeshellarg( PHP_BINARY ) . ' ' . escapeshellarg( __FILE__ ) . ' ' . escapeshellarg( $case ), $exit_code );
        if ( 0 !== $exit_code ) {
            exit( $exit_code );
        }
    }
    exit( 0 );
}

$mode = $argv[1];
$GLOBALS['hp_test_transients'] = array();
$GLOBALS['hp_test_remote_posts'] = array();

if ( 'delegated-success' === $mode || 'delegated-error' === $mode ) {
    eval( <<<'PHP'
namespace HP_Core\Services;
class ShipStationRatesService {
    public function getCarrierRates( array $v1_request, array $context ): array {
        $GLOBALS['hp_test_recorded_request'] = $v1_request;
        $GLOBALS['hp_test_recorded_context'] = $context;
        return 'delegated-error' === ($GLOBALS['hp_test_mode'] ?? '')
            ? array('success' => false, 'error' => 'x')
            : array('success' => true, 'rates' => array(array('service' => 'central')));
    }
}
PHP
    );
    $GLOBALS['hp_test_mode'] = $mode;
}

require_once dirname( __DIR__ ) . '/includes/class-hp-ss-client.php';

try {
    $result = fixture_call();

    if ( 'delegated-success' === $mode ) {
        $expected_request = array(
            'carrierCode' => 'stamps_com', 'serviceCode' => null, 'packageCode' => 'package',
            'fromPostalCode' => '10001', 'fromCity' => 'New York', 'fromState' => 'NY', 'fromCountry' => 'US',
            'toCountry' => 'US', 'toPostalCode' => '90210', 'toCity' => 'Beverly Hills', 'toState' => 'CA',
            'toStreet1' => '123 Main St', 'toStreet2' => 'Apt 4', 'weight' => array( 'value' => 2.5, 'units' => 'pounds' ),
            'dimensions' => array( 'units' => 'inches', 'length' => 10, 'width' => 8, 'height' => 4 ),
            'confirmation' => 'none', 'residential' => true,
            'rate_options' => array( 'rate_type' => 'quick' ), 'rateOptions' => array( 'rateType' => 'quick' ),
        );
        assert_true( $result === array(array('service' => 'central')), 'Delegated rates were not returned.' );
        assert_true( $GLOBALS['hp_test_recorded_request'] === $expected_request, 'Delegated request body differs from the V1 body.' );
        assert_true( count( $GLOBALS['hp_test_remote_posts'] ) === 0, 'Delegated success called wp_remote_post.' );
        assert_true( $GLOBALS['hp_test_recorded_context']['source_plugin'] === 'hp-shipstation-rates', 'Delegation context is incorrect.' );
    } elseif ( 'delegated-error' === $mode ) {
        assert_true( is_wp_error( $result ) && $result->code === 'hp_ss_central_service' && $result->message === 'x', 'Central error was not honored.' );
        assert_true( count( $GLOBALS['hp_test_remote_posts'] ) === 0, 'Central error double-quoted via wp_remote_post.' );
    } else {
        assert_true( is_array( $result ) && $result === array(array('inline' => true)), 'Inline fallback did not return its response.' );
        assert_true( count( $GLOBALS['hp_test_remote_posts'] ) === 1, 'Inline fallback did not call wp_remote_post once.' );
        assert_true( json_decode( $GLOBALS['hp_test_remote_posts'][0][1]['body'], true )['toPostalCode'] === '90210', 'Inline request body is incorrect.' );
    }

    echo "$mode: PASS\n";
} catch ( Throwable $exception ) {
    fwrite( STDERR, "$mode: FAIL - {$exception->getMessage()}\n" );
    exit( 1 );
}
