<?php
/** Pure, optional estimate policy. No HTTP, checkout rate changes, or tracking calls. */
final class HP_SS_Delivery_Promise {
    public function __construct(private array $rules = []) {}

    public function estimate(array $context): array {
        $service = $context['service_key'] ?? null;
        $destination = $context['destination'] ?? null;
        $stamp = $context['evaluated_at'] ?? null;
        if (!is_string($service) || !preg_match('/^[a-z0-9_]+:[a-z0-9_]+$/D', $service)
            || !is_array($destination) || !is_string($destination['country'] ?? null)
            || !is_string($destination['state'] ?? null) || !is_string($stamp)) {
            return $this->unavailable('invalid_context');
        }
        // PHP accepts overflowing offsets such as +99:99 without a warning.
        // Accept only explicit real-world RFC3339 offsets, never normalization.
        if (!preg_match('/^\d{4}-\d{2}-\d{2}T(?:[01]\d|2[0-3]):[0-5]\d:[0-5]\d(?:Z|[+-](?:0\d|1[0-3]):[0-5]\d|[+-]14:00)$/D', $stamp)) {
            return $this->unavailable('invalid_evaluation_time');
        }
        $instant = DateTimeImmutable::createFromFormat('!Y-m-d\TH:i:sP', $stamp);
        $errors = DateTimeImmutable::getLastErrors();
        if (!$instant || ($errors && ($errors['warning_count'] || $errors['error_count']))
            || $instant->format(DATE_ATOM) !== preg_replace('/Z$/', '+00:00', $stamp)) {
            return $this->unavailable('invalid_evaluation_time');
        }
        $matches = [];
        foreach ($this->rules as $rule) {
            if (!self::valid_rule($rule)) { continue; }
            if ($rule['service_key'] === $service && $rule['country'] === $destination['country']
                && in_array($destination['state'], $rule['states'], true)) { $matches[] = $rule; }
        }
        if (count($matches) !== 1) {
            return $this->unavailable($matches ? 'ambiguous_transit_policy' : 'transit_policy_unavailable');
        }
        $rule = $matches[0];
        $local = $instant->setTimezone(new DateTimeZone('America/New_York'));
        $anchor = $local->setTime(0, 0);
        // Exclude the eligible anchor; allow two full dispatch business days.
        if ($local->format('H:i:s') >= '18:00:00' || !$this->business_day($anchor)) {
            do { $anchor = $anchor->modify('+1 day'); } while (!$this->business_day($anchor));
        }
        $dispatch = $this->add_days($anchor, 2, 'us_federal_mon_fri');
        // Transit is separate. The selected rule explicitly declares its calendar.
        $arrival = $this->add_days($dispatch, $rule['max_days'], $rule['calendar']);
        return [
            'version' => 1, 'status' => 'ready',
            'promise' => ['version'=>1, 'latest_date'=>$arrival->format('Y-m-d'), 'semantics_version'=>'hp-ss-delivery-v1:' . $rule['id'], 'includes_handling'=>true],
            'provenance' => [
                'rule_id'=>$rule['id'], 'source'=>$rule['source'], 'evaluated_at'=>$instant->format(DATE_ATOM),
                'dispatch_date'=>$dispatch->format('Y-m-d'), 'handling_calendar'=>'us_federal_mon_fri',
                'timezone'=>'America/New_York', 'handling_max_days'=>2, 'cutoff'=>'18:00',
                'transit_max_days'=>$rule['max_days'], 'transit_day_type'=>$rule['day_type'], 'transit_calendar'=>$rule['calendar'],
            ],
        ];
    }

    public static function valid_rule(mixed $rule): bool {
        if (!is_array($rule) || ($rule['approved'] ?? null) !== true
            || ($rule['includes_handling'] ?? false) !== false
            || !is_string($rule['id'] ?? null) || !preg_match('/^[a-zA-Z0-9_.-]{1,50}$/D', $rule['id'])
            || !is_string($rule['service_key'] ?? null) || !preg_match('/^[a-z0-9_]+:[a-z0-9_]+$/D', $rule['service_key'])
            || !is_string($rule['country'] ?? null) || !preg_match('/^[A-Z]{2}$/D', $rule['country'])
            || !is_array($rule['states'] ?? null) || $rule['states'] === []
            || !is_int($rule['max_days'] ?? null) || $rule['max_days'] < 1 || $rule['max_days'] > 60
            || !is_string($rule['source'] ?? null) || trim($rule['source']) === '' || strlen($rule['source']) > 500) {
            return false;
        }
        foreach ($rule['states'] as $state) {
            if (!is_string($state) || !preg_match('/^[A-Z0-9-]{1,8}$/D', $state)) { return false; }
        }
        return (($rule['day_type'] ?? '') === 'calendar_days' && ($rule['calendar'] ?? '') === 'calendar')
            || (($rule['day_type'] ?? '') === 'business_days' && ($rule['calendar'] ?? '') === 'us_federal_mon_fri');
    }

    private function add_days(DateTimeImmutable $date, int $days, string $calendar): DateTimeImmutable {
        for ($count = 0; $count < $days;) {
            $date = $date->modify('+1 day');
            if ($calendar === 'calendar' || $this->business_day($date)) { $count++; }
        }
        return $date;
    }

