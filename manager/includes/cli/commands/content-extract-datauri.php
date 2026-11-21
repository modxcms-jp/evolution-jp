<?php

require_once MODX_CORE_PATH . 'datauri_store.class.inc.php';
require_once MODX_CORE_PATH . 'backup_manager.class.inc.php';

$usage = 'Usage: php evo.php content:extract-datauri [--dry-run] [--ids=1,2,3] [--limit=N]';

$dryRun = false;
$idsArg = null;
$limit = null;
foreach ($args as $arg) {
    if ($arg === '--dry-run') {
        $dryRun = true;
    } elseif (strpos($arg, '--ids=') === 0) {
        $idsArg = substr($arg, strlen('--ids='));
    } elseif (strpos($arg, '--limit=') === 0) {
        $limitArg = substr($arg, strlen('--limit='));
        if (!preg_match('/^[1-9][0-9]*$/', $limitArg)) {
            cli_usage("Error: --limit must be a positive integer.\n" . $usage);
        }
        $limit = (int)$limitArg;
    } else {
        cli_usage($usage);
    }
}
$ids = [];
if ($idsArg !== null) {
    if (!preg_match('/^\s*\d+(\s*,\s*\d+)*\s*$/', $idsArg)) {
        cli_usage("Error: --ids=1,2,3 must be a comma-separated list of document IDs.\n" . $usage);
    }
    $ids = array_values(array_unique(array_map('intval', explode(',', $idsArg))));
}

// 1 つの文書の、data: 画像が入りうる値の一覧を作る。ID の絞り込みは (int) で作った条件だけを SQL に渡す
$idCondition = function ($column) use ($ids) {
    return $ids ? sprintf(' AND %s IN (%s)', $column, implode(',', $ids)) : '';
};
$candidateIds = [];
$queries = [
    ['id', '[+prefix+]site_content', "(content LIKE '%data:%' OR introtext LIKE '%data:%')" . $idCondition('id')],
    ['contentid AS id', '[+prefix+]site_tmplvar_contentvalues', "value LIKE '%data:%'" . $idCondition('contentid')],
    ['elmid AS id', '[+prefix+]site_revision', "element='resource' AND version=0 AND content LIKE '%data:%'" . $idCondition('elmid')],
];
foreach ($queries as [$fields, $table, $where]) {
    $rs = db()->select($fields, $table, $where);
    while ($row = db()->getRow($rs)) {
        $candidateIds[(int)$row['id']] = true;
    }
}
$candidateIds = array_keys($candidateIds);
sort($candidateIds);

if (!$candidateIds) {
    cli_out('No data: images found.');
    return;
}

// 大きさの上限は、保存時の変換と同じ 50MB（ピッカーの upload_maxsize とは別）。拡張子は upload_images に従う
$store = EvoDataUriStore::create(evo(), EvoDataUri::MAX_BYTES);
if (!($store instanceof EvoDataUriStore)) {
    cli_usage(sprintf('Error: the images folder of the file browser cannot be used (%s: %s); nothing was changed. Check rb_base_dir and that images is not a link to outside of it.', $store['code'], lang($store['lang'])));
}

// 書き換えの前に DB の控えを取る（最初に書く直前に 1 回だけ）。取れなければ何もせずに終了コード 1
$snapshotTaken = false;
$takeSnapshot = function () use (&$snapshotTaken) {
    if ($snapshotTaken) {
        return;
    }
    $backup = new EvoBackupManager();
    $snapshots = $backup->store();
    if (!$snapshots->ensureDir()) {
        if ($snapshots->problem() !== EvoSnapshotStore::UNWRITABLE) {
            cli_usage('Error: the snapshot directory ' . $snapshots->dir() . ' cannot be used (outside the site folder, or a folder that holds other files); nothing was changed. Fix snapshot_path in the global settings.');
        }
        cli_usage('Error: cannot create or write directory ' . $snapshots->dir() . '; nothing was changed.');
    }
    // 下書き（site_revision）も書き換えるので、「コンテンツのみ」ではなく、このサイトの表の全部を控える。
    // 管理画面のスナップショットと同じ PHP の書き手を使う（mysqldump が無い環境でも動かすため）
    $filename = $snapshots->autoFilename();
    if ($backup->createSnapshot($filename) === false) {
        cli_usage('Error: failed to write the backup ' . $snapshots->dir() . $filename . '; nothing was changed.');
    }
    $snapshotTaken = true;
    cli_out('Backup written: ' . $snapshots->dir() . $filename);
    foreach ($backup->pruned() as $removed) {
        cli_out('Removed old backup: ' . $removed);
    }
    $counts = $snapshots->counts();
    cli_out('Backups in ' . $snapshots->dir() . ": auto-named {$counts['auto']}/" . EvoSnapshotStore::KEEP
        . ' (older auto-named backups beyond this limit are removed each time one is taken)');
};

