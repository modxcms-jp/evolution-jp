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

class Base
{
    public $raw_cwd;
    public $real_cwd;
    public $filename;
    public $foldername;
    public $refreshURL;
    public $newname;
}
