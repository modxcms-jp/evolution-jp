<?php
if (!isset($modx) || !evo()->isLoggedin()) {
    exit;
}
if (!evo()->hasPermission('file_manager')) {
    alert()->setError(3);
    alert()->dumpError();
}
global $_style;
// settings
$style_path = parseText('[+site_url+]manager/media/style/common/images/', $modx->config);
$alias_suffix = (!empty($friendly_url_suffix)) ? ',' . ltrim($friendly_url_suffix, '.') : '';

$proteted_path = proteted_path();

// Mod added by Raymond
$enablefileunzip = true;
$enablefiledownload = true;
$newfolderaccessmode = $modx->config['new_folder_permissions'] ? octdec($modx->config['new_folder_permissions']) : 0777;
$new_file_permissions = config('new_file_permissions') ? octdec(config('new_file_permissions')) : 0666;

// get the current work directory
if (anyv('path')) {
    $_REQUEST['path'] = str_replace('..', '', anyv('path'));
    $startpath = is_dir(anyv('path')) ? anyv('path') : removeLastPath(anyv('path'));
} else {
    $startpath = config('filemanager_path');
}
$startpath = rtrim($startpath, '/');

if (!is_readable($startpath)) {
    echo lang('not_readable_dir');
    exit;
}

// ファイル操作より前に、要求されたすべてのパスを保護対象と照合する
$requested_paths = array_filter([$startpath, getv('path'), postv('path'), anyv('folderpath')]);
if (anyv('file')) {
    $requested_paths[] = $startpath . '/' . anyv('file');
}
if (anyv('name')) {
    // 新規フォルダ・新規ファイルで実際に使う形で照合する
    $requested_paths[] = $startpath . '/' . evo()->sanitizeFilename(anyv('name'), false);
}
foreach ($requested_paths as $requested_path) {
    if (is_protected_path($requested_path, $proteted_path)) {
        echo lang('files_dir_not_viewable');
        exit;
    }
}

?>
<style type="text/css">
    .warning {
        color: #c00;
    }
</style>
<h1><?= lang('manage_files') ?></h1>

<div id="actions">
    <ul class="actionButtons">
        <?php
        if (getv('mode') !== 'drill') {
            $href = 'a=31&path=' . urlencode($startpath);
        } else {
            $href = 'a=2';
        }

        if (is_writable($startpath)) {
            $ph = [];
            $_ = '';
            if (anyv('mode') === 'save') {
                $_ = $modx->parseText(
                    '<li class="primary"><a href="#" onclick="document.editFile.submit();">[+lang_save+]</a></li>',
                    [
                        'lang_save' => lang('save')
                    ]
                ) . "\n";
            }
            $_ .= $modx->parseText(
                '<li><a href="[+href+]" data-evo-post onclick="return getFolderName(this);">[+subject+]</a></li>',
                [
                    'href' => hsc('index.php?a=31&mode=newfolder&path=' . urlencode($startpath) . '&name='),
                    'subject' => lang('add_folder')
                ]
            );

            $tpl = '<li><a href="[+href+]" data-evo-post onclick="return getFileName(this);">[+lang_newfile+]</a></li>';
            $ph['href'] = hsc('index.php?a=31&mode=newfile&path=' . urlencode($startpath) . '&name=');
            $ph['lang_newfile'] = lang('files_create_textfile');
            $_ .= $modx->parseText($tpl, $ph);
            echo $_;
        }
        ?>
        <li id="Button5" class="mutate">
            <a
                href="#"
                onclick="documentDirty=false;document.location.href='index.php?<?= $href ?>';"><?= lang('cancel') ?></a>
        </li>
    </ul>
</div>

