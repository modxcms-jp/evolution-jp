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
 * File Name: CreateFolder.php
 * Implements the CreateFolder command to make a new folder
 * in the current directory. Output is in XML.
 *
 * File Authors:
 * Grant French (grant@mcpuk.net)
 */

require_once 'Base.php';

class CreateFolder extends Base
{
    public $newfolder;

    function __construct($fckphp_config, $type, $cwd)
    {
        parent::__construct($fckphp_config, $type, $cwd);
        $folderName = getv('NewFolderName');
        // Use system-wide stripAlias for consistent name sanitization
        if (evo()->config('clean_uploaded_filename') == 1) {
            $folderName = evo()->stripAlias($folderName, ['file_manager']);
        }
        $this->newfolder = $this->sanitizeFolderName($folderName);
    }

    public function checkFolderName($folderName)
    {
        //Check the name is not too long
        if (strlen($folderName) > $this->fckphp_config['MaxDirNameLength']) {
            return false;
        }
        // Character validation is now handled by stripAlias()
        return true;
    }

    public function run()
    {
        $newdir = str_replace(
            "//",
            "/",
            $this->real_cwd . "/" . $this->newfolder
        );

        if ($this->checkFolderName($this->newfolder)) {
            if (is_dir($newdir)) {
                $err_no = 101;
            } else {
                if (is_writable($this->real_cwd)) {
                    if (mkdir($newdir, 0777)) {
                        $err_no = 0;
                        @chmod(
                            $newdir,
                            octdec(evo()->config('new_folder_permissions', 0777))
                        );
                    } else {
                        $err_no = 110;
                    }
                } else {
                    $err_no = 103;
                }
            }
        } else {
            $err_no = 102;
        }

        $response = $this->newXmlResponse('CreateFolder');
        $response->setCurrentFolder($this->raw_cwd, $this->actual_cwd)
            ->addChild('Error', ['number' => (string)$err_no]);

        $this->outputXml($response);
    }
}
