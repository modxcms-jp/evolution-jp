<?php

$runDir = '';
$planId = '';
$runId = '';
$skill = '';
$strict = false;

$usage = function () {
    cli_usage('Usage: php evo skill:validate --run-dir=PATH | --plan=PLAN_ID --run-id=RUN_ID [--skill=SKILL] [--strict]');
};

$validateIdentifier = function (string $value, string $label) {
    if (!preg_match('/^[A-Za-z0-9][A-Za-z0-9._-]*$/', $value)) {
        cli_usage("Invalid {$label}: {$value}");
    }
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

    $usage();
}

if ($runDir === '') {
    if ($planId === '' || $runId === '') {
        $usage();
    }
    $validateIdentifier($planId, 'plan id');
    $validateIdentifier($runId, 'run id');
    $runDir = MODX_BASE_PATH . '.agent/runs/' . $runId . '/';
}

$runDir = rtrim($runDir, "/\\") . '/';

if (!is_dir($runDir)) {
    cli_usage("Run directory not found: {$runDir}");
}

$errors = [];

if ($planId !== '') {
    $validateIdentifier($planId, 'plan id');
}
if ($runId !== '') {
    $validateIdentifier($runId, 'run id');
}
if ($skill !== '') {
    $validateIdentifier($skill, 'skill name');
}

$validateAllowed = function ($value, array $allowed, string $label) use (&$errors) {
    if (!in_array($value, $allowed, true)) {
        $errors[] = "{$label} invalid value: " . (is_scalar($value) ? (string)$value : gettype($value));
    }
};

$validateRequiredKeys = function (array $data, array $keys, string $label) use (&$errors) {
    foreach ($keys as $key) {
        if (!array_key_exists($key, $data)) {
            $errors[] = "{$label} missing key: {$key}";
        }
    }
};

