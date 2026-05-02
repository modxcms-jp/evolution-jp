<?php

$planId = '';
$skill = '';
$runId = '';
$trigger = 'execplan_completed';
$priority = 'normal';
$force = false;

$usage = function () {
    cli_usage('Usage: php evo skill:init --plan=PLAN_ID --skill=SKILL [--run-id=RUN_ID] [--trigger=execplan_completed|user_feedback|failure_threshold_exceeded] [--priority=low|normal|high] [--force]');
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
    if (strpos($arg, '--run-id=') === 0) {
        $runId = trim(substr($arg, strlen('--run-id=')));
        continue;
    }
    if (strpos($arg, '--trigger=') === 0) {
        $trigger = trim(substr($arg, strlen('--trigger=')));
        continue;
    }
    if (strpos($arg, '--priority=') === 0) {
        $priority = trim(substr($arg, strlen('--priority=')));
        continue;
    }
    if ($arg === '--force') {
        $force = true;
        continue;
    }

    $usage();
}

if ($planId === '' || $skill === '') {
    $usage();
}

if (!preg_match('/^[A-Za-z0-9][A-Za-z0-9._-]*$/', $planId)) {
    cli_usage("Invalid plan id: {$planId}");
}
if (!preg_match('/^[A-Za-z0-9][A-Za-z0-9._-]*$/', $skill)) {
    cli_usage("Invalid skill name: {$skill}");
}

$allowedTriggers = ['execplan_completed', 'user_feedback', 'failure_threshold_exceeded'];
if (!in_array($trigger, $allowedTriggers, true)) {
    $usage();
}

$allowedPriorities = ['low', 'normal', 'high'];
if (!in_array($priority, $allowedPriorities, true)) {
    $usage();
}

$runsRoot = MODX_BASE_PATH . '.agent/runs/';
if (!is_dir($runsRoot) && !mkdir($runsRoot, 0775, true)) {
    cli_usage("Failed to create directory: {$runsRoot}");
}

if ($runId === '') {
    $pattern = '/^' . preg_quote($planId, '/') . '-([0-9]{3})$/';
    $maxSeq = 0;
    $existing = glob($runsRoot . $planId . '-*', GLOB_ONLYDIR);
    if (is_array($existing)) {
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
        if ($skipIfExists && !$force) {
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

$encode = function (array $data): string {
    $json = json_encode($data, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_PRETTY_PRINT);
    if ($json === false) {
        cli_usage('Failed to encode JSON.');
    }
    return $json . PHP_EOL;
};

$writeFile($runDir . 'trace.jsonl', '');
$writeFile($runDir . 'chat.md', "# Chat Log\n\n");
$writeFile($runDir . 'notes.md', "# Notes\n\n");

$writeFile(
    $runDir . 'learning-request.json',
    $encode([
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
    $encode([
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
    $encode([
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
    $encode([
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
    $encode([
        'items' => [],
    ]),
    false,
    true
);

$writeFile(
    $skillDir . 'stats.json',
    $encode([
        'items' => [],
    ]),
    false,
    true
);

$writeFile($skillDir . 'history.jsonl', '', false, true);

cli_out("Created run scaffold: {$runDir}");
cli_out("Created skill metadata: {$skillDir}");
