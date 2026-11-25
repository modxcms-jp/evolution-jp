<?php
if (!defined('MODX_BASE_PATH')) {
    die('What are you doing? Get out of here!');
}

/*
 * Ditto: aggregates documents to create blogs, article/news collections, and more.
 * Parameters and placeholders:
 * https://github.com/modxcms-jp/evolution-jp/blob/main/assets/snippets/ditto/README.md
 *
 * Author:
 *      Mark Kaplan for MODX CMF
*/

//---Core Settings---------------------------------------------------- //

$ditto_version = '2.1.4';

// 指定された置き場所は、サイトの中（本体のバインドと同じ判定）に限る。外れたら同梱の場所を使う
if (isset($ditto_base) && trim($ditto_base, '/ ') !== '') {
    $ditto_base = evo()->resolveBindDirPath(MODX_BASE_PATH . ltrim($ditto_base, '/'));
} else {
    $ditto_base = false;
}
if (empty($ditto_base)) {
    $ditto_base = str_replace('\\', '/', __DIR__) . '/';
}

$dittoID = isset($id) ? $id . '_' : '';
$GLOBALS['dittoID'] = $dittoID;

$language = $language ?? evo()->config('manager_language');
if (!preg_match('/^[\w\-]+$/', (string) $language)
    || !is_file(sprintf('%slang/%s.inc.php', $ditto_base, $language))
) {
    $language = 'english';
}

$format = isset($format) ? strtolower($format) : 'html';
if (!preg_match('/^[\w\-]+$/', $format)) {
    $format = 'html';
}

$config = $config ?? 'default';

if (is_file($ditto_base . 'configs/default.config.php'))
    include $ditto_base . 'configs/default.config.php';

if (str_starts_with($config, '@CHUNK')) {
    evo()->logEvent(
        0,
        2,
        'Ditto の &config の @CHUNK は廃止しました。チャンクの PHP をファイルに移し、@FILE（サイトのルートからのパス）で指定してください。',
        'Ditto ' . $ditto_version,
        ['value' => $config]
    );
} elseif ($config === 'compatibility') {
    evo()->logEvent(
        0,
        2,
        'Ditto の &config=compatibility は廃止予定です。今は summary extender を読み込みますが、スニペットの呼び出しから外し、[+summary+] を使うときは &extenders に summary を指定してください。',
        'Ditto ' . $ditto_version
    );
} elseif (str_starts_with($config, '@FILE')) {
    // サイトのルートからの指定は、本体のバインドと同じ判定に通す（PHP として読み込むので .php だけ）
    $config_path = evo()->resolveBindFilePath(MODX_BASE_PATH . ltrim(trim(substr($config, 6)), '/'));
    if ($config_path && evo()->getExtention($config_path) === '.php') {
        include $config_path;
    }
} elseif ($config !== 'default') {
    // 名前から組み立てたパスは、configs/ の下にあるときだけ読む
    $config_path = evo()->resolveFileUnderDir(
        $ditto_base . 'configs/' . $config . '.config.php',
        $ditto_base . 'configs'
    );
    if ($config_path && evo()->getExtention($config_path) === '.php') {
        include $config_path;
    }
}

if (isset($debug)) {
    evo()->logEvent(
        0,
        2,
        'Ditto の &debug は廃止しました。スニペットの呼び出しから &debug を外してください。',
        'Ditto ' . $ditto_version
    );
}

$renamedParams = array_intersect_key(
    [
        'startID' => 'parents',
        'summarize' => 'display',
        'limit' => 'queryLimit',
        'commentsChunk' => 'removeChunk',
        'hiddenTVs' => 'hiddenFields',
        'dateFormatType' => 'dateSource',
        'descendentDepth' => 'depth',
        'rssLanguage' => 'abbrLanguage',
        'tplAltRows' => 'tplAlt',
        'tplFirstRow' => 'tplFirst',
        'tplLastRow' => 'tplLast',
        'tplArchivePrevious' => 'tplPaginatePrevious',
        'tplArchiveNext' => 'tplPaginateNext',
        'emptyText' => 'noResults',
    ],
    (array) event()->params
);
if ($renamedParams) {
    // 古い名前の値は今の名前に引き継ぐ。両方を指定したときは今の名前を使う。
    // format などは event()->params を直接読むので、params にも今の名前で入れる
    $ignoredParams = [];
    foreach ($renamedParams as $oldName => $newName) {
        if (array_key_exists($newName, event()->params)) {
            $ignoredParams[$oldName] = $newName;
            continue;
        }
        ${$newName} = event()->params[$oldName];
        event()->setParam($newName, ${$newName});
    }
    evo()->logEvent(
        0,
        2,
        'Ditto の古いパラメータ名は廃止予定です。今は値を今の名前に引き継ぎますが、スニペットの呼び出しを今の名前に書き換えてください。',
        'Ditto ' . $ditto_version,
        ['renamed' => $renamedParams, 'ignored' => $ignoredParams]
    );
}

