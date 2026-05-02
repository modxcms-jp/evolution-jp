<?php

require_once __DIR__ . '/../skill-lib.php';

$usage = function () {
    cli_usage('Usage: php evo skill:sync [--skill=SKILL] [--plan=PLAN_ID] [--json] [--dry-run]');
};

$skill = skill_get_arg($args, 'skill', '');
$planId = skill_get_arg($args, 'plan', '');
$json = skill_has_flag($args, 'json');
$dryRun = skill_has_flag($args, 'dry-run');

foreach ($args as $arg) {
    if (strpos($arg, '--skill=') === 0 || strpos($arg, '--plan=') === 0 || $arg === '--json' || $arg === '--dry-run') {
        continue;
    }

    $usage();
}

skill_validate_skill_name($skill, true);
skill_validate_identifier($planId, 'plan id', true);

$metaRoot = MODX_BASE_PATH . '.agent/skill-metadata/';
$runsRoots = [
    MODX_BASE_PATH . '.agent/runs/',
    MODX_BASE_PATH . '.agent/runs/archive/',
];

if (!is_dir($metaRoot)) {
    cli_usage("Skill metadata directory not found: {$metaRoot}");
}

$skills = [];
if ($skill !== '') {
    $skills = [$skill];
} else {
    $entries = glob($metaRoot . '*', GLOB_ONLYDIR);
    if (is_array($entries)) {
        foreach ($entries as $entry) {
            $name = basename($entry);
            if (in_array($name, SKILL_RESERVED_DIRS, true)) {
                continue;
            }
            $skills[] = $name;
        }
    }
}

sort($skills, SORT_STRING);

$skillDirs = [];
foreach ($skills as $skillName) {
    $skillDir = $metaRoot . $skillName . '/';
    if (!is_dir($skillDir)) {
        if ($skill !== '') {
            cli_usage("Skill metadata directory not found: {$skillDir}");
        }
        continue;
    }

    $skillDirs[] = [
        'name' => $skillName,
        'dir' => $skillDir,
    ];
}

$runRecords = [];
foreach ($runsRoots as $runRoot) {
    if (!is_dir($runRoot)) {
        continue;
    }

    $dirs = glob($runRoot . '*', GLOB_ONLYDIR);
    if (!is_array($dirs)) {
        continue;
    }

    foreach ($dirs as $dir) {
        $baseName = basename($dir);
        if (in_array($baseName, SKILL_RESERVED_DIRS, true)) {
            continue;
        }

        $request = null;
        $proposal = null;
        $requestPath = $dir . '/learning-request.json';
        $proposalPath = $dir . '/proposal.json';
        if (is_file($requestPath)) {
            $request = json_decode((string)file_get_contents($requestPath), true);
        }
        if (is_file($proposalPath)) {
            $proposal = json_decode((string)file_get_contents($proposalPath), true);
        }
        if (!is_array($request) || !is_array($proposal)) {
            continue;
        }

        $runRecords[] = [
            'run_dir' => $dir,
            'run_id' => basename($dir),
            'request' => $request,
            'proposal' => $proposal,
            'archived' => str_starts_with($runRoot, MODX_BASE_PATH . '.agent/runs/archive/'),
        ];
    }
}

$writeText = function (string $path, string $content, bool $append = false) use ($dryRun) {
    if ($dryRun) {
        return;
    }
    $flags = $append ? FILE_APPEND : 0;
    if (file_put_contents($path, $content, $flags) === false) {
        cli_usage("Failed to write: {$path}");
    }
    chmod($path, 0644);
};

$summary = [
    'skills' => [],
    'runs_scanned' => 0,
    'runs_matched' => 0,
    'archived_runs' => 0,
    'active_runs' => 0,
    'proposal_changes' => 0,
    'history_appended' => 0,
    'inventory_items' => 0,
    'stats_items' => 0,
];

$summary['runs_scanned'] = count($runRecords);

