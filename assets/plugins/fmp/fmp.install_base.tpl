//<?php
/**
 * Forgot Manager Login
 * 
 * 管理画面のログインパスワードを忘れた時に、一時的に無条件ログインできるURLを発行
 *
 * @category 	plugin
 * @version 	1.3
 * @license 	http://www.gnu.org/copyleft/gpl.html GNU Public License (GPL)
 * @internal	@events OnManagerLoginFormPrerender,OnManagerLoginFormRender,OnManagerAuthentication,OnManagerMainFrameHeaderHTMLBlock,OnManagerLogin 
 * @internal	@modx_category Manager and Admin
 * @internal    @installset base
 */

include_once(MODX_BASE_PATH . 'assets/plugins/fmp/fmp.class.inc.php');
$forgot = new ForgotManagerPassword();
$forgot->run();
