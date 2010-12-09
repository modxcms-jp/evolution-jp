<?php
/*******************************************************
 *
 * MODxMailer Class extends PHPMailer
 * Created by ZeRo (http://www.petit-power.com/)
 *
 * -----------------------------------------------------
 * [History]
 * Ver1.4.4.6    2009/02/28
 * -----------------------------------------------------
 * Update $Date: 2009-03-01 17:17:18 +0900 (日, 01 3 2009) $ 
 *        $Revision: 59 $
 *******************************************************
 */

if (file_exists(dirname(__FILE__)."/class.phpmailer.php")) {
	include_once dirname(__FILE__)."/class.phpmailer.php";
} else {
	include_once MODX_BASE_PATH . 'manager/includes/controls/class.phpmailer.php';
}

/* ------------------------------------------------------------------
 *
 * MODxMailer - PhpMailerの派生クラス
 *
 * -----------------------------------------------------------------
 */
class MODxMailer extends PHPMailer
{
var $mb_config = null;
var $charset_array = array(
            'japanese-utf8' =>
                array('charset' => 'iso-2022-jp','bit' => '7bit','lang' => 'japanese'),
            'japanese-euc' =>
                array('charset' => 'iso-2022-jp','bit' => '7bit','lang' => 'japanese'),
                
            );


    /*
     * コンストラクタ(PHP4風）
     */
    function MODxMailer()
	{
	global $modx;
	
        $this->CharSet = $modx->config['modx_charset'];

	    if (array_key_exists($modx->config['manager_language'],$this->charset_array))
		{
			$this->mb_config = $this->charset_array[$modx->config['manager_language']];
			$this->CharSet = $this->mb_config['charset'];
			$this->Encoding = $this->mb_config['bit'];
        }
	}

    /*
     * メール送信関数（オーバーライド）
     */
	function Send()
	{
	global $modx;

		if (!empty($this->mb_config))
		{	mb_language($this->mb_config['lang']);
			mb_internal_encoding($modx->config['modx_charset']);
			$this->FromName	= mb_encode_mimeheader($this->FromName,$this->CharSet,"B",$this->LE);
			$this->Subject	= mb_convert_encoding($this->Subject,$this->CharSet,$modx->config['modx_charset']);
			$this->Body= mb_convert_encoding($this->Body,$this->CharSet,$modx->config['modx_charset']);
		}
		return parent::Send();
	}
}

?>
