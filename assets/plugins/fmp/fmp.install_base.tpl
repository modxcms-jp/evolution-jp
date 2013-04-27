//<?php
/**
 * Forgot Manager Login
 * 
 * Resets your manager login when you forget your password via email confirmation
 *
 * @category 	plugin
 * @version 	1.2b2
 * @license 	http://www.gnu.org/copyleft/gpl.html GNU Public License (GPL)
 * @internal	@events OnManagerLoginFormPrerender,OnBeforeManagerLogin,OnManagerAuthentication,OnManagerLoginFormRender,OnManagerChangePassword 
 * @internal	@modx_category Manager and Admin
 * @internal    @installset base
 */

include_once($modx->config['base_path'] . 'assets/plugins/fmp/fmp.class.inc.php');
$forgot = new ForgotManagerPassword();
$forgot->run();
