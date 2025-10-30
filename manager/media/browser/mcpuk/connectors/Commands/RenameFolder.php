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
 * File Name: RenameFolder.php
 * Implements the DeleteFile command to delete a file
 * in the current directory. Output is in XML
 *
 * File Authors:
 * Grant French (grant@mcpuk.net)
 */

require_once 'Base.php';
class RenameFolder extends Base
{
    public $newfolder;

    function __construct($fckphp_config, $type, $cwd)
    {
        parent::__construct($fckphp_config, $type, $cwd);
        $this->foldername = $this->sanitizeFolderName(getv('FolderName'));
        $this->newname = $this->sanitizeFolderName($this->checkName(getv('NewName')));
    }

    function checkName($name)
    {
        $newName = "";
        for ($i = 0; $i < strlen($name); $i++) {
            if (in_array($name[$i], $this->fckphp_config['DirNameAllowedChars'])) $newName .= $name[$i];
        }
        return $newName;
    }

    function run()
    {
        $result1 = false;


        if ($this->newname != '') {
            $result1 = rename($this->real_cwd . '/' . $this->foldername, $this->real_cwd . '/' . $this->newname);
        }

        if ($result1) {
            $err_no = 0;
        } else {
            $err_no = 602;
        }

        $response = $this->newXmlResponse('RenameFolder');
        $response->setCurrentFolder($this->raw_cwd, $this->actual_cwd)
            ->addChild('Error', ['number' => (string)$err_no]);

        $this->outputXml($response);
    }
}
