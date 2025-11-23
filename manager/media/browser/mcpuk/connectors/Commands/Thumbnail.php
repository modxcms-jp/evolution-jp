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
 * File Name: Thumbnail.php
 * Implements the Thumbnail command, to return
 * a thumbnail to the browser for the sent file,
 * if the file is an image an attempt is made to
 * generate a thumbnail, otherwise an appropriate
 * icon is returned.
 * Output is image data
 *
 * File Authors:
 * Grant French (grant@mcpuk.net)
 */

if (!defined('MODX_BASE_PATH') || strpos(str_replace('\\', '/', __FILE__), MODX_BASE_PATH) !== 0) {
    exit;
}
include_once(MODX_BASE_PATH . 'manager/media/browser/mcpuk/connectors/Commands/helpers/iconlookup.php');

require_once 'Base.php';
class Thumbnail extends Base
{
    public $filename;

    function __construct($fckphp_config, $type, $cwd)
    {
        parent::__construct($fckphp_config, $type, $cwd);
        $this->real_cwd = rtrim($this->real_cwd, '/');
        $this->filename = $this->sanitizeFileName(unescape(getv('FileName')));
    }

    function run()
    {
        $thumbfile = $this->real_cwd . '/.thumb/' . $this->filename;
        $file_permissions = octdec(evo()->config['new_file_permissions']);
        $folder_permissions = octdec(evo()->config['new_folder_permissions']);
        $icon = false;

        // Convert paths for filesystem compatibility with multibyte characters
        $thumbfile_fs = $this->convertPathToFilesystem($thumbfile);
        $fullfile = $this->real_cwd . '/' . $this->filename;
        $fullfile_fs = $this->convertPathToFilesystem($fullfile);

        if (is_file($thumbfile_fs)) {
            $icon = $thumbfile_fs;
        } else {
            $thumbdir = dirname($thumbfile_fs);
            $mime = evo()->getMimeType($fullfile_fs);
            $ext = strtolower($this->getExtension($this->filename));

            if ($this->isImage($mime, $ext)) {
                if (!is_dir($thumbdir)) {
                    $rs = mkdir($thumbdir, $folder_permissions, true);
                    if ($rs) {
                        chmod($thumbdir, $folder_permissions);
                    }
                }
                //Try and find a thumbnail, else try to generate one
                //    else send generic picture icon.

                if ($this->isJPEG($mime, $ext)) {
                    $result = $this->resizeFromJPEG($fullfile_fs);
                } elseif ($this->isPNG($mime, $ext)) {
                    $result = $this->resizeFromPNG($fullfile_fs);
                } elseif ($this->isGIF($mime, $ext)) {
                    $result = $this->resizeFromGIF($fullfile_fs);
                } else {
                    $result = false;
                }

                if ($result !== false && function_exists('imagejpeg')) {
                    imagejpeg($result, $thumbfile_fs, 80);
                    @chmod($thumbfile_fs, $file_permissions);
                    $icon = $thumbfile_fs;
                }
            }
            if ($icon === false) {
                $icon = iconLookup($mime, $ext);
            }
        }

        $iconMime = evo()->getMimeType($icon);
        if ($iconMime == false) {
            $iconMime = 'image/jpeg';
        }
        header(sprintf('Content-type: %s', $iconMime), true);
        readfile($icon);
    }

    function isImage($mime, $ext)
    {
        if (in_array($mime, ['image/gif', 'image/jpg', 'image/jpeg', 'image/png'])) {
            return true;
        }
        if (in_array($ext, ['gif', 'jpg', 'jpeg', 'png'])) {
            return true;
        }
        return false;
    }

    function isJPEG($mime, $ext)
    {
        if (in_array($mime, ['image/jpg', 'image/jpeg'])) {
            return true;
        }
        if (in_array($ext, ['jpg', 'jpeg'])) {
            return true;
        }
        return false;
    }

    function isGIF($mime, $ext)
    {
        return ($mime === 'image/gif') || ($ext === 'gif');
    }

    function isPNG($mime, $ext)
    {
        return ($mime === 'image/png') || ($ext === 'png');
    }

    function getExtension($filename)
    {
        $lastpos = strrpos($filename, '.');
        if ($lastpos !== false) {
            return strtolower(substr($filename, ($lastpos + 1)));
        }
        return '';
    }

    function resizeFromJPEG($file)
    {
        $img = @imagecreatefromjpeg($file);
        if ($img) {
            return ($this->resizeImage($img));
        }
        return false;
    }

    function resizeFromGIF($file)
    {
        $img = @imagecreatefromgif($file);
        if ($img) {
            return ($this->resizeImage($img));
        }
        return false;
    }

    function resizeFromPNG($path)
    {
        $img = @imagecreatefrompng($path);
        if ($img) {
            imagesavealpha($img, true);
            return ($this->resizeImage($img));
        }
        return false;
    }

    function resizeImage($img)
    {
        $width = imagesx($img);
        $height = imagesy($img);
        if ($width > $height) {
            $n_height = $height * (64 / $width);
            $n_width = 64;
        } else {
            $n_width = $width * (64 / $height);
            $n_height = 64;
        }

        $x = 0;
        $y = 0;
        if ($n_width < 64) {
            $x = round((64 - $n_width) / 2);
        }
        if ($n_height < 64) {
            $y = round((64 - $n_height) / 2);
        }

        $thumb = imagecreatetruecolor(64, 64);

        #Ben Lancaster (benlanc@ster.me.uk)
        imagefill($thumb, 0, 0, imagecolorallocate($thumb, 255, 255, 255));

        if (!function_exists('imagecopyresampled')) {
            return imagecopyresized($thumb, $img, $x, $y, 0, 0, $n_width, $n_height, $width, $height);
        }

        $result = @imagecopyresampled($thumb, $img, $x, $y, 0, 0, $n_width, $n_height, $width, $height);
        if (!$result) {
            return imagecopyresized($thumb, $img, $x, $y, 0, 0, $n_width, $n_height, $width, $height);
        }
        return $result ? $thumb : false;
    }

    /**
     * Convert file path encoding for filesystem compatibility
     * Ensures multibyte characters in filenames work correctly with filesystem operations
     *
     * @param string $path File path with potentially multibyte characters
     * @return string Path with encoding suitable for filesystem operations
     */
    private function convertPathToFilesystem($path)
    {
        // Check if path contains non-ASCII (multibyte) characters
        if (mb_check_encoding($path, 'ASCII')) {
            // ASCII only, no conversion needed
            return $path;
        }

        // Path contains multibyte characters
        // Set UTF-8 locale to ensure proper filesystem encoding handling
        @setlocale(LC_CTYPE, 'en_US.UTF-8', 'ja_JP.UTF-8', 'C.UTF-8', 'UTF-8');

        // Path is already in UTF-8 (from MODX), return as-is
        return $path;
    }
}
