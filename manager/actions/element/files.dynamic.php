<?php
if (!isset($modx) || !evo()->isLoggedin()) {
    exit;
}
if (!evo()->hasPermission('file_manager')) {
    alert()->setError(3);
    alert()->dumpError();
}

global $_style, $modx_manager_charset;

$page = new FileManagerPage(
    $modx,
    $_style,
    $modx_manager_charset,
    manager()->makeToken()
);
$page->render();

class FileManagerPage
{
    /** @var DocumentParser */
    private $modx;
    /** @var array */
    private $style;
    /** @var string */
    private $stylePath;
    /** @var string */
    private $charset;
    /** @var string */
    private $token;
    /** @var string */
    private $startPath = '';
    /** @var array */
    private $messages = [];
    /** @var string|null */
    private $uploadFeedback;
    /** @var bool */
    private $enableFileUnzip = true;
    /** @var bool */
    private $enableFileDownload = true;
    /** @var int */
    private $newFolderMode;
    /** @var int */
    private $newFilePermissions;
    /** @var array */
    private $protectedPaths = [];
    /** @var string */
    private $webstartPath;
    /** @var array|null */
    private $editableExtensions;
    /** @var array|null */
    private $uploadableExtensionsCache;
    /** @var array|null */
    private $inlineViewableExtensionsCache;
    /** @var array|null */
    private $viewableExtensionsCache;

    public function __construct($modx, array $style, $charset, $token)
    {
        $this->modx = $modx;
        $this->style = $style;
        $this->charset = $charset;
        $this->token = $token;
        $this->stylePath = parseText('[+site_url+]manager/media/style/[+manager_theme+]/images/', $modx->config);
        $this->webstartPath = $this->buildWebstartPath();
        $this->newFolderMode = $modx->config['new_folder_permissions']
            ? octdec($modx->config['new_folder_permissions'])
            : 0777;
        $this->newFilePermissions = config('new_file_permissions')
            ? octdec(config('new_file_permissions'))
            : 0666;
    }

    public function render()
    {
        $this->startPath = $this->determineStartPath();
        if (!is_readable($this->startPath)) {
            echo lang('not_readable_dir');
            return;
        }

        $this->protectedPaths = $this->buildProtectedPaths();
        $this->processRequest();

        if ($this->isProtectedPath($this->startPath)) {
            echo lang('files.dynamic.php2');
            return;
        }

        $breadcrumb = $this->buildBreadcrumb();
        $listing = $this->renderDirectoryListing();

        echo $this->renderLayout($breadcrumb, $listing);
    }

    private function determineStartPath()
    {
        if (anyv('path')) {
            $safePath = str_replace('..', '', anyv('path'));
            $_REQUEST['path'] = $safePath;
            $startPath = is_dir($safePath) ? $safePath : $this->removeLastPath($safePath);
            if (!$startPath) {
                $startPath = config('filemanager_path');
            }
        } else {
            $startPath = config('filemanager_path');
        }

        return rtrim($startPath, '/');
    }

    private function processRequest()
    {
        if (!empty($_FILES['userfile'])) {
            $this->uploadFeedback = $this->handleFileUpload();
        } elseif (postv('mode') === 'save') {
            $this->messages[] = $this->saveFile();
        } elseif (anyv('mode') === 'delete') {
            $this->messages[] = $this->deleteFile();
        }

        if (anyv('mode') === 'unzip' && is_writable($this->startPath)) {
            $message = $this->handleUnzip();
            if ($message) {
                $this->messages[] = $message;
            }
        }

        if (!is_writable($this->startPath)) {
            $this->messages = array_filter($this->messages);
            return;
        }

        switch (anyv('mode')) {
            case 'deletefolder':
                $this->messages[] = $this->handleDeleteFolder();
                break;
            case 'newfolder':
                $this->messages[] = $this->handleCreateFolder();
                break;
            case 'newfile':
                $this->messages[] = $this->handleCreateFile();
                break;
        }

        $this->messages = array_filter($this->messages);
    }

    private function buildBreadcrumb()
    {
        if (rtrim($this->startPath, '/') == config('filemanager_path')) {
            $ph = [
                'image' => $this->style['tree_deletedfolder'],
                'subject' => '<span style="color:#bbb;cursor:default;">Top</span>'
            ];
        } else {
            $ph = [
                'image' => $this->style['tree_folder'],
                'subject' => sprintf(
                    '<a href="index.php?a=31&mode=drill&path=%s">Top</b></a> / ',
                    config('filemanager_path')
                )
            ];
        }

        $breadcrumb = $this->modx->parseText(
            '<img src="[+image+]" align="absmiddle" alt="" />[+subject+] ',
            $ph
        );

        $len = strlen(config('filemanager_path'));
        if (substr($this->startPath, $len) == '') {
            $topicPath = '/';
        } else {
            $topicPath = substr($this->startPath, $len);
            $pieces = explode('/', rtrim($topicPath, '/'));
            $path = '';
            $count = count($pieces);
            foreach ($pieces as $i => $value) {
                if (empty($value)) {
                    continue;
                }
                $path .= rtrim($value, '/') . '/';
                if (1 < $count) {
                    $pieces[$i] = sprintf(
                        '<a href="%s">%s</a>',
                        'index.php?a=31&mode=drill&path=' . urlencode(config('filemanager_path') . $path),
                        trim($value, '/')
                    );
                } else {
                    $pieces[$i] = trim($value, '/');
                }
                $count--;
            }
            $topicPath = join(' / ', $pieces);
        }

        $topicPath = mb_convert_encoding(
            $topicPath,
            $this->charset,
            'SJIS-win,SJIS,EUCJP-win,EUC-JP,UTF-8'
        );

        return $breadcrumb . '<b>' . $topicPath . '</b>';
    }

