<?php
declare(strict_types=1);

define('WPINC', 'wp-includes');

function get_option(string $key, mixed $default = false): mixed {
    return $default;
}

function wc_get_weight(float $value, string $unit): float {
    return $value * 2.2046226218;
}

function wc_get_dimension(float $value, string $unit): float {
    return $value;
}

final class HP_SS_Packager_Test_Product {
    public function __construct(private string $weight) {}
    public function needs_shipping(): bool { return true; }
    public function get_weight(): string { return $this->weight; }
    public function get_length(): string { return '12'; }
    public function get_width(): string { return '8'; }
    public function get_height(): string { return '4'; }
}

require_once dirname(__DIR__) . '/includes/class-hp-ss-packager.php';

function hp_ss_packager_assert_same(mixed $expected, mixed $actual, string $message): void {
    if ($expected !== $actual) {
        fwrite(STDERR, "FAIL: {$message}\nExpected: " . var_export($expected, true) . "\nActual: " . var_export($actual, true) . "\n");
        exit(1);
    }
}

$raw_weight = 0.294835;
$package = HP_SS_Packager::build_package([
    ['data' => new HP_SS_Packager_Test_Product((string) $raw_weight), 'quantity' => 1],
]);
$expected_weight = $raw_weight * 2.2046226218;

hp_ss_packager_assert_same(
    $expected_weight,
    $package['weight'] ?? null,
    'legacy package adapter preserves exact converted positive weight'
);
hp_ss_packager_assert_same(
    false,
    ($package['weight'] ?? 1.0) === round($expected_weight, 2),
    'legacy package adapter does not round quote weight'
);

echo "ShipStation package weight contract passed.\n";
