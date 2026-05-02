<?php

require_once __DIR__ . '/../skill-lib.php';

$usage = function () {
    cli_usage('Usage: php evo skill:complete --run-dir=PATH | --plan=PLAN_ID --run-id=RUN_ID [--skill=SKILL] [--strict] [--skip-next]');
};

$runDir = skill_get_arg($args, 'run-dir', '');
$planId = skill_get_arg($args, 'plan', '');
$runId = skill_get_arg($args, 'run-id', '');
$skill = skill_get_arg($args, 'skill', '');
$strict = skill_has_flag($args, 'strict');
$skipNext = skill_has_flag($args, 'skip-next');

foreach ($args as $arg) {
    if (strpos($arg, '--run-dir=') === 0 || strpos($arg, '--plan=') === 0 || strpos($arg, '--run-id=') === 0 || strpos($arg, '--skill=') === 0 || $arg === '--strict' || $arg === '--skip-next') {
        continue;
    }

    $usage();
}

if ($runDir === '') {
    if ($planId === '' || $runId === '') {
        $usage();
    }
    $runDir = MODX_BASE_PATH . '.agent/runs/' . $runId . '/';
}

$runDir = rtrim($runDir, "/\\") . '/';
if (!is_dir($runDir)) {
    cli_usage("Run directory not found: {$runDir}");
}

$identifiers = skill_complete_identifiers_from_request($runDir, $planId, $runId, $skill);
$planId = $identifiers['plan_id'];
$runId = $identifiers['run_id'];
$skill = $identifiers['skill'];

$request = skill_read_json_strict($runDir . 'learning-request.json', 'learning-request.json');

if ($strict) {
    $validateArgs = ['--run-dir=' . $runDir, '--strict', '--plan=' . $planId, '--run-id=' . $runId, '--skill=' . $skill];
    $args = $validateArgs;
    include __DIR__ . '/skill-validate.php';
} else {
    $validateArgs = ['--run-dir=' . $runDir, '--plan=' . $planId, '--run-id=' . $runId, '--skill=' . $skill];
    $args = $validateArgs;
    include __DIR__ . '/skill-validate.php';
}

$currentRunDir = $runDir;
$currentRequestPath = $currentRunDir . 'learning-request.json';

if ($planId === '' || $skill === '') {
    cli_usage('Unable to determine plan or skill from the run.');
}

cli_out("Validated: {$runDir}");

if ($skipNext) {
    $request['status'] = 'completed';
    skill_write_json($currentRequestPath, $request);
    cli_out("Learning request marked completed: {$currentRequestPath}");
    exit(0);
}

$initArgs = [
    '--plan=' . $planId,
    '--skill=' . $skill,
];
$args = $initArgs;
include __DIR__ . '/skill-init.php';

$request['status'] = 'completed';
skill_write_json($currentRequestPath, $request);
cli_out("Learning request marked completed: {$currentRequestPath}");