    private function renderDirectoryListing()
    {
        $baseLength = strlen(config('filemanager_path'));
        if (strlen(MODX_BASE_PATH) < strlen(config('filemanager_path'))) {
            $baseLength--;
        }

        $lister = new FileManagerLister(
            $this->modx,
            $this->style,
            $this->stylePath,
            $this->charset,
            $this->enableFileUnzip,
            $this->enableFileDownload,
            $this->protectedPaths,
            $baseLength,
            $this->webstartPath,
            $this->getEditableExtensions(),
            $this->getUploadableExtensions(),
            $this->getInlineViewableExtensions(),
            $this->getViewableExtensions()
        );

        $rows = $lister->render($this->startPath);

        return [
            'rows' => $rows,
            'folders' => $lister->getFolderCount(),
            'files' => $lister->getFileCount(),
            'totalSize' => $lister->getTotalSize()
        ];
    }

    private function renderLayout($breadcrumb, array $listing)
    {
        $mode = anyv('mode');
        $startPath = $this->startPath;
        $token = $this->token;
        $style = $this->style;
        $messages = $this->messages;
        $uploadFeedback = $this->uploadFeedback;
        $isWritable = is_writable($startPath);

        ob_start();
        ?>
        <style type="text/css">
            .warning {
                color: #c00;
            }
        </style>
        <h1><?= lang('manage_files') ?></h1>

        <div id="actions">
            <?= $this->renderActionButtons($mode, $isWritable) ?>
        </div>

        <div class="section">
            <div class="sectionBody">
                <script type="text/javascript">
                    var current_path = '<?= htmlspecialchars($startPath, ENT_QUOTES, $this->charset) ?>';

                    function show_image(url) {
                        document.getElementById('imageviewer').style.border = "1px solid #ccc";
                        document.getElementById('imageviewer').src = url;
                    }

                    function setColor(o, state) {
                        if (!o) return;
                        if (state && o.style) o.style.backgroundColor = '#eeeeee';
                        else if (o.style) o.style.backgroundColor = 'transparent';
                    }

                    function confirmDelete() {
                        return confirm("<?= lang('confirm_delete_file') ?>");
                    }

                    function confirmDeleteFolder(status) {
                        if (status !== 'file_exists')
                            return confirm("<?= lang('confirm_delete_dir') ?>");
                        else
                            return confirm("<?= lang('confirm_delete_dir_recursive') ?>");
                    }

                    function confirmUnzip() {
                        return confirm("<?= lang('confirm_unzip_file') ?>");
                    }

                    function unzipFile(file) {
                        if (confirmUnzip()) {
                            window.location.href = "index.php?a=31&mode=unzip&path=" + current_path + '/&file=' + file + "&token=<?= $token ?>";
                            return false;
                        }
                    }

                    function getFolderName(a) {
                        var f;
                        f = window.prompt('Enter New Folder Name:', '')
                        if (f) a.href += escape(f);
                        return (f) ? true : false;
                    }

                    function getFileName(a) {
                        var f;
                        f = window.prompt('Enter New File Name:', '')
                        if (f) a.href += escape(f);
                        return (f) ? true : false;
                    }

                    function deleteFolder(folder, status) {
                        if (confirmDeleteFolder(status)) {
                            window.location.href = "index.php?a=31&mode=deletefolder&path=" + current_path + "&folderpath=" + current_path + '/' + folder + "&token=<?= $token ?>";
                            return false;
                        }
                    }

                    function deleteFile(file) {
                        if (confirmDelete()) {
                            window.location.href = "index.php?a=31&mode=delete&path=" + current_path + '/' + file + "&token=<?= $token ?>";
                            return false;
                        }
                    }
                </script>
                <?php
                foreach ($messages as $message) {
                    echo $message;
                }
                ?>
                <?= $breadcrumb ?>
                <table>
                    <tr>
                        <td><b><?= lang('files_filename') ?></b></td>
                        <td><b><?= lang('files_modified') ?></b></td>
                        <td><b><?= lang('files_filesize') ?></b></td>
                        <td><b><?= lang('files_fileoptions') ?></b></td>
                    </tr>
                    <?= $listing['rows'] ?>
                    <?php if ($listing['folders'] == 0 && $listing['files'] == 0) { ?>
                        <tr>
                            <td colspan="4"><img src="<?= $style['tree_deletedfolder'] ?>" /><span style="color:#888;cursor:default;"> This directory is empty.</span></td>
                        </tr>
                    <?php } ?>
                </table>
                <hr />
                <?php
                echo lang('files_directories'), ': <b>', $listing['folders'], '</b> ';
                echo lang('files_files'), ': <b>', $listing['files'], '</b> ';
                echo lang('files_data'), ': <b><span dir="ltr">', $this->modx->nicesize($listing['totalSize']), '</span></b> ';
                $writableLabel = $isWritable ? lang('yes') . '.' : lang('no') . '.';
                echo lang('files_dirwritable'), ' <b>', $writableLabel, '</b>';
                ?>
                <div>
                    <img src="<?= $style['tx'] ?>" id="imageviewer" />
                </div>

                <?= $this->renderUploadSection($uploadFeedback, $isWritable) ?>
            </div>
        </div>

        <?= $this->renderFileEditor($mode) ?>
        <?php
        return ob_get_clean();
    }

