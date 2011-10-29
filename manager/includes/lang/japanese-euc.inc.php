<?php
	$filename = dirname(__FILE__) . '/japanese-utf8.inc.php';
	$contents = file_get_contents($filename);
	$contents = mb_convert_encoding($contents, "EUC-JP", "UTF-8");
	$contents = str_replace("'UTF-8'", "'EUC-JP'", $contents);
	eval('?>' . $contents);
?>