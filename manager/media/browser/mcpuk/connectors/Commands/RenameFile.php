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
        $newName = unescape(getv('NewName'));
        // Use system-wide stripAlias for consistent name sanitization
        if (evo()->config('clean_uploaded_filename') == 1) {
            $newName = evo()->stripAlias($newName, ['file_manager']);
        }
        $this->newname = $this->sanitizeFileName($newName);
    }

    function run()
    {
        $result1 = false;
        $result2 = true;

        if ($this->newname != '') {
            $result2 = true;
            $thumb = $this->real_cwd . '/.thumb/' . $this->newname;
            if (file_exists($thumb)) {
                $result2 = unlink($thumb);
            }
            $result1 = rename(
                $this->real_cwd . '/' . unescape($this->filename),
                $this->real_cwd . '/' . $this->newname
            );
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
}
