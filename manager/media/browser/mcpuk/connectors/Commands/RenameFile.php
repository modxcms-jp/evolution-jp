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
 * File Name: RenameFile.php
 * Implements the DeleteFile command to delete a file
 * in the current directory. Output is in XML
 *
 * File Authors:
 * Grant French (grant@mcpuk.net)
 */

require_once 'Base.php';
class RenameFile extends Base
{
    function __construct($fckphp_config, $type, $cwd)
    {
        parent::__construct($fckphp_config, $type, $cwd);
        $this->filename = $this->sanitizeFileName(getv('FileName'));
        $this->newname = $this->sanitizeFileName($this->checkName(getv('NewName')));
    }

    function checkName($name)
    {
        return unescape($name);
        $newName = "";
        for ($i = 0, $iMax = strlen($name); $i < $iMax; $i++) {
            if (in_array($name[$i], $this->fckphp_config['FileNameAllowedChars'])) {
                $newName .= $name[$i];
            }
        }
        return $newName;
    }

    function run()
    {
        $result1 = false;
        $result2 = true;

        if ($this->newname != '') {
            $result2 = true;
            $thumb = $this->real_cwd . '/.thumb/' . $this->newname;
            $thumb_fs = $this->convertPathToFilesystem($thumb);

            if (file_exists($thumb_fs)) {
                $result2 = unlink($thumb_fs);
            }

            $oldpath = $this->real_cwd . '/' . unescape($this->filename);
            $newpath = $this->real_cwd . '/' . $this->newname;
            $oldpath_fs = $this->convertPathToFilesystem($oldpath);
            $newpath_fs = $this->convertPathToFilesystem($newpath);

            $result1 = rename($oldpath_fs, $newpath_fs);
        }
        if ($result1 && $result2) {
            $err_no = 0;
        } else {
            $err_no = 502;
        }

        $response = $this->newXmlResponse('RenameFile');
        $response->setCurrentFolder($this->raw_cwd, $this->actual_cwd)
            ->addChild('Error', ['number' => (string)$err_no]);

        $this->outputXml($response);
    }

    function nameValid($fname)
    {
        $lastdot = strrpos($fname, '.');
        if ($lastdot === false) {
            return false;
        }
        $ext = substr($fname, ($lastdot + 1));
        $extensions = $this->fckphp_config['ResourceAreas'][$this->type]['AllowedExtensions'];
        if (!in_array(strtolower($ext), $extensions)) {
            return false;
        }
        return true;
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