    private function renderActionButtons($mode, $isWritable)
    {
        if ($mode !== 'drill') {
            $href = 'a=31&path=' . urlencode($this->startPath);
        } else {
            $href = 'a=2';
        }

        ob_start();
        ?>
        <ul class="actionButtons">
            <?php if ($isWritable) {
                if ($mode === 'save') {
                    ?>
                    <li class="primary"><a href="#" onclick="document.editFile.submit();"><img src="<?= $this->style['icons_save'] ?>" /> <?= lang('save') ?></a></li>
                    <?php
                }
                ?>
                <li><a href="<?= 'index.php?a=31&mode=newfolder&path=' . urlencode($this->startPath) . '&name=' ?>" onclick="return getFolderName(this);"><img src="<?= $this->style['tree_folder'] ?>" alt="" /> <?= lang('add_folder') ?></a></li>
                <?php
                $tpl = '<li><a href="[+href+]" onclick="return getFileName(this);"><img src="[+image+]" alt="" /> [+lang_newfile+]</a></li>';
                $placeholders = [
                    'image' => $this->style['tree_page'],
                    'href' => 'index.php?a=31&mode=newfile&path=' . urlencode($this->startPath) . '&name=',
                    'lang_newfile' => lang('files.dynamic.php1')
                ];
                echo $this->modx->parseText($tpl, $placeholders);
            }
            ?>
            <li id="Button5" class="mutate">
                <a href="#" onclick="documentDirty=false;document.location.href='index.php?<?= $href ?>';"><img alt="icons_cancel" src="<?= $this->style['icons_cancel'] ?>" /> <?= lang('cancel') ?></a>
            </li>
        </ul>
        <?php
        return ob_get_clean();
    }

    private function renderUploadSection($uploadFeedback, $isWritable)
    {
        ob_start();
        if ($isWritable) {
            ?>
            <form name="upload" enctype="multipart/form-data" action="index.php" method="post">
                <input type="hidden" name="MAX_FILE_SIZE" value="<?= evo()->config('upload_maxsize', 32 * 1024 * 1024) ?>">
                <input type="hidden" name="a" value="31">
                <input type="hidden" name="path" value="<?= htmlspecialchars($this->startPath, ENT_QUOTES, $this->charset) ?>">
                <?php if ($uploadFeedback) {
                    echo $uploadFeedback;
                } ?>
                <div id="uploader" class="actionButtons" style="margin-top:10px;">
                    <input type="file" name="userfile" onchange="document.upload.submit();">
                    <a class="default" href="#" onclick="document.upload.submit()" style="display:inline;float:none;">
                        <img src="<?= $this->style['icons_add'] ?>" /> <?= lang('files_uploadfile') ?>
                    </a>
                    <input type="submit" value="<?= lang('files_uploadfile') ?>" style="display:none;">
                </div>
            </form>
            <?php
        } else {
            echo '<p>' . lang('files_upload_inhibited_msg') . '</p>';
        }
        return ob_get_clean();
    }