<div class="section">
    <div class="sectionBody">
        <script type="text/javascript">
            // パスと名前は PHP で URL エンコードしたものを使う（UTF-8 でない名前も、そのままのバイト列で送り返せるように）
            var current_path_q = <?= js_literal(rawurlencode($startpath)) ?>;

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
                    EvoCsrf.post("index.php?a=31&mode=unzip&path=" + current_path_q + '%2F&file=' + file);
                    return false;
                }
            }

            function getFolderName(a) {
                var f;
                f = window.prompt('Enter New Folder Name:', '')
                if (f) a.href += encodeURIComponent(f);
                return (f) ? true : false;
            }

            function getFileName(a) {
                var f;
                f = window.prompt('Enter New File Name:', '')
                if (f) a.href += encodeURIComponent(f);
                return (f) ? true : false;
            }

            function deleteFolder(folder, status) {
                if (confirmDeleteFolder(status)) {
                    EvoCsrf.post("index.php?a=31&mode=deletefolder&path=" + current_path_q + "&folderpath=" + current_path_q + '%2F' + folder);
                    return false;
                }
            }

            function deleteFile(file) {
                if (confirmDelete()) {
                    EvoCsrf.post("index.php?a=31&mode=delete&path=" + current_path_q + '%2F' + file);
                    return false;
                }
            }
        </script>
        <?php
        if (!empty($_FILES['userfile'])) {
            $information = fileupload();
        } elseif (postv('mode') === 'save') {
            echo textsave();
        } elseif (anyv('mode') === 'delete') {
            echo delete_file();
        }

        if (rtrim($startpath, '/') == config('filemanager_path')) {
            $ph = [
                'image' => $_style['tree_deletedfolder'],
                'subject' => '<span class="file-disabled">Top</span>'
            ];
        } else {
            $ph = [
                'image' => $_style['tree_folder'],
                'subject' => sprintf(
                    '<a href="index.php?a=31&mode=drill&path=%s">Top</b></a> / ',
                    config('filemanager_path')
                )
            ];
        }
        echo $modx->parseText(
            '<img src="[+image+]" align="absmiddle" alt="" />[+subject+] ',
            $ph
        );

        $len = strlen(config('filemanager_path'));
        if (substr($startpath, $len) == '') {
            $topic_path = '/';
        } else {
            $topic_path = substr($startpath, $len);
            $pieces = explode('/', rtrim($topic_path, '/'));
            $path = '';
            $count = count($pieces);
            foreach ($pieces as $i => $v) {
                if (empty($v)) {
                    continue;
                }
                $path .= rtrim($v, '/') . '/';
                // 名前は UTF-8 として扱う（推測で変換すると UTF-8 の日本語を取り違えて化ける）。不正なバイトは hsc() が置き換える
                $label = hsc(trim($v, '/'));
                if (1 < $count) {
                    $pieces[$i] = sprintf(
                        '<a href="%s">%s</a>',
                        hsc('index.php?a=31&mode=drill&path=' . urlencode(config('filemanager_path') . $path)),
                        $label
                    );
                } else {
                    $pieces[$i] = $label;
                }
                $count--;
            }
            $topic_path = implode(' / ', $pieces);
        }

        ?> <b><?= $topic_path ?></b>
        <?php
        // check to see user isn't trying to move below the document_root
        if (substr(strtolower(str_replace('//', '/', $startpath . "/")), 0, $len) != strtolower(str_replace(
            '//',
            '/',
            config('filemanager_path') . '/'
        ))) {
            echo lang('files_access_denied') ?>
    </div>

<?php
            exit;
        }

        // Unzip .zip files - by Raymond
        if ($enablefileunzip && anyv('mode') === 'unzip' && is_writable($startpath)) {
            $failed = [];
            $skipped = [];
            $err = unzip(realpath($startpath . '/' . anyv('file')), realpath($startpath), $failed, $skipped);
            if (!$err) {
                echo sprintf(
                    '<span class="warning"><b>%s%s</b></span><br /><br />',
                    lang('file_unzip_fail'),
                    $err === 0 ? 'Missing zip library (php_zip.dll / zip.so)' : ''
                );
            } else {
                if ($failed) {
                    echo sprintf(
                        '<span class="warning"><b>%s</b></span><br />%s<br /><br />',
                        lang('file_unzip_entry_failed'),
                        implode('<br />', array_map('hsc', $failed))
                    );
                } else {
                    echo '<span class="success"><b>' . lang('file_unzip') . '</b></span><br /><br />';
                }
                if ($skipped) {
                    echo sprintf(
                        '<span class="warning"><b>%s</b></span><br />%s<br /><br />',
                        lang('file_unzip_skipped'),
                        implode('<br />', array_map('hsc', $skipped))
                    );
                }
            }
        }
        // End Unzip - Raymond


        // New Folder & Delete Folder option - Raymond
        if (is_writable($startpath)) {
            // Delete Folder
            if (anyv('mode') === 'deletefolder') {
                $folder = anyv('folderpath');
                if (!is_dir($folder)) {
                    echo sprintf(
                        '<span class="warning"><b>%s</b></span><br /><br />',
                        lang('file_folder_not_found')
                    );
                } elseif (!@rrmdir($folder)) {
                    echo sprintf(
                        '<span class="warning"><b>%s</b></span><br /><br />',
                        lang('file_folder_not_deleted')
                    );
                } else {
                    echo sprintf(
                        '<span class="success"><b>%s</b></span><br /><br />',
                        lang('file_folder_deleted')
                    );
                }
            }

            // Create folder here
            if (anyv('mode') === 'newfolder') {
                $old_umask = umask(0);
                $foldername = evo()->sanitizeFilename(anyv('name'), false);
                if ($foldername === '' || !mkdirs($startpath . "/" . $foldername, 0777)) {
                    echo sprintf(
                        '<span class="warning"><b>%s</b></span><br /><br />',
                        lang('file_folder_not_created')
                    );
                } elseif (!@chmod($startpath . '/' . $foldername, $newfolderaccessmode)) {
                    echo sprintf(
                        '<span class="warning"><b>%s</b></span><br /><br />',
                        lang('file_folder_chmod_error')
                    );
                } else {
                    echo sprintf(
                        '<span class="success"><b>%s</b></span><br /><br />',
                        lang('file_folder_created')
                    );
                }
                umask($old_umask);
            }
            // Create file here
            if (anyv('mode') === 'newfile') {
                $old_umask = umask(0);
                $filename = evo()->sanitizeFilename(anyv('name'), false);

                if ($filename === '') {
                    echo lang('files_filename_invalid');
                } elseif (!checkExtension($filename)) {
                    echo sprintf(
                        '<span class="warning"><b>%s</b></span><br /><br />',
                        lang('files_filetype_notok')
                    );
                } elseif (file_exists($startpath . '/' . $filename) || is_link($startpath . '/' . $filename)) {
                    // 同じ名前のファイル・フォルダー（リンク切れのリンクも含む）があれば、空の中身で上書きしない
                    echo sprintf(
                        '<span class="warning"><b>%s</b></span><br /><br />',
                        lang('files_file_exists')
                    );
                } else {
                    $rs = file_put_contents($startpath . '/' . $filename, '');
                    if ($rs === false) {
                        echo '<span class="warning"><b>' . lang('file_folder_not_created') . '</b></span><br /><br />';
                    } else {
                        echo lang('files_textfile_created');
                    }
                    umask($old_umask);
                }
            }
        }
        // End New Folder - Raymond

        $filesize = 0;
        $files = 0;
        $folders = 0;
        $dirs_array = [];
        $files_array = [];
        if (strlen(MODX_BASE_PATH) < strlen(config('filemanager_path'))) {
            $len--;
        }

        echo '<br />';
