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
        $instant = DateTimeImmutable::createFromFormat('!Y-m-d\TH:i:sP', $stamp);
        $errors = DateTimeImmutable::getLastErrors();
        if (!$instant || ($errors && ($errors['warning_count'] || $errors['error_count']))) {
            return $this->unavailable('invalid_evaluation_time');
        }
        $matches = [];
        foreach ($this->rules as $rule) {
            if (!$this->valid_rule($rule)) { continue; }
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

    private function valid_rule(mixed $rule): bool {
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
