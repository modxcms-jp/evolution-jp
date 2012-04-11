//<?php
/**
 * 管理画面カスタマイズ
 *
 * ログイン画面・ダッシュボードのカスタマイズコード
 *
 * @category 	plugin
 * @version 	1.0
 * @license 	http://www.gnu.org/copyleft/gpl.html GNU Public License (GPL)
 * @internal	@events OnManagerLoginFormPrerender,OnManagerWelcomePrerender
 * @internal	@modx_category Manager and Admin
 * @internal    @installset base
 */

// 当プラグインを無効にした場合はMODX本体内蔵のコードが出力されます
// コード本体はチャンク「ログイン画面」「ダッシュボード」に記述されていますので
// これを書き換えて自由にカスタマイズできます。
// もしコードを書き間違えてログインできなくなった場合は
// manager/index.php内の「$modx->safeMode = true;」行頭のコメントアウトを
// 削除してログインし、修正してください。

switch($modx->event->name)
{
	case 'OnManagerLoginFormPrerender':
		$src = $modx->getChunk('ログイン画面');
		break;
	case 'OnManagerWelcomePrerender':
		$src = $modx->getChunk('ダッシュボード');
		break;
}
if($src!==false && !empty($src))
{
	global $tpl;
	$tpl = $src;
}