?>
<table>
    <tr>
        <td><b><?= lang('files_filename') ?></b></td>
        <td><b><?= lang('files_modified') ?></b></td>
        <td><b><?= lang('files_filesize') ?></b></td>
        <td><b><?= lang('files_fileoptions') ?></b></td>
    </tr>
    <?php
    ls($startpath);
    echo "\n\n\n";
    if ($folders == 0 && $files == 0) {
        echo '<tr><td colspan="4"><img src="' . $_style['tree_deletedfolder'] . '" /><span class="file-disabled"> This directory is empty.</span></td></tr>';
    }
    ?>
</table>
<hr />
<?php
global $filesizes;

echo lang('files_directories'), ': <b>', $folders, '</b> ';
echo lang('files_files'), ': <b>', $files, '</b> ';
echo lang('files_data'), ': <b><span dir="ltr">', $modx->nicesize($filesizes), '</span></b> ';
echo lang('files_dirwritable'), ' <b>', is_writable($startpath) == 1 ? lang('yes') . '.' : lang('no') . '.'
?></b>
<div>
    <img src="<?= $_style['tx'] ?>" id="imageviewer" />
</div>

<?php
if (is_writable($startpath)) {
?>
    <form name="upload" enctype="multipart/form-data" action="index.php" method="post">
        <input
            type="hidden" name="MAX_FILE_SIZE"
            value="<?= evo()->config('upload_maxsize', 32 * 1024 * 1024) ?>">
        <input type="hidden" name="a" value="31">
        <input type="hidden" name="path" value="<?= hsc($startpath) ?>">

        <?php if (isset($information)) {
            echo $information;
        } ?>

        <div id="uploader" class="actionButtons">
            <input type="file" name="userfile" onchange="document.upload.submit();">
            <a
                class="default" href="#" onclick="document.upload.submit()"
                style="display:inline;float:none;"><?= lang('files_uploadfile') ?></a>
            <input type="submit" value="<?= lang('files_uploadfile') ?>" style="display:none;">
        </div>
    </form>
<?php
} else {
    echo "<p>" . lang('files_upload_inhibited_msg') . "</p>";
}

?>


</div>
</div>
<?php

if (anyv('mode') === 'save' || anyv('mode') === 'view') {
?>

    <div class="section">
        <div
            class="sectionHeader"
            id="file_editfile"><?= anyv('mode') === 'save' ? lang('files_editfile') : lang('files_viewfile') ?></div>
        <div class="sectionBody">
            <?php
            $filename = anyv('path');
            $buffer = is_file($filename) ? file_get_contents($filename) : false;

            // Log the change
            logFileChange('view', $filename);
            if ($buffer === false) {
                echo 'Error opening file for reading.';
                exit;
            }

            $ent_buffer = htmlentities($buffer, ENT_COMPAT, $modx_manager_charset);
            if (!empty($buffer) && empty($ent_buffer)) {
                $buffer = mb_convert_encoding($buffer, $modx_manager_charset, 'SJIS-win,SJIS,EUCJP-win,EUC-JP,UTF-8');
                $ent_buffer = htmlentities($buffer, ENT_COMPAT, $modx_manager_charset);
            }

            ?>

            <?php if (anyv('mode') === 'save') { ?>
                <form action="index.php" method="post" name="editFile">
                    <input type="hidden" name="a" value="31" />
                    <input type="hidden" name="mode" value="save" />
                    <input type="hidden" name="path" value="<?= hsc(anyv('path')) ?>" />
                    <table width="100%" border="0" cellspacing="0" cellpadding="0">
                        <tr>
                            <td>
                                <textarea
                                    dir="ltr" style="width:100%; height:370px;" name="content"
                                    class="phptextarea"><?= $ent_buffer ?></textarea>
                            </td>
                        </tr>
                    </table>
                </form>
            <?php } else { ?>
                <div style="background-color:#fcfcfc;border: 1px solid #ccc; padding:10px 20px;">
                    <?= '<pre>' . $ent_buffer . '</pre>' ?>
                </div>
            <?php } ?>
        </div>
    </div>
<?php
}

