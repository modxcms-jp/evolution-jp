<?php
# WebSignup 1.0
# Created By Raymond Irving April, 2005
#::::::::::::::::::::::::::::::::::::::::::::::::::::::::::::::::

defined('IN_PARSER_MODE') or die();

$tbl_web_users = $modx->getFullTableName('web_users');
$tbl_web_user_attributes = $modx->getFullTableName('web_user_attributes');

# load tpl
if(is_numeric($tpl)) $tpl = ($doc=$modx->getDocuments($tpl)) ? $doc['content']:"Document '$tpl' not found.";
else if($tpl) $tpl = ($chunk=$modx->getChunk($tpl)) ? $chunk:"Chunk '$tpl' not found.";
if(!$tpl) $tpl = getWebSignuptpl($useCaptcha);

// extract declarations
$declare = webLoginExtractDeclarations($tpl);
$tpls = explode((isset($declare["separator"]) ? $declare["separator"]:"<!--tpl_separator-->"),$tpl);

if(!$isPostBack){
    // display signup screen
    $tpl = $tpls[0];
    $tpl = str_replace("[+action+]",$modx->makeURL($modx->documentIdentifier),$tpl);
    $tpl.="<script type='text/javascript'>
        if (document.websignupfrm) document.websignupfrm.username.focus();
        </script>";
    $output .= $tpl;
} 
else if ($isPostBack){

    $username = $modx->db->escape($modx->stripTags(trim($_POST['username'])));
    $fullname = $modx->db->escape($modx->stripTags($_POST['fullname']));
    $email = $modx->db->escape($modx->stripTags($_POST['email']));
    $password = $modx->db->escape($modx->stripTags($_POST['password']));
    $country = $modx->db->escape($modx->stripTags($_POST['country']));
    $state = $modx->db->escape($modx->stripTags($_POST['state']));
    $zip = $modx->db->escape($modx->stripTags($_POST['zip']));
    $formcode = $_POST['formcode'];

    // load template section #1
    $tpl = $tpls[0];
    $tpl = str_replace("[+action+]",$modx->makeURL($modx->documentIdentifier),$tpl);
    $tpl = str_replace("[+username+]",$username,$tpl);
    $tpl = str_replace("[+fullname+]",$fullname,$tpl);
    $tpl = str_replace("[+email+]",$email,$tpl);
    $tpl = str_replace("[+country+]",$country,$tpl);
    $tpl = str_replace("[+state+]",$state,$tpl);
    $tpl = str_replace("[+zip+]",$zip,$tpl);
    $tpl.="<script type='text/javascript'>if (document.websignupfrm) document.websignupfrm.username.focus();</script>";

    // check for duplicate user name
    if($username=="") {
        $output = webLoginAlert("Missing username. Please enter a user name.").$tpl;
        return;
    }
    else {
        if(!$rs = $modx->db->select('id',$tbl_web_users,"username='{$username}'")){
            $output = webLoginAlert("An error occured while attempting to retreive all users with username $username.").$tpl;
            return;
        } 
        $limit = $modx->db->getRecordCount($rs);
        if($limit>0) {
            $output = webLoginAlert("Username is already in use!").$tpl;
            return;
        }        
    }
    
    // verify email
    if($email=='' || !preg_match("/^[A-Z0-9._%+-]+@[A-Z0-9.-]+\.[A-Z]{2,6}$/i", $email)){
        $output = webLoginAlert("E-mail address doesn't seem to be valid!").$tpl;
        return;
    }

    // check for duplicate email address
    if(!$rs = $modx->db->select('internalKey',$tbl_web_user_attributes,"email='{$email}'")){
        $output = webLoginAlert("An error occured while attempting to retreive all users with email $email.").$tpl;
        return;
    } 
    $limit = $modx->db->getRecordCount($rs);
    if($limit>0) {
        $row=$modx->db->getRow($rs);
        if($row['internalKey']!=$id) {
            $output = webLoginAlert("Email is already in use!").$tpl;
            return;
        }
    }
    
    // if there is no password, randomly generate a new one 	 
 	if (isset($_POST['password'])) { 	  	 
		// verify password 	  	 
 	    if ($_POST['password'] != $_POST['confirmpassword']) {
 	  		$output = webLoginAlert("Password typed is mismatched"). $tpl;
 	  	    return; 	  	 
 	  	} 	  	 

	    // check password
	    if (strlen($password) < 6 ) {
	        $output = webLoginAlert("Password is too short!").$tpl;
	        return;
	    } 
	    elseif($password=="") {
	        $output = webLoginAlert("You didn't specify a password for this user!").$tpl;
	        return;        
	    }
 	} else {
 		$password = webLoginGeneratePassword();
 	}

    // verify form code
    if($useCaptcha && $_SESSION['veriword']!=$formcode) {
        $output = webLoginAlert("Incorrect form code. Please enter the correct code displayed by the image.").$tpl;
        return;
    }

    // create the user account
    $sql = "INSERT INTO ".$tbl_web_users." (username, password) 
            VALUES('".$username."', md5('".$password."'));";
    $rs = $modx->db->query($sql);
    if(!$rs){
        $output = webLoginAlert("An error occured while attempting to save the user.").$tpl;
        return;
    }         
    // now get the id
    $key=$modx->db->getInsertId();

    // save user attributes
    $sql = "INSERT INTO ".$tbl_web_user_attributes." (internalKey, fullname, email, zip, state, country) 
            VALUES($key, '$fullname', '$email', '$zip', '$state', '$country');";
    $rs = $modx->db->query($sql);
    if(!$rs){
        $output = webLoginAlert("An error occured while attempting to save the user's attributes.").$tpl;
        return;
    }

    // add user to web groups
    if(count($groups)>0) {
        $joind_groups = "('" . join("','",$groups) . "')";
        $ds = $modx->db->select('id', $modx->getFullTableName("webgroup_names"), "name IN {$joind_groups}");
        if(!$ds) return $modx->webAlert('An error occured while attempting to update user\'s web groups');
        else {
            while ($row = $modx->db->getRow($ds)) {
                $wg = $row["id"];
                $modx->db->query("REPLACE INTO ".$modx->getFullTableName("web_groups")." (webgroup,webuser) VALUES('$wg','$key')");
            }
        }
    }
            
    // invoke OnWebSaveUser event
    $modx->invokeEvent("OnWebSaveUser",
                        array(
                            "mode"         => "new",
                            "userid"       => $key,
                            "username"     => $username,
                            "userpassword" => $password,
                            "useremail"    => $email,
                            "userfullname" => $fullname
                        ));
                        
    // send email notification
    $rt = webLoginSendNewPassword($email,$username,$password,$fullname);
    if ($rt!==true) { // an error occured
        $output = $rt.$tpl;
        return;
    }
        
    // display change notification
    $newpassmsg = "A copy of the new password was sent to your email address.";
    $tpl = $tpls[1];
    $tpl = str_replace("[+newpassmsg+]",$newpassmsg,$tpl);    
    $output .= $tpl;
}

