<?php

/*
 * FCKeditor - The text editor for internet
 * Copyright (C) 2003-2005 Frederico Caldeira Knabben
 *
 * Licensed under the terms of the GNU Lesser General Public License:
 * http://www.opensource.org/licenses/lgpl-license.php
 *
 * For further information visit:
 * http://www.fckeditor.net/
 *
 * File Name: FileUpload.php
 * Implements the FileUpload command,
 * Checks the file uploaded is allowed,
 * then moves it to the user data area.
 *
 * File Authors:
 * Grant French (grant@mcpuk.net)
 *
 * Modified:
 * 2009-03-23 by Kazuyuki Ikeda (http://www.hikidas.com/)
 * (*1) fix the bug `MaxSize` unit mismatch (Kbytes => Bytes)
 * (*2) replace `basename` other codes, because it has bugs for multibyte characters
 * ++  japanese localization
 * 2009-03-24 by Kazuyuki Ikeda (http://www.hikidas.com/)
 * (*3) add invoking event `OnFileManagerUpload`
 * 2025-11-24 Evolution CMS JP Edition
 * (*4) remove cleanFilename() method, use system-wide stripAlias() instead
 */

require_once 'Base.php';

class FileUpload extends Base
{
    public $fckphp_config;
    public $type;

    function __construct($fckphp_config, $type, $cwd)
    {
        global $modx;
        if (!defined('IN_MANAGER_MODE')) {
            define('IN_MANAGER_MODE', 'true');
            if (!defined('MODX_API_MODE')) define('MODX_API_MODE', true);
            $self = 'manager/media/browser/mcpuk/connectors/Commands/FileUpload.php';
            $base_path = str_replace($self, '', str_replace('\\', '/', __FILE__));
            include_once($base_path . 'manager/includes/document.parser.class.inc.php');
            $modx = new DocumentParser;
            $modx->getSettings();
        }

        if (!isset($_SESSION['mgrValidated'])) exit;

        parent::__construct($fckphp_config, $type, $cwd);
        $this->real_cwd = rtrim($this->real_cwd, '/');
    }