function ls($curpath)
{
    if (!defined('SCANDIR_SORT_ASCENDING')) {
        define('SCANDIR_SORT_ASCENDING', 0);
        define('SCANDIR_SORT_DESCENDING', 1);
    }
    global $style_path, $_style;
    global $enablefileunzip, $enablefiledownload, $folders, $files, $filesizes, $len, $dirs_array, $files_array, $modx;
    $dircounter = 0;
    $filecounter = 0;
    $curpath = str_replace('//', '/', $curpath . '/');

    if (!is_dir($curpath)) {
        echo 'Invalid path "', hsc($curpath), '"<br />';
        return;
    }
    $editablefiles = add_dot(
        explode(',', 'txt,php,tpl,shtml,html,htm,xml,js,css,pageCache,htaccess' . config('alias_suffix'))
    );
    $uploadablefiles = add_dot(uploadablefiles());
    $inlineviewablefiles = add_dot(
        explode(',', 'txt,php,tpl,html,htm,xml,js,css,pageCache,htaccess,sample' . config('alias_suffix'))
    );
    $viewablefiles = add_dot(
        explode(',', 'jpg,gif,png,ico')
    );

    $proteted_path = proteted_path();

    $dir = scandir($curpath, SCANDIR_SORT_ASCENDING);

    // first, get info
    foreach ($dir as $file) {
        $newpath = $curpath . $file;
        if ($file === '..' || $file === '.') {
            continue;
        }
        if (is_dir($newpath)) {
            $dirs_array[$dircounter]['dir'] = $newpath;
            $dirs_array[$dircounter]['stats'] = lstat($newpath);
            if ($file === '..' || $file === '.') {
                continue;
            }

            if (is_protected_path($newpath, $proteted_path)) {
                $dirs_array[$dircounter]['text'] = sprintf(
                    '<img src="%s" align="absmiddle" alt="" /> <span class="file-disabled">%s</span>',
                    $_style['tree_deletedfolder'],
                    hsc($file)
                );
                if (is_writable($curpath)) {
                    $dirs_array[$dircounter]['delete'] = sprintf(
                        '<span style="width:20px" class="disabledImage"><img src="%sicons/delete.gif" alt="%s" title="%s" /></span>',
                        $style_path,
                        lang('file_delete_folder'),
                        lang('file_delete_folder')
                    );
                } else {
                    $dirs_array[$dircounter]['delete'] = '';
                }
            } else {
                $dirs_array[$dircounter]['text'] = sprintf(
                    '<img src="%s" align="absmiddle" alt="" /> <a href="index.php?a=31&mode=drill&path=%s"><b>%s</b></a>',
                    $_style['tree_folder'],
                    hsc(urlencode($newpath)),
                    hsc($file)
                );

                $dfiles = scandir($newpath, SCANDIR_SORT_ASCENDING);
                foreach ($dfiles as $i => $infile) {
                    switch ($infile) {
                        case '..':
                        case '.':
                            unset($dfiles[$i]);
                            break;
                    }
                }
                if (is_writable($curpath)) {
                    $dirs_array[$dircounter]['delete'] = sprintf(
                        '<span style="width:20px"><a href="#" onclick="deleteFolder(%s, %s);return false;"><img src="%sicons/delete.gif" alt="%s" title="%s" /></a></span>',
                        hsc(js_literal(rawurlencode($file))),
                        hsc(js_literal((0 < count($dfiles)) ? 'file_exists' : '')),
                        $style_path,
                        lang('file_delete_folder'),
                        lang('file_delete_folder')
                    );
                } else {
                    $dirs_array[$dircounter]['delete'] = '';
                }
            }

            // increment the counter
            $dircounter++;
        } else {
            $type = getExtension($newpath);
            $files_array[$filecounter]['file'] = $newpath;
            $files_array[$filecounter]['stats'] = lstat($newpath);
            $files_array[$filecounter]['text'] = '<img src="' . $_style['tree_page'] . '" align="absmiddle" alt="" />' . hsc($file);
            if (in_array($type, $viewablefiles)) {
                $files_array[$filecounter]['view'] = sprintf(
                    '<span style="cursor:pointer; width:20px;" onclick="show_image(%s);"><img src="%sicons/context_view.gif" align="absmiddle" alt="%s" title="%s" /></span> ',
                    hsc(js_literal(webstart_path() . implode('/', array_map('rawurlencode', explode('/', substr($newpath, $len)))))),
                    $style_path,
                    lang('files_viewfile'),
                    lang('files_viewfile')
                );
            } else {
                if ($enablefiledownload && in_array($type, $uploadablefiles)) {
                    $files_array[$filecounter]['view'] = sprintf(
                        '<a href="%s%s" style="cursor:pointer; width:20px;"><img src="%smisc/ed_save.gif" align="absmiddle" alt="%s" title="%s" /></a> ',
                        webstart_path(),
                        implode('/', array_map('rawurlencode', explode('/', substr(
                            $newpath,
                            $len,
                            strlen($newpath)
                        )))),
                        $style_path,
                        lang('file_download_file'),
                        lang('file_download_file')
                    );
                } else {
                    $files_array[$filecounter]['view'] = sprintf(
                        '<span class="disabledImage"><img src="%sicons/context_view.gif" align="absmiddle" alt="%s" title="%s" /></span> ',
                        $style_path,
                        lang('files_viewfile'),
                        lang('files_viewfile')
                    );
                }
            }
            if (in_array($type, $inlineviewablefiles)) {
                $files_array[$filecounter]['view'] = sprintf(
                    '<span style="width:20px;"><a href="index.php?a=31&mode=view&path=%s"><img src="%sicons/context_view.gif" align="absmiddle" alt="%s" title="%s" /></a></span> ',
                    urlencode($newpath),
                    $style_path,
                    lang('files_viewfile'),
                    lang('files_viewfile')
                );
            }
            if ($enablefileunzip && $type === '.zip') {
                $files_array[$filecounter]['unzip'] = sprintf(
                    '<span style="width:20px;"><a href="#" onclick="unzipFile(%s);return false;"><img src="%sicons/unzip.gif" align="absmiddle" alt="%s" title="%s" /></a></span> ',
                    hsc(js_literal(rawurlencode($file))),
                    $style_path,
                    lang('file_download_unzip'),
                    lang('file_download_unzip')
                );
            } else {
                $files_array[$filecounter]['unzip'] = '';
            }
            if (in_array($type, $editablefiles) && is_writable($curpath) && is_writable($newpath)) {
                $files_array[$filecounter]['edit'] = sprintf(
                    '<span style="width:20px;"><a href="index.php?a=31&mode=save&path=%s#file_editfile"><img src="%s" align="absmiddle" alt="%s" title="%s" /></a></span> ',
                    urlencode($newpath),
                    $_style['icons_edit_document'],
                    lang('files_editfile'),
                    lang('files_editfile')
                );
            } else {
                $files_array[$filecounter]['edit'] = sprintf(
                    '<span class="disabledImage"><img src="%s" align="absmiddle" alt="%s" title="%s" /></span> ',
                    $_style['icons_edit_document'],
                    lang('files_editfile'),
                    lang('files_editfile')
                );
            }
            if (is_writable($curpath) && is_writable($newpath)) {
                $files_array[$filecounter]['delete'] = sprintf(
                    '<span style="width:20px;"><a href="#" onclick="deleteFile(%s);return false;"><img src="%sicons/delete.gif" align="absmiddle" alt="%s" title="%s" /></a></span> ',
                    hsc(js_literal(rawurlencode($file))),
                    $style_path,
                    lang('file_delete_file'),
                    lang('file_delete_file')
                );
            } else {
                $files_array[$filecounter]['delete'] = sprintf(
                    '<span class="disabledImage"><img src="%sicons/delete.gif" align="absmiddle" alt="%s" title="%s" /></span> ',
                    $style_path,
                    lang('file_delete_file'),
                    lang('file_delete_file')
                );
            }

            // increment the counter
            $filecounter++;
        }
    }

    // dump array entries for directories
    $folders = count($dirs_array);
    sort($dirs_array); // sorting the array alphabetically (Thanks pxl8r!)
    foreach ($dirs_array as $i => $iValue) {
        $filesizes += $dirs_array[$i]['stats']['7'];
        echo '<tr style="cursor:default;" onmouseout="setColor(this,0)" onmouseover="setColor(this,1)">';
        echo '<td style="padding-right:10px;">', $iValue['text'], '</td>';
        echo '<td>', $modx->toDateFormat($iValue['stats']['9']), '</td>';
        echo '<td dir="ltr">', $modx->nicesize($iValue['stats']['7']), '</td>';
        echo '<td>';
        echo $iValue['delete'];
        echo '</td>';
        echo '</tr>';
    }

    // dump array entries for files
    $files = count($files_array);
    sort($files_array); // sorting the array alphabetically (Thanks pxl8r!)
    foreach ($files_array as $i => $iValue) {
        $filesizes += $files_array[$i]['stats']['7'];
        echo '<tr onmouseout="setColor(this,0)" onmouseover="setColor(this,1)">';
        echo '<td style="padding-right:10px;">', $iValue['text'], '</td>';
        echo '<td>', $modx->toDateFormat($iValue['stats']['9']), '</td>';
        echo '<td dir="ltr">', $modx->nicesize($iValue['stats']['7']), '</td>';
        echo '<td>';
        echo $iValue['unzip'];
        echo $iValue['view'];
        echo $iValue['edit'];
        echo $iValue['delete'];
        echo '</td>';
        echo '</tr>';
    }
}

