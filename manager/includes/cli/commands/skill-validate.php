<?php

require_once __DIR__ . '/../skill-lib.php';

$usage = function () {
    cli_usage('Usage: php evo skill:validate --run-dir=PATH | --plan=PLAN_ID --run-id=RUN_ID [--skill=SKILL] [--strict]');
};

$runDir = skill_get_arg($args, 'run-dir', '');
$planId = skill_get_arg($args, 'plan', '');
$runId = skill_get_arg($args, 'run-id', '');
$skill = skill_get_arg($args, 'skill', '');
$strict = skill_has_flag($args, 'strict');

foreach ($args as $arg) {
    if (strpos($arg, '--run-dir=') === 0 || strpos($arg, '--plan=') === 0 || strpos($arg, '--run-id=') === 0 || strpos($arg, '--skill=') === 0 || $arg === '--strict') {
        continue;
    }

    $usage();
}

if ($runDir === '') {
    if ($planId === '' || $runId === '') {
        $usage();
    }
    skill_validate_identifier($planId, 'plan id');
    skill_validate_identifier($runId, 'run id');
    $runDir = MODX_BASE_PATH . '.agent/runs/' . $runId . '/';
} else {
    if ($runId === '') {
        $runId = basename(rtrim($runDir, "/\\"));
    }
}

$runDir = rtrim($runDir, "/\\") . '/';

if (!is_dir($runDir)) {
    cli_usage("Run directory not found: {$runDir}");
}

$errors = [];

if ($planId !== '') {
    skill_validate_identifier($planId, 'plan id');
}
if ($runId !== '') {
    skill_validate_identifier($runId, 'run id');
}
if ($skill !== '') {
    skill_validate_skill_name($skill);
}


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

            skill_validate_required_keys($event, ['ts', 'plan_id', 'run_id', 'agent', 'skill', 'type', 'summary'], 'trace event', $errors);
            skill_validate_allowed($event['type'] ?? null, SKILL_TRACE_EVENT_TYPES, 'trace event type', $errors);
            skill_validate_allowed($event['agent'] ?? null, SKILL_TRACE_AGENTS, 'trace agent', $errors);

            if (($event['type'] ?? '') === 'step') {
                skill_validate_required_keys($event, ['action', 'status'], 'trace step event', $errors);
                skill_validate_allowed($event['status'] ?? null, SKILL_TRACE_STATUSES, 'trace step status', $errors);
            }

            if (($event['type'] ?? '') === 'decision') {
                skill_validate_required_keys($event, ['category'], 'trace decision event', $errors);
            }

            if (($event['type'] ?? '') === 'error') {
                skill_validate_required_keys($event, ['failure_mode', 'status'], 'trace error event', $errors);
                skill_validate_allowed($event['failure_mode'] ?? null, SKILL_FAILURE_MODES, 'trace failure_mode', $errors);
                skill_validate_allowed($event['status'] ?? null, SKILL_TRACE_STATUSES, 'trace error status', $errors);
            }

            if (($event['type'] ?? '') === 'feedback') {
                skill_validate_required_keys($event, ['feedback_type', 'source'], 'trace feedback event', $errors);
                skill_validate_allowed($event['feedback_type'] ?? null, SKILL_FEEDBACK_TYPES, 'trace feedback_type', $errors);
            }

            if (($event['type'] ?? '') === 'result') {
                skill_validate_required_keys($event, ['status'], 'trace result event', $errors);
                skill_validate_allowed($event['status'] ?? null, SKILL_TRACE_STATUSES, 'trace result status', $errors);
            }
        }
    }
} elseif ($strict) {
    $errors[] = "trace.jsonl missing: {$tracePath}";
}

$learningRequest = skill_read_json_with_errors($runDir . 'learning-request.json', 'learning-request.json', $errors);

// 自律性向上: --skillが未指定でもlearning-request.jsonから取得
if ($skill === '' && is_array($learningRequest)) {
    $learningRequestSkill = $learningRequest['skill'] ?? null;
    if (is_string($learningRequestSkill)) {
        $skill = $learningRequestSkill;
        if ($skill === '') {
            $errors[] = 'learning-request.json skill must not be empty';
        } else {
            if (!skill_is_valid_identifier($skill)) {
                $errors[] = 'learning-request.json skill has invalid format: ' . $skill;
            }
            if (in_array($skill, SKILL_RESERVED_NAMES, true)) {
                $errors[] = 'learning-request.json skill is reserved: ' . $skill;
            }
        }
    } elseif ($learningRequestSkill !== null) {
        $errors[] = 'learning-request.json skill must be a string';
    }
}

