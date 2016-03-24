<?php
/*
*************************************************************************
    MODx Content Management System and PHP Application Framework
    Managed and maintained by Raymond Irving, Ryan Thrash and the
    MODx community
*************************************************************************
    MODx is an opensource PHP/MySQL content management system and content
    management framework that is flexible, adaptable, supports XHTML/CSS
    layouts, and works with most web browsers, including Safari.

    MODx is distributed under the GNU General Public License
*************************************************************************

    MODx CMS and Application Framework ("MODx")
    Copyright 2005 and forever thereafter by Raymond Irving & Ryan Thrash.
    All rights reserved.

    This file and all related or dependant files distributed with this filie
    are considered as a whole to make up MODx.

    MODx is free software; you can redistribute it and/or modify
    it under the terms of the GNU General Public License as published by
    the Free Software Foundation; either version 2 of the License, or
    (at your option) any later version.

    MODx is distributed in the hope that it will be useful,
    but WITHOUT ANY WARRANTY; without even the implied warranty of
    MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
    GNU General Public License for more details.

    You should have received a copy of the GNU General Public License
    along with MODx (located in "/assets/docs/"); if not, write to the Free Software
    Foundation, Inc., 51 Franklin Street, Fifth Floor, Boston, MA  02110-1301, USA

    For more information on MODX please visit http://modx.com/

**************************************************************************
    Originally based on Etomite by Alex Butter
**************************************************************************
*/


/**
 *  Filename: manager/index.php
 *  Function: This file is the main root file for MODx. It is
 *          only file that will be directly requested, and
 *          depending on the request, will branch different
 *          content
 */

// get start time
if(!isset($_SERVER['REQUEST_TIME_FLOAT'])) $_SERVER['REQUEST_TIME_FLOAT'] = microtime(true);
$mstart = memory_get_usage();

$self_path      = str_replace('\\','/',__FILE__);
$self_dir  = str_replace('/index.php','',$self_path);
$mgr_dir   = substr($self_dir,strrpos($self_dir,'/')+1);
$base_path = str_replace($mgr_dir . '/index.php','',$self_path);
if(!is_dir("{$base_path}assets/cache")) mkdir("{$base_path}assets/cache");
$site_mgr_path = $base_path . 'assets/cache/siteManager.php';

if( !is_file($site_mgr_path) )
{
	$src = "<?php\n";
	$src .= "define('MGR_DIR', '{$mgr_dir}');\n";
	$rs = file_put_contents($site_mgr_path,$src);
	if(!$rs) exit('siteManager.php write error');
	define('MGR_DIR', $mgr_dir);
}
else include_once($site_mgr_path);

define('IN_MANAGER_MODE', 'true');  // we use this to make sure files are accessed through the manager instead of seperately.

$core_path = "{$base_path}manager/includes/";
$incPath = $core_path;

if (@is_file("{$base_path}autoload.php")) {
    include_once("{$base_path}autoload.php");
}

// initiate the content manager class
$modx = include_once('includes/document.parser.class.inc.php');
$modx->mstart = $mstart;
$modx->safeMode = 0;
if(isset($_SESSION['safeMode']) && $_SESSION['safeMode']==1)
{
    if($modx->hasPermission('save_role')) $modx->safeMode = 1;
    else                                  $modx->safeMode = $_SESSION['safeMode'] = 0;
}

$modx->getSettings();

extract($modx->config);

if (isset($_POST['updateMsgCount']) && $modx->hasPermission('messages')) {
    $modx->manager->getMessageCount();
}

// include_once the language file
$modx->loadLexicon('manager');

// send the charset header
header(sprintf('Content-Type: text/html; charset=%s',$modx->config['modx_manager_charset']));

$modx->manager->action = isset($_REQUEST['a']) ? (int) $_REQUEST['a'] : 1;

// accesscontrol.php checks to see if the user is logged in. If not, a log in form is shown
include_once(MODX_CORE_PATH . 'accesscontrol.inc.php');

// double check the session
if (!isset($_SESSION['mgrValidated'])) exit('Not Logged In!');

