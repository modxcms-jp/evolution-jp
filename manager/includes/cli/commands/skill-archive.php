<?php

require_once __DIR__ . '/../skill-lib.php';

$usage = function () {
    cli_usage('Usage: php evo skill:archive --run-dir=PATH | --plan=PLAN_ID --run-id=RUN_ID [--skill=SKILL] [--strict] [--force]');
};

$runDir = skill_get_arg($args, 'run-dir', '');
$planId = skill_get_arg($args, 'plan', '');
$runId = skill_get_arg($args, 'run-id', '');
$skill = skill_get_arg($args, 'skill', '');
$strict = skill_has_flag($args, 'strict');
$force = skill_has_flag($args, 'force');

foreach ($args as $arg) {
    if (strpos($arg, '--run-dir=') === 0 || strpos($arg, '--plan=') === 0 || strpos($arg, '--run-id=') === 0 || strpos($arg, '--skill=') === 0 || $arg === '--strict' || $arg === '--force') {
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
$proposal = skill_read_json_strict($runDir . 'proposal.json', 'proposal.json');

$validateArgs = ['--run-dir=' . $runDir, '--plan=' . $planId, '--run-id=' . $runId, '--skill=' . $skill];
if ($strict) {
    $validateArgs[] = '--strict';
}
$args = $validateArgs;
include __DIR__ . '/skill-validate.php';

if ($planId === '' || $skill === '') {
    cli_usage('Unable to determine plan or skill from the run.');
}

if (($request['status'] ?? '') !== 'completed' && !$force) {
    cli_usage('Refusing to archive a run whose learning request is not completed.');
}
if (!in_array($proposal['status'] ?? '', ['proposed', 'approved', 'rejected'], true) && !$force) {
    cli_usage('Refusing to archive a run whose proposal is in an unexpected state.');
}

$archiveRoot = MODX_BASE_PATH . '.agent/runs/archive/';
if (!is_dir($archiveRoot) && !mkdir($archiveRoot, 0775, true)) {
    cli_usage("Failed to create directory: {$archiveRoot}");
}

$targetDir = $archiveRoot . $runId . '/';
if (is_dir($targetDir)) {
    if (!$force) {
        cli_usage("Archive already exists: {$targetDir}");
    }
}

if (!rename($runDir, $targetDir)) {
    cli_usage("Failed to move {$runDir} to {$targetDir}");
}

$proposalPath = $targetDir . 'proposal.json';
$proposal = skill_read_json_strict($proposalPath, 'proposal.json');
$proposal['status'] = 'archived';
skill_write_json($proposalPath, $proposal);

cli_out("Archived run: {$targetDir}");
cli_out("Proposal marked archived: {$proposalPath}");
