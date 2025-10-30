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
 * File Name: GetFolders.php
 * Implements the GetFolders command, to list the folders
 * in the current directory. Output is in XML
 *
 * File Authors:
 * Grant French (grant@mcpuk.net)
 */

require_once 'Base.php';

class GetFolders extends Base
{
    function __construct($fckphp_config, $type, $cwd)
    {
        parent::__construct($fckphp_config, $type, $cwd);
    }

    function run()
    {
        $files_in_folder = [];
        $files = scandir($this->real_cwd);
        if ($files) {
            foreach ($files as $filename) {
                if (($filename != '.') && ($filename != '..')) {
                    if (is_dir($this->real_cwd . $filename)) {
                        $hide = false;
                        for ($i = 0; $i < sizeof($this->fckphp_config['ResourceAreas'][$this->type]['HideFolders']); $i++) {
                            $pattern = $this->fckphp_config['ResourceAreas'][$this->type]['HideFolders'][$i];
                            $hide = (preg_match("/{$pattern}/", $filename) ? true : $hide);
                        }
                        if (!$hide) {
                            $files_in_folder[] = $filename;
                        }
                    }
                }
            }
        }

        natcasesort($files_in_folder);

        $response = $this->newXmlResponse('GetFolders');
        $response->setCurrentFolder($this->raw_cwd, $this->actual_cwd);
        $foldersNode = $response->addChild('Folders');
        foreach ($files_in_folder as $folder) {
            $response->addChild('Folder', ['name' => $folder], null, $foldersNode);
        }

        $this->outputXml($response);
    }
}