switch ($modx->manager->action) {
    case 5:
    case 20:
    case 24:
    case 79:
    case 103:
    case 109:
    case 30:
    case 302:
    case 86:
        break;
    default:
        $cache_path = "{$modx->config['base_path']}assets/cache/rolePublishing.idx.php";
        if(is_file($cache_path))
        {
            $role = unserialize(file_get_contents($cache_path));
            if($_SESSION['mgrLastlogin'] < $role[$_SESSION['mgrRole']])
            {
                @session_destroy();
                session_unset();
                header("Location: {$modx->config['site_url']}manager/");
                exit;
            }
        }
}

// include_once the style variables file
$theme_dir = "media/style/{$manager_theme}/";
if(is_file("{$theme_dir}style.php")) include_once("{$theme_dir}style.php");

// check if user is allowed to access manager interface
if (isset($allow_manager_access) && $allow_manager_access==0) {
    include_once(MODX_CORE_PATH . 'manager.lockout.inc.php');
}

// include_once the error handler
include_once(MODX_CORE_PATH . 'error.class.inc.php');
$e = new errorHandler;

// Initialize System Alert Message Queque
if (!isset($_SESSION['SystemAlertMsgQueque'])) $_SESSION['SystemAlertMsgQueque'] = array();
$modx->SystemAlertMsgQueque = &$_SESSION['SystemAlertMsgQueque'];

// first we check to see if this is a frameset request
if (!isset($_POST['a']) && !isset($_GET['a']) && ($e->getError()==0) && !isset($_POST['updateMsgCount'])) {
    // this looks to be a top-level frameset request, so let's serve up a frameset
    include_once(MODX_MANAGER_PATH . 'frames/1.php');
    exit;
}

// OK, let's retrieve the action directive from the request
if (isset($_GET['a']) && isset($_POST['a'])) {
    $e->setError(100);
    $e->dumpError();
    // set $e to a corresponding errorcode
    // we know that if an error occurs here, something's wrong,
    // so we dump the error, thereby stopping the script.

} else {
    if(isset($_REQUEST['a'])) $modx->manager->action= (int) $_REQUEST['a'];
    else                      $modx->manager->action = '';
}

// attempt to foil some simple types of CSRF attacks
$modx->manager->validate_referer($modx->config['validate_referer']);

$modx->manager->setView($modx->manager->action);

if(isset($_POST['stay'])&&$_POST['stay']!=='new') $_SESSION['saveAfter'] = $_POST['stay'];

// invoke OnManagerPageInit event
// If you would like to output $evtOutOnMPI , set $action to 999 or 998 in Plugin. 
//   ex)$modx->event->setGlobalVariable('action',999);
$tmp = array("action" => $modx->manager->action);
$evtOutOnMPI = $modx->invokeEvent("OnManagerPageInit", $tmp);

$action_path = MODX_MANAGER_PATH . 'actions/';
$prc_path    = MODX_MANAGER_PATH . 'processors/';

// Now we decide what to do according to the action request. This is a BIG list :)

if(in_array($modx->manager->action,array(2,3,120,4,72,27,132,131,51,133,7,87,88,11,12,74,28,38,35,16,19,22,23,78,77,18,26,106,107,108,113,101,102,127,200,31,40,91,17,53,13,10,70,71,59,75,99,86,76,83,95,9,300,301,114,115,998)))
    include_once($action_path.'header.inc.php');