$rows = [];
// 書いたが、DB の更新がまだ済んでいないファイル。DB の更新が messageQuit() で止まったとき（プロセスごと終わる）も、終わりの処理で消す
$pending = [];
register_shutdown_function(function () use (&$pending, $store) {
    if (!$pending) {
        return;
    }
    foreach (array_keys($pending) as $rel) {
        $store->discard($rel);
    }
    cli_err('Error: the database update failed; the files written for it were removed. Re-run the command after fixing the cause.');
    exit(1);
});
$failed = 0;
$conflicts = 0;
$changedDocs = [];
$docsDone = 0;

// 1 つの値の data: 画像を処理し、新しい値を返す（--dry-run のときは何も書かず、値も変えない）。
// $whole が true のときは、値そのものが data: の画像のこと（画像の TV）も扱う
$convertValue = function ($docId, $label, $value, $timestamp, $whole, &$placed) use (&$rows, &$pending, $store, $dryRun, $takeSnapshot) {
    $handle = function ($dataUri, $dimensions) use (&$rows, &$pending, &$placed, $docId, $label, $timestamp, $store, $dryRun, $takeSnapshot) {
        $row = [
            'doc' => $docId,
            'field' => $label,
            'mime' => preg_match('/^data:([^;,]+)/', $dataUri, $m) ? $m[1] : '?',
            'size' => null,
            'resized' => false,
            'status' => 'skipped',
            'detail' => '',
            'rel' => null,
        ];
        $prepared = $store->prepare($dataUri, $dimensions, $docId);
        if (isset($prepared['error'])) {
            $row['mime'] = $prepared['mime'] ?? $row['mime'];
            $row['size'] = $prepared['size'] ?? null;
            $row['detail'] = $prepared['param'] !== null ? sprintf($prepared['error'], $prepared['param']) : $prepared['error'];
            $rows[] = $row;
            return null;
        }
        $row['size'] = strlen($prepared['bytes']);
        $row['resized'] = $prepared['resized'];
        if ($dryRun) {
            $row['status'] = 'move';
            $row['detail'] = 'images/' . FileStore::pastedFolder($timestamp) . '/';
            $rows[] = $row;
            return null;
        }
        $takeSnapshot();
        $result = $store->place($prepared, $docId, $timestamp);
        if (isset($result['error'])) {
            $row['detail'] = $result['param'] !== null ? sprintf($result['error'], $result['param']) : $result['error'];
            $rows[] = $row;
            return null;
        }
        $row['status'] = 'moved';
        $row['detail'] = $result['path'];
        $row['rel'] = $result['rel'];
        $placed[] = count($rows);
        $pending[$result['rel']] = true;
        $rows[] = $row;
        return $result['path'];
    };

    if ($whole && ($dataUri = EvoDataUri::wholeImageValue($value)) !== null) {
        $path = $handle($dataUri, null);
        return $path !== null ? $path : $value;
    }
    return EvoDataUri::replaceImgSrc($value, $handle);
};

// 新しい値を DB に書く。$update は 1（書けた）・0（読んだあとに別の書き換えがあり、条件に合わなかった＝競合）・false（DB のエラー）を返す。
// 書けなければ、その値のために書いたファイルを消す
$commit = function (array $placed, $docId, callable $update) use (&$rows, &$pending, &$failed, &$conflicts, &$changedDocs, $store) {
    if (!$placed) {
        return;
    }
    $updated = $update();
    foreach ($placed as $index) {
        unset($pending[$rows[$index]['rel']]);
    }
    if ($updated === 1) {
        $changedDocs[$docId] = true;
        return;
    }
    foreach ($placed as $index) {
        $store->discard($rows[$index]['rel']);
        $rows[$index]['status'] = $updated === 0 ? 'conflict' : 'failed';
        $rows[$index]['detail'] = $updated === 0
            ? 'skipped: it was changed while this command was running; run the command again'
            : 'database update failed: ' . db()->getLastError();
        $rows[$index]['rel'] = null;
    }
    $updated === 0 ? $conflicts++ : $failed++;
};

// 読んだときの値を条件に入れて書き戻し、1・0・false を返す（$commit を参照）
$updateWhere = function (array $fields, $table, $where) {
    if (!db()->update(db()->escape($fields), $table, $where)) {
        return false;
    }
    return db()->getAffectedRows() > 0 ? 1 : 0;
};

