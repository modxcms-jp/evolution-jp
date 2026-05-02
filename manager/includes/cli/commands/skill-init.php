<?php

require_once __DIR__ . '/../skill-lib.php';

$usage = function () {
    cli_usage('Usage: php evo skill:init --plan=PLAN_ID --skill=SKILL [--run-id=RUN_ID] [--trigger=execplan_completed|user_feedback|failure_threshold_exceeded] [--priority=low|normal|high] [--force]');
};

$planId = skill_get_arg($args, 'plan', '');
$skill = skill_get_arg($args, 'skill', '');
$runId = skill_get_arg($args, 'run-id', '');
$trigger = skill_get_arg($args, 'trigger', 'execplan_completed');
$priority = skill_get_arg($args, 'priority', 'normal');
$force = skill_has_flag($args, 'force');

foreach ($args as $arg) {
    if (strpos($arg, '--plan=') === 0 || strpos($arg, '--skill=') === 0 || strpos($arg, '--run-id=') === 0 || strpos($arg, '--trigger=') === 0 || strpos($arg, '--priority=') === 0 || $arg === '--force') {
        continue;
    }

    $usage();
}

if ($planId === '' || $skill === '') {
    $usage();
}

skill_validate_identifier($planId, 'plan id');
skill_validate_skill_name($skill);

if (!in_array($trigger, SKILL_TRIGGERS, true)) {
    $usage();
}

if (!in_array($priority, SKILL_PRIORITIES, true)) {
    $usage();
}

$runsRoot = MODX_BASE_PATH . '.agent/runs/';
if (!is_dir($runsRoot) && !mkdir($runsRoot, 0775, true)) {
    cli_usage("Failed to create directory: {$runsRoot}");
}

$scanRunRoots = [
    $runsRoot,
    MODX_BASE_PATH . '.agent/runs/archive/',
];

if ($runId === '') {
    $pattern = '/^' . preg_quote($planId, '/') . '-([0-9]{3})$/';
    $maxSeq = 0;
    foreach ($scanRunRoots as $root) {
        if (!is_dir($root)) {
            continue;
        }

        $existing = glob($root . $planId . '-*', GLOB_ONLYDIR);
        if (!is_array($existing)) {
            continue;
        }

        foreach ($existing as $dir) {
            $name = basename($dir);
            if (preg_match($pattern, $name, $matches)) {
                $maxSeq = max($maxSeq, (int)$matches[1]);
            }
        }
    }

    $runId = sprintf('%s-%03d', $planId, $maxSeq + 1);
} elseif (!preg_match('/^' . preg_quote($planId, '/') . '-[0-9]{3}$/', $runId)) {
    cli_usage("Invalid run id for plan {$planId}: {$runId}");
}

$runDir = $runsRoot . $runId . '/';
if (is_dir($runDir)) {
    if (!$force) {
        cli_usage("Run already exists: {$runDir}");
    }
} elseif (!mkdir($runDir, 0775, true)) {
    cli_usage("Failed to create directory: {$runDir}");
}

$skillMetaRoot = MODX_BASE_PATH . '.agent/skill-metadata/';
if (!is_dir($skillMetaRoot) && !mkdir($skillMetaRoot, 0775, true)) {
    cli_usage("Failed to create directory: {$skillMetaRoot}");
}

$skillDir = $skillMetaRoot . $skill . '/';
if (!is_dir($skillDir) && !mkdir($skillDir, 0775, true)) {
    cli_usage("Failed to create directory: {$skillDir}");
}

$now = date('Y-m-d\TH:i:sP');
$requestedAt = $now;
$generatedAt = $now;

$evidence = ['trace.jsonl'];
if ($trigger === 'user_feedback') {
    $evidence[] = 'chat.md';
} elseif ($trigger === 'failure_threshold_exceeded') {
    $evidence[] = 'notes.md';
}

$reasonSummary = 'ExecPlan完了により学び生成対象になった';
if ($trigger === 'user_feedback') {
    $reasonSummary = 'ユーザー差し戻しにより学び生成対象になった';
} elseif ($trigger === 'failure_threshold_exceeded') {
    $reasonSummary = '直近10 runの失敗閾値超過により学び生成対象になった';
}

$writeFile = function (string $path, string $content, bool $allowOverwrite = false, bool $skipIfExists = false) use ($force) {
    if (is_file($path)) {
        if ($skipIfExists) {
            return;
        }
        if (!$allowOverwrite && !$force) {
            cli_usage("File already exists: {$path}");
        }
    }
    $written = file_put_contents($path, $content);
    if ($written === false) {
        cli_usage("Failed to write: {$path}");
    }
    chmod($path, 0644);
};

$writeFile($runDir . 'trace.jsonl', '');
$writeFile($runDir . 'chat.md', "# Chat Log\n\n");
$writeFile($runDir . 'notes.md', "# Notes\n\n");

$writeFile(
    $runDir . 'learning-request.json',
    skill_encode_json([
        'version' => 1,
        'plan_id' => $planId,
        'run_id' => $runId,
        'skill' => $skill,
        'trigger' => $trigger,
        'requested_at' => $requestedAt,
        'status' => 'pending',
        'priority' => $priority,
        'reason_summary' => $reasonSummary,
        'evidence' => $evidence,
    ])
);

$writeFile(
    $runDir . 'learning.json',
    skill_encode_json([
        'version' => 1,
        'plan_id' => $planId,
        'run_id' => $runId,
        'skill' => $skill,
        'generated_at' => $generatedAt,
        'request_ref' => 'learning-request.json',
        'outcome' => 'partial',
        'findings' => [],
    ])
);

$writeFile(
    $runDir . 'pruning.json',
    skill_encode_json([
        'version' => 1,
        'plan_id' => $planId,
        'run_id' => $runId,
        'skill' => $skill,
        'generated_at' => $generatedAt,
        'budget' => [
            'skill_md_max_lines' => 200,
            'max_loaded_references' => 3,
        ],
        'items' => [],
    ])
);

$writeFile(
    $runDir . 'proposal.json',
    skill_encode_json([
        'version' => 1,
        'plan_id' => $planId,
        'run_id' => $runId,
        'skill' => $skill,
        'generated_at' => $generatedAt,
        'status' => 'proposed',
        'source_files' => [
            'learning.json',
            'pruning.json',
        ],
        'changes' => [],
    ])
);

$writeFile(
    $skillDir . 'inventory.json',
    skill_encode_json([
        'items' => [],
    ]),
    false,
    true
);

$writeFile(
    $skillDir . 'stats.json',
    skill_encode_json([
        'items' => [],
    ]),
    false,
    true
);

$writeFile($skillDir . 'history.jsonl', '', false, true);

cli_out("Created run scaffold: {$runDir}");
cli_out("Created skill metadata: {$skillDir}");