function removeLastPath($string)
{
    $pos = strrpos($string, '/');
    if ($pos !== false) {
        $path = substr($string, 0, $pos);
    } else {
        $path = false;
    }
    return $path;
}

function getExtension($string)
{
    $pos = strrpos($string, '.');
    if ($pos !== false) {
        $ext = substr($string, $pos);
        $ext = strtolower($ext);
    } else {
        $ext = false;
    }
    return $ext;
}

/**
 * 名前の拡張子が、アップロードを許している拡張子（uploadablefiles()）に含まれるかを返す。
 * アップロード・新しいファイルの作成・zip の展開で同じ基準を使う。
 * 名前は sanitizeFilename() を通した後のものを渡す。大文字小文字は区別せず、拡張子の無い名前は許さない。
 */
function checkExtension($path = '')
{
    $uploadablefiles = add_dot(uploadablefiles());
    if (!in_array(getExtension($path), $uploadablefiles, true)) {
        return false;
    }

    return true;
}

function mkdirs($strPath, $mode)
{ // recursive mkdir function
    if (is_dir($strPath)) {
        return true;
    }
    $pStrPath = dirname($strPath);
    if (!mkdirs($pStrPath, $mode)) {
        return false;
    }
    return @mkdir($strPath);
}

function logFileChange($type, $filename)
{
    // UTF-8 でない名前（FTP で置いたものなど）は、そのままでは管理ログ（utf8mb4）に書けないため置き換える
    $filename = mb_scrub((string) $filename, 'UTF-8');
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

    $string = sprintf($string, $filename);
    $log->initAndWriteLog($string, '', '', '', $type, $filename);

    global $action;
    $action = 1;
}

/**
 * zip を展開する。by patrick_allaert - php user notes
 * ファイルのエントリは、アップロードと同じ拡張子の許可リスト（checkExtension()）で確かめ、
 * 許されていないものは展開せずに $skipped へ加える（名前の重なりの確かめより先に飛ばすので、名前を取らない）。
 * フォルダーのエントリは確かめない。
 *
 * @param array $failed 展開できなかったエントリ（展開先からの相対パス）を加える
 * @param array $skipped 拡張子が許されていないため展開しなかったエントリ（展開先からの相対パス）を加える
 * @return bool|int 展開できたら true、zip を開けない・展開先が保護対象なら false、zip 拡張が無ければ 0
 */
