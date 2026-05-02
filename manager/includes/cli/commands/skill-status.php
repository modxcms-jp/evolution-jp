<?php

require_once __DIR__ . '/../skill-lib.php';

$usage = function () {
    cli_usage('Usage: php evo skill:status [--plan=PLAN_ID] [--skill=SKILL] [--limit=N] [--json] [--archived]');
};

$planId = skill_get_arg($args, 'plan', '');
$skill = skill_get_arg($args, 'skill', '');
$limit = skill_get_int_arg($args, 'limit', 20, 1, 200);
$json = skill_has_flag($args, 'json');
$archived = skill_has_flag($args, 'archived');

if ($planId !== '') {
    skill_validate_identifier($planId, '--plan');
}
if ($skill !== '') {
    skill_validate_skill_name($skill);
}

foreach ($args as $arg) {
    if (strpos($arg, '--plan=') === 0 || strpos($arg, '--skill=') === 0 || strpos($arg, '--limit=') === 0 || $arg === '--json' || $arg === '--archived') {
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

$rows = [];
foreach ($runDirs as $runDir) {
    $name = basename($runDir);
    if (in_array($name, SKILL_RESERVED_DIRS, true)) {
        continue;
    }

    $request = skill_read_json($runDir . '/learning-request.json');
    $proposal = skill_read_json($runDir . '/proposal.json');

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
            $request = skill_read_json($runDir . '/learning-request.json');
            $proposal = skill_read_json($runDir . '/proposal.json');
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
