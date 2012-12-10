//<?php
/**
 * 管理画面カスタマイズ
 *
 * ログイン画面・ダッシュボードのカスタマイズコード
 *
 * @category 	plugin
 * @version 	1.0.3
 * @license 	http://www.gnu.org/copyleft/gpl.html GNU Public License (GPL)
 * @internal	@events OnManagerLoginFormPrerender,OnManagerWelcomePrerender
 * @internal	@modx_category Manager and Admin
 * @internal    @installset base
 */

/* 当プラグインの使い方

1. チャンク「ログイン画面」「ダッシュボード」を作成します。
2. assets/templates/manager/ディレクトリのlogin.tpl・welcome.tplの内容を各チャンクにコピー
3. 必要に応じて各チャンクをカスタマイズ

当プラグインを無効にした場合はassets/templates/manager/ディレクトリのコードが出力されます。
assets/templates/manager/ディレクトリにファイルがない場合はMODX本体内蔵のコードが出力されます。

●コードを書き間違えてログインできなくなった場合
manager/index.php内の「$modx->safeMode = 0;」を「$modx->safeMode = 1;」としてセーフモードで
ログインできます。特権ロール(Administratorロール)のアカウントがある場合は、ユーザ名の末尾に
「:safemode」を付加することでもセーフモードログインできます。

●応用情報
メンバーごと・ロールごとに制御したい場合は $modx->getLoginUserID() や $_SESSION['mgrRole']の
値を用いるとよいでしょう。

*/

switch($modx->event->name)
{
	case 'OnManagerLoginFormPrerender':
		$src = $modx->getChunk('ログイン画面');
		break;
	case 'OnManagerWelcomePrerender':
		$src = $modx->getChunk('ダッシュボード');
		break;
	default:
		return;
}
if($src!==false && !empty($src))
{
	global $tpl;
	$tpl = $src;
}
