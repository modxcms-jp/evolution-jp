<?php
/**
 * eForm スニペットの処理（雛形 eform.install_base.tpl から読み込まれる）
 */

# Set Snippet Paths
$snip_dir = isset($snip_dir) && preg_match('/^[\w\-]+$/', (string) $snip_dir) ? $snip_dir : 'eform';
$snipPath = base_path() . "assets/snippets/{$snip_dir}/";

if (evo()->isBackend()) return ''; // don't go any further when inside manager

// eform.install_base.tpl の @version とそろえる
$version = '1.5.0';
include("{$snipPath}eform.inc.php");

$output = eForm(evo(), $params);

return $output;
