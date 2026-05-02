<?php

$planId = '';
$skill = '';
$limit = 20;
$json = false;
$archived = false;

$usage = function () {
    cli_usage('Usage: php evo skill:status [--plan=PLAN_ID] [--skill=SKILL] [--limit=N] [--json] [--archived]');
};

foreach ($args as $arg) {
    if (strpos($arg, '--plan=') === 0) {
        $planId = trim(substr($arg, strlen('--plan=')));
        continue;
    }
    if (strpos($arg, '--skill=') === 0) {
        $skill = trim(substr($arg, strlen('--skill=')));
        continue;
    }
    if (strpos($arg, '--limit=') === 0) {
        $limit = max(1, min(200, (int)substr($arg, strlen('--limit='))));
        continue;
    }
    if ($arg === '--json') {
        $json = true;
        continue;
    }
    if ($arg === '--archived') {
        $archived = true;
        continue;
    }

    $usage();
}

$runsRoot = MODX_BASE_PATH . '.agent/runs/';
if (!is_dir($runsRoot)) {
    cli_usage("Run directory not found: {$runsRoot}");
}

$runDirs = glob($runsRoot . '*', GLOB_ONLYDIR);
if (!is_array($runDirs)) {
    cli_usage('Failed to enumerate run directories.');
}

rsort($runDirs, SORT_STRING);

$readJson = function (string $path) {
    if (!is_file($path)) {
        return null;
    }

    $raw = file_get_contents($path);
    if ($raw === false) {
        return null;
    }

    $data = json_decode($raw, true);
    return is_array($data) ? $data : null;
};

$rows = [];
foreach ($runDirs as $runDir) {
    $name = basename($runDir);
    if ($name === 'templates' || $name === 'archive') {
        continue;
    }

    $request = $readJson($runDir . '/learning-request.json');
    $proposal = $readJson($runDir . '/proposal.json');

    $row = [
        'run_id' => $name,
        'plan_id' => (string)($request['plan_id'] ?? ''),
        'skill' => (string)($request['skill'] ?? ''),
        'trigger' => (string)($request['trigger'] ?? ''),
        'request_status' => (string)($request['status'] ?? 'missing'),
        'priority' => (string)($request['priority'] ?? ''),
        'proposal_status' => (string)($proposal['status'] ?? 'missing'),
        'requested_at' => (string)($request['requested_at'] ?? ''),
        'generated_at' => (string)($proposal['generated_at'] ?? ''),
        'run_dir' => $runDir,
    ];

    if ($planId !== '' && $row['plan_id'] !== $planId) {
        continue;
    }
    if ($skill !== '' && $row['skill'] !== $skill) {
        continue;
    }

    $rows[] = $row;
    if (count($rows) >= $limit) {
        break;
    }
}

$archiveRoot = $runsRoot . 'archive/';
if ($archived && is_dir($archiveRoot)) {
    $archiveDirs = glob($archiveRoot . '*', GLOB_ONLYDIR);
    if (is_array($archiveDirs)) {
        rsort($archiveDirs, SORT_STRING);
        foreach ($archiveDirs as $runDir) {
            $name = basename($runDir);
            $request = $readJson($runDir . '/learning-request.json');
            $proposal = $readJson($runDir . '/proposal.json');
            $row = [
                'run_id' => $name,
                'plan_id' => (string)($request['plan_id'] ?? ''),
                'skill' => (string)($request['skill'] ?? ''),
                'trigger' => (string)($request['trigger'] ?? ''),
                'request_status' => (string)($request['status'] ?? 'missing'),
                'priority' => (string)($request['priority'] ?? ''),
                'proposal_status' => (string)($proposal['status'] ?? 'missing'),
                'requested_at' => (string)($request['requested_at'] ?? ''),
                'generated_at' => (string)($proposal['generated_at'] ?? ''),
                'run_dir' => $runDir,
            ];

            if ($planId !== '' && $row['plan_id'] !== $planId) {
                continue;
            }
            if ($skill !== '' && $row['skill'] !== $skill) {
                continue;
            }

            $rows[] = $row;
            if (count($rows) >= $limit) {
                break;
            }
        }
    }
}

if ($json) {
    cli_out(json_encode($rows, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_PRETTY_PRINT));
    exit(0);
}

if (!$rows) {
    cli_out('(no skill runs)');
    exit(0);
}

foreach ($rows as $row) {
    cli_out(sprintf(
        '%s plan=%s skill=%s request=%s proposal=%s priority=%s trigger=%s',
        $row['run_id'],
        $row['plan_id'] !== '' ? $row['plan_id'] : '-',
        $row['skill'] !== '' ? $row['skill'] : '-',
        $row['request_status'],
        $row['proposal_status'],
        $row['priority'] !== '' ? $row['priority'] : '-',
        $row['trigger'] !== '' ? $row['trigger'] : '-'
    ));
}
