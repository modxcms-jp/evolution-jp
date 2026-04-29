<?php
if (!isset($modx) || !evo()->isLoggedin()) {
    exit;
}
if (!evo()->hasPermission('view_eventlog')) {
    alert()->setError(3);
    alert()->dumpError();
}

include_once MODX_CORE_PATH . 'system_log.viewer.inc.php';

$logRoot = MODX_BASE_PATH . 'temp/logs/system/';
$canDeleteLog = evo()->hasPermission('delete_eventlog');
$files = SystemLogViewer::files($logRoot);
$latestFiles = SystemLogViewer::latestFiles($files, 100);
$isDownload = getv('download') === '1';
$isEntriesApi = getv('ajax') === 'entries';
$period = SystemLogViewer::period((string)getv('period', 'latest'));
$isLatest = $period === 'latest' && !$isDownload;
$isYearPeriod = strpos($period, 'year:') === 0;
$months = SystemLogViewer::months($files, $period);
$selectedMonth = $isYearPeriod ? SystemLogViewer::selectedMonth((string)getv('month', ''), $months) : '';
$visibleFiles = SystemLogViewer::filterFiles($files, $period, $selectedMonth);
if (!$isLatest && !$isDownload && !$isEntriesApi) {
    $visibleFiles = SystemLogViewer::withLineCounts($visibleFiles);
}
$selectedFile = $isLatest ? '' : getv('file', '');
$selectedFileInfo = SystemLogViewer::findFile($visibleFiles, $selectedFile);
if (!$isLatest && ($selectedFile === '' || !SystemLogViewer::findFile($visibleFiles, $selectedFile)) && $visibleFiles) {
    $selectedFile = $visibleFiles[0]['relative'];
    $selectedFileInfo = $visibleFiles[0];
}
if (!$isLatest && !$selectedFileInfo && $files) {
    $selectedFile = $files[0]['relative'];
    $selectedFileInfo = SystemLogViewer::withLineCounts([$files[0]])[0];
}
$selectedPath = $isLatest ? '' : SystemLogViewer::resolveFile($logRoot, $selectedFile);
$fileList = SystemLogViewer::fileList($visibleFiles);
$years = SystemLogViewer::years($files);
$level = strtolower((string)getv('level', ''));
$source = trim((string)getv('source', ''));
$query = trim((string)getv('q', ''));
$allowedLevels = ['', 'emergency', 'alert', 'critical', 'error', 'warning', 'notice', 'info', 'debug'];
if (!in_array($level, $allowedLevels, true)) {
    $level = '';
}
$sourceOptions = [
    '' => lang('all', 'All'),
    'MODxMailer request log' => 'MODxMailer',
];
if (!isset($sourceOptions[$source])) {
    $source = '';
}
$deleteActionParams = [
    'a' => '114',
    'period' => $period,
    'file' => $selectedFile,
];
if ($selectedMonth !== '') {
    $deleteActionParams['month'] = $selectedMonth;
}
if ($level !== '') {
    $deleteActionParams['level'] = $level;
}
if ($source !== '') {
    $deleteActionParams['source'] = $source;
}
if ($query !== '') {
    $deleteActionParams['q'] = $query;
}
$deleteActionUrl = 'index.php?' . http_build_query($deleteActionParams);
$deleteError = '';

if (is_post() && postv('delete_log') === '1') {
    if (!$canDeleteLog) {
        $deleteError = lang('access_permission_denied', 'アクセス権限がありません。');
    } elseif ($selectedPath !== '' && @unlink($selectedPath)) {
        if (basename($selectedPath) !== 'system-' . date('Y-m-d') . '.log') {
            evo()->logEvent(0, 1, 'Deleted system log file', 'SystemLogViewer', [
                'file' => $selectedFile,
            ]);
        }

        $redirect = [
            'a' => '114',
            'period' => $period,
        ];
        if ($selectedMonth !== '') {
            $redirect['month'] = $selectedMonth;
        }
        if ($level !== '') {
            $redirect['level'] = $level;
        }
        if ($source !== '') {
            $redirect['source'] = $source;
        }
        if ($query !== '') {
            $redirect['q'] = $query;
        }

        $redirectUrl = 'index.php?' . http_build_query($redirect);
        if (!headers_sent()) {
            header('Location: ' . $redirectUrl);
        } else {
            echo '<script>window.location.href=' . json_encode($redirectUrl, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE) . ';</script>';
        }
        exit;
    } else {
        $deleteError = lang('failed_delete', '削除に失敗しました。');
    }
}