function unzip($file, $path, &$failed = [], &$skipped = [])
{
    // added by Raymond
    if (!extension_loaded('zip')) {
        return 0;
    }
    // end mod
    $zip = new ZipArchive();
    if ($zip->open($file) !== true) {
        return false;
    }

    $path = rtrim($path, '/') . '/';
    // 展開先の外や保護対象へ書き込むエントリがあれば、何も展開しない
    $proteted_path = proteted_path();
    for ($i = 0; $i < $zip->numFiles; $i++) {
        $zip_entry_name = str_replace('\\', '/', unzip_entry_name($zip, $i));
        if (preg_match('@(^|/)\.\.(/|$)@', $zip_entry_name)
            || is_protected_path($path . unzip_entry_path($zip_entry_name), $proteted_path)) {
            $zip->close();
            return false;
        }
    }

    $old_umask = umask(0);
    $assigned = ['dirs' => [], 'paths' => []];
    for ($i = 0; $i < $zip->numFiles; $i++) {
        $zip_entry_name = unzip_entry_name($zip, $i);
        $relative_name = unzip_entry_path($zip_entry_name);
        if ($relative_name === '') {
            continue;
        }
        // フォルダーのエントリ（名前が / で終わる）は、フォルダーを作るだけにする
        $is_dir_entry = substr(str_replace('\\', '/', $zip_entry_name), -1) === '/';
        // 拡張子は、無害化した後の名前の最後の要素で確かめる。飛ばすエントリは、次の名前の割り当てに加えない
        $slash_pos = strrpos($relative_name, '/');
        $entry_filename = $slash_pos === false ? $relative_name : substr($relative_name, $slash_pos + 1);
        if (!$is_dir_entry && !checkExtension($entry_filename)) {
            $skipped[] = $relative_name;
            continue;
        }
        // 無害化で、この zip で先に展開したファイル・フォルダーと名前が重なったときは上書きせず、(1) などを付けた名前にする
        $complete_name = $path . unzip_unique_path($path, $relative_name, $is_dir_entry, $assigned);
        $complete_path = $is_dir_entry ? $complete_name : dirname($complete_name);
        // 書けないエントリは警告で画面を止めず、残りを展開してから一覧で知らせる（ログにも残す）
        if (!is_dir($complete_path) && !@mkdir($complete_path, 0777, true)) {
            $failed[] = $relative_name;
            continue;
        }
        if ($is_dir_entry) {
            continue;
        }
        if (!unzip_copy_entry($zip, $i, $complete_name)) {
            $failed[] = $relative_name;
        }
    }
    if ($failed) {
        evo()->logEvent(0, 2, 'Some zip entries could not be extracted', 'unzip', [
            'zip' => $file,
            'entries' => $failed,
        ]);
    }
    umask($old_umask);
    $zip->close();
    return true;
}

/**
 * zip のエントリ $index の中身を $target へ書き出す。
 * zip://<アーカイブのパス>#<エントリ名> の形は、アーカイブのパスにある # を区切りと読むため使わず、
 * 開いている ZipArchive から読む。大きなエントリでもメモリに載せないよう、ストリームで写す。
 * getStream() には getNameIndex() の既定の名前を渡す（zip の中で名前を探すときと同じ形。Shift_JIS の名前も開ける）。
 */
function unzip_copy_entry($zip, $index, $target)
{
    $stat = $zip->statIndex($index);
    $in = $stat ? $zip->getStream($zip->getNameIndex($index)) : false;
    if (!$in) {
        return false;
    }
    $out = @fopen($target, 'wb');
    if (!$out) {
        fclose($in);
        return false;
    }
    $copied = stream_copy_to_stream($in, $out);
    fclose($in);
    $closed = fclose($out);
    return $closed && $copied === (int) $stat['size'];
}

/**
 * zip のエントリ名を UTF-8 で返す。
 * Windows で作った zip は名前を Shift_JIS（CP932）で持つことが多い。getNameIndex() の既定では
 * CP437 とみなして変換した別の文字列（\ を含むこともある）になるため、元のバイト列から変換する。
 */
function unzip_entry_name($zip, $index)
{
    $raw = $zip->getNameIndex($index, ZipArchive::FL_ENC_RAW);
    if (mb_check_encoding($raw, 'UTF-8') || !mb_check_encoding($raw, 'SJIS-win')) {
        return $raw;
    }
    return mb_convert_encoding($raw, 'UTF-8', 'SJIS-win');
}

/**
 * zip のエントリ名を、展開先からの相対パスにする。
 * 各要素をアップロードと同じ規則（sanitizeFilename()）で無害化する。
 * UTF-8 にも Shift_JIS にもならない名前は、英数字に置き換わる。
 */
function unzip_entry_path($zip_entry_name)
{
    $segments = [];
    foreach (explode('/', str_replace('\\', '/', $zip_entry_name)) as $segment) {
        if ($segment === '' || $segment === '.') {
            continue;
        }
        $segments[] = evo()->sanitizeFilename($segment);
    }
    return implode('/', $segments);
}

/**
 * 展開するエントリの相対パスを、同じ zip で先に展開したファイル・フォルダーと重ならないパスにして返す。
 * パスの各要素を確かめ、重なったときは、新しいファイルブラウザのアップロードと同じく名前に (1)・(2)… を付ける。
 * - ファイルは、先に展開したファイル・フォルダーと同じ名前にしない
 * - フォルダーは、同じ名前のフォルダーとは一つにまとめ、ファイルとは同じ名前にしない
 * - 付け直したフォルダーは、同じ zip の配下のエントリすべてに同じ付け直しを当てる
 * 展開先に前からあるファイルは、これまでどおり上書きする（同じ zip を展開し直して更新する使い方のため）。
 *
 * @param string $path 展開先（末尾に /）
 * @param string $relative_name unzip_entry_path() の結果
 * @param bool $is_dir フォルダーのエントリか
 * @param array $assigned これまでの割り当て。dirs: 無害化した後のフォルダーのパス => 付け直した後のパス、
 *                        paths: 割り当てたパス => 'file' | 'dir'。この関数が書き足す
 */
