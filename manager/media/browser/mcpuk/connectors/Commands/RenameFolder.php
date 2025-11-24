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
        $newName = getv('NewName');
        // Use system-wide stripAlias for consistent name sanitization
        if (evo()->config('clean_uploaded_filename') == 1) {
            $newName = evo()->stripAlias($newName, ['file_manager']);
        }
        $this->newname = $this->sanitizeFolderName($newName);
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