$validatePathList = function ($value, string $label) use (&$errors) {
    if (!is_array($value)) {
        $errors[] = "{$label} must be an array";
        return;
    }

    foreach ($value as $item) {
        if (!is_string($item) || $item === '' || str_starts_with($item, '/') || strpos($item, '..') !== false) {
            $errors[] = "{$label} contains invalid path: " . json_encode($item, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        }
    }
};

$readJson = function (string $path, string $label) use (&$errors) {
    if (!is_file($path)) {
        $errors[] = "{$label} missing: {$path}";
        return null;
    }

    $raw = file_get_contents($path);
    if ($raw === false) {
        $errors[] = "{$label} unreadable: {$path}";
        return null;
    }

    $data = json_decode($raw, true);
    if (!is_array($data)) {
        $errors[] = "{$label} invalid JSON: {$path}";
        return null;
    }

    return $data;
};

$tracePath = $runDir . 'trace.jsonl';
if (is_file($tracePath)) {
    $lines = file($tracePath, FILE_IGNORE_NEW_LINES);
    if ($lines === false) {
        $errors[] = "trace.jsonl unreadable: {$tracePath}";
    } else {
        foreach ($lines as $lineNo => $line) {
            $line = trim($line);
            if ($line === '') {
                continue;
            }

            $event = json_decode($line, true);
            if (!is_array($event)) {
                $errors[] = 'trace.jsonl invalid JSON at line ' . ($lineNo + 1);
                continue;
            }

            $validateRequiredKeys($event, ['ts', 'plan_id', 'run_id', 'agent', 'skill', 'type', 'summary'], 'trace event');
            $validateAllowed($event['type'] ?? null, ['step', 'decision', 'error', 'feedback', 'result'], 'trace event type');
            $validateAllowed($event['agent'] ?? null, ['worker', 'explorer', 'reviewer', 'planner', 'user', 'system'], 'trace agent');

            if (($event['type'] ?? '') === 'step') {
                $validateRequiredKeys($event, ['action', 'status'], 'trace step event');
                $validateAllowed($event['status'] ?? null, ['started', 'ok', 'failed', 'blocked', 'done'], 'trace step status');
            }

            if (($event['type'] ?? '') === 'decision') {
                $validateRequiredKeys($event, ['category'], 'trace decision event');
            }

            if (($event['type'] ?? '') === 'error') {
                $validateRequiredKeys($event, ['failure_mode', 'status'], 'trace error event');
                $validateAllowed($event['failure_mode'] ?? null, ['bad_assumption', 'missing_instruction', 'missing_reference', 'repeated_manual_work', 'tool_gap', 'validation_gap'], 'trace failure_mode');
                $validateAllowed($event['status'] ?? null, ['started', 'ok', 'failed', 'blocked', 'done'], 'trace error status');
            }

            if (($event['type'] ?? '') === 'feedback') {
                $validateRequiredKeys($event, ['feedback_type', 'source'], 'trace feedback event');
                $validateAllowed($event['feedback_type'] ?? null, ['direction_change', 'rework_request', 'scope_change', 'priority_change'], 'trace feedback_type');
            }

            if (($event['type'] ?? '') === 'result') {
                $validateRequiredKeys($event, ['status'], 'trace result event');
                $validateAllowed($event['status'] ?? null, ['started', 'ok', 'failed', 'blocked', 'done'], 'trace result status');
            }
        }
    }
} elseif ($strict) {
    $errors[] = "trace.jsonl missing: {$tracePath}";
}

$learningRequest = $readJson($runDir . 'learning-request.json', 'learning-request.json');
if (is_array($learningRequest)) {
    $validateRequiredKeys($learningRequest, ['version', 'plan_id', 'run_id', 'skill', 'trigger', 'requested_at', 'status', 'priority', 'reason_summary', 'evidence'], 'learning-request.json');
    $trigger = (string)($learningRequest['trigger'] ?? '');
    $validateAllowed($trigger, ['execplan_completed', 'user_feedback', 'failure_threshold_exceeded'], 'learning-request trigger');
    $validateAllowed($learningRequest['status'] ?? null, ['pending', 'processing', 'completed', 'skipped'], 'learning-request status');
    $validateAllowed($learningRequest['priority'] ?? null, ['low', 'normal', 'high'], 'learning-request priority');
    $evidence = $learningRequest['evidence'] ?? null;
    $validatePathList($evidence, 'learning-request evidence');
    if (is_array($evidence)) {
        if (!in_array('trace.jsonl', $evidence, true)) {
            $errors[] = 'learning-request evidence must include trace.jsonl';
        }
        if ($trigger === 'user_feedback' && !in_array('chat.md', $evidence, true)) {
            $errors[] = 'learning-request evidence must include chat.md for user_feedback';
        }
        if ($trigger === 'failure_threshold_exceeded' && !in_array('notes.md', $evidence, true)) {
            $errors[] = 'learning-request evidence must include notes.md for failure_threshold_exceeded';
        }
        $allowedEvidence = ['trace.jsonl', 'chat.md', 'learning.json', 'pruning.json', 'proposal.json', 'notes.md'];
        foreach ($evidence as $item) {
            if (is_string($item) && !in_array($item, $allowedEvidence, true)) {
                $errors[] = "learning-request evidence contains invalid file: {$item}";
            }
        }
    }
    if ($planId !== '' && (string)($learningRequest['plan_id'] ?? '') !== $planId) {
        $errors[] = 'learning-request.json plan_id does not match CLI plan id';
    }
    if ($runId !== '' && (string)($learningRequest['run_id'] ?? '') !== $runId) {
        $errors[] = 'learning-request.json run_id does not match CLI run id';
    }
    if ($skill !== '' && (string)($learningRequest['skill'] ?? '') !== $skill) {
        $errors[] = 'learning-request.json skill does not match CLI skill';
    }
}

$learning = $readJson($runDir . 'learning.json', 'learning.json');
if (is_array($learning)) {
    $validateRequiredKeys($learning, ['version', 'plan_id', 'run_id', 'skill', 'generated_at', 'request_ref', 'outcome', 'findings'], 'learning.json');
    $validateAllowed($learning['outcome'] ?? null, ['success', 'success_with_rework', 'partial', 'failed', 'cancelled'], 'learning outcome');
    if (!is_array($learning['findings'] ?? null)) {
        $errors[] = 'learning.json findings must be an array';
    }
}

$pruning = $readJson($runDir . 'pruning.json', 'pruning.json');
if (is_array($pruning)) {
    $validateRequiredKeys($pruning, ['version', 'plan_id', 'run_id', 'skill', 'generated_at', 'budget', 'items'], 'pruning.json');
    if (is_array($pruning['budget'] ?? null)) {
        $validateRequiredKeys($pruning['budget'], ['skill_md_max_lines', 'max_loaded_references'], 'pruning budget');
    } else {
        $errors[] = 'pruning.json budget must be an array';
    }
    if (!is_array($pruning['items'] ?? null)) {
        $errors[] = 'pruning.json items must be an array';
    }
}

$proposal = $readJson($runDir . 'proposal.json', 'proposal.json');
if (is_array($proposal)) {
    $validateRequiredKeys($proposal, ['version', 'plan_id', 'run_id', 'skill', 'generated_at', 'status', 'source_files', 'changes'], 'proposal.json');
    $validateAllowed($proposal['status'] ?? null, ['proposed', 'approved', 'rejected', 'applied', 'archived'], 'proposal status');
    $validatePathList($proposal['source_files'] ?? null, 'proposal source_files');
    if (!is_array($proposal['changes'] ?? null)) {
        $errors[] = 'proposal.json changes must be an array';
    }
    if ($planId !== '' && (string)($proposal['plan_id'] ?? '') !== $planId) {
        $errors[] = 'proposal.json plan_id does not match CLI plan id';
    }
    if ($runId !== '' && (string)($proposal['run_id'] ?? '') !== $runId) {
        $errors[] = 'proposal.json run_id does not match CLI run id';
    }
    if ($skill !== '' && (string)($proposal['skill'] ?? '') !== $skill) {
        $errors[] = 'proposal.json skill does not match CLI skill';
    }
}

if ($skill !== '') {
    $skillDir = MODX_BASE_PATH . '.agent/skill-metadata/' . $skill . '/';
    if (!is_dir($skillDir)) {
        $errors[] = "skill metadata missing: {$skillDir}";
    } else {
        $inventory = $readJson($skillDir . 'inventory.json', 'inventory.json');
        if (is_array($inventory) && !is_array($inventory['items'] ?? null)) {
            $errors[] = 'inventory.json items must be an array';
        }

        $stats = $readJson($skillDir . 'stats.json', 'stats.json');
        if (is_array($stats) && !is_array($stats['items'] ?? null)) {
            $errors[] = 'stats.json items must be an array';
        }

        $historyPath = $skillDir . 'history.jsonl';
        if (!is_file($historyPath) && $strict) {
            $errors[] = "history.jsonl missing: {$historyPath}";
        }
    }
}

if ($errors) {
    foreach ($errors as $error) {
        cli_err($error);
    }
    exit(1);
}

cli_kv('validated', true);
cli_kv('run_dir', $runDir);
if ($skill !== '') {
    cli_kv('skill', $skill);
}
cli_kv('files', ['trace.jsonl', 'learning-request.json', 'learning.json', 'pruning.json', 'proposal.json']);
if ($strict) {
    cli_kv('strict', true);
}
