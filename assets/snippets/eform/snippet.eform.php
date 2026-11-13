<?php
/**
 * eForm スニペットの処理（雛形 eform.install_base.tpl から読み込まれる）
 *
 * 雛形の @version と、下の $version は、eForm の版（1.4.5）。
 */

# eForm 1.4.5 - Electronic Form Snippet
# Original created by Raymond Irving 15-Dec-2004.
# Version 1.3+ extended by Jelle Jager (TobyL) September 2006
# -----------------------------------------------------
# Captcha image support - thanks to Djamoer
# Multi checkbox, radio, select support - thanks to Djamoer
# Form Parser and extened validation - by Jelle Jager
#

# Set Snippet Paths
$snip_dir = isset($snip_dir) && preg_match('/^[\w\-]+$/', (string) $snip_dir) ? $snip_dir : 'eform';
$snipPath = base_path() . "assets/snippets/{$snip_dir}/";

# check if inside manager
if (evo()->isBackend()) return ''; // don't go any further when inside manager

# Start processing

$version = '1.4.5';
include("{$snipPath}eform.inc.php");

$output = eForm(evo(), $params);

# Return
return $output;
