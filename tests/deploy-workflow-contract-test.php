<?php
/**
 * Deploy workflow contract (2026-10-31 side-findings sweep).
 *
 * Canonical two-job shape: staging runs on a dev push OR a staging dispatch,
 * production only on a production dispatch; the staging lease gate lives only
 * in the staging job and precedes every host step; both jobs purge the Kinsta
 * page cache after rsync and prune dev-only paths listed in deploy-exclude.
 *
 * Run: php tests/deploy-workflow-contract-test.php
 */

declare(strict_types=1);

$root     = dirname(__DIR__);
$workflow = str_replace("\r\n", "\n", (string) file_get_contents($root . '/.github/workflows/deploy.yml'));
$exclude  = str_replace("\r\n", "\n", (string) file_get_contents($root . '/.github/deploy-exclude.txt'));

$failures = 0;
function hp_ssr_deploy_assert(bool $condition, string $message): void
{
    global $failures;
    if (!$condition) {
        fwrite(STDERR, 'FAIL: ' . $message . PHP_EOL);
        $failures++;
    }
}

$jobs_at = strpos($workflow, "\njobs:\n");
hp_ssr_deploy_assert($jobs_at !== false, 'Workflow must declare jobs.');
$staging_at    = strpos($workflow, "\n  deploy-staging:\n");
$production_at = strpos($workflow, "\n  deploy-production:\n");
hp_ssr_deploy_assert($staging_at !== false && $production_at !== false && $staging_at < $production_at, 'Workflow must have a deploy-staging job followed by a deploy-production job.');
hp_ssr_deploy_assert(preg_match_all('/^  [A-Za-z0-9_-]+:\s*$/m', substr($workflow, (int) $jobs_at)) === 2, 'Workflow must have exactly two jobs.');

$staging    = substr($workflow, (int) $staging_at, (int) $production_at - (int) $staging_at);
$production = substr($workflow, (int) $production_at);

// Guards.
$norm = static fn(string $s): string => trim((string) preg_replace('/\s+/', ' ', $s));
hp_ssr_deploy_assert(
    preg_match('/deploy-staging:\n\s*if: \|\n(.*?)\n\s*name:/s', $staging, $guard) === 1
        && $norm($guard[1]) === "(github.event_name == 'push' && github.ref_name == 'dev') || (github.event_name == 'workflow_dispatch' && inputs.environment == 'staging')",
    'Staging guard must be the canonical push-scoped dev guard OR a staging dispatch.'
);
hp_ssr_deploy_assert(
    preg_match("/^    if: github.event_name == 'workflow_dispatch' && inputs.environment == 'production'$/m", $production) === 1,
    'Production job must run only on a production dispatch.'
);
hp_ssr_deploy_assert(!str_contains($workflow, 'steps.vars.outputs'), 'Single-job environment switching must be gone.');

// Secrets stay in their own job.
hp_ssr_deploy_assert(!str_contains($staging, 'KINSTAPROD_'), 'Staging job must not reference production secrets.');
hp_ssr_deploy_assert(!preg_match('/secrets\.KINSTA_(?!PROD)/', $production), 'Production job must not reference staging secrets.');

// Lease gate: staging only, before the first host step.
$lease_at = strpos($staging, '- name: Respect active staging leases');
hp_ssr_deploy_assert($lease_at !== false && str_contains($staging, '- name: Test staging lease check'), 'Staging job must self-test and run the staging lease check.');
hp_ssr_deploy_assert(str_contains($staging, '"python3 - --plugin hp-shipstation-rates" < .github/hp-deploy/staging-lease-check.py'), 'Lease check must name the hp-shipstation-rates slug.');
foreach (['- name: Setup SSH key', '- name: Test SSH connection', '- name: Backup existing plugin', '- name: Rsync plugin files'] as $host_step) {
    $at = strpos($staging, $host_step);
    hp_ssr_deploy_assert($at !== false && $lease_at !== false && $at > $lease_at, 'Lease check must precede ' . $host_step . '.');
}
hp_ssr_deploy_assert(!str_contains($production, 'staging-lease') && !str_contains($production, 'IGNORE_STAGING_LEASES'), 'Production must never be gated on staging leases.');

// Both jobs: rsync, then prune dev-only paths, then purge the Kinsta page cache.
foreach (['staging' => $staging, 'production' => $production] as $name => $job) {
    $rsync = strpos($job, '- name: Rsync plugin files');
    $prune = strpos($job, '- name: Remove dev-only paths from deployed tree');
    $purge = strpos($job, '- name: Purge Kinsta page cache');
    hp_ssr_deploy_assert($rsync !== false && str_contains($job, "--exclude-from='.github/deploy-exclude.txt'"), "$name job must rsync with deploy-exclude.");
    hp_ssr_deploy_assert($prune !== false && $rsync !== false && $prune > $rsync, "$name job must prune dev-only paths after rsync.");
    hp_ssr_deploy_assert($purge !== false && $rsync !== false && $purge > $rsync, "$name job must purge the Kinsta page cache after rsync.");
    hp_ssr_deploy_assert($purge !== false && preg_match('/wp kinsta cache purge --all"\n/', substr($job, (int) $purge)) === 1, "$name purge must run wp kinsta cache purge --all without swallowing failure.");
}

// deploy-exclude covers every dev-only path the workflow prunes.
hp_ssr_deploy_assert(preg_match('/^  DEV_ONLY_PATHS: (.+)$/m', $workflow, $dev_only) === 1, 'Workflow must declare DEV_ONLY_PATHS.');
$excluded = array_map('trim', explode("\n", $exclude));
$paths    = isset($dev_only[1]) ? preg_split('/\s+/', trim($dev_only[1])) : [];
hp_ssr_deploy_assert(in_array('tests', $paths, true), 'DEV_ONLY_PATHS must include tests.');
foreach ($paths as $path) {
    hp_ssr_deploy_assert(in_array($path . '/', $excluded, true), "deploy-exclude.txt must list $path/.");
}
foreach (['.github/', 'tests/'] as $required) {
    hp_ssr_deploy_assert(in_array($required, $excluded, true), "deploy-exclude.txt must list $required.");
}

if ($failures > 0) {
    exit(1);
}
echo "Deploy workflow contract passed\n";