// Returns Default WebChangePwd tpl
function getWebSignuptpl($useCaptcha){
	$countryOptions = getCountryCode();
    ob_start();
    ?>
    <!-- #declare:separator <hr> --> 
    <!-- login form section-->
    <form method="post" name="websignupfrm" action="[+action+]">
	User name : * <input type="text" name="username" size="20" value="[+username+]"><br />
	Full name : <input type="text" name="fullname" size="20" value="[+fullname+]"><br />
	Email address:* <input type="text" name="email" size="20" value="[+email+]"><br />
	Password:* <input type="password" name="password" size="20"><br />
	Confirm password:* <input type="password" name="confirmpassword" size="20"><br />
	Country :
	<select size="1" name="country" style="width:300px">
	<?php echo $countryOptions; ?>
	</select><br />
	State : <input type="text" name="state" size="20" value="[+state+]"><br />
	Zip : <input type="text" name="zip" size="20" value="[+zip+]"><br />
	<?php if ($useCaptcha)
	{ ?>
	Form code : * <input type="text" name="formcode" style="width:150px" size="20">
	<a href="[+action+]"><img align="top" src="captcha.php?rand=<?php echo mt_rand(); ?>" /></a><br />
	<?php
	} ?>
	<input type="submit" value="Submit" name="cmdwebsignup" />
	<input type="reset" value="Reset" name="cmdreset" />
    </form>
    <script language="javascript" type="text/javascript"> 
        var id = "[+country+]";
        var f = document.websignupfrm;
        var i = parseInt(id);
        if (!isNaN(i)) f.country.options[i].selected = true;
    </script>
    <hr>
    <!-- notification section -->
    <span style="font-weight:bold;">Signup completed successfully</span><br />
    Your account was successfully created.<br />
    A copy of your signup information was sent to your email address.<br /><br />
    <?php 
    $t = ob_get_contents();
    ob_end_clean();
    return $t;
}

function getCountryCode()
{
	global $modx;
	incluede_once($modx->config['base_path'] . 'manager/includes/lang/country/' . $modx->config['manager_language'] . '_country.inc.php');
	foreach($_country_lang as $k=>$v)
	{
		$option = '<option value="' . $k . '">' . $v . '</option>';
	}
	return join("\n",$option);
}