    private function renderFileEditor($mode)
    {
        if (!in_array($mode, ['save', 'view'], true)) {
            return '';
        }

        $filename = anyv('path');
        $buffer = @file_get_contents($filename);
        $this->logFileChange('view', $filename);
        if ($buffer === false) {
            return 'Error opening file for reading.';
        }

        $entBuffer = htmlentities($buffer, ENT_COMPAT, $this->charset);
        if (!empty($buffer) && empty($entBuffer)) {
            $buffer = mb_convert_encoding($buffer, $this->charset, 'SJIS-win,SJIS,EUCJP-win,EUC-JP,UTF-8');
            $entBuffer = htmlentities($buffer, ENT_COMPAT, $this->charset);
        }

        ob_start();
        ?>
        <div class="section">
            <div class="sectionHeader" id="file_editfile"><?= $mode === 'save' ? lang('files_editfile') : lang('files_viewfile') ?></div>
            <div class="sectionBody">
                <?php if ($mode === 'save') { ?>
                    <form action="index.php" method="post" name="editFile">
                        <input type="hidden" name="a" value="31" />
                        <input type="hidden" name="mode" value="save" />
                        <input type="hidden" name="path" value="<?= htmlspecialchars(anyv('path'), ENT_QUOTES, $this->charset) ?>" />
                        <table width="100%" border="0" cellspacing="0" cellpadding="0">
                            <tr>
                                <td>
                                    <textarea dir="ltr" style="width:100%; height:370px;" name="content" class="phptextarea"><?= $entBuffer ?></textarea>
                                </td>
                            </tr>
                        </table>
                    </form>
                <?php } else { ?>
                    <div style="background-color:#fcfcfc;border: 1px solid #ccc; padding:10px 20px;">
                        <?= '<pre>' . $entBuffer . '</pre>' ?>
                    </div>
                <?php } ?>
            </div>
        </div>
        <?php
        return ob_get_clean();
    }

    private function handleFileUpload()
    {
        $msg = '';

        if (!filev('userfile.tmp_name')) {
            return $msg;
        }

        $userfile = [
            'tmp_name' => filev('userfile.tmp_name'),
            'error' => filev('userfile.error'),
            'name' => filev('userfile.name'),
            'type' => filev('userfile.type')
        ];

        if (evo()->config('clean_uploaded_filename') == 1) {
            $nameparts = explode('.', $userfile['name']);
            $nameparts = array_map(function ($part) {
                return $this->modx->stripAlias($part, 'file_manager');
            }, $nameparts);
            $userfile['name'] = implode('.', $nameparts);
        }

        $path = MODX_SITE_URL . substr($this->startPath, strlen(config('filemanager_path')));
        $path = rtrim($path, '/') . '/' . $userfile['name'];
        $msg .= $path;
        if ($userfile['error'] == 0) {
            $img = (strpos($userfile['type'], 'image') !== false) ? '<br /><img src="' . $path . '" height="75" />' : '';
            $msg .= sprintf(
                '<p>%s%s, %s%s</p>',
                lang('files_file_type'),
                $userfile['type'],
                $this->modx->nicesize(filesize($userfile['tmp_name'])),
                $img
            );
        }

        $userfilename = $userfile['tmp_name'];

        if (!is_uploaded_file($userfilename)) {
            $msg .= '<br /><span class="warning"><b>' . lang('files_upload_error') . ':</b>';
            switch ($userfile['error']) {
                case 0:
                    $msg .= lang('files_upload_error0');
                    break;
                case 1:
                    $msg .= lang('files_upload_error1');
                    break;
                case 2:
                    $msg .= lang('files_upload_error2');
                    break;
                case 3:
                    $msg .= lang('files_upload_error3');
                    break;
                case 4:
                    $msg .= lang('files_upload_error4');
                    break;
                default:
                    $msg .= lang('files_upload_error5');
                    break;
            }
            return $msg . '</span><br />';
        }

        if (!$this->isExtensionAllowed($userfile['name'])) {
            return $msg . '<p><span class="warning">' . lang('files_filetype_notok') . '</span></p>';
        }

        $targetDirectory = postv('path') ? str_replace('..', '', postv('path')) : $this->startPath;
        $destination = rtrim($targetDirectory, '/') . '/' . $userfile['name'];
        $rs = $this->modx->move_uploaded_file(
            $userfile['tmp_name'],
            $destination
        );
        if (!$rs) {
            return $msg . '<p><span class="warning">' . lang('files_upload_copyfailed') . '</span> ' . lang('files_upload_permissions_error') . '</p>';
        }

        evo()->invokeEvent('OnFileManagerUpload', [
            'filepath' => $targetDirectory,
            'filename' => $userfile['name']
        ]);

        $this->logFileChange('upload', $destination);
        return $msg . '<p><span class="success">' . lang('files_upload_ok') . '</span></p>';
    }

    private function saveFile()
    {
        $this->logFileChange('modify', postv('path'));
        if (file_put_contents(postv('path'), postv('content')) === false) {
            return lang('editing_file') . '<span class="warning"><b>' . lang('file_not_saved') . '</b></span><br /><br />';
        }

        $_REQUEST['mode'] = 'save';
        return lang('editing_file') . '<span class="success"><b>' . lang('file_saved') . '</b></span><br /><br />';
    }

    private function deleteFile()
    {
        $this->logFileChange('delete', anyv('path'));
        $msg = sprintf(lang('deleting_file'), str_replace('\\', '/', anyv('path')));
        if (!unlink(anyv('path'))) {
            return $msg . '<span class="warning"><b>' . lang('file_not_deleted') . '</b></span><br /><br />';
        }

        return $msg . '<span class="success"><b>' . lang('file_deleted') . '</b></span><br /><br />';
    }