function unzip_unique_path($path, $relative_name, $is_dir, array &$assigned)
{
    $segments = explode('/', $relative_name);
    $last = count($segments) - 1;
    $key = '';
    $parent = '';
    foreach ($segments as $i => $segment) {
        $key .= ($key === '' ? '' : '/') . $segment;
        if ($i < $last || $is_dir) {
            if (!isset($assigned['dirs'][$key])) {
                $dir = unzip_free_name($path, $parent, $segment, 'dir', $assigned['paths']);
                $assigned['dirs'][$key] = $dir;
                $assigned['paths'][$dir] = 'dir';
            }
            $parent = $assigned['dirs'][$key];
            continue;
        }
        $file = unzip_free_name($path, $parent, $segment, 'file', $assigned['paths']);
        $assigned['paths'][$file] = 'file';
        return $file;
    }
    return $parent;
}

/**
 * unzip_unique_path() の下請け。$parent の下で $name が使えるか確かめ、使えなければ (1)・(2)… を付けた名前を返す。
 */
function unzip_free_name($path, $parent, $name, $kind, array $paths)
{
    $prefix = $parent === '' ? '' : $parent . '/';
    $dot_pos = $kind === 'file' ? strrpos($name, '.') : false;
    $base = $dot_pos === false ? $name : substr($name, 0, $dot_pos);
    $ext = $dot_pos === false ? '' : substr($name, $dot_pos);
    for ($i = 0; ; $i++) {
        $candidate = $prefix . ($i === 0 ? $name : evo()->sanitizeFilename($base . '(' . $i . ')' . $ext));
        if ($kind === 'dir') {
            $taken = (isset($paths[$candidate]) && $paths[$candidate] !== 'dir') || is_file($path . $candidate);
        } else {
            $taken = isset($paths[$candidate]) || is_dir($path . $candidate);
        }
        if (!$taken) {
            return $candidate;
        }
    }
}

function rrmdir($dir)
{
    foreach (glob($dir . '/*') as $file) {
        // リンク先はたどらず、リンク自体だけを消す
        if (is_dir($file) && !is_link($file)) {
            rrmdir($file);
        } else {
            unlink($file);
        }
    }
    return rmdir($dir);
}

function fileupload()
{
    global $modx, $startpath;
    $msg = '';

    if (filev('userfile.tmp_name')) {
        $userfile['tmp_name'] = filev('userfile.tmp_name');
        $userfile['error'] = filev('userfile.error');
        $userfile['name'] = filev('userfile.name');
        $userfile['type'] = filev('userfile.type');
    }

    // 保存先は $modx->move_uploaded_file() と同じ無害化をかけて決める。
    // 表示・拡張子の確かめ・イベント・ログには、元の名前ではなく実際に保存する名前を使う
    $targetPath = $modx->sanitizeUploadedFilename(postv('path') . '/' . $userfile['name']);
    $savedName = substr($targetPath, strrpos($targetPath, '/') + 1);

    // this seems to be an upload action.
    $relPath = ltrim(rtrim(substr($startpath, strlen(config('filemanager_path'))), '/') . '/' . $savedName, '/');
    $path = MODX_SITE_URL . $relPath;
    // 画像の src には、名前の # や % などで切れないよう要素ごとにエンコードした URL を使う
    $url = MODX_SITE_URL . implode('/', array_map('rawurlencode', explode('/', $relPath)));
    $msg .= hsc($path);
    if ($userfile['error'] == 0) {
        $img = (strpos($userfile['type'], 'image') !== false) ? '<br /><img src="' . hsc($url) . '" height="75" />' : '';
        $msg .= sprintf(
            '<p>%s%s, %s%s</p>',
            lang('files_file_type'),
            hsc($userfile['type']),
            $modx->nicesize(filesize($userfile['tmp_name'])),
            $img
        );
    }

    $userfilename = $userfile['tmp_name'];

    if (!is_uploaded_file($userfilename)) {
        $msg .= '<br /><span class="warning"><b>' . lang('files_upload_error') . ':</b>';
        switch ($userfile['error']) {
            case 0: //no error; possible file attack!
                $msg .= lang('files_upload_error0');
                break;
            case 1: //uploaded file exceeds the upload_max_filesize directive in php.ini
                $msg .= lang('files_upload_error1');
                break;
            case 2: //uploaded file exceeds the MAX_FILE_SIZE directive that was specified in the html form
                $msg .= lang('files_upload_error2');
                break;
            case 3: //uploaded file was only partially uploaded
                $msg .= lang('files_upload_error3');
                break;
            case 4: //no file was uploaded
                $msg .= lang('files_upload_error4');
                break;
            default: //a default error, just in case!  :)
                $msg .= lang('files_upload_error5');
                break;
        }
        return $msg . '</span><br />';
    }
    // file is uploaded file, process it!
    if (!checkExtension($savedName)) {
        return $msg . '<p><span class="warning">' . lang('files_filetype_notok') . '</span></p>';
    }

    $rs = $modx->move_uploaded_file($userfile['tmp_name'], $targetPath);
    if (!$rs) {
        return $msg . '<p><span class="warning">' . lang('files_upload_copyfailed') . '</span> ' . lang('files_upload_permissions_error') . '</p>';
    }
    // invoke OnFileManagerUpload event
    $tmp = [
        'filepath' => postv('path'),
        'filename' => $savedName
    ];
    evo()->invokeEvent('OnFileManagerUpload', $tmp);
    // Log the change
    logFileChange('upload', $targetPath);
    return $msg . '<p><span class="success">' . lang('files_upload_ok') . '</span></p>';
}