// a config may already have added extenders as an array ($extenders[] = ...)
if (!isset($extenders)) {
    $extenders = [];
} elseif (!is_array($extenders)) {
    $extenders = explode(',', $extenders);
}
if ($config === 'compatibility') {
    $extenders[] = 'summary';
}

// keep placeholders a config may have added
$placeholders = isset($placeholders) && is_array($placeholders) ? $placeholders : [];

$orderBy = trim($orderBy ?? '');

if ($orderBy !== '' && substr(strtolower($orderBy), -2) !== 'sc') {
    $orderBy .= ' desc';
}
$orderBy = ['parsed' => [], 'custom' => [], 'unparsed' => $orderBy];

//---Includes-------------------------------------------------------- //

$files = [
    'language' => sprintf('%slang/%s.inc.php', $ditto_base, $language),
    'main_class' => sprintf('%sclasses/ditto.class.inc.php', $ditto_base),
    'template_class' => sprintf('%sclasses/template.class.inc.php', $ditto_base),
    'filter_class' => sprintf('%sclasses/filter.class.inc.php', $ditto_base),
    'format' => sprintf('%sformats/%s.format.inc.php', $ditto_base, $format)
];

if (isset($randomize)) {
    $files['randomize_class'] = $ditto_base . 'classes/random.class.inc.php';
}

foreach (array_unique($files) as $filename => $filevalue) {
    if (is_file($filevalue)) {
        if (strpos($filename, 'class') !== false) {
            include_once($filevalue);
        } else {
            include($filevalue);
        }
        continue;
    }
    $message = $filename === 'language'
        ? sprintf('Language file does not exist Please check: %s', $filevalue)
        : sprintf('%s %s', $filevalue, $_lang['file_does_not_exist']);
    evo()->logEvent(1, 3, $message, 'Ditto ' . $ditto_version);
    return $message;
}

//---Initiate Class-------------------------------------------------- //
if (!class_exists('ditto')) {
    evo()->logEvent(1, 3, $_lang['invalid_class'], 'Ditto ' . $ditto_version);
    return $_lang['invalid_class'];
}
$ditto = new ditto($format, $_lang);

//---Initiate Extenders---------------------------------------------- //
if (isset($tagData)) {
    $extenders[] = 'tagging';
}

if (count($extenders) > 0) {
    $extenders = array_unique($extenders);
    foreach ($extenders as $extender) {
        $extender = str_replace('\\', '/', trim($extender));
        if (strpos($extender, '@CHUNK') === 0) {
            evo()->logEvent(
                0,
                2,
                'Ditto の &extenders の @CHUNK は廃止しました。チャンクの PHP をファイルに移し、@FILE（サイトのルートからのパス）で指定してください。',
                'Ditto ' . $ditto_version,
                ['value' => $extender]
            );
            continue;
        }

        if (strpos($extender, '@FILE') === 0) {
            // サイトのルートからの指定は、本体のバインドと同じ判定に通す
            $extender_path = evo()->resolveBindFilePath(
                MODX_BASE_PATH . trim(trim(substr($extender, 6)), '/')
            );
        } else {
            // 名前から組み立てたパスは、extenders/ の下にあるときだけ読む
            $extender_path = evo()->resolveFileUnderDir(
                sprintf('%sextenders/%s.extender.inc.php', $ditto_base, $extender),
                $ditto_base . 'extenders'
            );
        }

        // PHP として読み込むので .php だけ
        if (!$extender_path || evo()->getExtention($extender_path) !== '.php') {
            if (strpos($extender, '@') !== 0 && evo()->getChunk($extender) !== '') {
                evo()->logEvent(
                    0,
                    2,
                    'Ditto の &extenders にチャンク名を書く指定は廃止しました。チャンクの PHP をファイルに移し、@FILE（サイトのルートからのパス）で指定してください。',
                    'Ditto ' . $ditto_version,
                    ['value' => $extender]
                );
            }
            $message = $extender . ' ' . $_lang['extender_does_not_exist'];
            evo()->logEvent(1, 3, $message, 'Ditto ' . $ditto_version);
            return $message;
        }
        include($extender_path);
    }
}

//---Parameters------------------------------------------------------- //
// legacy &sortBy / &sortDir: the missing one defaults to createdon / DESC
if (isset($sortBy) || isset($sortDir)) {
    $sortDir = isset($sortDir) ? strtoupper(trim($sortDir)) : 'DESC';
    if ($sortDir !== 'ASC') {
        $sortDir = 'DESC';
    }
    $sortBy = $sortBy ?? 'createdon';
    $orderBy['parsed'][] = [$sortBy, $sortDir];
}

