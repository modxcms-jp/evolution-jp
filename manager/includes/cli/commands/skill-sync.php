<?php

$skill = '';
$planId = '';
$json = false;
$dryRun = false;

$usage = function () {
    cli_usage('Usage: php evo skill:sync [--skill=SKILL] [--plan=PLAN_ID] [--json] [--dry-run]');
};

foreach ($args as $arg) {
    if (strpos($arg, '--skill=') === 0) {
        $skill = trim(substr($arg, strlen('--skill=')));
        continue;
    }
    if (strpos($arg, '--plan=') === 0) {
        $planId = trim(substr($arg, strlen('--plan=')));
        continue;
    }
    if ($arg === '--json') {
        $json = true;
        continue;
    }
    if ($arg === '--dry-run') {
        $dryRun = true;
        continue;
    }

    $usage();
}

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
            if ($name === 'templates') {
                continue;
            }
            $skills[] = $name;
        }
    }
}

sort($skills, SORT_STRING);

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

$writeJson = function (string $path, array $data) use ($dryRun) {
    if ($dryRun) {
        return;
    }
    $json = json_encode($data, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_PRETTY_PRINT);
    if ($json === false) {
        cli_usage("Failed to encode JSON: {$path}");
    }
    if (file_put_contents($path, $json . PHP_EOL) === false) {
        cli_usage("Failed to write: {$path}");
    }
    chmod($path, 0644);
};

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

$isRelevantRun = function (array $request, string $currentSkill) use ($skill, $planId) {
    $requestSkill = (string)($request['skill'] ?? '');
    $expectedSkill = $skill !== '' ? $skill : $currentSkill;
    if ($requestSkill !== $expectedSkill) {
        return false;
    }
    if ($planId !== '' && (string)($request['plan_id'] ?? '') !== $planId) {
        return false;
    }
    return true;
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

foreach ($skills as $skillName) {
    $skillDir = $metaRoot . $skillName . '/';
    $inventory = $readJson($skillDir . 'inventory.json');
    $stats = $readJson($skillDir . 'stats.json');
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
    $runDirs = [];
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
            if ($baseName === 'templates' || $baseName === 'archive') {
                continue;
            }
            $runDirs[] = $dir;
        }
    }

    sort($runDirs, SORT_STRING);

    foreach ($runDirs as $runDir) {
        $summary['runs_scanned']++;
        $request = $readJson($runDir . '/learning-request.json');
        $proposal = $readJson($runDir . '/proposal.json');
        if (!is_array($request) || !is_array($proposal)) {
            continue;
        }
        if (!$isRelevantRun($request, $skillName)) {
            continue;
        }

        $matchedRuns++;
        $runId = basename($runDir);
        $runSkill = (string)($request['skill'] ?? '');
        $runPlanId = (string)($request['plan_id'] ?? '');
        $requestStatus = (string)($request['status'] ?? 'missing');
        $proposalStatus = (string)($proposal['status'] ?? 'missing');
        $isArchived = str_starts_with($runDir, MODX_BASE_PATH . '.agent/runs/archive/');
        if ($isArchived) {
            $archivedRuns++;
        } else {
            $activeRuns++;
        }

        if ($proposalStatus === 'rejected') {
            continue;
        }

        $changes = is_array($proposal['changes'] ?? null) ? $proposal['changes'] : [];
        foreach ($changes as $change) {
            if (!is_array($change)) {
                continue;
            }
            $itemId = (string)($change['target'] ?? '');
            if ($itemId === '') {
                continue;
            }
            $action = (string)($change['action'] ?? '');
            $location = (string)($change['destination'] ?? $change['target']);
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

            $runItemStats[$itemId]['seen_runs']++;
            if (in_array($action, ['add', 'move', 'merge', 'script_extraction'], true)) {
                $runItemStats[$itemId]['used_runs']++;
                $runItemStats[$itemId]['helped_runs']++;
                $runItemStats[$itemId]['last_used_at'] = $proposalTs;
                $runItemStats[$itemId]['source'] = $runId;
            }
            if ($action === 'retire') {
                $runItemStats[$itemId]['stale_runs']++;
            }
            if ($proposalStatus === 'rejected') {
                $runItemStats[$itemId]['conflicted_runs']++;
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
        'seen_runs' => $matchedRuns,
        'used_runs' => $proposalChanges,
        'helped_runs' => $proposalChanges,
        'conflicted_runs' => 0,
        'stale_runs' => 0,
        'archived_runs' => $archivedRuns,
        'active_runs' => $activeRuns,
    ];

    $writeJson($skillDir . 'inventory.json', ['items' => $inventoryList]);
    $writeJson($skillDir . 'stats.json', ['items' => $statsItems]);

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