    private function business_day(DateTimeImmutable $date): bool {
        if ((int) $date->format('N') > 5) { return false; }
        $year = (int) $date->format('Y');
        $holidays = [];
        // Adjacent years include Friday observation of a Saturday New Year's Day.
        foreach ([$year - 1, $year, $year + 1] as $holidayYear) {
            foreach (['01-01','06-19','07-04','11-11','12-25'] as $monthDay) {
                if ($monthDay === '06-19' && $holidayYear < 2021) { continue; }
                $holiday = new DateTimeImmutable($holidayYear . '-' . $monthDay, $date->getTimezone());
                $weekday = (int) $holiday->format('N');
                if ($weekday === 6) { $holiday = $holiday->modify('-1 day'); }
                if ($weekday === 7) { $holiday = $holiday->modify('+1 day'); }
                $holidays[$holiday->format('Y-m-d')] = true;
            }
            foreach (['third monday of january','third monday of february','last monday of may','first monday of september','second monday of october','fourth thursday of november'] as $relative) {
                $holiday = new DateTimeImmutable($relative . ' ' . $holidayYear, $date->getTimezone());
                $holidays[$holiday->format('Y-m-d')] = true;
            }
        }
        return !isset($holidays[$date->format('Y-m-d')]);
    }

    private function unavailable(string $reason): array {
        return ['version'=>1, 'status'=>'unavailable', 'reason'=>$reason];
    }
}

/** Public server-only provider. Rules are owned here and default empty. */
function hp_ss_get_delivery_promise_v1(array $context): array {
    try {
        $option = function_exists('get_option') ? get_option('hp_ss_delivery_transit_rules_v1', []) : [];
        $rules = is_array($option) && ($option['version'] ?? null) === 1 && is_array($option['rules'] ?? null) ? $option['rules'] : [];
        return (new HP_SS_Delivery_Promise($rules))->estimate($context);
    } catch (Throwable $error) {
        return ['version'=>1, 'status'=>'unavailable', 'reason'=>'estimate_provider_unavailable'];
    }
}

/**
 * Return the configured delivery-policy state for an owner-controlled Google
 * Submit Data presentation. This report is deliberately pure and sanitized:
 * it neither quotes rates nor makes HTTP requests, and omits rule source text.
 */
function hp_ss_get_google_submit_data_v1(): array {
    $report = [
        'schema_version' => 1,
        'version' => 1,
        'generated_at' => gmdate('c'),
        'status' => 'unavailable',
        'errors' => [],
        'provider' => ['owner' => 'HP ShipStation Rates', 'plugin_version' => defined('HP_SS_VERSION') ? HP_SS_VERSION : null],
        'configuration' => [
            'transit_rules' => ['configured' => false, 'valid_rule_count' => 0, 'rejected_rule_count' => 0, 'ambiguous_rule_count' => 0, 'rules' => []],
            'handling' => ['cutoff' => '18:00', 'timezone' => 'America/New_York', 'max_days' => 2, 'calendar' => 'us_federal_mon_fri'],
        ],
        'scope' => ['countries' => [], 'states' => [], 'service_keys' => []],
        'limitations' => [
            'package_not_enforced',
            'origin_not_enforced',
            'postcode_not_enforced',
            'disruption_not_enforced',
        ],
    ];

    try {
        $option = function_exists('get_option') ? get_option('hp_ss_delivery_transit_rules_v1', []) : [];
        if (!is_array($option) || ($option['version'] ?? null) !== 1 || !is_array($option['rules'] ?? null)) {
            $report['errors'][] = 'transit_policy_unavailable';
            return $report;
        }

        $report['configuration']['transit_rules']['configured'] = true;
        $ruleStates = [];
        foreach ($option['rules'] as $rule) {
            if (!HP_SS_Delivery_Promise::valid_rule($rule)) {
                $report['configuration']['transit_rules']['rejected_rule_count']++;
                continue;
            }
            $sanitized = [
                'id' => $rule['id'], 'service_key' => $rule['service_key'], 'country' => $rule['country'],
                'states' => array_values($rule['states']), 'max_days' => $rule['max_days'],
                'day_type' => $rule['day_type'], 'calendar' => $rule['calendar'], 'approved' => $rule['approved'],
            ];
            $report['configuration']['transit_rules']['rules'][] = $sanitized;
            $report['configuration']['transit_rules']['valid_rule_count']++;
            $ruleKey = $rule['service_key'] . '|' . $rule['country'];
            foreach ($rule['states'] as $state) {
                if (isset($ruleStates[$ruleKey][$state])) {
                    $report['configuration']['transit_rules']['ambiguous_rule_count']++;
                }
                $ruleStates[$ruleKey][$state] = true;
            }
            if ($rule['approved'] === true) {
                $report['scope']['countries'][] = $rule['country'];
                $report['scope']['states'] = array_merge($report['scope']['states'], $rule['states']);
                $report['scope']['service_keys'][] = $rule['service_key'];
            }
        }
        foreach (['countries', 'states', 'service_keys'] as $key) {
            $report['scope'][$key] = array_values(array_unique($report['scope'][$key]));
            sort($report['scope'][$key]);
        }
        if ($report['configuration']['transit_rules']['valid_rule_count'] === 0) {
            $report['errors'][] = 'transit_policy_unavailable';
        } elseif ($report['configuration']['transit_rules']['rejected_rule_count'] > 0) {
            $report['errors'][] = 'invalid_transit_policy_configuration';
        } elseif ($report['configuration']['transit_rules']['ambiguous_rule_count'] > 0) {
            $report['errors'][] = 'ambiguous_transit_policy_configuration';
        } else {
            $report['status'] = 'ready';
        }
    } catch (Throwable $error) {
        $report['errors'][] = 'google_submit_data_provider_unavailable';
    }

    return $report;
}