if ($isEntriesApi) {
    $olderBeforeLine = (int)getv('before', 0);
    $result = $isLatest
        ? SystemLogViewer::readLatestEntries($logRoot, $latestFiles, $level, $source, $query, (string)getv('cursor_file', ''), $olderBeforeLine, 20)
        : ($selectedPath === ''
        ? ['entries' => [], 'has_more' => false, 'before_line' => 0]
        : SystemLogViewer::readEntries($selectedPath, $level, $source, $query, $olderBeforeLine, 20));
    header('Content-Type: application/json; charset=utf-8');
    echo SystemLogViewer::jsonEncode($result);
    exit;
}

if (getv('download') === '1' && $selectedPath !== '') {
    $downloadName = basename($selectedPath);
    header('Content-Type: application/x-ndjson; charset=utf-8');
    header('Content-Disposition: attachment; filename="' . addslashes($downloadName) . '"');
    header('Content-Length: ' . filesize($selectedPath));
    readfile($selectedPath);
    exit;
}

?>
<h1><?= hsc(lang('system_log', 'システムログ')) ?></h1>

<div id="actions">
    <ul class="actionButtons">
        <li id="Button5" class="mutate">
            <a href="#" onclick="documentDirty=false;document.location.href='index.php?a=2';">
                <img alt="icons_cancel" src="<?= style('icons_cancel') ?>" /> <?= lang('cancel') ?>
            </a>
        </li>
    </ul>
</div>

<style>
    .system-log-meta {
        color: #666;
        font-size: 12px;
    }
    .system-log-summary {
        color: #666;
        margin: 0 0 10px;
    }
    .system-log-summary a {
        margin-left: 8px;
        white-space: nowrap;
    }
    .system-log-summary form {
        display: inline;
        margin-left: 8px;
    }
    .system-log-delete {
        cursor: pointer;
    }
    .system-log-toolbar {
        display: flex;
        gap: 8px;
        flex-wrap: wrap;
        align-items: center;
        margin-bottom: 10px;
    }
    .system-log-toolbar select[name="file"] {
        min-width: 260px;
        max-width: 100%;
    }
    .system-log-toolbar .system-log-month-selector.is-hidden,
    .system-log-toolbar .system-log-file-selector.is-hidden {
        display: none;
    }
    .system-log-toolbar input[type="text"] {
        min-width: 220px;
    }
    .system-log-stream {
        border: 1px solid #d6d6d6;
        background: #fafafa;
        height: 62vh;
        min-height: 360px;
        overflow: auto;
        padding: 12px;
    }
    .system-log-entry {
        border-left: 4px solid #9aa7b4;
        background: #fff;
        margin-bottom: 10px;
        padding: 9px 12px;
    }
    .system-log-entry[data-level="error"],
    .system-log-entry[data-level="critical"],
    .system-log-entry[data-level="alert"],
    .system-log-entry[data-level="emergency"] {
        border-left-color: #c0392b;
    }
    .system-log-entry[data-level="warning"] {
        border-left-color: #d68910;
    }
    .system-log-entry[data-level="info"],
    .system-log-entry[data-level="notice"] {
        border-left-color: #2874a6;
    }
    .system-log-entry-header {
        display: flex;
        gap: 10px;
        flex-wrap: wrap;
        align-items: baseline;
        font-family: monospace;
        color: #333;
    }
    .system-log-entry-actions {
        margin-left: auto;
        font-family: sans-serif;
    }
    .system-log-copy {
        border: 1px solid #aaa;
        background: #fff;
        padding: 2px 8px;
        cursor: pointer;
    }
    .system-log-copy.is-copied {
        background: #e8f5e9;
        border-color: #5a9b62;
    }
    .system-log-level {
        font-weight: bold;
        text-transform: uppercase;
    }
    .system-log-message {
        margin-top: 6px;
        white-space: pre-wrap;
        word-break: break-word;
    }
    .system-log-context {
        margin-top: 6px;
    }
    .system-log-context summary {
        cursor: pointer;
    }
    .system-log-context pre {
        margin: 6px 0 0;
        padding: 8px;
        white-space: pre-wrap;
        word-break: break-word;
        max-height: 260px;
        overflow: auto;
        background: #f7f7f7;
        border: 1px solid #ddd;
    }
    .system-log-highlight {
        background: #fff3a3;
        padding: 0 2px;
    }
    .system-log-empty,
    .system-log-loading {
        padding: 18px;
        color: #666;
        border: 1px solid #ddd;
        background: #fff;
    }
    .system-log-loading {
        text-align: center;
        margin-bottom: 10px;
    }
</style>