    private function handleUnzip()
    {
        $fileName = str_replace(['../', '..\\'], '', anyv('file'));
        $err = $this->unzipArchive(realpath($this->startPath . '/' . $fileName), realpath($this->startPath));
        if (!$err) {
            return sprintf(
                '<span class="warning"><b>%s%s</b></span><br /><br />',
                lang('file_unzip_fail'),
                $err === 0 ? 'Missing zip library (php_zip.dll / zip.so)' : ''
            );
        }

        return '<span class="success"><b>' . lang('file_unzip') . '</b></span><br /><br />';
    }

    private function handleDeleteFolder()
    {
        $folderPath = str_replace(['../', '..\\'], '', anyv('folderpath'));
        if (!@$this->removeDirectory($folderPath)) {
            return sprintf('<span class="warning"><b>%s</b></span><br /><br />', lang('file_folder_not_deleted'));
        }

        return sprintf('<span class="success"><b>%s</b></span><br /><br />', lang('file_folder_deleted'));
    }

    private function handleCreateFolder()
    {
        $foldername = str_replace(['../', '..\\'], '', anyv('name'));
        $oldUmask = umask(0);
        if (!$this->mkdirRecursive($this->startPath . '/' . $foldername, 0777)) {
            $message = sprintf('<span class="warning"><b>%s</b></span><br /><br />', lang('file_folder_not_created'));
        } elseif (!@chmod($this->startPath . '/' . $foldername, $this->newFolderMode)) {
            $message = sprintf('<span class="warning"><b>%s</b></span><br /><br />', lang('file_folder_chmod_error'));
        } else {
            $message = sprintf('<span class="success"><b>%s</b></span><br /><br />', lang('file_folder_created'));
        }
        umask($oldUmask);

        return $message;
    }

    private function handleCreateFile()
    {
        $filename = str_replace(['../', '..\\'], '', anyv('name'));
        $filename = db()->escape($filename);

        if (!$this->isExtensionAllowed($filename)) {
            return sprintf('<span class="warning"><b>%s</b></span><br /><br />', lang('files_filetype_notok'));
        }
        if (preg_match('@([/:;,*?"<>|])@', $filename) !== 0) {
            return lang('files.dynamic.php3');
        }

        $oldUmask = umask(0);
        $result = file_put_contents($this->startPath . '/' . $filename, '');
        umask($oldUmask);

        if ($result === false) {
            return '<span class="warning"><b>' . lang('file_folder_not_created') . '</b></span><br /><br />';
        }

        return lang('files.dynamic.php4');
    }

    private function getEditableExtensions()
    {
        if ($this->editableExtensions !== null) {
            return $this->editableExtensions;
        }

        $aliasSuffix = config('alias_suffix');
        if (!empty($aliasSuffix)) {
            $aliasSuffix = ',' . ltrim($aliasSuffix, '.');
        }
        $this->editableExtensions = $this->addDot(
            explode(',', 'txt,php,tpl,shtml,html,htm,xml,js,css,pageCache,htaccess' . $aliasSuffix)
        );

        return $this->editableExtensions;
    }

    private function getUploadableExtensions()
    {
        if ($this->uploadableExtensionsCache !== null) {
            return $this->uploadableExtensionsCache;
        }

        $this->uploadableExtensionsCache = $this->addDot(array_merge(
            explode(',', config('upload_files', '')),
            explode(',', config('upload_images', '')),
            explode(',', config('upload_media', ''))
        ));

        return $this->uploadableExtensionsCache;
    }

    private function getInlineViewableExtensions()
    {
        if ($this->inlineViewableExtensionsCache !== null) {
            return $this->inlineViewableExtensionsCache;
        }

        $aliasSuffix = config('alias_suffix');
        if (!empty($aliasSuffix)) {
            $aliasSuffix = ',' . ltrim($aliasSuffix, '.');
        }
        $this->inlineViewableExtensionsCache = $this->addDot(
            explode(',', 'txt,php,tpl,html,htm,xml,js,css,pageCache,htaccess,sample' . $aliasSuffix)
        );

        return $this->inlineViewableExtensionsCache;
    }

    private function getViewableExtensions()
    {
        if ($this->viewableExtensionsCache === null) {
            $this->viewableExtensionsCache = $this->addDot(explode(',', 'jpg,gif,png,ico'));
        }

        return $this->viewableExtensionsCache;
    }

    private function addDot(array $extensions)
    {
        $extensions = array_filter(array_map('trim', $extensions));
        foreach ($extensions as $index => $value) {
            $extensions[$index] = '.' . strtolower($value);
        }
        return array_values(array_unique($extensions));
    }

    private function isExtensionAllowed($path)
    {
        return in_array($this->getExtension($path), $this->getUploadableExtensions(), true);
    }

    private function getExtension($string)
    {
        $pos = strrpos($string, '.');
        if ($pos !== false) {
            return strtolower(substr($string, $pos));
        }

        return false;
    }

