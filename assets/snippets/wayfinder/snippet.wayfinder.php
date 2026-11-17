<?php
/**
 * Wayfinder
 *
 * Template-driven menu builder: lists the documents under &startId as a nested menu.
 *
 * @author Kyle Jaebker, Ryan Thrash and others
 */
if (!defined('MODX_BASE_PATH')) {
    die('What are you doing? Get out of here!');
}

$wf_base_path = str_replace('\\', '/', __DIR__) . '/';
$conf_path = $wf_base_path . 'configs/';

if (is_file($conf_path . 'default.config.php')) {
    include $conf_path . 'default.config.php';
}

$config = ltrim(trim($config ?? 'default'), '/');

if (strpos($config, '@CHUNK') === 0) {
    eval('?>' . evo()->getChunk(trim(substr($config, 7))));
} else {
    // 名前から組み立てたパスは configs/ の下にあるときだけ、サイトのルートからの指定は
    // 本体のバインドと同じ判定を通ったときだけ読む（PHP として読み込むので .php だけ）
    if (strpos($config, '@FILE') === 0) {
        $config_candidates = [[MODX_BASE_PATH . trim(substr($config, 6)), null]];
    } else {
        $config_candidates = [
            [$conf_path . $config, $conf_path],
            [MODX_BASE_PATH . $config, null]
        ];
        if ($config !== 'default') {
            array_unshift($config_candidates, [$conf_path . $config . '.config.php', $conf_path]);
        }
    }
    foreach ($config_candidates as $config_candidate) {
        if ($config_candidate[1] === null) {
            $config_path = evo()->resolveBindFilePath($config_candidate[0]);
        } else {
            $config_path = evo()->resolveFileUnderDir($config_candidate[0], $config_candidate[1]);
        }
        if ($config_path && evo()->getExtention($config_path) === '.php') {
            include $config_path;
            break;
        }
    }
}

include_once $wf_base_path . 'wayfinder.inc.php';

if (!class_exists('Wayfinder')) {
    return 'error: Wayfinder class not found';
}
$wf = new Wayfinder();

if (isset($debug)) {
    evo()->logEvent(
        0,
        2,
        'Wayfinder の &debug は廃止しました。スニペットの呼び出しから &debug を外してください。',
        'Wayfinder'
    );
}

if (!isset($startId)) {
    $startId = evo()->documentIdentifier;
} elseif (stripos($startId, 'p') === 0) {
    $startId = $wf->getParentID(evo()->documentIdentifier);
} elseif (stripos($startId, 'i') === 0) {
    $startId = $wf->getIndexID(evo()->documentIdentifier);
} elseif (!preg_match('@^[0-9]+$@', $startId)) {
    // show the error here only; do not stop the whole page
    return sprintf('# %s # Wayfinder &startId error', hsc($startId));
}

$wf->_config = [
    'id' => $startId,
    'level' => $level ?? 0,
    'includeDocs' => $includeDocs ?? 0,
    'excludeDocs' => $excludeDocs ?? 0,
    'where' => $where ?? '',
    'ph' => $ph ?? false,
    'ignoreHidden' => $ignoreHidden ?? false,
    'hideSubMenus' => $hideSubMenus ?? false,
    'useWeblinkUrl' => $useWeblinkUrl ?? true,
    // false: /about.html のようにルート相対で出す。true: サイトの URL から出す
    'fullLink' => $fullLink ?? false,
    'nl' => isset($removeNewLines) ? '' : "\n",
    // sortBy: 列をカンマ区切りで並べる。列ごとに向きを書ける（例: menuindex DESC, pagetitle）
    // sortOrder: 向きを書かない列の向き。同じ値どうしは最後に id の小さい順
    'sortOrder' => isset($sortOrder) ? strtoupper($sortOrder) : 'ASC',
    'sortBy' => $sortBy ?? 'menuindex',
    'limit' => $limit ?? 0,
    'cssTpl' => $cssTpl ?? false,
    'jsTpl' => $jsTpl ?? false,
    'rowIdPrefix' => $rowIdPrefix ?? false,
    'textOfLinks' => $textOfLinks ?? 'menutitle',
    'titleOfLinks' => $titleOfLinks ?? 'pagetitle',
    'displayStart' => $displayStart ?? false,
    'showPrivate' => $showPrivate ?? false,
    'entityEncode' => $entityEncode ?? false,
    // for local references - use original document fields separated by comma (useful for set active if it is current, titles, link attr, etc)
    'useReferenced' => $useReferenced ?? "id",
    'hereId' => isset($hereId) ? (int)$hereId : evo()->documentIdentifier
];

$wf->_css = [
    'first' => $firstClass ?? '',
    'last' => $lastClass ?? 'last',
    'here' => $hereClass ?? 'active',
    'parent' => $parentClass ?? '',
    'row' => $rowClass ?? '',
    'outer' => $outerClass ?? '',
    'inner' => $innerClass ?? '',
    'outerLevel' => $outerLevelClass ?? '',
    'level' => $levelClass ?? '',
    'self' => $selfClass ?? '',
    'weblink' => $webLinkClass ?? '',
];

$wf->_templates = [
    'outerTpl' => $outerTpl ?? '',
    'rowTpl' => $rowTpl ?? '',
    'parentRowTpl' => $parentRowTpl ?? '',
    'parentRowHereTpl' => $parentRowHereTpl ?? '',
    'hereTpl' => $hereTpl ?? '',
    'innerTpl' => $innerTpl ?? '',
    'innerRowTpl' => $innerRowTpl ?? '',
    'innerHereTpl' => $innerHereTpl ?? '',
    'activeParentRowTpl' => $activeParentRowTpl ?? '',
    'categoryFoldersTpl' => $categoryFoldersTpl ?? '',
    'startItemTpl' => $startItemTpl ?? '',
    'lastRowTpl' => $lastRowTpl ?? '',
];

$output = $wf->run();

if ($wf->_config['ph']) {
    evo()->setPlaceholder($wf->_config['ph'], $output);
    return;
}

return $output;