    function run()
    {
        global $modx;

        $typeconfig = $this->fckphp_config['ResourceAreas'][$this->type];

        if (count($_FILES) < 1) exit(0);

        if ($_FILES['NewFile']['name']) {
            $_FILES['NewFile']['name'] = str_replace("\\", '/', $_FILES['NewFile']['name']);
            $filename = explode('/', $_FILES['NewFile']['name']);
            $filename = end($filename);  // (*2)
            if (strpos($filename, '.') !== false) {
                $ext = strtolower(substr($filename, strrpos($filename, '.') + 1));
            }
        }

        if (!array_key_exists('NewFile', $_FILES)) $disp = "202,'Unable to find uploaded file.'"; //No file uploaded with field name NewFile
        elseif ($_FILES['NewFile']['error'] || ($typeconfig['MaxSize']) < $_FILES['NewFile']['size']) {
            $disp = "202,'ファイル容量オーバーです。'";//Too big
        } elseif (!isset($ext)) {
            $disp = "202,'種類を判別できないファイル名です。'";//No file extension to check
        } elseif (!in_array($ext, $typeconfig['AllowedExtensions'])) {
            $disp = "202,'" . $ext . "はアップロードできない種類のファイルです。'";//Disallowed file extension
        } else {
            $basename = substr($filename, 0, strrpos($filename, '.'));
            $dirSizes = [];
            $globalSize = 0;
            $failSizeCheck = false;
            if ($this->fckphp_config['DiskQuota']['Global'] != -1) {
                foreach ($this->fckphp_config['ResourceTypes'] as $resType) {
                    $dirSizes[$resType] = $this->getDirSize($modx->config['rb_base_dir'] . "$resType");
                    if ($dirSizes[$resType] === false) {
                        //Failed to stat a directory, fall out
                        $failSizeCheck = true;
                        $msg = "\\nディスク使用量を測定できません。";
                        break;
                    }
                    $globalSize += $dirSizes[$resType];
                }

                $globalSize += $_FILES['NewFile']['size'];

                if (!$failSizeCheck && $globalSize > ($this->fckphp_config['DiskQuota']['Global'] * 1048576)) {
                    $failSizeCheck = true;
                    $msg = "\\nリソース全体の割当ディスク容量オーバー";
                }
            }

            if (($typeconfig['DiskQuota'] != -1) && (!$failSizeCheck)) {
                if ($this->fckphp_config['DiskQuota']['Global'] == -1) {
                    $dirSizes[$this->type] = $this->getDirSize($modx->config['rb_base_dir'] . $this->type);
                }

                if (($dirSizes[$this->type] + $_FILES['NewFile']['size']) > ($typeconfig['DiskQuota'] * 1048576)) {
                    $failSizeCheck = true;
                    $msg = "\\nリソース種類別の割当ディスク容量オーバー";
                }
            }

            if ((($this->fckphp_config['DiskQuota']['Global'] != -1) || ($typeconfig['DiskQuota'] != -1)) && $failSizeCheck) {
                //Disk Quota over
                $disp = "202,'割当ディスク容量オーバー, " . $msg . "'";
            } else {
                $tmp_name = $_FILES['NewFile']['tmp_name'];
                $filename = "{$basename}.{$ext}";
                $target = "{$this->real_cwd}/{$filename}";
                $originalFilename = $filename;
                $target = $modx->sanitizeUploadedFilename($target);
                $filename = basename($target);
                $basename = substr($filename, 0, strrpos($filename, '.'));
                if ($filename !== $originalFilename) {
                    $disp = "201,'ファイル名に使えない文字が含まれているため変更しました。'";
                }
                if (!is_file($target)) {
                    //Upload file
                    $rs = $this->file_upload($tmp_name, $target);
                    if ($rs) $disp = '0';
                    else    $disp = "202,'Failed to upload file, internal error.'";
                } else {
                    $taskDone = false;

                    for ($i = 1; ($i < 200 && $taskDone === false); $i++) {
                        $filename = "{$basename}({$i}).{$ext}";
                        $target = "{$this->real_cwd}/{$filename}";

                        if (is_file($target)) continue;

                        $rs = $this->file_upload($tmp_name, $target);
                        if ($rs) $disp = "201,'{$filename}'";
                        else    $disp = "202,'Failed to upload file, internal error.'";

                        $taskDone = true;
                    }
                    if ($taskDone == false) $disp = "202,'Failed to upload file, internal error..'";
                }

                // (*4)
                if (substr($disp, 0, 3) !== '202') {
                    $tmp = [
                        'filepath' => $this->real_cwd,
                        'filename' => $filename
                    ];
                    evo()->invokeEvent('OnFileBrowserUpload', $tmp);
                }
            }
        }

        if (!empty($disp) && $disp !== '0' && substr($disp, 0, 3) !== '201') {
            $modx->logEvent(0, 2, $disp, 'mcpuk connector');
        }
        header("content-type: text/html; charset={$modx->config['modx_charset']}");
        ?>
        <html>
        <head>
            <title>Upload Complete</title>
        </head>
        <body>
        <script type="text/javascript">
            window.parent.frames['frmUpload'].OnUploadCompleted(<?= $disp ?>);
        </script>
        </body>
        </html>
        <?php
    }

    function getDirSize($dir)
    {
        $dirSize = 0;
        $files = scandir($dir);
        if ($files) {
            foreach ($files as $file) {
                if (($file != '.') && ($file != '..')) {
                    if (is_dir("{$dir}/{$file}")) {
                        $tmp_dirSize = $this->getDirSize("{$dir}/{$file}");
                        if ($tmp_dirSize !== false) $dirSize += $tmp_dirSize;
                    } else $dirSize += filesize("{$dir}/{$file}");
                }
            }
        } else $dirSize = false;

        return $dirSize;
    }

    function file_upload($tmp_name, $target)
    {
        global $modx;

        if (is_uploaded_file($tmp_name)):
            if ($modx->move_uploaded_file($tmp_name, $target))
                return true;
            else
                return false;
        else:
            if (rename($tmp_name, ($target)))
                return true;
            else
                return false;
        endif;
    }
}
