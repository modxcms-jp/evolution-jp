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
