<?php
function getSVNRev() {
	// SVN property required to be set, e.g. $Rev: 5948 $ 
    $svnrev = '$Rev: 5948 $';
    $svnrev = substr($svnrev, 6);
    return intval(substr($svnrev, 0, strlen($svnrev) - 2));
}
function getSVNDate() {
	// $Date: 2009-10-22 12:23:46 -0500 (Thu, 22 Oct 2009) $ SVN property required to be set: $Date: 2009-10-22 12:23:46 -0500 (Thu, 22 Oct 2009) $
	$svndate = '$Date: 2009-10-22 12:23:46 -0500 (Thu, 22 Oct 2009) $';
	$svndate = substr($svndate, 40);
	// need to convert this to a timestamp for using MODx native date format function
	return strval(substr($svndate, 0, strlen($svndate) - 3));
}

$modx_version = '1.0.1J'; // Current version
$modx_branch = 'Evolution';
$code_name = 'rev '.getSVNRev(); // SVN version number
$modx_release_date = getSVNDate();
$modx_full_appname = 'MODx '.$modx_branch.' '.$modx_version.' (Rev: '.getSVNRev().' Date:'.getSVNDate();