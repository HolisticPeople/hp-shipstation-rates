<?php
require dirname(__DIR__) . '/includes/class-hp-ss-delivery-promise.php';
function check($actual, $expected, $message) { if ($actual !== $expected) { throw new RuntimeException($message . ': ' . json_encode([$actual,$expected])); } }
$rule=['id'=>'synthetic-v1','service_key'=>'usps:synthetic','country'=>'US','states'=>['CT','DC'],'max_days'=>1,'day_type'=>'calendar_days','calendar'=>'calendar','source'=>'synthetic test only','approved'=>true];
$provider=new HP_SS_Delivery_Promise([$rule]);
function context($time) { return ['service_key'=>'usps:synthetic','destination'=>['country'=>'US','state'=>'CT','postcode'=>'06897'],'evaluated_at'=>$time]; }
$cases=[
 ['2026-09-04T17:59:59-04:00','2026-09-09','2026-09-10'],
 ['2026-09-04T18:00:00-04:00','2026-09-10','2026-09-11'],
 ['2026-09-05T10:00:00-04:00','2026-09-10','2026-09-11'],
 ['2026-09-07T10:00:00-04:00','2026-09-10','2026-09-11'],
 ['2026-07-02T17:00:00-04:00','2026-07-07','2026-07-08'],
 ['2021-12-30T17:00:00-05:00','2022-01-04','2022-01-05'],
 ['2026-03-06T17:00:00-05:00','2026-03-10','2026-03-11'],
 ['2026-03-08T21:30:00+00:00','2026-03-11','2026-03-12'],
 ['2026-10-30T17:00:00-04:00','2026-11-03','2026-11-04'],
];
foreach($cases as [$time,$dispatch,$arrival]) {
 $result=$provider->estimate(context($time)); check($result['status'],'ready','Synthetic rule ready');
 check($result['provenance']['dispatch_date'],$dispatch,'Handling cutoff/holiday/DST dispatch');
 check($result['promise']['latest_date'],$arrival,'Separate calendar transit');
 check($result['promise']['includes_handling'],true,'Handling included exactly once');
}
$ruleBusiness=$rule; $ruleBusiness['day_type']='business_days';$ruleBusiness['calendar']='us_federal_mon_fri';
$b=new HP_SS_Delivery_Promise([$ruleBusiness]);
// Tue→Thu dispatch; one calendar transit Friday. Wed→Fri dispatch; business transit skips weekend/LaborDay.
check($b->estimate(context('2026-09-02T10:00:00-04:00'))['promise']['latest_date'],'2026-09-08','Transit business calendar excludes holiday independently');
check($provider->estimate(context('2026-09-02T10:00:00-04:00'))['promise']['latest_date'],'2026-09-05','Calendar transit does not inherit handling weekends');
check((new HP_SS_Delivery_Promise())->estimate(context('2026-09-04T17:00:00-04:00'))['reason'],'transit_policy_unavailable','Empty production default');
$invalid=$rule;$invalid['approved']=false;check((new HP_SS_Delivery_Promise([$invalid]))->estimate(context('2026-09-04T17:00:00-04:00'))['status'],'unavailable','Unapproved rules ignored');
$invalid=$rule;$invalid['includes_handling']=true;check((new HP_SS_Delivery_Promise([$invalid]))->estimate(context('2026-09-04T17:00:00-04:00'))['status'],'unavailable','Handling-inclusive input cannot double count handling');
$invalid=$rule;$invalid['max_days']='5';check((new HP_SS_Delivery_Promise([$invalid]))->estimate(context('2026-09-04T17:00:00-04:00'))['status'],'unavailable','Duration must be integer');
$invalid=$rule;$invalid['calendar']='unknown';check((new HP_SS_Delivery_Promise([$invalid]))->estimate(context('2026-09-04T17:00:00-04:00'))['status'],'unavailable','Unknown calendar rejected');
check((new HP_SS_Delivery_Promise([$rule,$rule]))->estimate(context('2026-09-04T17:00:00-04:00'))['reason'],'ambiguous_transit_policy','No ambiguous source precedence');
$c=context('2026-09-04T17:00:00-04:00');$c['destination']['state']='AK';check($provider->estimate($c)['status'],'unavailable','Unsupported route excluded');
$c=context('2026-09-04T17:00:00-04:00');$c['service_key']='ups:ground';check($provider->estimate($c)['status'],'unavailable','Exact service identity');
check($provider->estimate(context('2026-02-30T17:00:00-05:00'))['reason'],'invalid_evaluation_time','Invalid dates not normalized');
check($provider->estimate(context('tomorrow'))['reason'],'invalid_evaluation_time','No implicit timezone/time parsing');
check(hp_ss_get_delivery_promise_v1(context('2026-09-04T17:00:00-04:00'))['status'],'unavailable','Missing WP/options fail soft');
echo "Delivery promise synthetic contract passed.\n";