foreach ($candidateIds as $docId) {
    if ($limit !== null && $docsDone >= $limit) {
        break;
    }
    $before = count($rows);

    $doc = db()->getRow(db()->select(
        'id,content,introtext,editedon,createdon',
        '[+prefix+]site_content',
        sprintf('id=%d', $docId)
    ));
    $docTime = $doc ? ((int)$doc['editedon'] ?: (int)$doc['createdon']) : 0;

    // 本文と要約（img の src だけ）
    foreach ($doc ? ['content', 'introtext'] : [] as $field) {
        if (strpos((string)$doc[$field], 'data:') === false) {
            continue;
        }
        $placed = [];
        $new = $convertValue($docId, $field, $doc[$field], $docTime, false, $placed);
        $commit($placed, $docId, function () use ($updateWhere, $doc, $docId, $field, $new) {
            return $updateWhere([$field => $new], '[+prefix+]site_content', sprintf('id=%d AND editedon=%d', $docId, (int)$doc['editedon']));
        });
    }

    // TV の値（img の src と、値そのものが data: の画像のもの）
    if ($doc) {
        $rs = db()->select(
            'id,tmplvarid,value',
            '[+prefix+]site_tmplvar_contentvalues',
            sprintf("contentid=%d AND value LIKE '%%data:%%'", $docId),
            'id'
        );
        while ($tv = db()->getRow($rs)) {
            $placed = [];
            $new = $convertValue($docId, 'tv' . $tv['tmplvarid'], $tv['value'], $docTime, true, $placed);
            $commit($placed, $docId, function () use ($updateWhere, $tv, $docId, $new) {
                return $updateWhere(
                    ['value' => $new],
                    '[+prefix+]site_tmplvar_contentvalues',
                    sprintf("id=%d AND contentid=%d AND tmplvarid=%d AND value='%s'", (int)$tv['id'], $docId, (int)$tv['tmplvarid'], db()->escape($tv['value']))
                );
            });
        }
    }

    // 下書き（site_revision。element=resource の version 0）。シリアライズした配列の、本文・要約・TV の値
    $draft = db()->getRow(db()->select(
        'internalKey,content,editedon,checksum',
        '[+prefix+]site_revision',
        sprintf("element='resource' AND elmid=%d AND version=0 AND content LIKE '%%data:%%'", $docId)
    ));
    if ($draft) {
        $data = @unserialize($draft['content'], ['allowed_classes' => false]);
        if (is_array($data)) {
            $draftTime = (int)$draft['editedon'] ?: $docTime;
            $placed = [];
            $changed = false;
            foreach ($data as $key => $value) {
                if (!is_string($value) || strpos($value, 'data:') === false) {
                    continue;
                }
                if ($key !== 'content' && $key !== 'introtext' && strpos($key, 'tv') !== 0) {
                    continue;
                }
                $new = $convertValue($docId, 'draft:' . $key, $value, $draftTime, strpos($key, 'tv') === 0, $placed);
                if ($new !== $value) {
                    $data[$key] = $new;
                    $changed = true;
                }
            }
            if ($changed) {
                $serialized = serialize($data);
                $commit($placed, $docId, function () use ($updateWhere, $draft, $serialized) {
                    return $updateWhere(
                        ['content' => $serialized, 'checksum' => hash('crc32b', $serialized)],
                        '[+prefix+]site_revision',
                        sprintf("internalKey=%d AND checksum='%s'", (int)$draft['internalKey'], db()->escape($draft['checksum']))
                    );
                });
            }
        }
    }

    // 移す（または移せる）画像がある文書だけを、--limit の数に入れる
    for ($i = $before; $i < count($rows); $i++) {
        if ($rows[$i]['status'] !== 'skipped') {
            $docsDone++;
            break;
        }
    }
}

$moved = 0;
$skipped = 0;
$movedDocs = [];
foreach ($rows as $row) {
    $size = $row['size'] === null ? '?' : $row['size'] . ' bytes' . ($row['resized'] ? ', resized' : '');
    $line = sprintf('#%d %s: %s, %s', $row['doc'], $row['field'], $row['mime'], $size);
    if ($row['status'] === 'move') {
        cli_out($line . ' -> would move to ' . $row['detail']);
        $moved++;
        $movedDocs[$row['doc']] = true;
    } elseif ($row['status'] === 'moved') {
        cli_out($line . ' -> ' . $row['detail']);
        $moved++;
        $movedDocs[$row['doc']] = true;
    } elseif ($row['status'] === 'failed' || $row['status'] === 'conflict') {
        cli_err($line . ' -> ' . ($row['status'] === 'failed' ? 'failed: ' : '') . $row['detail']);
    } else {
        cli_out($line . ' -> left as is: ' . $row['detail']);
        $skipped++;
    }
}

if ($dryRun) {
    cli_out(sprintf(
        'Dry run: %d image(s) in %d document(s) can be moved, %d left as is. Nothing was changed.',
        $moved,
        count($movedDocs),
        $skipped
    ));
    return;
}

if ($changedDocs) {
    $store->record();
    // ページキャッシュは書き換えた文書のぶんだけでなく、サイト全体を作り直す（clearCache() の決まり）
    evo()->clearCache('full');
}
cli_out(sprintf(
    'Moved %d image(s) in %d document(s), %d left as is, %d failed, %d skipped by a conflict.',
    $moved,
    count($changedDocs),
    $skipped,
    $failed,
    $conflicts
));
if ($failed > 0 || $conflicts > 0) {
    exit(1);
}
