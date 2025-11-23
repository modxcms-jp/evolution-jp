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
 * File Name: GetFoldersAndFiles.php
 * Implements the GetFoldersAndFiles command, to list
 * files and folders in the current directory.
 * Output is in XML
 *
 * File Authors:
 * Grant French (grant@mcpuk.net)
 */

require_once 'Base.php';
class GetFoldersAndFiles extends Base
{
    private const CONNECTOR_DOCTYPE = <<<'XML'
<!DOCTYPE Connector [

    <!ELEMENT Connector    (CurrentFolder,Folders,Files)>
<!ATTLIST Connector command CDATA "noname">
<!ATTLIST Connector resourceType CDATA "0">

<!ELEMENT CurrentFolder    (#PCDATA)>
<!ATTLIST CurrentFolder path CDATA "noname">
<!ATTLIST CurrentFolder url CDATA "0">

<!ELEMENT Folders    (#PCDATA)>

<!ELEMENT Folder    (#PCDATA)>
<!ATTLIST Folder name CDATA "noname_dir">

<!ELEMENT Files        (#PCDATA)>

<!ELEMENT File        (#PCDATA)>
<!ATTLIST File name CDATA "noname_file">
<!ATTLIST File size CDATA "0">
<!ATTLIST File editable CDATA "0">
]>
XML;

    public $enable_imgedit;

    public function __construct($fckphp_config, $type, $cwd)
    {
        parent::__construct($fckphp_config, $type, $cwd);
        $self = 'manager/media/browser/mcpuk/connectors/Commands/GetFoldersAndFiles.php';
        $base_path = str_replace(['\\', $self], ['/', ''], __FILE__);
        if (!is_file("{$base_path}manager/media/ImageEditor/editor.php")) $this->enable_imgedit = false;
        else                                                             $this->enable_imgedit = true;
    }

    public function run()
    {
        $files = [];
        $folders_array = [];
        $filenames = scandir($this->real_cwd);
        if ($filenames) {
            foreach ($filenames as $filename) {
                if ($filename === '.' || $filename === '..') {
                    continue;
                }

                if (!is_dir($this->real_cwd . "/$filename")) {
                    $files[] = $filename;
                    continue;
                }

                $hide = false;
                foreach ($this->fckphp_config['ResourceAreas'][$this->type]['HideFolders'] as $pattern) {
                    $hide = (preg_match("/" . $pattern . "/", $filename) ? true : $hide);
                }

                if (!$hide) {
                    $folders_array[] = $filename;
                }
            }
        }

        natcasesort($folders_array);
        natcasesort($files);
        $files = array_values($files);

        $response = $this->newXmlResponse(
            'GetFoldersAndFiles',
            ['doctype' => self::CONNECTOR_DOCTYPE]
        );
        $response->setCurrentFolder(
            $this->raw_cwd,
            $this->fckphp_config['urlprefix'] . $this->actual_cwd
        );

        $foldersNode = $response->addChild('Folders');
        foreach ($folders_array as $folder) {
            $response->addChild('Folder', ['name' => $folder], null, $foldersNode);
        }

        $filesNode = $response->addChild('Files');
        foreach ($files as $fileName) {
            $lastdot = strrpos($fileName, '.');
            $ext = $lastdot !== false ? strtolower(substr($fileName, $lastdot + 1)) : '';

            if (!in_array($ext, $this->fckphp_config['ResourceAreas'][$this->type]['AllowedExtensions'])) {
                continue;
            }

            $hide = false;
            foreach ($this->fckphp_config['ResourceAreas'][$this->type]['HideFiles'] as $pattern) {
                $hide = (preg_match("/" . $pattern . "/", $fileName) ? true : $hide);
            }

            if ($hide) {
                continue;
            }

            $editable = false;
            if ($this->fckphp_config['ResourceAreas'][$this->type]['AllowImageEditing'] && $this->enable_imgedit) {
                $editable = $this->isImageEditable($this->real_cwd . '/' . $fileName);
            }

            if (extension_loaded('mbstring')) {
                $name = mb_convert_encoding(
                    $fileName,
                    'UTF-8',
                    mb_detect_encoding($fileName, ['ASCII', 'ISO-2022-JP', 'UTF-8', 'EUC-JP', 'SJIS'], true)
                );
            } else {
                $name = $fileName;
            }

            $response->addChild(
                'File',
                [
                    'name' => $name,
                    'size' => ceil(filesize($this->real_cwd . '/' . $fileName) / 1024),
                    'editable' => $editable ? '1' : '0',
                ],
                null,
                $filesNode
            );
        }

        $this->outputXml($response, 'Content-Type: application/xml; charset=utf-8');
    }


    public function isImageEditable($file)
    {
        // Convert path encoding for filesystem compatibility with multibyte characters
        $file_fs = $this->convertPathToFilesystem($file);

        $fh = @fopen($file_fs, 'rb');
        if (!$fh) {
            return false;
        }

        $start4 = fread($fh, 4);
        fclose($fh);

        $start3 = substr($start4, 0, 3);

        if ($start4 === "\x89PNG") { //PNG
            return (function_exists("imagecreatefrompng") && function_exists("imagepng"));
        }

        if ($start3 === "GIF") { //GIF
            return (function_exists("imagecreatefromgif") && function_exists("imagegif"));
        }

        if ($start3 === "\xFF\xD8\xFF") { //JPEG
            return (function_exists("imagecreatefromjpeg") && function_exists("imagejpeg"));
        }

        if ($start4 === "hsi1") { //JPEG
            return (function_exists("imagecreatefromjpeg") && function_exists("imagejpeg"));
        }
        return false;
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
