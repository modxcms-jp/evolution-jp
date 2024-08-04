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

class GetFoldersAndFiles
{
    public $fckphp_config;
    public $type;
    public $cwd;
    public $actual_cwd;
    public $enable_imgedit;

    public function __construct($fckphp_config, $type, $cwd)
    {
        $this->fckphp_config = $fckphp_config;
        $this->type = $type;
        $this->raw_cwd = $cwd;
        $this->actual_cwd = str_replace("//", "/", ($fckphp_config['UserFilesPath'] . "/$type/" . $this->raw_cwd));
        $this->real_cwd = str_replace("//", "/", ($this->fckphp_config['basedir'] . "/" . $this->actual_cwd));
        $self = 'manager/media/browser/mcpuk/connectors/Commands/GetFoldersAndFiles.php';
        $base_path = str_replace(array('\\', $self), array('/', ''), __FILE__);
        if (!is_file("{$base_path}manager/media/ImageEditor/editor.php")) $this->enable_imgedit = false;
        else                                                             $this->enable_imgedit = true;
    }

    public function run()
    {

        header("Content-Type: application/xml; charset=utf-8");
        echo "<?xml version=\"1.0\" encoding=\"utf-8\" ?>\n";
        ?>
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
        ] >

    <Connector command="GetFoldersAndFiles" resourceType="<?php echo $this->type; ?>">
        <CurrentFolder path="<?php echo $this->raw_cwd; ?>"
                       url="<?php echo $this->fckphp_config['urlprefix'] . $this->actual_cwd; ?>"/>
        <Folders>
        <?php
        $files = array();
        if (opendir($this->real_cwd)) {
            /**
             * Initiate the array to store the foldernames
             */
            $folders_array = array();
            $filenames = scandir($this->real_cwd);
            if ($filenames) {
                foreach ($filenames as $filename) {
                    if ($filename === "." || $filename === "..") {
                        continue;
                    }
                    if (!is_dir($this->real_cwd . "/$filename")) {
                        $files[] = $filename;
                        continue;
                    }

                    //check if$fckphp_configured not to show this folder
                    $hide = false;
                    foreach ($this->fckphp_config['ResourceAreas'][$this->type]['HideFolders'] as $iValue) {
                        $pattern = $iValue;
                        $hide = (preg_match("/" . $pattern . "/", $filename) ? true : $hide);
                    }
                    /**
                     * Dont echo the entry, push it in the array
                     */
                    //if (!$hide) echo "\t\t<Folder name=\"$filename\" />\n";
                    if (!$hide) {
                        $folders_array[] = $filename;
                    }
                }
            }
            /**
             * Sort the array by the way you like and show it.
             */
            natcasesort($folders_array);
            foreach ($folders_array as $k => $v) {
                echo '<Folder name="' . $v . '" />' . "\n";
            }
        }
        echo "\t</Folders>\n";
        echo "\t<Files>\n";

        /**
         * The filenames are in the array $files
         * SORT IT!
         */
        natcasesort($files);
        $files = array_values($files);

        foreach ($files as $i => $iValue) {
            $lastdot = strrpos($iValue, ".");
            $ext = $lastdot !== false ? strtolower(substr($iValue, $lastdot + 1)) : "";

            if (!in_array($ext, $this->fckphp_config['ResourceAreas'][$this->type]['AllowedExtensions'])) {
                continue;
            }

            //check if$fckphp_configured not to show this file
            $editable = $hide = false;
            foreach ($this->fckphp_config['ResourceAreas'][$this->type]['HideFiles'] as $jValue) {
                $hide = (preg_match("/" . $jValue . "/", $files[$i]) ? true : $hide);
            }

            if ($hide) {
                continue;
            }

            if ($this->fckphp_config['ResourceAreas'][$this->type]['AllowImageEditing']) {
                if ($this->enable_imgedit) {
                    $editable = $this->isImageEditable($this->real_cwd . "/" . $iValue);
                }
            }
            if (extension_loaded('mbstring')) {
                $name = mb_convert_encoding(
                    $iValue,
                    'UTF-8',
                    mb_detect_encoding($iValue, ['ASCII', 'ISO-2022-JP', 'UTF-8', 'EUC-JP', 'SJIS'], true)
                );
            } else {
                $name = $iValue;
            }
            echo sprintf(
                '<File name="%s" size="%s" editable="%s" />',
                htmlentities($name, ENT_QUOTES, 'UTF-8'),
                ceil(filesize($this->real_cwd . "/" . $iValue) / 1024),
                $editable ? "1" : "0"
            );
        }
        echo "\t</Files>\n";
        echo "</Connector>\n";
    }


    public function isImageEditable($file)
    {
        $fh = fopen($file, 'rb');
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
}
