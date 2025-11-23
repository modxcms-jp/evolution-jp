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
 * File Name: DeleteFile.php
 * Implements the DeleteFile command to delete a file
 * in the current directory. Output is in XML.
 *
 * File Authors:
 * Grant French (grant@mcpuk.net)
 */

require_once 'Base.php';

class DeleteFile extends Base
{
    public function __construct($fckphp_config, $type, $cwd)
    {
        parent::__construct($fckphp_config, $type, $cwd);
        $this->filename = $this->sanitizeFileName(unescape(getv('FileName')));
    }

    function run()
    {
        $result2 = true;

        $file = $this->real_cwd . '/' . $this->filename;
        $thumb = $this->real_cwd . '/.thumb/' . $this->filename;

        // Convert paths for filesystem compatibility with multibyte characters
        $file_fs = $this->convertPathToFilesystem($file);
        $thumb_fs = $this->convertPathToFilesystem($thumb);

        $result1 = unlink($file_fs);
        if (is_file($thumb_fs)) {
            $result2 = unlink($thumb_fs);
        }
        if ($result1 && $result2) {
            $err_no = 0;
        } else {
            $err_no = 302;
        }

        $response = $this->newXmlResponse('DeleteFile');
        $response->setCurrentFolder($this->raw_cwd, $this->actual_cwd)
            ->addChild('Error', ['number' => (string)$err_no]);

        $this->outputXml($response);
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
