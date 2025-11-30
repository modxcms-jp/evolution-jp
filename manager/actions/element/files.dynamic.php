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
                    '<li class="primary"><a href="#" onclick="document.editFile.submit();"><img src="[+icons_save+]" /> [+lang_save+]</a></li>',
                    [
                        'icons_save' => $_style['icons_save'],
                        'lang_save' => lang('save')
                    ]
                ) . "\n";
            }
            $_ .= $modx->parseText(
                '<li><a href="[+href+]" onclick="return getFolderName(this);"><img src="[+tree_folder+]" alt="" /> [+subject+]</a></li>',
                [
                    'href' => 'index.php?a=31&mode=newfolder&path=' . urlencode($startpath) . '&name=',
                    'tree_folder' => $_style['tree_folder'],
                    'subject' => lang('add_folder')
                ]
            );

            $tpl = '<li><a href="[+href+]" onclick="return getFileName(this);"><img src="[+image+]" alt="" /> [+lang_newfile+]</a></li>';
            $ph['image'] = $_style['tree_page'];
            $ph['href'] = 'index.php?a=31&mode=newfile&path=' . urlencode($startpath) . '&name=';
            $ph['lang_newfile'] = lang('files.dynamic.php1');
            $_ .= $modx->parseText($tpl, $ph);
            echo $_;
        }
        ?>
        <li id="Button5" class="mutate">
            <a
                href="#"
                onclick="documentDirty=false;document.location.href='index.php?<?= $href ?>';"><img
                    alt="icons_cancel"
                    src="<?= $_style["icons_cancel"] ?>" /> <?= lang('cancel') ?></a>
        </li>
    </ul>
</div>

