<?php
/**
 * Installer remover processor
 * --------------------------------
 * This little script will be used by the installer to remove
 * the install folder from the web root after an install. Having
 * the install folder present after an install is considered a
 * security risk
 *
 * This file is mormally called from the installer
 *
 */
$self = 'manager/processors/remove_installer.processor.php';
$base_path = str_replace(['\\', $self], ['/', ''], __FILE__);

$install_dir = "{$base_path}install";
if ($_GET['rminstall']??null) {
    if (is_dir($install_dir)) {
        if (!rmdirRecursive($install_dir)) {
            $msg = 'An error occured while attempting to remove the install folder';
            echo "<script>alert('{$msg}');</script>";
            exit;
        }
    }
}
echo "<script>window.location='../index.php?a=2';</script>";

// rmdirRecursive - detects symbollic links on unix
function rmdirRecursive($path)
{
    $files = scandir($path);
    foreach ($files as $entry) {
        if ($entry === '.') {
            continue;
        }
        if ($entry === '..') {
            continue;
        }

        $target = $path . '/' . $entry;
        if (is_file($target)) {
            @unlink($target);
        } elseif (is_dir($target)) {
            rmdirRecursive($target);
        }
    }
    return @rmdir($path);
}