<div class="sectionBody">
    <p><?= hsc(lang('system_log_msg', 'システムログはJSONLines形式で保存されたエラー・警告・情報メッセージを表示します。')) ?></p>

    <div class="sectionBody">
        <?php if (!$files) { ?>
            <div class="system-log-empty"><?= hsc(lang('no_records_found')) ?></div>
        <?php } else { ?>
            <?php if ($deleteError !== '') { ?>
                <div class="system-log-empty"><?= hsc($deleteError) ?></div>
            <?php } ?>
            <form class="system-log-toolbar" method="get" action="index.php">
                <input type="hidden" name="a" value="114" />
                <label>
                    <?= hsc(lang('period', '期間')) ?>
                    <select name="period" id="systemLogPeriod">
                        <option value="latest"<?= $period === 'latest' ? ' selected' : '' ?>><?= hsc(lang('latest_log', '最新ログ')) ?></option>
                        <option value="recent30"<?= $period === 'recent30' ? ' selected' : '' ?>><?= hsc(lang('recent_30_days', '直近30日')) ?></option>
                        <?php foreach ($years as $year) { ?>
                            <?php $yearPeriod = 'year:' . $year; ?>
                            <option value="<?= hsc($yearPeriod) ?>"<?= $period === $yearPeriod ? ' selected' : '' ?>>
                                <?= hsc($year) ?>
                            </option>
                        <?php } ?>
                    </select>
                </label>
                <label id="systemLogMonthSelector" class="system-log-month-selector<?= $isYearPeriod ? '' : ' is-hidden' ?>">
                    <?= hsc(lang('month', '月')) ?>
                    <select name="month" id="systemLogMonth"<?= $isYearPeriod ? '' : ' disabled' ?>>
                        <?php foreach ($months as $month) { ?>
                            <option value="<?= hsc($month) ?>"<?= $selectedMonth === $month ? ' selected' : '' ?>>
                                <?= hsc($month) ?><?= hsc(lang('month_suffix', '月')) ?>
                            </option>
                        <?php } ?>
                    </select>
                </label>
                <label id="systemLogFileSelector" class="system-log-file-selector<?= $isLatest ? ' is-hidden' : '' ?>">
                    <?= hsc(lang('log_files', 'ログファイル')) ?>
                    <select name="file" id="systemLogFile" data-selected="<?= hsc($selectedFile) ?>"></select>
                </label>
                <label>
                    <?= hsc(lang('type')) ?>
                    <select name="level" id="systemLogLevel">
                        <option value=""><?= hsc(lang('all', 'All')) ?></option>
                        <?php foreach (array_slice($allowedLevels, 1) as $option) { ?>
                            <option value="<?= hsc($option) ?>"<?= $level === $option ? ' selected' : '' ?>>
                                <?= hsc($option) ?>
                            </option>
                        <?php } ?>
                    </select>
                </label>
                <label>
                    <?= hsc(lang('source', '発生元')) ?>
                    <select name="source" id="systemLogSource">
                        <?php foreach ($sourceOptions as $sourceValue => $sourceLabel) { ?>
                            <option value="<?= hsc($sourceValue) ?>"<?= $source === $sourceValue ? ' selected' : '' ?>>
                                <?= hsc($sourceLabel) ?>
                            </option>
                        <?php } ?>
                    </select>
                </label>
                <label title="<?= hsc(lang('search')) ?>">
                    <input
                        type="text"
                        name="q"
                        id="systemLogQuery"
                        value="<?= hsc($query) ?>"
                        placeholder="<?= hsc(lang('keyword', 'キーワード')) ?>"
                    />
                </label>
                <button type="submit" class="primary"><?= hsc(lang('search')) ?></button>
            </form>
            <?php if ($isLatest) { ?>
                <p class="system-log-summary">
                    <?= hsc(lang('latest_log_summary', '最新100件のログファイルを横断して表示しています。')) ?>
                </p>
            <?php } elseif ($selectedFileInfo) { ?>
                <div class="system-log-summary">
                    <?= hsc($selectedFileInfo['name']) ?>
                    <?php if ($selectedPath !== '') { ?>
                    <a
                        href="<?= hsc('index.php?a=114&download=1&file=' . urlencode($selectedFile)) ?>"
                        onclick="downloadSystemLog(this.href);return false;"
                        title="<?= hsc(lang('file_download_file', 'Download File')) ?>"
                    >
                        <img src="<?= style('icons_save') ?>" alt="" /> <?= hsc(lang('file_download_file', 'Download File')) ?>
                    </a>
                    <?php if ($canDeleteLog) { ?>
                    <form
                        method="post"
                        action="<?= hsc($deleteActionUrl) ?>"
                        onsubmit="return confirm('<?= hsc(lang('confirm_delete_system_log', 'このシステムログを削除します。よろしいですか？')) ?>');"
                    >
                        <?= csrfTokenField() ?>
                        <input type="hidden" name="delete_log" value="1" />
                        <input type="hidden" name="period" value="<?= hsc($period) ?>" />
                        <input type="hidden" name="month" value="<?= hsc($selectedMonth) ?>" />
                        <input type="hidden" name="file" value="<?= hsc($selectedFile) ?>" />
                        <input type="hidden" name="level" value="<?= hsc($level) ?>" />
                        <input type="hidden" name="source" value="<?= hsc($source) ?>" />
                        <input type="hidden" name="q" value="<?= hsc($query) ?>" />
                        <button type="submit" class="system-log-delete"><?= hsc(lang('delete', '削除')) ?></button>
                    </form>
                    <?php } ?>
                    <?php } ?>
                </div>
            <?php } ?>
            <?php if (!$isLatest && $selectedPath === '') { ?>
                <div class="system-log-empty"><?= hsc(lang('no_records_found')) ?></div>
            <?php } else { ?>
                <div
                    id="systemLogStream"
                    class="system-log-stream"
                    data-period="<?= hsc($period) ?>"
                    data-file="<?= hsc($selectedFile) ?>"
                    data-level="<?= hsc($level) ?>"
                    data-source="<?= hsc($source) ?>"
                    data-query="<?= hsc($query) ?>"
                >
                    <div class="system-log-loading"><?= hsc(lang('loading', 'Loading...')) ?></div>
                </div>
            <?php } ?>
        <?php } ?>
    </div>