switch ($modx->manager->action) {
    case 1 : //frame management - show the requested frame  
        // get the requested frame
        if(isset($_REQUEST['f'])) {
            $frame = $_REQUEST['f'];
            if($frame!=='tree'&&$frame!=='menu'&&$frame!=='nodes') return;
            include_once(MODX_MANAGER_PATH . "frames/{$frame}.php");
        }
        elseif(isset($_REQUEST['ajaxa']))
            include(MODX_MANAGER_PATH . 'ajax.php'); // ajax-action
        break;
    case 2: // get the home page
        include_once($action_path.'main/welcome.static.php');break;
    case 3: // get the page to show document's data
        include_once($action_path.'document/document_data.static.php');break;
    case 120: // get the mutate page for changing content
        include_once($action_path.'document/resources_list.static.php');break;
    case 4: // get the mutate page for adding content
    case 72: // get the weblink page
    case 27: // get the mutate page for changing content
    case 132: // get the mutate page for changing draft content
    case 131: // get the mutate page for changing draft content
        include_once($action_path.'document/mutate_content.dynamic.php');break;
    case 5: // get the save processor
        include_once($prc_path.'document/save_resource.processor.php');break;
    case 6: // get the delete processor
        include_once($prc_path.'document/delete_content.processor.php');break;
    case 63: // get the undelete processor
        
        include_once($prc_path.'document/undelete_content.processor.php');
    break;
    case 51: // get the move action
        include_once($action_path.'document/move_document.dynamic.php');break;
    case 52: // get the move document processor
        include_once($prc_path.'document/move_document.processor.php');break;
    case 61: // get the processor for publishing content
        include_once($prc_path.'document/publish_content.processor.php');break;
    case 62: // get the processor for publishing content
        include_once($prc_path.'document/unpublish_content.processor.php');break;
    case 133: // get the mutate page for changing draft content
        include_once($action_path.'document/publish_draft.dynamic.php');break;
    case 134: // get the processor for unpublishing draft content
        include_once($prc_path.'document/unpublish_draft_content.processor.php');break;
    case 7: // get the wait page (so the tree can reload)
        include_once($action_path.'wait.static.php');break;
    case 8: // get the logout processor
        include_once($prc_path.'logout.processor.php');break;
    case 87: // get the new web user page
    case 88: // get the edit web user page
        include_once($action_path.'permission/mutate_web_user.dynamic.php');break;
    case 89: // get the save web user processor
        include_once($prc_path.'permission/save_web_user.processor.php');break;
    case 90: // get the delete web user page
        include_once($prc_path.'permission/delete_web_user.processor.php');break;
    case 11: // get the new user page
    case 12: // get the edit user page
        include_once($action_path.'permission/mutate_user.dynamic.php');break;
    case 32: // get the save user processor
        include_once($prc_path.'permission/save_user.processor.php');break;
    case 74: // get the edit user profile page 
        include_once($action_path.'permission/mutate_user_pf.dynamic.php');break;
    case 28: // get the change password page
        include_once($action_path.'permission/mutate_password.dynamic.php');break;
    case 34: // get the save new password page
        include_once($prc_path.'permission/save_password.processor.php');break;
    case 33: // get the delete user page
        include_once($prc_path.'permission/delete_user.processor.php');break;
// role management
    case 38: // get the new role page
    case 35: // get the edit role page
        include_once($action_path.'permission/mutate_role.dynamic.php');break;
    case 36: // get the save role page
        include_once($prc_path.'permission/save_role.processor.php');break;
    case 37: // get the delete role page
        include_once($prc_path.'permission/delete_role.processor.php');break;
    case 16: // get the edit template action
    case 19: // get the new template action
        include_once($action_path.'element/mutate_templates.dynamic.php');break;
    case 20: // get the save processor
        include_once($prc_path.'template/save_template.processor.php');break;
    case 21: // get the delete processor
        include_once($prc_path.'template/delete_template.processor.php');break;
    case 96: // get the duplicate template processor
        include_once($prc_path.'template/duplicate_template.processor.php');break;
    case 117:
        // change the tv rank for selected template
        include_once($action_path.'element/mutate_template_tv_rank.dynamic.php');break;
    case 22: // get the edit snippet action
    case 23: // get the new snippet action
        include_once($action_path.'element/mutate_snippet.dynamic.php');break;
    case 24: // get the save processor
        include_once($prc_path.'snippet/save_snippet.processor.php');break;
    case 25: // get the delete processor
        include_once($prc_path.'snippet/delete_snippet.processor.php');break;
    case 98: // get the duplicate processor
        include_once($prc_path.'snippet/duplicate_snippet.processor.php');break;
    case 78: // get the edit snippet action
    case 77: // get the new chunk action
        include_once($action_path.'element/mutate_htmlsnippet.dynamic.php');break;
    case 79: // get the save processor
        include_once($prc_path.'chunk/save_htmlsnippet.processor.php');break;
    case 80: // get the delete processor
        include_once($prc_path.'chunk/delete_htmlsnippet.processor.php');break;
    case 97: // get the duplicate processor
        include_once($prc_path.'chunk/duplicate_htmlsnippet.processor.php');break;
    case 18: // get the credits page
        include_once($action_path.'credits.static.php');break;
    case 26: // get the cache emptying processor
        include_once($action_path.'main/refresh_site.dynamic.php');break;
    case 106: // get module management
        include_once($action_path.'element/modules.static.php');break;
    case 107: // get the new modul
    case 108: // get the edit module action
        include_once($action_path.'element/mutate_module.dynamic.php');break;
    case 109: // get the save processor
        include_once($prc_path.'module/save_module.processor.php');break;
    case 110: // get the delete processor
        include_once($prc_path.'module/delete_module.processor.php');break;
    case 111: // get the duplicate processor
        include_once($prc_path.'module/duplicate_module.processor.php');break;
    case 112:
        // execute/run the module
        include_once($prc_path.'module/execute_module.processor.php');break;
    case 113: // get the module resources (dependencies) action
        include_once($action_path.'element/mutate_module_resources.dynamic.php');break;
    case 100: // change the plugin priority
        include_once($action_path.'element/mutate_plugin_priority.dynamic.php');break;
    case 101: // get the new plugin action
    case 102: // get the edit plugin action
        include_once($action_path.'element/mutate_plugin.dynamic.php');break;
    case 103: // get the save processor
        include_once($prc_path.'plugin/save_plugin.processor.php');break;
    case 104: // get the delete processor
        include_once($prc_path.'plugin/delete_plugin.processor.php');break;
    case 105: // get the duplicate processor
        include_once($prc_path.'plugin/duplicate_plugin.processor.php');break;
    case 127: // get review action
        include_once($action_path.'document/revision.dynamic.php');break;
    case 128: // save draft action
        include_once($prc_path.'document/save_draft_content.processor.php');break;
    case 129: // publish draft action
        include_once($prc_path.'document/publish_draft_content.processor.php');break;
    case 130: // delete draft action
        include_once($prc_path.'document/delete_draft_content.processor.php');break;
// view phpinfo
    case 200: // show phpInfo
        include_once($action_path.'report/phpinfo.static.php');break;
// errorpage
    case 29: // get the error page
        include_once($action_path.'error_dialog.static.php');break;
// file manager
    case 31: // get the page to manage files
        include_once($action_path.'element/files.dynamic.php');break;
    case 40: // access permissions
        include_once($action_path.'permission/access_permissions.dynamic.php');break;
    case 91:
        include_once($action_path.'permission/web_access_permissions.dynamic.php');break;
    case 41: // access groups processor
        include_once($prc_path.'permission/access_groups.processor.php');break;
    case 92:
        include_once($prc_path.'permission/web_access_groups.processor.php');break;
    case 17: // get the settings editor
        include_once($action_path.'tool/mutate_settings.dynamic.php');break;
    case 118: // call settings ajax include
        ob_clean();
        include_once "{$core_path}mutate_settings.ajax.php";break;
    case 30: // get the save settings processor
        include_once($prc_path.'save_settings.processor.php');break;
    case 53: // get the settings editor
        include_once($action_path.'report/sysinfo.static.php');break;
    case 54: // get the table optimizer/truncate processor
        include_once($prc_path.'db/optimize_table.processor.php');break;
    case 13: // view logging
        include_once($action_path.'report/logging.static.php');break;
    case 55: // get the settings editor
        include_once($prc_path.'db/empty_table.processor.php');break;
    case 64: // get the Recycle bin emptier
        include_once($prc_path.'document/remove_content.processor.php');break;
    case 10: // get the messages page
        include_once($action_path.'permission/messages.static.php');break;
    case 65: // get the message deleter
        include_once($prc_path.'pm/delete_message.processor.php');break;
    case 66: // get the message deleter
        include_once($prc_path.'pm/send_message.processor.php');break;
    case 67: // get the lock remover
        include_once($prc_path.'remove_locks.processor.php');break;
    case 70: // get the schedule page
        include_once($action_path.'report/site_schedule.static.php');break;
    case 71: // get the search page
        include_once($action_path.'main/search.static.php');break;
    case 59: // get the about page
        include_once($action_path.'about.static.php');break;
    case 75: // User management
        include_once($action_path.'permission/user_management.static.php');break;
    case 99:
        include_once($action_path.'permission/web_user_management.static.php');break;
    case 86:
        include_once($action_path.'permission/role_management.static.php');break;
    case 76: // template/ snippet management
        include_once($action_path.'element/resources.static.php');break;
    case 83: // Export to file
        include_once($action_path.'tool/export_site.static.php');break;
    case 84: // Resource Selector
        include_once($action_path.'element/resource_selector.static.php');break;
    case 93: // Backup Manager
        # header and footer will be handled interally
        include_once($action_path.'tool/bkmanager.static.php');break;
    case 305: // Backup Manager
        include_once($prc_path.'backup/restore.processor.php');break;
    case 307: // Backup Manager
        include_once($prc_path.'backup/snapshot.processor.php');break;
    case 94: // get the duplicate processor
        include_once($prc_path.'document/duplicate_content.processor.php');break;
    case 95: // Import Document from file
        include_once($action_path.'tool/import_site.static.php');break;
    case 9: // get the help page
        include_once($action_path.'tool/help.static.php');break;
              // Template Variables - Based on Apodigm's Docvars
    case 300: // get the new document variable action
    case 301: // get the edit document variable action
        include_once($action_path.'element/mutate_tmplvars.dynamic.php');break;
    case 302: // get the save processor
        include_once($prc_path.'tmplvars/save_tmplvars.processor.php');break;
    case 303: // get the delete processor
        include_once($prc_path.'tmplvars/delete_tmplvars.processor.php');break;
    case 304: // get the duplicate processor
        include_once($prc_path.'tmplvars/duplicate_tmplvars.processor.php');break;
    case 114: // Event viewer: show event message log
        include_once($action_path.'report/eventlog.dynamic.php');break;
    case 115: // get event log details viewer
        include_once($action_path.'report/eventlog_details.dynamic.php');break;
    case 116: // get the event log delete processor
        include_once($prc_path.'delete_eventlog.processor.php');break;
    case 501: //delete category
        include_once($prc_path.'delete_category.processor.php');break;
    case 998: //Output of OnManagerPageInit with Header/Footer
        if (is_array($evtOutOnMPI)) echo implode('', $evtOutOnMPI);break;
    case 999: //Output of OnManagerPageInit
        if (is_array($evtOutOnMPI)) echo implode('', $evtOutOnMPI);break;
    default : // default action: show not implemented message
        // say that what was requested doesn't do anything yet
        include_once($action_path.'header.inc.php');
        echo "
            <div class='section'>
            <div class='sectionHeader'>".$_lang['functionnotimpl']."</div>
            <div class='sectionBody'>
                <p>".$_lang['functionnotimpl_message']."</p>
            </div>
            </div>
        ";
        include_once($action_path.'footer.inc.php');
}

if(in_array($modx->manager->action,array(2,3,120,4,72,27,132,131,51,133,7,87,88,11,12,74,28,38,35,16,19,117,22,23,78,77,18,26,106,107,108,113,100,101,102,127,200,31,40,91,17,53,13,10,70,71,59,75,99,86,76,83,95,9,300,301,114,115,998)))
    include_once($action_path.'footer.inc.php');

// log action, unless it's a frame request
switch ($modx->manager->action) {
    case 1:
    case 7:
    case 2:
    case 998:
    case 999:
        break;
    default:
    include_once(MODX_CORE_PATH . 'log.class.inc.php');
    $log = new logHandler;
    $log->initAndWriteLog();
}

unset($_SESSION['itemname']); // clear this, because it's only set for logging purposes
