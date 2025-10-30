<?php
/*
 * FCKeditor - The text editor for internet
 * Copyright (C) 2003-2005 Frederico Caldeira Knabben
 *
 * Licensed under the terms of the GNU Lesser General Public License:
 *   http://www.opensource.org/licenses/lgpl-license.php
 *
 * For further information visit:
 *   http://www.fckeditor.net/
 *
 * "Support Open Source software. What about a donation today?"
 *
 * File Name: config.php
 *  Configuration file
 *
 * File Authors:
 *   Grant French (grant@mcpuk.net)
 */

require_once __DIR__ . '/ConnectorConfigBuilder.php';

$builder = new ConnectorConfigBuilder(evo());
$fckphp_config = $builder->build();