    private function removeLastPath($string)
    {
        $pos = strrpos($string, '/');
        if ($pos !== false) {
            return substr($string, 0, $pos);
        }

        return false;
    }

    private function mkdirRecursive($path, $mode)
    {
        if (is_dir($path)) {
            return true;
        }
        $parent = dirname($path);
        if (!$this->mkdirRecursive($parent, $mode)) {
            return false;
        }
        return @mkdir($path, $mode);
    }

    private function logFileChange($type, $filename)
    {
        include_once(MODX_CORE_PATH . 'log.class.inc.php');
        $log = new logHandler();

        switch ($type) {
            case 'upload':
                $string = 'Uploaded File';
                break;
            case 'delete':
                $string = 'Deleted File';
                break;
            case 'modify':
                $string = 'Modified File';
                break;
            default:
                $string = 'Viewing File';
                break;
        }

        $log->initAndWriteLog($string, '', '', '', $type, $filename);

        global $action;
        $action = 1;
    }

    private function unzipArchive($file, $path)
    {
        if (!extension_loaded('zip')) {
            return 0;
        }
        $zip = new ZipArchive();
        if ($zip->open($file) !== true) {
            return false;
        }

        $oldUmask = umask(0);
        $path = rtrim($path, '/') . '/';
        for ($i = 0; $i < $zip->numFiles; $i++) {
            $zipEntryName = $zip->getNameIndex($i);
            $completePath = $path . str_replace('\\', '/', dirname($zipEntryName));
            $completeName = $path . str_replace('\\', '/', $zipEntryName);
            if (!is_dir($completePath)) {
                $tmp = '';
                foreach (explode('/', $completePath) as $segment) {
                    if ($segment === '') {
                        continue;
                    }
                    $tmp .= $segment . '/';
                    if (!is_dir($tmp)) {
                        mkdir($tmp, 0777);
                    }
                }
            }
            copy("zip://" . $file . "#" . $zipEntryName, $completeName);
        }
        umask($oldUmask);
        $zip->close();
        return true;
    }

    private function removeDirectory($dir)
    {
        foreach (glob($dir . '/*') as $file) {
            if (is_dir($file)) {
                $this->removeDirectory($file);
            } else {
                unlink($file);
            }
        }
        return rmdir($dir);
    }

    private function buildWebstartPath()
    {
        $webstartPath = str_replace(
            [realpath('../'), '\\'],
            ['', '/'],
            realpath(config('filemanager_path'))
        );
        if (strpos($webstartPath, '/') === 0) {
            return '..' . $webstartPath;
        }
        return '../' . $webstartPath;
    }

    private function buildProtectedPaths()
    {
        if (manager()->isAdmin()) {
            return [];
        }

        $paths = [
            rtrim(base_path() . 'manager', '/'),
            rtrim(base_path() . 'temp/backup', '/'),
            rtrim(base_path() . 'assets/backup', '/')
        ];

        if (!evo()->hasPermission('save_plugin')) {
            $paths[] = rtrim(base_path() . 'assets/plugins', '/');
        }
        if (!evo()->hasPermission('save_snippet')) {
            $paths[] = rtrim(base_path() . 'assets/snippets', '/');
        }
        if (!evo()->hasPermission('save_template')) {
            $paths[] = rtrim(base_path() . 'assets/templates', '/');
        }
        if (!evo()->hasPermission('save_module')) {
            $paths[] = rtrim(base_path() . 'assets/modules', '/');
        }
        if (!evo()->hasPermission('empty_cache')) {
            $paths[] = rtrim(MODX_CACHE_PATH, '/');
        }
        if (!evo()->hasPermission('import_static')) {
            $paths[] = rtrim(base_path() . 'temp/import', '/');
            $paths[] = rtrim(base_path() . 'assets/import', '/');
        }
        if (!evo()->hasPermission('export_static')) {
            $paths[] = rtrim(base_path() . 'temp/export', '/');
            $paths[] = rtrim(base_path() . 'assets/export', '/');
        }

        return array_values(array_unique($paths));
    }

    private function isProtectedPath($path)
    {
        return in_array(rtrim($path, '/'), $this->protectedPaths, true);
    }
}

class FileManagerLister
{
    private $modx;
    private $style;
    private $stylePath;
    private $charset;
    private $enableFileUnzip;
    private $enableFileDownload;
    private $protectedPaths;
    private $baseLength;
    private $webstartPath;
    private $editableExtensions;
    private $uploadableExtensions;
    private $inlineViewableExtensions;
    private $viewableExtensions;
    private $folderCount = 0;
    private $fileCount = 0;
    private $totalSize = 0;