$idType = isset($documents) ? 'documents' : 'parents';
$parents = isset($parents)
    ? trim(preg_replace('`(,)+`', ',', $parents), ',')
    : evo()->documentIdentifier;
$documents = isset($documents)
    ? trim(preg_replace('`(,)+`', ',', $documents), ',')
    : false;
$IDs = $idType === 'parents' ? $parents : $documents;

$depth = $depth ?? 1;
$paginate = $paginate ?? 0;
$dateSource = $dateSource ?? 'createdon';
$dateFormat = $dateFormat ?? $_lang['dateFormat'];
$display = $display ?? 'all';
$total = $total ?? 'all';
$showPublishedOnly = $showPublishedOnly ?? 1;
$showInMenuOnly = $showInMenuOnly ?? 0;
$hideFolders = $hideFolders ?? 0;
$hidePrivate = $hidePrivate ?? 1;
$seeThruUnpub = $seeThruUnpub ?? 1;
$queryLimit = $queryLimit ?? null;
$where = $where ?? '';
// keep the value a format (rss, json etc.) has set unless &noResults is given
$noResults = event()->param('noResults', $noResults ?? $_lang['no_documents']);
$removeChunk = isset($removeChunk) ? explode(',', $removeChunk) : false;
$hiddenFields = isset($hiddenFields) ? explode(',', $hiddenFields) : false;

$offset = $start ?? 0;
// 先頭のゼロも含めて 9 桁まで
$rawStart = !empty($paginate) ? get_strvar($dittoID . 'start') : '';
$start = strlen($rawStart) <= 9 ? to_uintvar($rawStart, 0) : 0;

$globalFilterDelimiter = $globalFilterDelimiter ?? '|';
$localFilterDelimiter = $localFilterDelimiter ?? ',';

// false turns the custom / parsed filters off; any other non-array value starts empty.
// $cFilters and $parsedFilters are the names used by configs written for 2.0.0
if (!isset($filters) || !is_array($filters)) {
    $filters = [];
}
foreach (['custom' => $cFilters ?? null, 'parsed' => $parsedFilters ?? null] as $filterType => $legacyFilters) {
    if (!array_key_exists($filterType, $filters)
        || ($filters[$filterType] !== false && !is_array($filters[$filterType]))
    ) {
        $filters[$filterType] = [];
    }
    if ($legacyFilters !== null && $filters[$filterType] !== false) {
        $filters[$filterType] = array_merge($filters[$filterType], $legacyFilters);
    }
}
$filter = $filter ?? '';
if ($filter || $filters['custom'] !== false || $filters['parsed'] !== false) {
    $filter = $ditto->parseFilters(
        $filter,
        $filters['custom'],
        $filters['parsed'],
        $globalFilterDelimiter,
        $localFilterDelimiter
    );
} else {
    $filter = false;
}

$randomize = $randomize ?? 0;
$save = $save ?? 0;

$templates = [
    'default' => '@CODE ' . $_lang['default_template'],
    'base' => $tpl ?? '',
    'alt' => $tplAlt ?? '',
    'first' => $tplFirst ?? '',
    'last' => $tplLast ?? '',
    'current' => $tplCurrentDocument ?? ''
];

$orderBy = $ditto->parseOrderBy($orderBy, $randomize);

//---Documents-------------------------------------------------------- //
$templates = $ditto->template->process($templates);

$ditto->setDisplayFields($ditto->template->fields, $hiddenFields);

$ditto->parseFields($placeholders, $seeThruUnpub, $dateSource, $randomize);

$documentIDs = $ditto->determineIDs(
    $IDs,
    $idType,
    $ditto->fields['backend']['tv'],
    $orderBy,
    $depth,
    $showPublishedOnly,
    $seeThruUnpub,
    $hideFolders,
    $hidePrivate,
    $showInMenuOnly,
    $where,
    $dateSource,
    $queryLimit,
    $display,
    $filter,
    $randomize
);

if ($ditto->tvPrefixFields) {
    evo()->logEvent(
        0,
        2,
        'Ditto の TV の tv 接頭辞は廃止しました。テンプレートのプレースホルダーや &orderBy・&filter などのフィールド名には、tv を付けずに TV の名前を書いてください。',
        'Ditto ' . $ditto_version,
        ['fields' => $ditto->tvPrefixFields]
    );
}

$count = count($documentIDs) - $offset;

