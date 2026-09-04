<?php
$option = [];
function get_option(string $name, mixed $default = false): mixed {
    global $option;
    return $name === 'hp_ss_delivery_transit_rules_v1' ? $option : $default;
}
require dirname(__DIR__) . '/includes/class-hp-ss-delivery-promise.php';
function report_check(mixed $actual, mixed $expected, string $message): void { if ($actual !== $expected) { throw new RuntimeException($message . ': ' . json_encode([$actual, $expected])); } }

$report = hp_ss_get_google_submit_data_v1();
report_check($report['status'], 'unavailable', 'Empty option is not ready');
report_check($report['errors'], ['transit_policy_unavailable'], 'Empty option stays explicit');
report_check($report['limitations'], ['package_not_enforced', 'origin_not_enforced', 'postcode_not_enforced', 'disruption_not_enforced'], 'Known matcher limitations remain explicit');

$option = ['version' => 1, 'rules' => [[
    'id' => 'gcr-ordinary-mainland', 'service_key' => 'ups:ups_ground_saver', 'country' => 'US', 'states' => ['DC', 'NY'],
    'max_days' => 7, 'day_type' => 'business_days', 'calendar' => 'us_federal_mon_fri', 'source' => 'private operational note', 'approved' => true,
]]];
$report = hp_ss_get_google_submit_data_v1();
report_check($report['status'], 'ready', 'Valid approved rule is ready');
report_check($report['configuration']['transit_rules']['valid_rule_count'], 1, 'One rule reported');
report_check($report['configuration']['transit_rules']['rules'][0]['source'] ?? null, null, 'Source text is never exposed');
report_check($report['scope']['states'], ['DC', 'NY'], 'Reported scope is deterministic');
report_check($report['provider']['plugin_version'], null, 'Provider works before plugin constants load');
report_check($report['provider']['owner'], 'HP ShipStation Rates', 'Provider owner is explicit');

$option['rules'][] = ['id' => 'invalid'];
$report = hp_ss_get_google_submit_data_v1();
report_check($report['status'], 'unavailable', 'Mixed valid and invalid configuration is not ready');
report_check($report['errors'], ['invalid_transit_policy_configuration'], 'Invalid configuration is explicit');
report_check($report['configuration']['transit_rules']['rejected_rule_count'], 1, 'Invalid rule count is reported without contents');

$option['rules'] = [$option['rules'][0], $option['rules'][0]];
$option['rules'][1]['id'] = 'gcr-ordinary-mainland-duplicate';
$report = hp_ss_get_google_submit_data_v1();
report_check($report['status'], 'unavailable', 'Overlapping rules are not ready');
report_check($report['errors'], ['ambiguous_transit_policy_configuration'], 'Overlap is explicit');
report_check($report['configuration']['transit_rules']['ambiguous_rule_count'], 2, 'Every overlapping state is counted');
echo "Google Submit Data synthetic contract passed.\n";