<div class="section">
    <div class="sectionBody">
        <script type="text/javascript">
            var current_path = '<?= $startpath; ?>';

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
                    window.location.href = "index.php?a=31&mode=unzip&path=" + current_path + '/&file=' + file;
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
                    window.location.href = "index.php?a=31&mode=deletefolder&path=" + current_path + "&folderpath=" + current_path + '/' + folder;
                    return false;
                }
            }

            function deleteFile(file) {
                if (confirmDelete()) {
                    window.location.href = "index.php?a=31&mode=delete&path=" + current_path + '/' + file;
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

        if (in_array($startpath, $proteted_path)) {
            echo lang('files.dynamic.php2');
            exit;
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
                if (1 < $count) {
                    $pieces[$i] = sprintf(
                        '<a href="%s">%s</a>',
                        'index.php?a=31&mode=drill&path=' . urlencode(config('filemanager_path') . $path),
                        trim($v, '/')
                    );
                } else {
                    $pieces[$i] = trim($v, '/');
                }
                $count--;
            }
            $topic_path = implode(' / ', $pieces);
        }

        ?> <b><?= mb_convert_encoding(
                        $topic_path,
                        $modx_manager_charset,
                        'SJIS-win,SJIS,EUCJP-win,EUC-JP,UTF-8'
                    ) ?></b>
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
            $err = unzip(realpath($startpath . '/' . anyv('file')), realpath($startpath));
            if (!$err) {
                echo sprintf(
                    '<span class="warning"><b>%s%s</b></span><br /><br />',
                    lang('file_unzip_fail'),
                    $err === 0 ? 'Missing zip library (php_zip.dll / zip.so)' : ''
                );
            } else {
                echo '<span class="success"><b>' . lang('file_unzip') . '</b></span><br /><br />';
            }
        }
        // End Unzip - Raymond


        // New Folder & Delete Folder option - Raymond
        if (is_writable($startpath)) {
            // Delete Folder
            if (anyv('mode') === 'deletefolder') {
                $folder = anyv('folderpath');
                if (!@rrmdir($folder)) {
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
                $foldername = str_replace(['../', '..\\'], '', anyv('name'));
                if (!mkdirs($startpath . "/" . $foldername, 0777)) {
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
                $filename = str_replace(['../', '..\\'], '', anyv('name'));
                $filename = db()->escape($filename);

                if (!checkExtension($filename)) {
                    echo sprintf(
                        '<span class="warning"><b>%s</b></span><br /><br />',
                        lang('files_filetype_notok')
                    );
                } elseif (preg_match('@([/:;,*?"<>|])@', $filename) !== 0) {
                    echo lang('files.dynamic.php3');
                } else {
                    $rs = file_put_contents($startpath . '/' . $filename, '');
                    if ($rs === false) {
                        echo '<span class="warning"><b>' . lang('file_folder_not_created') . '</b></span><br /><br />';
                    } else {
                        echo lang('files.dynamic.php4');
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
        <input type="hidden" name="path" value="<?= $startpath ?>">

        <?php if (isset($information)) {
            echo $information;
        } ?>

        <div id="uploader" class="actionButtons" style="margin-top:10px;">
            <input type="file" name="userfile" onchange="document.upload.submit();">
            <a
                class="default" href="#" onclick="document.upload.submit()"
                style="display:inline;float:none;"><?=
                                                    '<img src="' . $_style['icons_add'] . '" /> '
                                                        . lang('files_uploadfile')
                                                    ?></a>
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
            $buffer = file_get_contents($filename);

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
                    <input type="hidden" name="path" value="<?= anyv('path') ?>" />
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
    global $style_path, $_style, $modx_manager_charset;
    global $enablefileunzip, $enablefiledownload, $folders, $files, $filesizes, $len, $dirs_array, $files_array, $modx;
    $dircounter = 0;
    $filecounter = 0;
    $curpath = str_replace('//', '/', $curpath . '/');

    if (!is_dir($curpath)) {
        echo 'Invalid path "', $curpath, '"<br />';
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

            if (in_array($newpath, $proteted_path)) {
                $dirs_array[$dircounter]['text'] = sprintf(
                    '<img src="%s" align="absmiddle" alt="" /> <span class="file-disabled">%s</span>',
                    $_style['tree_deletedfolder'],
                    $file
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
                $file = mb_convert_encoding($file, $modx_manager_charset, 'SJIS-win,SJIS,EUCJP-win,EUC-JP,UTF-8');
                $dirs_array[$dircounter]['text'] = sprintf(
                    '<img src="%s" align="absmiddle" alt="" /> <a href="index.php?a=31&mode=drill&path=%s"><b>%s</b></a>',
                    $_style['tree_folder'],
                    urlencode($newpath),
                    $file
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
                        '<span style="width:20px"><a href="javascript: deleteFolder(\'%s\',\'%s\');"><img src="%sicons/delete.gif" alt="%s" title="%s" /></a></span>',
                        urlencode($file),
                        (0 < count($dfiles)) ? 'file_exists' : '',
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
            $files_array[$filecounter]['text'] = '<img src="' . $_style['tree_page'] . '" align="absmiddle" alt="" />' . $file;
            if (in_array($type, $viewablefiles)) {
                $files_array[$filecounter]['view'] = sprintf(
                    '<span style="cursor:pointer; width:20px;" onclick="show_image(\'%s%s\');"><img src="%sicons/context_view.gif" align="absmiddle" alt="%s" title="%s" /></span> ',
                    webstart_path(),
                    substr($newpath, $len, strlen($newpath)),
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
                    '<span style="width:20px;"><a href="javascript:unzipFile(\'%s\');"><img src="%sicons/unzip.gif" align="absmiddle" alt="%s" title="%s" /></a></span> ',
                    urlencode($file),
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
                    '<span style="width:20px;"><a href="javascript:deleteFile(\'%s\');"><img src="%sicons/delete.gif" align="absmiddle" alt="%s" title="%s" /></a></span> ',
                    urlencode($file),
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

// by patrick_allaert - php user notes
function unzip($file, $path)
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

    $old_umask = umask(0);
    $path = rtrim($path, '/') . '/';
    for ($i = 0; $i < $zip->numFiles; $i++) {
        $zip_entry_name = $zip->getNameIndex($i);
        $complete_path = $path . str_replace('\\', '/', dirname($zip_entry_name));
        $complete_name = $path . str_replace('\\', '/', $zip_entry_name);
        if (!is_dir($complete_path)) {
            $tmp = '';
            foreach (explode('/', $complete_path) as $k) {
                $tmp .= $k . '/';
                if (!is_dir($tmp)) {
                    mkdir($tmp, 0777);
                }
            }
        }
        copy("zip://" . $file . "#" . $zip_entry_name, $complete_name);
    }
    umask($old_umask);
    $zip->close();
    return true;
}

function rrmdir($dir)
{
    foreach (glob($dir . '/*') as $file) {
        if (is_dir($file)) {
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
        $name = filev('userfile.name');
        if (evo()->config('clean_uploaded_filename') == 1) {
            $nameparts = explode('.', $name);
            $nameparts = array_map([$modx, 'stripAlias'], $nameparts, ['file_manager']);
            $name = implode('.', $nameparts);
        }
        $userfile['name'] = $name;
        $userfile['type'] = filev('userfile.type');
    }

    // this seems to be an upload action.
    $path = MODX_SITE_URL . substr($startpath, strlen(config('filemanager_path')));
    $path = rtrim($path, '/') . '/' . $userfile['name'];
    $msg .= $path;
    if ($userfile['error'] == 0) {
        $img = (strpos($userfile['type'], 'image') !== false) ? '<br /><img src="' . $path . '" height="75" />' : '';
        $msg .= sprintf(
            '<p>%s%s, %s%s</p>',
            lang('files_file_type'),
            $userfile['type'],
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
    if (!checkExtension($userfile['name'])) {
        return $msg . '<p><span class="warning">' . lang('files_filetype_notok') . '</span></p>';
    }

    $rs = $modx->move_uploaded_file(
        $userfile['tmp_name'],
        postv('path') . '/' . $userfile['name']
    );
    if (!$rs) {
        return $msg . '<p><span class="warning">' . lang('files_upload_copyfailed') . '</span> ' . lang('files_upload_permissions_error') . '</p>';
    }
    // invoke OnFileManagerUpload event
    $tmp = [
        'filepath' => postv('path'),
        'filename' => $userfile['name']
    ];
    evo()->invokeEvent('OnFileManagerUpload', $tmp);
    // Log the change
    logFileChange('upload', postv('path') . '/' . $userfile['name']);
    return $msg . '<p><span class="success">' . lang('files_upload_ok') . '</span></p>';
}

function textsave()
{
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

    logFileChange('delete', anyv('path'));

    $msg = sprintf(lang('deleting_file'), str_replace('\\', '/', anyv('path')));

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

    $proteted_path[] = [
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

function uploadablefiles()
{
    return array_merge(
        explode(',', config('upload_files', [])),
        explode(',', config('upload_images', [])),
        explode(',', config('upload_media', []))
    );
}