if (is_array($learningRequest)) {
    skill_validate_required_keys($learningRequest, ['version', 'plan_id', 'run_id', 'skill', 'trigger', 'requested_at', 'status', 'priority', 'reason_summary', 'evidence'], 'learning-request.json', $errors);

    $jsonPlanId = $learningRequest['plan_id'] ?? null;
    if (is_string($jsonPlanId) && $jsonPlanId !== '') {
        if (!skill_is_valid_identifier($jsonPlanId)) {
            $errors[] = 'learning-request.json plan_id has invalid format: ' . $jsonPlanId;
        }
    } elseif ($jsonPlanId !== null) {
        $errors[] = 'learning-request.json plan_id must be a string';
    }

    $jsonRunId = $learningRequest['run_id'] ?? null;
    if (is_string($jsonRunId) && $jsonRunId !== '') {
        if (!skill_is_valid_identifier($jsonRunId)) {
            $errors[] = 'learning-request.json run_id has invalid format: ' . $jsonRunId;
        }
    } elseif ($jsonRunId !== null) {
        $errors[] = 'learning-request.json run_id must be a string';
    }

    $triggerValue = $learningRequest['trigger'] ?? null;
    skill_validate_allowed($triggerValue, SKILL_TRIGGERS, 'learning-request trigger', $errors);
    $trigger = is_string($triggerValue) ? $triggerValue : '';
    skill_validate_allowed($learningRequest['status'] ?? null, SKILL_REQUEST_STATUSES, 'learning-request status', $errors);
    skill_validate_allowed($learningRequest['priority'] ?? null, SKILL_PRIORITIES, 'learning-request priority', $errors);
    $evidence = $learningRequest['evidence'] ?? null;
    skill_validate_path_list($evidence, 'learning-request evidence', $errors);
    if (is_array($evidence)) {
        if (!in_array('trace.jsonl', $evidence, true)) {
            $errors[] = 'learning-request evidence must include trace.jsonl';
        }
        if ($trigger === 'user_feedback' && !in_array('chat.md', $evidence, true)) {
            $errors[] = 'learning-request evidence must include chat.md for user_feedback';
        }
        if ($trigger === 'user_feedback' && $strict && in_array('chat.md', $evidence, true) && !is_file($runDir . 'chat.md')) {
            $errors[] = "learning-request evidence file missing: {$runDir}chat.md";
        }
        foreach ($evidence as $item) {
            if (is_string($item) && !in_array($item, SKILL_ALLOWED_EVIDENCE, true)) {
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
    if ($skill === '' && (string)($learningRequest['skill'] ?? '') === 'templates') {
        $errors[] = 'learning-request.json skill is reserved: templates';
    }
}

$learning = skill_read_json_with_errors($runDir . 'learning.json', 'learning.json', $errors);
if (is_array($learning)) {
    skill_validate_required_keys($learning, ['version', 'plan_id', 'run_id', 'skill', 'generated_at', 'request_ref', 'outcome', 'findings'], 'learning.json', $errors);
    skill_validate_allowed($learning['outcome'] ?? null, SKILL_OUTCOMES, 'learning outcome', $errors);
    if (!is_array($learning['findings'] ?? null)) {
        $errors[] = 'learning.json findings must be an array';
    }
}

$pruning = skill_read_json_with_errors($runDir . 'pruning.json', 'pruning.json', $errors);
if (is_array($pruning)) {
    skill_validate_required_keys($pruning, ['version', 'plan_id', 'run_id', 'skill', 'generated_at', 'budget', 'items'], 'pruning.json', $errors);
    if (is_array($pruning['budget'] ?? null)) {
        skill_validate_required_keys($pruning['budget'], ['skill_md_max_lines', 'max_loaded_references'], 'pruning budget', $errors);
    } else {
        $errors[] = 'pruning.json budget must be an array';
    }
    if (!is_array($pruning['items'] ?? null)) {
        $errors[] = 'pruning.json items must be an array';
    }
}

$proposal = skill_read_json_with_errors($runDir . 'proposal.json', 'proposal.json', $errors);
if (is_array($proposal)) {
    skill_validate_required_keys($proposal, ['version', 'plan_id', 'run_id', 'skill', 'generated_at', 'status', 'source_files', 'changes'], 'proposal.json', $errors);
    skill_validate_allowed($proposal['status'] ?? null, SKILL_PROPOSAL_STATUSES, 'proposal status', $errors);
    skill_validate_path_list($proposal['source_files'] ?? null, 'proposal source_files', $errors);
    if (!is_array($proposal['changes'] ?? null)) {
        $errors[] = 'proposal.json changes must be an array';
    } else {
        $proposalChangeActions = defined('SKILL_CHANGE_ACTIONS') ? constant('SKILL_CHANGE_ACTIONS') : null;
        foreach ($proposal['changes'] as $index => $change) {
            if (!is_array($change)) {
                $errors[] = 'proposal.json changes[' . $index . '] must be an array';
                continue;
            }
            if (!array_key_exists('action', $change)) {
                $errors[] = 'proposal.json changes[' . $index . '].action is required';
                continue;
            }
            if (!is_string($change['action']) || trim($change['action']) === '') {
                $errors[] = 'proposal.json changes[' . $index . '].action must be a non-empty string';
                continue;
            }
            if (is_array($proposalChangeActions)) {
                skill_validate_allowed($change['action'], $proposalChangeActions, 'proposal changes[' . $index . '].action', $errors);
            }
        }
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
    if ($skill === '' && (string)($proposal['skill'] ?? '') === 'templates') {
        $errors[] = 'proposal.json skill is reserved: templates';
    }
}

if ($skill !== '') {
    $skillDir = MODX_BASE_PATH . '.agent/skill-metadata/' . $skill . '/';
    if (!is_dir($skillDir)) {
        $errors[] = "skill metadata missing: {$skillDir}";
    } else {
        $inventory = skill_read_json_with_errors($skillDir . 'inventory.json', 'inventory.json', $errors);
        if (is_array($inventory) && !is_array($inventory['items'] ?? null)) {
            $errors[] = 'inventory.json items must be an array';
        }

        $stats = skill_read_json_with_errors($skillDir . 'stats.json', 'stats.json', $errors);
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
