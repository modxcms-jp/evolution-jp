<?php
if (!isset($modx) || !evo()->isLoggedin()) {
    exit;
}
if (!evo()->hasPermission('view_eventlog')) {
    alert()->setError(3);
    alert()->dumpError();
}

$logRoot = MODX_BASE_PATH . 'temp/logs/system/';
$files = system_log_files($logRoot);
$isDownload = getv('download') === '1';
$period = system_log_period((string)getv('period', 'latest'));
$isLatest = $period === 'latest' && !$isDownload;
$isYearPeriod = strpos($period, 'year:') === 0;
$months = system_log_months($files, $period);
$selectedMonth = $isYearPeriod ? system_log_selected_month((string)getv('month', ''), $months) : '';
$visibleFiles = system_log_filter_files($files, $period, $selectedMonth);
$selectedFile = $isLatest ? '' : getv('file', '');
$selectedFileInfo = system_log_find_file($files, $selectedFile);
if (!$isLatest && ($selectedFile === '' || !system_log_find_file($visibleFiles, $selectedFile)) && $visibleFiles) {
    $selectedFile = $visibleFiles[0]['relative'];
    $selectedFileInfo = $visibleFiles[0];
}
if (!$isLatest && !$selectedFileInfo && $files) {
    $selectedFile = $files[0]['relative'];
    $selectedFileInfo = $files[0];
}
$selectedPath = $isLatest ? '' : system_log_resolve_file($logRoot, $selectedFile);
$fileList = system_log_file_list($visibleFiles);
$years = system_log_years($files);
$level = strtolower((string)getv('level', ''));
$query = trim((string)getv('q', ''));
$allowedLevels = ['', 'emergency', 'alert', 'critical', 'error', 'warning', 'notice', 'info', 'debug'];
if (!in_array($level, $allowedLevels, true)) {
    $level = '';
}

