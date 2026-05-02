<?php

$skill = '';
$limit = 50;
$json = false;
$minSeen = 10;
$minUsedRatio = 0.2;
$staleRunsThreshold = 2;

$usage = function () {
    cli_usage('Usage: php evo skill:prune [--skill=SKILL] [--limit=N] [--json] [--min-seen=N] [--min-used-ratio=R] [--stale-runs=N]');
};

foreach ($args as $arg) {
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
    if (strpos($arg, '--min-seen=') === 0) {
        $minSeen = max(1, (int)substr($arg, strlen('--min-seen=')));
        continue;
    }
    if (strpos($arg, '--min-used-ratio=') === 0) {
        $minUsedRatio = max(0.0, min(1.0, (float)substr($arg, strlen('--min-used-ratio='))));
        continue;
    }
    if (strpos($arg, '--stale-runs=') === 0) {
        $staleRunsThreshold = max(1, (int)substr($arg, strlen('--stale-runs=')));
        continue;
    }

    $usage();
}

$metaRoot = MODX_BASE_PATH . '.agent/skill-metadata/';
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

$candidates = [];
foreach ($skills as $skillName) {
    $skillDir = $metaRoot . $skillName . '/';
    $inventory = $readJson($skillDir . 'inventory.json');
    $stats = $readJson($skillDir . 'stats.json');
    $historyPath = $skillDir . 'history.jsonl';

    if (!is_array($stats) || !is_array($stats['items'] ?? null)) {
        continue;
    }

    $historyCount = 0;
    if (is_file($historyPath)) {
        $historyLines = file($historyPath, FILE_IGNORE_NEW_LINES);
        if (is_array($historyLines)) {
            foreach ($historyLines as $line) {
                if (trim($line) !== '') {
                    $historyCount++;
                }
            }
        }
    }

    foreach ($stats['items'] as $itemId => $itemStats) {
        if (str_starts_with((string)$itemId, '__')) {
            continue;
        }
        if (!is_array($itemStats)) {
            continue;
        }

        $seenRuns = (int)($itemStats['seen_runs'] ?? 0);
        $usedRuns = (int)($itemStats['used_runs'] ?? 0);
        $helpedRuns = (int)($itemStats['helped_runs'] ?? 0);
        $conflictedRuns = (int)($itemStats['conflicted_runs'] ?? 0);
        $staleRuns = (int)($itemStats['stale_runs'] ?? 0);

        $usedRatio = $seenRuns > 0 ? ($usedRuns / $seenRuns) : 0.0;
        $isStale = false;
        $reasons = [];

        if ($seenRuns >= $minSeen && $usedRuns === 0) {
            $isStale = true;
            $reasons[] = "seen_runs >= {$minSeen} and used_runs = 0";
        }
        if ($usedRuns > 0 && $usedRatio < $minUsedRatio) {
            $isStale = true;
            $reasons[] = sprintf('used/seen ratio %.2f < %.2f', $usedRatio, $minUsedRatio);
        }
        if ($staleRuns >= $staleRunsThreshold) {
            $isStale = true;
            $reasons[] = "stale_runs >= {$staleRunsThreshold}";
        }
        if ($conflictedRuns > $usedRuns) {
            $isStale = true;
            $reasons[] = 'conflicted_runs exceed used_runs';
        }

        if (!$isStale) {
            continue;
        }

        $location = '';
        $target = 'merge';
        if (is_array($inventory) && is_array($inventory['items'] ?? null)) {
            foreach ($inventory['items'] as $item) {
                if (!is_array($item) || (string)($item['id'] ?? '') !== $itemId) {
                    continue;
                }
                $location = (string)($item['location'] ?? '');
                break;
            }
        }

        if ($location !== '' && strpos($location, 'SKILL.md') !== false) {
            $target = 'move';
        }
        if ($usedRuns === 0 && $seenRuns >= $minSeen) {
            $target = 'retire';
        }

        $candidates[] = [
            'skill' => $skillName,
            'item_id' => $itemId,
            'action' => $target,
            'seen_runs' => $seenRuns,
            'used_runs' => $usedRuns,
            'helped_runs' => $helpedRuns,
            'conflicted_runs' => $conflictedRuns,
            'stale_runs' => $staleRuns,
            'used_ratio' => $usedRuns > 0 ? round($usedRatio, 3) : 0.0,
            'history_count' => $historyCount,
            'reasons' => $reasons,
        ];

        if (count($candidates) >= $limit) {
            break 2;
        }
    }
}

if ($json) {
    cli_out(json_encode($candidates, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_PRETTY_PRINT));
    exit(0);
}

if (!$candidates) {
    cli_out('(no stale candidates)');
    exit(0);
}

foreach ($candidates as $candidate) {
    cli_out(sprintf(
        '%s item=%s action=%s seen=%d used=%d helped=%d stale=%d ratio=%.2f',
        $candidate['skill'],
        $candidate['item_id'],
        $candidate['action'],
        $candidate['seen_runs'],
        $candidate['used_runs'],
        $candidate['helped_runs'],
        $candidate['stale_runs'],
        $candidate['used_ratio']
    ));
    foreach ($candidate['reasons'] as $reason) {
        cli_out('  - ' . $reason);
    }
}
