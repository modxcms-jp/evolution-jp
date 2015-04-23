<?php
// this is the old error handler. Here for legacy, until i replace all the old errors.
class errorHandler{

	var $errorcode;
	var $errors = array();
	
	function errorHandler()
	{
		global $_lang;
	
		$this->errors = array(
		0	=>	$_lang["No errors occured."],
		1	=>	$_lang["An error occured!"],
		2	=>	$_lang["Document's ID not passed in request!"],
		3	=>	$_lang["You don't have enough privileges for this action!"],
		4	=>	$_lang["ID passed in request is NaN!"],
		5	=>	$_lang["The document is locked!"],
		6	=>	$_lang["Too many results returned from database!"],
		7	=>	$_lang["Not enough/ no results returned from database!"],
		8	=>	$_lang["Couldn't find parent document's name!"],
		9	=>	$_lang["Logging error!"],
		10	=>	$_lang["Table to optimise not found in request!"],
		11	=>	$_lang["No settings found in request!"],
		12	=>	$_lang["The document must have a title!"],
		13	=>	$_lang["No user selected as recipient of this message!"],
		14	=>	$_lang["No group selected as recipient of this message!"],
		15	=>	$_lang["The document was not found!"],
	
		100 =>	$_lang["Double action (GET & POST) posted!"],
		600 =>	$_lang["Document cannot be it's own parent!"],
		601 =>	$_lang["Document's ID not passed in request!"],
		602 =>	$_lang["New parent not set in request!"],
		900 =>	$_lang["don't know the user!"], // don't know the user!
		901 =>	$_lang["wrong password!"], // wrong password!
		902 =>	$_lang["Due to too many failed logins, you have been blocked!"],
		903 =>	$_lang["You are blocked and cannot log in!"],
		904 =>	$_lang["You are blocked and cannot log in! Please try again later."],
		905 =>	$_lang["The security code you entered didn't validate! Please try to login again!"]
	);
	}

	function setError($errorcode, $custommessage=""){
		$this->errorcode=$errorcode;
		$this->errormessage=$this->errors[$errorcode];
		if($custommessage!="") {
			$this->errormessage=$custommessage;
		}
	}
	
	function getError() {
		return $this->errorcode;
	}
	
	function dumpError() {
		global $modx, $_lang;
		if(!isset($_GET['count_attempts']))
		{
			if(strpos($_SESSION['previous_request_uri'],'&count_attempts')===false)
				$previous_request_uri = $_SESSION['previous_request_uri'] . '&count_attempts=1';
			else
				$previous_request_uri = $_SESSION['previous_request_uri'];
		}
		else                                $previous_request_uri = 'index.php?a=2';
		
		$tpl = file_get_contents(MODX_MANAGER_PATH . 'media/style/common/dump_error.tpl');
		$ph['message']  = $modx->db->escape($this->errormessage);
		$ph['warning']  = $_lang['warning'];
		$ph['url']      = $previous_request_uri;
		$scr = $modx->parseText($tpl,$ph);

        include_once(MODX_CORE_PATH . 'header.inc.php');
		echo $scr;
		include_once(MODX_CORE_PATH . 'footer.inc.php');
		exit;
	}
}