function textsave()
{
    // 保存は既にあるファイルの編集だけにする（新しいファイルは newfile で、拡張子を確かめて作る）
    if (!is_file(postv('path'))) {
        return lang('editing_file') . '<span class="warning"><b>' . lang('file_not_saved') . '</b></span><br /><br />';
    }
    logFileChange('modify', postv('path'));

    // Write $content to our opened file.
    if (file_put_contents(postv('path'), postv('content')) === false) {
        return lang('editing_file') . '<span class="warning"><b>' . lang('file_not_saved') . '</b></span><br /><br />';
    }

    $_REQUEST['mode'] = 'save';
    return lang('editing_file') . '<span class="success"><b>' . lang('file_saved') . '</b></span><br /><br />';
}

function delete_file()
{
    $msg = sprintf(lang('deleting_file'), hsc(str_replace('\\', '/', anyv('path'))));

    // 無いもの・フォルダーは unlink() に渡さない（PHP の警告になる）。リンクはリンク自体を消す
    if (!is_file(anyv('path')) && !is_link(anyv('path'))) {
        return $msg . '<span class="warning"><b>' . lang('file_not_found') . '</b></span><br /><br />';
    }

    logFileChange('delete', anyv('path'));

    if (!unlink(anyv('path'))) {
        return $msg . '<span class="warning"><b>' . lang('file_not_deleted') . '</b></span><br /><br />';
    }

    return $msg . '<span class="success"><b>' . lang('file_deleted') . '</b></span><br /><br />';
}

function add_dot($array)
{
    foreach ($array as $i => $iValue) {
        $array[$i] = '.' . strtolower(trim($iValue)); // add a dot :)
    }
    return $array;
}

function webstart_path()
{
    $webstart_path = str_replace(
        [realpath('../'), '\\'],
        ['', '/'],
        realpath(config('filemanager_path'))
    );
    if (strpos($webstart_path, '/') === 0) {
        return '..' . $webstart_path;
    }
    return '../' . $webstart_path;
}

function proteted_path()
{
    if (manager()->isAdmin()) {
        return [];
    }

    $proteted_path = [
        base_path() . 'manager',
        base_path() . 'temp/backup',
        base_path() . 'assets/backup'
    ];

    if (!evo()->hasPermission('save_plugin')) {
        $proteted_path[] = base_path() . 'assets/plugins';
    }
    if (!evo()->hasPermission('save_snippet')) {
        $proteted_path[] = base_path() . 'assets/snippets';
    }
    if (!evo()->hasPermission('save_template')) {
        $proteted_path[] = base_path() . 'assets/templates';
    }
    if (!evo()->hasPermission('save_module')) {
        $proteted_path[] = base_path() . 'assets/modules';
    }
    if (!evo()->hasPermission('empty_cache')) {
        $proteted_path[] = rtrim(MODX_CACHE_PATH, '/');
    }
    if (!evo()->hasPermission('import_static')) {
        $proteted_path[] = base_path() . 'temp/import';
        $proteted_path[] = base_path() . 'assets/import';
    }
    if (!evo()->hasPermission('export_static')) {
        $proteted_path[] = base_path() . 'temp/export';
        $proteted_path[] = base_path() . 'assets/export';
    }
    return $proteted_path;
}

/**
 * 保護対象そのもの、またはその配下なら true。
 * どちらもまだ存在しなくてよい（アップロード先、作成するフォルダ、未作成のバックアップフォルダなど）。
 * 大文字小文字を区別しないファイルシステムもあるため、区別せずに照合する。
 */
function is_protected_path($path, $proteted_path)
{
    if (!$proteted_path) {
        return false;
    }
    $realpath = normalize_path($path);
    if ($realpath === false) {
        return false;
    }
    $realpath = strtolower($realpath);
    foreach ($proteted_path as $protected) {
        $protected = normalize_path($protected);
        if ($protected === false) {
            continue;
        }
        $protected = strtolower($protected);
        if ($realpath === $protected || strpos($realpath, $protected . '/') === 0) {
            return true;
        }
    }
    return false;
}

/**
 * 存在する部分は realpath() で解決し、存在しない残りは文字列のまま . と .. を畳んでつなぐ。
 */
function normalize_path($path)
{
    $path = str_replace('\\', '/', $path);
    $rest = [];
    $realpath = realpath($path);
    while ($realpath === false) {
        $parent = dirname($path);
        if ($parent === $path) {
            return false;
        }
        array_unshift($rest, basename($path));
        $path = $parent;
        $realpath = realpath($path);
    }
    $segments = explode('/', rtrim(str_replace('\\', '/', $realpath), '/'));
    foreach ($rest as $segment) {
        if ($segment === '' || $segment === '.') {
            continue;
        }
        if ($segment === '..') {
            if (count($segments) > 1) {
                array_pop($segments);
            }
            continue;
        }
        $segments[] = $segment;
    }
    return implode('/', $segments);
}

function uploadablefiles()
{
    return array_merge(
        explode(',', config('upload_files', [])),
        explode(',', config('upload_images', [])),
        explode(',', config('upload_media', []))
    );
}