if ($count > 0) {
    $total = $total === 'all' ? $count : min($total, $count);
    $display = $display === 'all' ? min($count, $total) : min($display, $total);
    $stop = $save == 1 ? min($count, $total) : min($total - $start, $display);

    if ($paginate == 1) {
        $paginateAlwaysShowLinks = $paginateAlwaysShowLinks ?? 0;
        $paginateSplitterCharacter = $paginateSplitterCharacter ?? $_lang['button_splitter'];

        $paginateTemplates = [
            'tplPaginatePrevious' => '<a href="[+url+]" class="ditto_previous_link">[+lang:previous+]</a>',
            'tplPaginateNext' => '<a href="[+url+]" class="ditto_next_link">[+lang:next+]</a>',
            'tplPaginateNextOff' => '<span class="ditto_next_off ditto_off">[+lang:next+]</span>',
            'tplPaginatePreviousOff' => '<span class="ditto_previous_off ditto_off">[+lang:previous+]</span>',
            'tplPaginatePage' => '<a class="ditto_page" href="[+url+]">[+page+]</a>',
            'tplPaginateCurrentPage' => '<span class="ditto_currentpage">[+page+]</span>',
        ];
        foreach ($paginateTemplates as $paginateName => $paginateDefault) {
            $$paginateName = isset($$paginateName)
                ? $ditto->template->fetch($$paginateName)
                : $paginateDefault;
        }

        $ditto->paginate(
            $start,
            $stop,
            $total,
            $display,
            $tplPaginateNext,
            $tplPaginatePrevious,
            $tplPaginateNextOff,
            $tplPaginatePreviousOff,
            $tplPaginatePage,
            $tplPaginateCurrentPage,
            $paginateAlwaysShowLinks,
            $paginateSplitterCharacter
        );
    }

    $dbFields = $ditto->fields['display']['db'];
    $TVs = $ditto->fields['display']['tv'];

    // skip &start documents. With the prefetch $stop is the length of the slice, otherwise the end position
    $stop += $ditto->prefetch === false ? $start + $offset : $offset;
    $start += $offset;

    if ($ditto->prefetch !== false) {
        $documentIDs = array_slice($documentIDs, $start, $stop);
        // fetch only what the prefetch has not read yet
        $dbFields = array_diff($dbFields, $ditto->prefetch['fields']['db']);
        $dbFields[] = 'id';
        $TVs = array_diff($TVs, $ditto->prefetch['fields']['tv']);
        $start = 0;
        $stop = min($display, ($queryLimit != 0) ? $queryLimit : $display, count($documentIDs));
    } elseif ($queryLimit == 0) {
        $queryLimit = '';
    }

    $resource = $ditto->getDocuments(
        $documentIDs,
        $dbFields,
        $TVs,
        $orderBy,
        $showPublishedOnly,
        0,
        $hidePrivate,
        $where,
        $queryLimit,
        $randomize,
        $dateSource
    );
    $output = $header;

    if ($resource) {
        if ($randomize != '0' && $randomize != '1') {
            $resource = $ditto->weightedRandom($resource, $randomize, $stop);
        }

        $resource = array_values($resource);
        // fewer documents than expected (e.g. &queryLimit): the last one gets tplLast
        $stop = min($stop, count($resource));

        for ($x = $start; $x < $stop; $x++) {
            $template = $ditto->template->determine(
                $templates,
                $x,
                0,
                $stop,
                $resource[$x]['id']
            );
            $renderedOutput = $ditto->render(
                $resource[$x],
                $template,
                $removeChunk,
                $dateSource,
                $dateFormat,
                $placeholders,
                abs($start - $x),
                $stop
            );
            evo()->setPlaceholder($dittoID . "item[" . abs($start - $x) . "]", $renderedOutput);
            $output .= $renderedOutput;
        }
    } else {
        $output .= $ditto->noResults($noResults, $paginate);
    }
    $output .= $footer;

    if ($save) {
        evo()->setPlaceholder($dittoID . "ditto_object", $ditto);
        evo()->setPlaceholder($dittoID . "ditto_resource", ($save == "1") ? array_slice($resource ?: [], $display) : $resource);
        if ($save == 3) {
            return is_array($resource) ? $resource : '';
        }
    }
} else {
    $output = $header . $ditto->noResults($noResults, $paginate) . $footer;
}
if (!empty($tplOuter)) {
    $outerTpl = $tplOuter;
}
if (!empty($outerTpl)) {
    if (empty($resource)) {
        $output = '';
    } elseif (substr($outerTpl, 0, 5) === '@CODE') {
        $outerTpl = trim(substr($outerTpl, 6));
    } elseif (strpos($outerTpl, '@') === 0) {
        $outerTpl = evo()->atBind($outerTpl);
    } elseif (evo()->getChunk($outerTpl) != '') {
        $outerTpl = evo()->getChunk($outerTpl);
    }

    if ($output) {
        $output = str_replace(
            ['[+ditto+]', '[+documents+]', '[+docs+]'],
            $output,
            $outerTpl
        );
    }
}

return ($save == 3) ? '' : $output;
