<?php
if (!function_exists('getSVNRev')) {
    function getSVNRev() {
        // SVN property required to be set, e.g. $Rev: 6643 $
        $svnrev = '$Rev: 6643 $';
        $svnrev = substr($svnrev, 6);
        return intval(substr($svnrev, 0, strlen($svnrev) - 2));
    }
}
if (!function_exists('getSVNDate')) {
    function getSVNDate() {
        // $Date: 2010-04-01 07:14:21 +0900 (Thu, 01 4 2010) $ SVN property required to be set: $Date: 2010-04-01 07:14:21 +0900 (Thu, 01 4 2010) $
        $svndate = '$Date: 2010-04-01 07:14:21 +0900 (Thu, 01 4 2010) $';
        $svndate = substr($svndate, 39);
        // need to convert this to a timestamp for using MODx native date format function
        return trim(strval(substr($svndate, 0, strlen($svndate) - 3)));
    }
}

$modx_version = '1.0.3J'; // Current version
$modx_branch = 'Evolution';
$code_name = 'rev '.getSVNRev(); // SVN version number
$modx_release_date = getSVNDate();
$modx_full_appname = 'MODx '.$modx_branch.' '.$modx_version.' (Rev: '.getSVNRev().' Date: '.getSVNDate().')';
