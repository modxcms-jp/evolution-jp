<?php

$runDir = '';
$planId = '';
$runId = '';
$skill = '';
$strict = false;
$force = false;

$usage = function () {
    cli_usage('Usage: php evo skill:archive --run-dir=PATH | --plan=PLAN_ID --run-id=RUN_ID [--skill=SKILL] [--strict] [--force]');
};

foreach ($args as $arg) {
    if (strpos($arg, '--run-dir=') === 0) {
        $runDir = trim(substr($arg, strlen('--run-dir=')));
        continue;
    }
    if (strpos($arg, '--plan=') === 0) {
        $planId = trim(substr($arg, strlen('--plan=')));
        continue;
    }
    if (strpos($arg, '--run-id=') === 0) {
        $runId = trim(substr($arg, strlen('--run-id=')));
        continue;
    }
    if (strpos($arg, '--skill=') === 0) {
        $skill = trim(substr($arg, strlen('--skill=')));
        continue;
    }
    if ($arg === '--strict') {
        $strict = true;
        continue;
    }
    if ($arg === '--force') {
        $force = true;
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

$readJson = function (string $path, string $label) {
    if (!is_file($path)) {
        cli_usage("{$label} missing: {$path}");
    }

    $raw = file_get_contents($path);
    if ($raw === false) {
        cli_usage("{$label} unreadable: {$path}");
    }

    $data = json_decode($raw, true);
    if (!is_array($data)) {
        cli_usage("{$label} invalid JSON: {$path}");
    }

    return $data;
};

$writeJson = function (string $path, array $data) {
    $json = json_encode($data, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_PRETTY_PRINT);
    if ($json === false) {
        cli_usage("Failed to encode JSON: {$path}");
    }
    if (file_put_contents($path, $json . PHP_EOL) === false) {
        cli_usage("Failed to write: {$path}");
    }
    chmod($path, 0644);
};

$validateArgs = ['--run-dir=' . $runDir];
if ($planId !== '') {
    $validateArgs[] = '--plan=' . $planId;
}
if ($runId !== '') {
    $validateArgs[] = '--run-id=' . $runId;
}
if ($skill !== '') {
    $validateArgs[] = '--skill=' . $skill;
}
if ($strict) {
    $validateArgs[] = '--strict';
}
$args = $validateArgs;
include __DIR__ . '/skill-validate.php';

$request = $readJson($runDir . 'learning-request.json', 'learning-request.json');
$proposal = $readJson($runDir . 'proposal.json', 'proposal.json');

$planId = $planId !== '' ? $planId : (string)($request['plan_id'] ?? '');
$skill = $skill !== '' ? $skill : (string)($request['skill'] ?? '');
$runId = $runId !== '' ? $runId : basename(rtrim($runDir, '/'));

if ($planId === '' || $skill === '') {
    cli_usage('Unable to determine plan or skill from the run.');
}

if (($request['status'] ?? '') !== 'completed' && !$force) {
    cli_usage('Refusing to archive a run whose learning request is not completed.');
}
if (($proposal['status'] ?? '') !== 'proposed' && ($proposal['status'] ?? '') !== 'approved' && ($proposal['status'] ?? '') !== 'rejected' && !$force) {
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
$proposal = $readJson($proposalPath, 'proposal.json');
$proposal['status'] = 'archived';
$writeJson($proposalPath, $proposal);

cli_out("Archived run: {$targetDir}");
cli_out("Proposal marked archived: {$proposalPath}");