</div>

<?php if ($isLatest || $selectedPath !== '') { ?>
<script>
function downloadSystemLog(url) {
    dontShowWorker = true;
    documentDirty = false;
    if (window.jQuery) {
        jQuery(window).off('beforeunload');
    }
    window.location.href = url;
}

(function () {
    const fileList = <?= json_encode($fileList, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) ?>;
    const periodSelect = document.getElementById('systemLogPeriod');
    const monthSelector = document.getElementById('systemLogMonthSelector');
    const monthSelect = document.getElementById('systemLogMonth');
    const fileSelector = document.getElementById('systemLogFileSelector');
    const fileSelect = document.getElementById('systemLogFile');
    const filterForm = fileSelect.form;
    const stream = document.getElementById('systemLogStream');
    const period = stream.dataset.period;
    const file = stream.dataset.file;
    const level = stream.dataset.level;
    const source = stream.dataset.source;
    const query = stream.dataset.query;
    let olderBeforeLine = 0;
    let olderCursorFile = '';
    let hasMore = true;
    let loading = false;

    function resetOptions(select) {
        while (select.firstChild) {
            select.removeChild(select.firstChild);
        }
    }

    function option(value, label, selected) {
        const item = document.createElement('option');
        item.value = value;
        item.textContent = label;
        item.selected = selected;
        return item;
    }

    function fillFiles() {
        const currentFile = fileSelect.dataset.selected;
        resetOptions(fileSelect);
        fileList.forEach(function (fileItem) {
            fileSelect.append(option(fileItem.relative, fileItem.label, fileItem.relative === currentFile));
        });
        if (!fileSelect.value && fileSelect.options.length) {
            fileSelect.selectedIndex = 0;
        }
    }

    function toggleFileSelector() {
        const isLatest = periodSelect.value === 'latest';
        const isYear = periodSelect.value.indexOf('year:') === 0;
        monthSelector.classList.toggle('is-hidden', !isYear);
        monthSelect.disabled = !isYear;
        fileSelector.classList.toggle('is-hidden', isLatest);
        fileSelect.disabled = isLatest;
    }

    function submitSelectedFile() {
        if (fileSelect.value) {
            filterForm.submit();
        }
    }

    periodSelect.addEventListener('change', function () {
        fileSelect.disabled = true;
        filterForm.submit();
    });

    monthSelect.addEventListener('change', function () {
        fileSelect.disabled = true;
        filterForm.submit();
    });

    fileSelect.addEventListener('change', submitSelectedFile);

    toggleFileSelector();
    fillFiles();

    function escapeHtml(value) {
        return String(value).replace(/[&<>"']/g, function (char) {
            return {'&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#039;'}[char];
        });
    }

    function highlight(value) {
        const text = escapeHtml(value);
        if (!query) {
            return text;
        }
        const escapedQuery = query.replace(/[.*+?^${}()|[\]\\]/g, '\\$&');
        return text.replace(new RegExp(escapedQuery, 'gi'), '<mark class="system-log-highlight">$&</mark>');
    }

    function renderEntry(entry) {
        const context = JSON.stringify(entry.context || {}, null, 2);
        const raw = JSON.stringify(entry.raw || {}, null, 2);
        const caller = entry.caller ? `${entry.caller.file || ''}:${entry.caller.line || ''}` : '';
        const timestampLabel = entry.timestamp_label || entry.timestamp || '';
        return `
            <div class="system-log-entry" data-line="${entry.line}" data-level="${escapeHtml(entry.level)}" data-raw="${escapeHtml(raw)}">
                <div class="system-log-entry-header">
                    <span class="system-log-level">${highlight(entry.level)}</span>
                    <span title="${escapeHtml(entry.timestamp || '')}">${highlight(timestampLabel)}</span>
                    ${entry.source ? `<span>${highlight(entry.source)}</span>` : ''}
                    ${caller ? `<span>${highlight(caller)}</span>` : ''}
                    <span class="system-log-entry-actions">
                        <button type="button" class="system-log-copy">copy</button>
                    </span>
                </div>
                <div class="system-log-message">${highlight(entry.message || '')}</div>
                <details class="system-log-context">
                    <summary>context</summary>
                    <pre>${highlight(context)}</pre>
                </details>
            </div>
        `;
    }

    async function loadEntries(loadMore) {
        if (loading || !hasMore && loadMore) {
            return;
        }
        loading = true;
        const marker = document.createElement('div');
        marker.className = 'system-log-loading';
        marker.textContent = 'Loading...';
        if (!loadMore) {
            stream.innerHTML = '';
        }
        stream.append(marker);

        const params = new URLSearchParams({
            a: '114',
            ajax: 'entries',
            period: period,
            before: loadMore ? String(olderBeforeLine) : '0'
        });
        if (period === 'latest') {
            if (loadMore && olderCursorFile) {
                params.set('cursor_file', olderCursorFile);
            }
        } else {
            params.set('file', file);
        }
        if (level) {
            params.set('level', level);
        }
        if (source) {
            params.set('source', source);
        }
        if (query) {
            params.set('q', query);
        }
        try {
            const response = await fetch(`index.php?${params.toString()}`, {headers: {'X-Requested-With': 'XMLHttpRequest'}});
            const text = await response.text();
            let payload;
            try {
                payload = text ? JSON.parse(text) : null;
            } catch (error) {
                payload = null;
            }
            if (!response.ok || !payload) {
                const message = text ? escapeHtml(text.slice(0, 1000)) : 'Empty response from log API.';
                stream.insertAdjacentHTML('beforeend', `<div class="system-log-empty">${message}</div>`);
                hasMore = false;
                return;
            }

            const html = payload.entries.slice().reverse().map(renderEntry).join('');
            if (loadMore) {
                stream.insertAdjacentHTML('beforeend', html);
            } else {
                stream.insertAdjacentHTML('beforeend', html || '<div class="system-log-empty">No matching log entries.</div>');
            }
            olderBeforeLine = payload.before_line || 0;
            olderCursorFile = payload.cursor_file || '';
            hasMore = !!payload.has_more;
        } catch (error) {
            const message = error && error.message ? escapeHtml(error.message) : 'Failed to load log entries.';
            stream.insertAdjacentHTML('beforeend', `<div class="system-log-empty">${message}</div>`);
            hasMore = false;
        } finally {
            marker.remove();
            loading = false;
        }
    }

    stream.addEventListener('scroll', function () {
        if (stream.scrollTop + stream.clientHeight > stream.scrollHeight - 48) {
            loadEntries(true);
        }
    });

    stream.addEventListener('click', async function (event) {
        if (!event.target.classList.contains('system-log-copy')) {
            return;
        }
        const button = event.target;
        const entry = button.closest('.system-log-entry');
        const text = entry ? entry.dataset.raw : '';
        if (!text) {
            return;
        }
        try {
            await navigator.clipboard.writeText(text);
            button.textContent = 'copied';
            button.classList.add('is-copied');
            setTimeout(function () {
                button.textContent = 'copy';
                button.classList.remove('is-copied');
            }, 1600);
        } catch (error) {
            const range = document.createRange();
            range.selectNodeContents(entry);
            const selection = window.getSelection();
            selection.removeAllRanges();
            selection.addRange(range);
        }
    });

    loadEntries(false);
})();
</script>
<?php } ?>