if (getv('ajax') === 'entries') {
    $beforeLine = (int)getv('before', 0);
    $result = $isLatest
        ? system_log_read_latest_entries($logRoot, $files, $level, $query, (string)getv('cursor_file', ''), $beforeLine, 20)
        : ($selectedPath === ''
        ? ['entries' => [], 'has_more' => false, 'before_line' => 0]
        : system_log_read_entries($selectedPath, $level, $query, $beforeLine, 20));
    header('Content-Type: application/json; charset=utf-8');
    echo system_log_json_encode($result);
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
                    <?= hsc(lang('search')) ?>
                    <input type="text" name="q" id="systemLogQuery" value="<?= hsc($query) ?>" />
                </label>
                <button type="submit" class="primary"><?= hsc(lang('go')) ?></button>
            </form>
            <?php if ($isLatest) { ?>
                <p class="system-log-summary">
                    <?= hsc(lang('latest_log_summary', '最新100件のログファイルを横断して表示しています。')) ?>
                </p>
            <?php } elseif ($selectedFileInfo) { ?>
                <p class="system-log-summary">
                    <?= hsc($selectedFileInfo['size']) ?> /
                    <?= hsc($selectedFileInfo['lines']) ?> lines /
                    <?= hsc($selectedFileInfo['modified']) ?>
                    <?php if ($selectedPath !== '') { ?>
                    <a
                        href="<?= hsc('index.php?a=114&download=1&file=' . urlencode($selectedFile)) ?>"
                        onclick="downloadSystemLog(this.href);return false;"
                        title="<?= hsc(lang('file_download_file', 'Download File')) ?>"
                    >
                        <img src="<?= style('icons_save') ?>" alt="" /> <?= hsc(lang('file_download_file', 'Download File')) ?>
                    </a>
                    <?php } ?>
                </p>
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
    const query = stream.dataset.query;
    let beforeLine = 0;
    let cursorFile = '';
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

    async function loadEntries(prepend) {
        if (loading || !hasMore && prepend) {
            return;
        }
        loading = true;
        const marker = document.createElement('div');
        marker.className = 'system-log-loading';
        marker.textContent = 'Loading...';
        if (prepend) {
            stream.prepend(marker);
        } else {
            stream.innerHTML = '';
            stream.append(marker);
        }

        const previousHeight = stream.scrollHeight;
        const params = new URLSearchParams({
            a: '114',
            ajax: 'entries',
            period: period,
            before: prepend ? String(beforeLine) : '0'
        });
        if (period === 'latest') {
            if (prepend && cursorFile) {
                params.set('cursor_file', cursorFile);
            }
        } else {
            params.set('file', file);
        }
        if (level) {
            params.set('level', level);
        }
        if (query) {
            params.set('q', query);
        }

        const response = await fetch(`index.php?${params.toString()}`, {headers: {'X-Requested-With': 'XMLHttpRequest'}});
        const text = await response.text();
        let payload;
        try {
            payload = text ? JSON.parse(text) : null;
        } catch (error) {
            payload = null;
        }
        if (!response.ok || !payload) {
            marker.remove();
            const message = text ? escapeHtml(text.slice(0, 1000)) : 'Empty response from log API.';
            stream.insertAdjacentHTML('beforeend', `<div class="system-log-empty">${message}</div>`);
            loading = false;
            hasMore = false;
            return;
        }
        marker.remove();

        const html = payload.entries.map(renderEntry).join('');
        if (prepend) {
            stream.insertAdjacentHTML('afterbegin', html);
            stream.scrollTop = stream.scrollHeight - previousHeight;
        } else {
            stream.insertAdjacentHTML('beforeend', html || '<div class="system-log-empty">No matching log entries.</div>');
            stream.scrollTop = stream.scrollHeight;
        }
        beforeLine = payload.before_line || 0;
        cursorFile = payload.cursor_file || '';
        hasMore = !!payload.has_more;
        loading = false;
    }

    stream.addEventListener('scroll', function () {
        if (stream.scrollTop < 48) {
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

<?php
function system_log_files(string $root): array
{
    if (!is_dir($root)) {
        return [];
    }

    $rootPath = realpath($root);
    if ($rootPath === false) {
        return [];
    }
    $rootPath = str_replace('\\', '/', $rootPath);
    $items = [];
    $iterator = new RecursiveIteratorIterator(
        new RecursiveDirectoryIterator($rootPath, FilesystemIterator::SKIP_DOTS)
    );

    foreach ($iterator as $file) {
        if (!$file->isFile()) {
            continue;
        }
        $name = $file->getFilename();
        if (!preg_match('/^system-[0-9]{4}-[0-9]{2}-[0-9]{2}\.log(\.[0-9]+)?$/', $name)) {
            continue;
        }

        $path = str_replace('\\', '/', $file->getPathname());
        $relative = ltrim(substr($path, strlen($rootPath)), '/');
        $year = '';
        $month = '';
        if (preg_match('@^([0-9]{4})/([0-9]{2})/@', $relative, $matches)) {
            $year = $matches[1];
            $month = $matches[2];
        }
        $dateTimestamp = $file->getMTime();
        if (preg_match('/^system-([0-9]{4}-[0-9]{2}-[0-9]{2})\.log/', $name, $matches)) {
            $parsedTimestamp = strtotime($matches[1] . ' 00:00:00');
            if ($parsedTimestamp !== false) {
                $dateTimestamp = $parsedTimestamp;
            }
        }
        $items[] = [
            'name' => $name,
            'relative' => $relative,
            'year' => $year,
            'month' => $month,
            'size' => system_log_format_bytes($file->getSize()),
            'lines' => system_log_count_lines($path),
            'date_ts' => $dateTimestamp,
            'mtime' => $file->getMTime(),
            'modified' => date('Y-m-d H:i:s', $file->getMTime()),
        ];
    }

    usort($items, function ($a, $b) {
        return $b['mtime'] <=> $a['mtime'];
    });

    return array_slice($items, 0, 100);
}

function system_log_period(string $period): string
{
    if (in_array($period, ['latest', 'recent30'], true)) {
        return $period;
    }

    if (preg_match('/^year:[0-9]{4}$/', $period)) {
        return $period;
    }

    return 'latest';
}

function system_log_filter_files(array $files, string $period, string $month = ''): array
{
    if (strpos($period, 'year:') === 0) {
        $year = substr($period, 5);
        return array_values(array_filter($files, function ($file) use ($year, $month) {
            return (string)array_get($file, 'year', '') === $year
                && ($month === '' || (string)array_get($file, 'month', '') === $month);
        }));
    }

    if ($period === 'latest') {
        return $files;
    }

    $days = 30;
    $today = strtotime(date('Y-m-d') . ' 00:00:00');
    if ($today === false) {
        return $files;
    }
    $threshold = strtotime('-' . ($days - 1) . ' days', $today);
    if ($threshold === false) {
        return $files;
    }

    $filtered = array_values(array_filter($files, function ($file) use ($threshold) {
        return (int)array_get($file, 'date_ts', 0) >= $threshold;
    }));

    return $filtered ?: $files;
}

function system_log_months(array $files, string $period): array
{
    if (strpos($period, 'year:') !== 0) {
        return [];
    }

    $year = substr($period, 5);
    $months = [];
    foreach ($files as $file) {
        if ((string)array_get($file, 'year', '') !== $year) {
            continue;
        }
        $month = (string)array_get($file, 'month', '');
        if ($month !== '') {
            $months[$month] = $month;
        }
    }

    krsort($months);

    return array_values($months);
}

function system_log_selected_month(string $month, array $months): string
{
    if (in_array($month, $months, true)) {
        return $month;
    }

    return $months[0] ?? '';
}

function system_log_years(array $files): array
{
    $years = [];
    foreach ($files as $file) {
        $year = (string)array_get($file, 'year', '');
        if ($year !== '') {
            $years[$year] = $year;
        }
    }

    krsort($years);

    return array_values($years);
}

function system_log_find_file(array $files, string $relative): array
{
    foreach ($files as $file) {
        if ($file['relative'] === $relative) {
            return $file;
        }
    }

    return [];
}

function system_log_file_list(array $files): array
{
    return array_map(function ($file) {
        return [
            'relative' => $file['relative'],
            'label' => system_log_file_label($file),
        ];
    }, $files);
}

function system_log_file_label(array $file): string
{
    return sprintf(
        '%s (%s / %s lines)',
        $file['name'],
        $file['size'],
        $file['lines']
    );
}

function system_log_resolve_file(string $root, string $relative): string
{
    if ($relative === '' || strpos($relative, "\0") !== false) {
        return '';
    }

    $rootPath = realpath($root);
    $path = realpath($root . $relative);
    if ($rootPath === false || $path === false || !is_file($path)) {
        return '';
    }

    $rootPath = rtrim(str_replace('\\', '/', $rootPath), '/') . '/';
    $path = str_replace('\\', '/', $path);
    if (strpos($path, $rootPath) !== 0) {
        return '';
    }
    if (!preg_match('/^system-[0-9]{4}-[0-9]{2}-[0-9]{2}\.log(\.[0-9]+)?$/', basename($path))) {
        return '';
    }

    return $path;
}

function system_log_read_latest_entries(
    string $root,
    array $files,
    string $level = '',
    string $query = '',
    string $cursorFile = '',
    int $beforeLine = 0,
    int $limit = 20
): array {
    if (!$files) {
        return ['entries' => [], 'has_more' => false, 'before_line' => 0, 'cursor_file' => ''];
    }

    $startIndex = 0;
    if ($cursorFile !== '') {
        $startIndex = system_log_file_index($files, $cursorFile);
        if ($startIndex < 0) {
            return ['entries' => [], 'has_more' => false, 'before_line' => 0, 'cursor_file' => ''];
        }
    }

    $chunks = [];
    $remaining = max(1, $limit);
    $nextCursorFile = '';
    $nextBeforeLine = 0;
    $hasMore = false;
    $lastIndex = $startIndex - 1;

    for ($i = $startIndex, $count = count($files); $i < $count && $remaining > 0; $i++) {
        $lastIndex = $i;
        $fileInfo = $files[$i];
        $path = system_log_resolve_file($root, $fileInfo['relative']);
        if ($path === '') {
            continue;
        }

        $lineCursor = $i === $startIndex ? $beforeLine : 0;
        $result = system_log_read_entries($path, $level, $query, $lineCursor, $remaining);
        if ($result['entries']) {
            $chunks[] = array_map(function ($entry) use ($fileInfo) {
                $entry['file'] = $fileInfo['relative'];
                return $entry;
            }, $result['entries']);
            $remaining -= count($result['entries']);
        }

        if ($result['has_more']) {
            $hasMore = true;
            $nextCursorFile = $fileInfo['relative'];
            $nextBeforeLine = (int)$result['before_line'];
            break;
        }
    }

    if (!$hasMore && $remaining <= 0 && isset($files[$lastIndex + 1])) {
        $hasMore = true;
        $nextCursorFile = $files[$lastIndex + 1]['relative'];
        $nextBeforeLine = 0;
    }

    $entries = [];
    foreach (array_reverse($chunks) as $chunk) {
        $entries = array_merge($entries, $chunk);
    }

    return [
        'entries' => $entries,
        'has_more' => $hasMore,
        'before_line' => $nextBeforeLine,
        'cursor_file' => $nextCursorFile,
    ];
}

function system_log_file_index(array $files, string $relative): int
{
    foreach ($files as $index => $file) {
        if ($file['relative'] === $relative) {
            return $index;
        }
    }

    return -1;
}

function system_log_read_entries(string $path, string $level = '', string $query = '', int $beforeLine = 0, int $limit = 20): array
{
    $entries = [];
    $lineNumber = 0;
    $firstLine = 0;
    $hasMore = false;

    $file = new SplFileObject($path, 'r');
    while (!$file->eof()) {
        $line = trim((string)$file->fgets());
        $lineNumber++;
        if ($line === '') {
            continue;
        }
        if ($beforeLine > 0 && $lineNumber >= $beforeLine) {
            continue;
        }

        $data = json_decode($line, true);
        if (!is_array($data)) {
            $data = [
                'timestamp' => '',
                'level' => 'raw',
                'message' => $line,
                'context' => [],
            ];
        }

        $entryLevel = strtolower((string)array_get($data, 'level', ''));
        $encoded = json_encode($data, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        if ($level !== '' && $entryLevel !== $level) {
            continue;
        }
        if ($query !== '' && stripos($encoded, $query) === false) {
            continue;
        }

        $entries[] = system_log_normalize_entry($data, $lineNumber);
        if (count($entries) > $limit) {
            array_shift($entries);
            $hasMore = true;
        }
    }

    if ($entries) {
        $firstLine = (int)$entries[0]['line'];
    }

    return [
        'entries' => $entries,
        'has_more' => $hasMore,
        'before_line' => $firstLine,
    ];
}

function system_log_normalize_entry(array $data, int $lineNumber): array
{
    $context = array_get($data, 'context', []);
    if (!is_array($context)) {
        $context = [];
    }

    return [
        'line' => $lineNumber,
        'timestamp' => (string)array_get($data, 'timestamp', ''),
        'timestamp_label' => system_log_format_timestamp((string)array_get($data, 'timestamp', '')),
        'level' => strtolower((string)array_get($data, 'level', 'unknown')) ?: 'unknown',
        'message' => system_log_plain_text((string)array_get($data, 'message', '')),
        'source' => (string)array_get($context, 'source', ''),
        'caller' => is_array(array_get($context, 'caller')) ? array_get($context, 'caller') : [],
        'context' => $context,
        'raw' => $data,
    ];
}

function system_log_format_timestamp(string $timestamp): string
{
    if ($timestamp === '') {
        return '';
    }

    $parsed = strtotime($timestamp);
    if ($parsed === false) {
        return $timestamp;
    }

    return date('Y-m-d H:i:s', $parsed);
}

function system_log_json_encode(array $payload): string
{
    $json = json_encode($payload, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_INVALID_UTF8_SUBSTITUTE);
    if ($json !== false) {
        return $json;
    }

    $fallback = system_log_utf8_normalize($payload);
    $json = json_encode($fallback, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    if ($json !== false) {
        return $json;
    }

    return json_encode([
        'entries' => [],
        'has_more' => false,
        'before_line' => 0,
        'cursor_file' => '',
        'error' => 'Failed to encode log response: ' . json_last_error_msg(),
    ], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
}

function system_log_utf8_normalize($value)
{
    if (is_array($value)) {
        foreach ($value as $key => $item) {
            $value[$key] = system_log_utf8_normalize($item);
        }
        return $value;
    }

    if (is_string($value)) {
        if (function_exists('mb_convert_encoding')) {
            return mb_convert_encoding($value, 'UTF-8', 'UTF-8');
        }
        if (function_exists('iconv')) {
            $converted = @iconv('UTF-8', 'UTF-8//IGNORE', $value);
            if ($converted !== false) {
                return $converted;
            }
        }
    }

    return $value;
}

function system_log_plain_text(string $message): string
{
    if ($message === '' || strpos($message, '<') === false) {
        return $message;
    }

    $message = preg_replace('@<(br|/p|/div|/tr|/table|/h[1-6])\b[^>]*>@i', "\n", $message);
    $message = strip_tags($message);
    $message = html_entity_decode($message, ENT_QUOTES | ENT_HTML5, config('modx_charset', 'utf-8'));
    $lines = array_map('trim', preg_split('/\R+/u', $message) ?: []);
    $lines = array_filter($lines, function ($line) {
        return $line !== '';
    });

    return implode("\n", $lines);
}

function system_log_count_lines(string $path): int
{
    $lines = 0;
    $file = new SplFileObject($path, 'r');
    while (!$file->eof()) {
        $file->fgets();
        $lines++;
    }

    return max(0, $lines - 1);
}

function system_log_format_bytes(int $bytes): string
{
    $units = ['B', 'KB', 'MB', 'GB'];
    $value = $bytes;
    foreach ($units as $unit) {
        if ($value < 1024 || $unit === 'GB') {
            return sprintf('%s %s', round($value, 1), $unit);
        }
        $value = $value / 1024;
    }

    return $bytes . ' B';
}