foreach ($skillDirs as $skillInfo) {
    $skillName = $skillInfo['name'];
    $skillDir = $skillInfo['dir'];
    $inventory = skill_read_json($skillDir . 'inventory.json');
    $stats = skill_read_json($skillDir . 'stats.json');
    $historyPath = $skillDir . 'history.jsonl';
    $existingHistory = [];
    if (is_file($historyPath)) {
        $historyLines = file($historyPath, FILE_IGNORE_NEW_LINES);
        if (is_array($historyLines)) {
            foreach ($historyLines as $line) {
                $line = trim($line);
                if ($line === '') {
                    continue;
                }
                $entry = json_decode($line, true);
                if (is_array($entry)) {
                    $existingHistory[(string)($entry['run_id'] ?? '') . '|' . (string)($entry['item_id'] ?? '') . '|' . (string)($entry['action'] ?? '')] = true;
                }
            }
        }
    }

    $inventoryItems = [];
    if (is_array($inventory) && is_array($inventory['items'] ?? null)) {
        foreach ($inventory['items'] as $item) {
            if (!is_array($item) || (string)($item['id'] ?? '') === '') {
                continue;
            }
            $inventoryItems[(string)$item['id']] = $item;
        }
    }

    $statsItems = [];
    if (is_array($stats) && is_array($stats['items'] ?? null)) {
        $statsItems = $stats['items'];
    }

    $matchedRuns = 0;
    $archivedRuns = 0;
    $activeRuns = 0;
    $proposalChanges = 0;
    $runItemStats = [];
    foreach ($runRecords as $record) {
        $request = $record['request'];
        $proposal = $record['proposal'];
        if (!is_array($request) || !is_array($proposal)) {
            continue;
        }
        if ((string)($request['skill'] ?? '') !== $skillName) {
            continue;
        }
        if ($planId !== '' && (string)($request['plan_id'] ?? '') !== $planId) {
            continue;
        }

        $matchedRuns++;
        $runId = (string)($record['run_id'] ?? '');
        $runSkill = (string)($request['skill'] ?? '');
        $runPlanId = (string)($request['plan_id'] ?? '');
        $requestStatus = (string)($request['status'] ?? 'missing');
        $proposalStatus = (string)($proposal['status'] ?? 'missing');
        $isArchived = (bool)($record['archived'] ?? false);
        if ($isArchived) {
            $archivedRuns++;
        } else {
            $activeRuns++;
        }

        $changes = is_array($proposal['changes'] ?? null) ? $proposal['changes'] : [];
        $seenRunItems = [];
        $usedRunItems = [];
        $conflictedRunItems = [];
        foreach ($changes as $change) {
            if (!is_array($change)) {
                continue;
            }
            $target = (string)($change['target'] ?? '');
            $itemId = (string)($change['id'] ?? $target);
            if ($itemId === '') {
                continue;
            }
            $action = (string)($change['action'] ?? '');
            $location = (string)($change['destination'] ?? $target);
            $proposalTs = (string)($proposal['generated_at'] ?? ($request['requested_at'] ?? ''));
            $key = $runId . '|' . $itemId . '|' . $action;

            if (!isset($runItemStats[$itemId])) {
                $runItemStats[$itemId] = [
                    'seen_runs' => 0,
                    'used_runs' => 0,
                    'helped_runs' => 0,
                    'conflicted_runs' => 0,
                    'stale_runs' => 0,
                    'last_used_at' => '',
                    'source' => '',
                ];
            }

            if ($proposalStatus === 'rejected') {
                if (!isset($conflictedRunItems[$itemId])) {
                    $runItemStats[$itemId]['conflicted_runs']++;
                    $conflictedRunItems[$itemId] = true;
                }
                continue;
            }

            if (!isset($seenRunItems[$itemId])) {
                $runItemStats[$itemId]['seen_runs']++;
                $seenRunItems[$itemId] = true;
            }
            if (in_array($action, ['add', 'move', 'merge', 'script_extraction'], true)) {
                if (!isset($usedRunItems[$itemId])) {
                    $runItemStats[$itemId]['used_runs']++;
                    $runItemStats[$itemId]['helped_runs']++;
                    $runItemStats[$itemId]['last_used_at'] = $proposalTs;
                    $runItemStats[$itemId]['source'] = $runId;
                    $usedRunItems[$itemId] = true;
                }
            }
            if ($action === 'retire') {
                $runItemStats[$itemId]['stale_runs']++;
            }

            if (!isset($inventoryItems[$itemId])) {
                $inventoryItems[$itemId] = [
                    'id' => $itemId,
                    'location' => $location,
                    'status' => $action === 'retire' ? 'retired' : 'adopted',
                    'added_at' => $proposalTs,
                    'last_used_at' => $proposalTs,
                    'source' => $runId,
                ];
            } else {
                $inventoryItems[$itemId]['location'] = $location;
                $inventoryItems[$itemId]['status'] = $action === 'retire' ? 'retired' : 'adopted';
                if (($inventoryItems[$itemId]['added_at'] ?? '') === '') {
                    $inventoryItems[$itemId]['added_at'] = $proposalTs;
                }
                $inventoryItems[$itemId]['last_used_at'] = $proposalTs;
                $inventoryItems[$itemId]['source'] = $runId;
            }

            if (!isset($existingHistory[$key])) {
                $summaryText = sprintf(
                    '%s %s %s (%s / %s)',
                    $runId,
                    $action,
                    $itemId,
                    $requestStatus,
                    $proposalStatus
                );
                $historyLine = [
                    'ts' => $proposalTs,
                    'skill' => $runSkill,
                    'plan_id' => $runPlanId,
                    'run_id' => $runId,
                    'item_id' => $itemId,
                    'action' => $action,
                    'source' => $runId,
                    'summary' => $summaryText,
                ];
                $existingHistory[$key] = true;
                $writeText($historyPath, json_encode($historyLine, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) . PHP_EOL, true);
                $summary['history_appended']++;
            }

            $proposalChanges++;
        }
    }

    foreach ($runItemStats as $itemId => $itemStats) {
        $statsItems[$itemId] = array_merge($statsItems[$itemId] ?? [], $itemStats);
    }

    $inventoryList = [];
    foreach ($inventoryItems as $item) {
        $inventoryList[] = $item;
    }
    usort($inventoryList, function (array $a, array $b) {
        return strcmp((string)($a['id'] ?? ''), (string)($b['id'] ?? ''));
    });

    $statsItems['__summary'] = [
        'seen_runs' => 0,
        'used_runs' => 0,
        'helped_runs' => 0,
        'conflicted_runs' => 0,
        'stale_runs' => 0,
        'archived_runs' => $archivedRuns,
        'active_runs' => $activeRuns,
    ];
    foreach ($statsItems as $itemId => $itemStats) {
        if (!is_array($itemStats) || str_starts_with((string)$itemId, '__')) {
            continue;
        }
        $statsItems['__summary']['seen_runs'] += (int)($itemStats['seen_runs'] ?? 0);
        $statsItems['__summary']['used_runs'] += (int)($itemStats['used_runs'] ?? 0);
        $statsItems['__summary']['helped_runs'] += (int)($itemStats['helped_runs'] ?? 0);
        $statsItems['__summary']['conflicted_runs'] += (int)($itemStats['conflicted_runs'] ?? 0);
        $statsItems['__summary']['stale_runs'] += (int)($itemStats['stale_runs'] ?? 0);
    }

    skill_write_json($skillDir . 'inventory.json', ['items' => $inventoryList], $dryRun);
    skill_write_json($skillDir . 'stats.json', ['items' => $statsItems], $dryRun);

    $summary['skills'][] = [
        'skill' => $skillName,
        'runs_matched' => $matchedRuns,
        'archived_runs' => $archivedRuns,
        'active_runs' => $activeRuns,
        'proposal_changes' => $proposalChanges,
        'inventory_items' => count($inventoryList),
        'stats_items' => count($statsItems),
        'dry_run' => $dryRun,
    ];
    $summary['runs_matched'] += $matchedRuns;
    $summary['archived_runs'] += $archivedRuns;
    $summary['active_runs'] += $activeRuns;
    $summary['proposal_changes'] += $proposalChanges;
    $summary['inventory_items'] += count($inventoryList);
    $summary['stats_items'] += count($statsItems);
}

if ($json) {
    cli_out(json_encode($summary, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_PRETTY_PRINT));
    exit(0);
}

foreach ($summary['skills'] as $row) {
    cli_out(sprintf(
        '%s runs=%d archived=%d active=%d proposals=%d inventory=%d stats=%d%s',
        $row['skill'],
        $row['runs_matched'],
        $row['archived_runs'],
        $row['active_runs'],
        $row['proposal_changes'],
        $row['inventory_items'],
        $row['stats_items'],
        $dryRun ? ' dry-run' : ''
    ));
}

if (!$summary['skills']) {
    cli_out('(no skill metadata synced)');
}
