<?php
if (!function_exists('getSVNRev')) {
function getSVNRev() {
        // SVN property required to be set, e.g. $Rev: 6066 $
        $svnrev = '$Rev: 6066 $';
    $svnrev = substr($svnrev, 6);
    return intval(substr($svnrev, 0, strlen($svnrev) - 2));
}
}
if (!function_exists('getSVNDate')) {
function getSVNDate() {
        // $Date: 2009-11-04 22:38:42 -0600 (Wed, 04 Nov 2009) $ SVN property required to be set: $Date: 2009-11-04 22:38:42 -0600 (Wed, 04 Nov 2009) $
        $svndate = '$Date: 2009-11-04 22:38:42 -0600 (Wed, 04 Nov 2009) $';
	$svndate = substr($svndate, 40);
	// need to convert this to a timestamp for using MODx native date format function
	return strval(substr($svndate, 0, strlen($svndate) - 3));
    }
}

$modx_version = '1.0.2J'; // Current version
$modx_branch = 'Evolution';
$code_name = 'rev '.getSVNRev(); // SVN version number
$modx_release_date = getSVNDate();
$modx_full_appname = 'MODx '.$modx_branch.' '.$modx_version.' (Rev: '.getSVNRev().' Date:'.getSVNDate();