    public function __construct($modx, array $style, $stylePath, $charset, $enableFileUnzip, $enableFileDownload, array $protectedPaths, $baseLength, $webstartPath, array $editableExtensions, array $uploadableExtensions, array $inlineViewableExtensions, array $viewableExtensions)
    {
        $this->modx = $modx;
        $this->style = $style;
        $this->stylePath = $stylePath;
        $this->charset = $charset;
        $this->enableFileUnzip = $enableFileUnzip;
        $this->enableFileDownload = $enableFileDownload;
        $this->protectedPaths = $protectedPaths;
        $this->baseLength = $baseLength;
        $this->webstartPath = $webstartPath;
        $this->editableExtensions = $editableExtensions;
        $this->uploadableExtensions = $uploadableExtensions;
        $this->inlineViewableExtensions = $inlineViewableExtensions;
        $this->viewableExtensions = $viewableExtensions;
    }

    public function render($path)
    {
        if (!defined('SCANDIR_SORT_ASCENDING')) {
            define('SCANDIR_SORT_ASCENDING', 0);
            define('SCANDIR_SORT_DESCENDING', 1);
        }

        $curpath = str_replace('//', '/', $path . '/');
        if (!is_dir($curpath)) {
            return 'Invalid path "' . $curpath . '"<br />';
        }

        $protectedPaths = $this->protectedPaths;
        $dir = scandir($curpath, SCANDIR_SORT_ASCENDING);
        $dirsArray = [];
        $filesArray = [];
        $dircounter = 0;
        $filecounter = 0;

        foreach ($dir as $file) {
            $newpath = $curpath . $file;
            if ($file === '..' || $file === '.') {
                continue;
            }

            if (is_dir($newpath)) {
                $dirsArray[$dircounter]['dir'] = $newpath;
                $dirsArray[$dircounter]['stats'] = lstat($newpath);
                if (in_array(rtrim($newpath, '/'), $protectedPaths, true)) {
                    $dirsArray[$dircounter]['text'] = sprintf(
                        '<img src="%s" align="absmiddle" alt="" /> <span style="color:#bbb;">%s</span>',
                        $this->style['tree_deletedfolder'],
                        $file
                    );
                    if (is_writable($curpath)) {
                        $dirsArray[$dircounter]['delete'] = sprintf(
                            '<span style="width:20px" class="disabledImage"><img src="%sicons/delete.gif" alt="%s" title="%s" /></span>',
                            $this->stylePath,
                            lang('file_delete_folder'),
                            lang('file_delete_folder')
                        );
                    } else {
                        $dirsArray[$dircounter]['delete'] = '';
                    }
                } else {
                    $displayName = mb_convert_encoding(
                        $file,
                        $this->charset,
                        'SJIS-win,SJIS,EUCJP-win,EUC-JP,UTF-8'
                    );
                    $dirsArray[$dircounter]['text'] = sprintf(
                        '<img src="%s" align="absmiddle" alt="" /> <a href="index.php?a=31&mode=drill&path=%s"><b>%s</b></a>',
                        $this->style['tree_folder'],
                        urlencode($newpath),
                        $displayName
                    );

                    $dfiles = scandir($newpath, SCANDIR_SORT_ASCENDING);
                    foreach ($dfiles as $i => $infile) {
                        if ($infile === '..' || $infile === '.') {
                            unset($dfiles[$i]);
                        }
                    }
                    if (is_writable($curpath)) {
                        $dirsArray[$dircounter]['delete'] = sprintf(
                            '<span style="width:20px"><a href="javascript: deleteFolder(\'%s\',\'%s\');"><img src="%sicons/delete.gif" alt="%s" title="%s" /></a></span>',
                            urlencode($file),
                            (0 < count($dfiles)) ? 'file_exists' : '',
                            $this->stylePath,
                            lang('file_delete_folder'),
                            lang('file_delete_folder')
                        );
                    } else {
                        $dirsArray[$dircounter]['delete'] = '';
                    }
                }
                $dircounter++;
            } else {
                $type = $this->getExtension($newpath);
                $filesArray[$filecounter]['file'] = $newpath;
                $filesArray[$filecounter]['stats'] = lstat($newpath);
                $filesArray[$filecounter]['text'] = '<img src="' . $this->style['tree_page'] . '" align="absmiddle" alt="" />' . $file;

                if (in_array($type, $this->viewableExtensions)) {
                    $filesArray[$filecounter]['view'] = sprintf(
                        '<span style="cursor:pointer; width:20px;" onclick="show_image(\'%s%s\');"><img src="%sicons/context_view.gif" align="absmiddle" alt="%s" title="%s" /></span> ',
                        $this->webstartPath,
                        substr($newpath, $this->baseLength, strlen($newpath)),
                        $this->stylePath,
                        lang('files_viewfile'),
                        lang('files_viewfile')
                    );
                } else {
                    if ($this->enableFileDownload && in_array($type, $this->uploadableExtensions)) {
                        $filesArray[$filecounter]['view'] = sprintf(
                            '<a href="%s%s" style="cursor:pointer; width:20px;"><img src="%smisc/ed_save.gif" align="absmiddle" alt="%s" title="%s" /></a> ',
                            $this->webstartPath,
                            implode('/', array_map('rawurlencode', explode('/', substr(
                                $newpath,
                                $this->baseLength,
                                strlen($newpath)
                            )))),
                            $this->stylePath,
                            lang('file_download_file'),
                            lang('file_download_file')
                        );
                    } else {
                        $filesArray[$filecounter]['view'] = sprintf(
                            '<span class="disabledImage"><img src="%sicons/context_view.gif" align="absmiddle" alt="%s" title="%s" /></span> ',
                            $this->stylePath,
                            lang('files_viewfile'),
                            lang('files_viewfile')
                        );
                    }
                }

                if (in_array($type, $this->inlineViewableExtensions)) {
                    $filesArray[$filecounter]['view'] = sprintf(
                        '<span style="width:20px;"><a href="index.php?a=31&mode=view&path=%s"><img src="%sicons/context_view.gif" align="absmiddle" alt="%s" title="%s" /></a></span> ',
                        urlencode($newpath),
                        $this->stylePath,
                        lang('files_viewfile'),
                        lang('files_viewfile')
                    );
                }

                if ($this->enableFileUnzip && $type === '.zip') {
                    $filesArray[$filecounter]['unzip'] = sprintf(
                        '<span style="width:20px;"><a href="javascript:unzipFile(\'%s\');"><img src="%sicons/unzip.gif" align="absmiddle" alt="%s" title="%s" /></a></span> ',
                        urlencode($file),
                        $this->stylePath,
                        lang('file_download_unzip'),
                        lang('file_download_unzip')
                    );
                } else {
                    $filesArray[$filecounter]['unzip'] = '';
                }

                if (in_array($type, $this->editableExtensions) && is_writable($curpath) && is_writable($newpath)) {
                    $filesArray[$filecounter]['edit'] = sprintf(
                        '<span style="width:20px;"><a href="index.php?a=31&mode=save&path=%s#file_editfile"><img src="%s" align="absmiddle" alt="%s" title="%s" /></a></span> ',
                        urlencode($newpath),
                        $this->style['icons_edit_document'],
                        lang('files_editfile'),
                        lang('files_editfile')
                    );
                } else {
                    $filesArray[$filecounter]['edit'] = sprintf(
                        '<span class="disabledImage"><img src="%s" align="absmiddle" alt="%s" title="%s" /></span> ',
                        $this->style['icons_edit_document'],
                        lang('files_editfile'),
                        lang('files_editfile')
                    );
                }

                if (is_writable($curpath) && is_writable($newpath)) {
                    $filesArray[$filecounter]['delete'] = sprintf(
                        '<span style="width:20px;"><a href="javascript:deleteFile(\'%s\');"><img src="%sicons/delete.gif" align="absmiddle" alt="%s" title="%s" /></a></span> ',
                        urlencode($file),
                        $this->stylePath,
                        lang('file_delete_file'),
                        lang('file_delete_file')
                    );
                } else {
                    $filesArray[$filecounter]['delete'] = sprintf(
                        '<span class="disabledImage"><img src="%sicons/delete.gif" align="absmiddle" alt="%s" title="%s" /></span> ',
                        $this->stylePath,
                        lang('file_delete_file'),
                        lang('file_delete_file')
                    );
                }

                $filecounter++;
            }
        }

        ob_start();
        $this->folderCount = count($dirsArray);
        sort($dirsArray);
        foreach ($dirsArray as $value) {
            $this->totalSize += $value['stats']['7'];
            echo '<tr style="cursor:default;" onmouseout="setColor(this,0)" onmouseover="setColor(this,1)">';
            echo '<td style="padding-right:10px;">', $value['text'], '</td>';
            echo '<td>', $this->modx->toDateFormat($value['stats']['9']), '</td>';
            echo '<td dir="ltr">', $this->modx->nicesize($value['stats']['7']), '</td>';
            echo '<td>', $value['delete'], '</td>';
            echo '</tr>';
        }

        $this->fileCount = count($filesArray);
        sort($filesArray);
        foreach ($filesArray as $value) {
            $this->totalSize += $value['stats']['7'];
            echo '<tr onmouseout="setColor(this,0)" onmouseover="setColor(this,1)">';
            echo '<td style="padding-right:10px;">', $value['text'], '</td>';
            echo '<td>', $this->modx->toDateFormat($value['stats']['9']), '</td>';
            echo '<td dir="ltr">', $this->modx->nicesize($value['stats']['7']), '</td>';
            echo '<td>', $value['unzip'], $value['view'], $value['edit'], $value['delete'], '</td>';
            echo '</tr>';
        }

        return ob_get_clean();
    }

    public function getFolderCount()
    {
        return $this->folderCount;
    }

    public function getFileCount()
    {
        return $this->fileCount;
    }

    public function getTotalSize()
    {
        return $this->totalSize;
    }

    private function getExtension($string)
    {
        $pos = strrpos($string, '.');
        if ($pos !== false) {
            return strtolower(substr($string, $pos));
        }
        return false;
    }
}
