<?php
if (!isset($modx) || !evo()->isLoggedin()) {
    exit;
}
if (!evo()->hasPermission('exec_module')) {
    alert()->setError(3);
    alert()->dumpError();
}

if (isset($_REQUEST['id'])) {
    $id = (int)$_REQUEST['id'];
} else {
    $id = 0;
}

$tbl_site_module_access = evo()->getFullTableName('site_module_access');
$tbl_member_groups = evo()->getFullTableName('member_groups');
$tbl_site_modules = evo()->getFullTableName('site_modules');

// make sure the id's a number
if (!is_numeric($id)) {
    echo "Passed ID is NaN!";
    exit;
}

// check if user has access permission, except admins
if (!manager()->isAdmin()) {
    $userid = evo()->getLoginUserID();
    $sql = sprintf("SELECT sma.usergroup,mg.member FROM %s sma LEFT JOIN %s mg ON mg.user_group = sma.usergroup AND member='%s'WHERE sma.module = '%s'",
        $tbl_site_module_access,
        $tbl_member_groups,
        $userid,
        $id
    );
    db()->query($sql);

    //initialize permission to -1, if it stays -1 no permissions
    //attached so permission granted
    $permissionAccessInt = -1;

    while ($row = db()->getRow()) {
        if ($row['usergroup'] && $row["member"]) {
            //if there are permissions and this member has permission, ofcourse
            //this is granted
            $permissionAccessInt = 1;
        } elseif ($permissionAccessInt == -1) {
            //if there are permissions but this member has no permission and the
            //variable was still in init state we set permission to 0; no permissions
            $permissionAccessInt = 0;
        }
    }

    if ($permissionAccessInt == 0) {
        echo "<script type='text/javascript'>" .
            "function jsalert(){ alert('You do not sufficient privileges to execute this module.');" .
            "window.location.href='index.php?a=106';}" .
            "setTimeout('jsalert()',100)" .
            "</script>";
        exit;
    }
}

$rs = db()->select('*', $tbl_site_modules, "id='" . $id . "'");
$limit = db()->count($rs);
if ($limit > 1) {
    echo "<script type='text/javascript'>" .
        "function jsalert(){ alert('Multiple modules sharing same unique id $id. Please contact the Site Administrator');" .
        "window.location.href='index.php?a=106';}" .
        "setTimeout('jsalert()',100)" .
        "</script>";
    exit;
}
if ($limit < 1) {
    echo "<script type='text/javascript'>" .
        "function jsalert(){ alert('No record found for id $id');" .
        "window.location.href='index.php?a=106';}" .
        "setTimeout('jsalert()',100)" .
        "</script>";
    exit;
}
$content = db()->getRow($rs);
$modx->moduleObject = $content;
if ($content['disabled']) {
    echo "<script type='text/javascript'>" .
        "function jsalert(){ alert('This module is disabled and cannot be executed.');" .
        "window.location.href='index.php?a=106';}" .
        "setTimeout('jsalert()',100)" .
        "</script>";
    exit;
}

// load module configuration
$parameter = [];
if (!empty($content["properties"])) {
    $tmpParams = explode("&", $content["properties"]);
    for ($x = 0, $xMax = count($tmpParams); $x < $xMax; $x++) {
        $pTmp = explode("=", $tmpParams[$x], 2);
        if (count($pTmp) < 2) {
            continue;
        }

        $paramName = trim($pTmp[0]);
        $paramValue = trim($pTmp[1]);
        if ($paramName === '' || $paramValue === '') {
            continue;
        }

        $pvTmp = explode(";", $paramValue);
        $pvTmp = array_pad($pvTmp, 4, '');

        if ($pvTmp[1] === 'list' && $pvTmp[3] !== "") {
            $parameter[$paramName] = $pvTmp[3];
        } elseif ($pvTmp[1] !== 'list' && $pvTmp[2] !== "") {
            $parameter[$paramName] = $pvTmp[2];
        }
    }
}

// Set the item name for logger
$_SESSION['itemname'] = $content['name'];

$output = evalModule($content["modulecode"], $parameter);
echo $output;
include(MODX_CORE_PATH . 'sysalert.display.inc.php');

// evalModule
function evalModule($moduleCode, $params)
{
    global $modx;
    $modx->event->params = &$params; // store params inside event object
    if (is_array($params)) {
        extract($params, EXTR_SKIP);
    }
    ob_start();
    $moduleCode = trim($moduleCode);
    if (substr($moduleCode, 0, 5) === '<?php') {
        $moduleCode = substr($moduleCode, 5);
    }
    if (substr($moduleCode, -2) === '?>') {
        $moduleCode = substr($moduleCode, 0, -2);
    }
    $mod = eval($moduleCode);
    $msg = ob_get_clean();
    if (error_get_last()) {
        $error_info = error_get_last();
        switch ($error_info['type']) {
            case E_NOTICE :
            case E_USER_NOTICE :
                $error_level = 1;
                break;
            case E_DEPRECATED :
            case E_USER_DEPRECATED :
                $error_level = 2;
                break;
            default:
                $error_level = 99;
        }
        if ($modx->config['error_reporting'] === '99' || 2 < $error_level) {
            extract($error_info);
            $result = $modx->messageQuit('PHP Parse Error', '', true, $type, $file, $content['name'] . ' - Module',
                $text, $line, $msg);
            $modx->event->alert("An error occurred while loading. Please see the event log for more information<p>{$msg}</p>");
        }
    }
    unset($modx->event->params);
    return $mod . $msg;
}
