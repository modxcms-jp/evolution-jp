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

        $thumb = $this->real_cwd . '/.thumb/' . $this->filename;
        $result1 = unlink($this->real_cwd . '/' . $this->filename);
        if (is_file($thumb)) {
            $result2 = unlink($thumb);
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
}
