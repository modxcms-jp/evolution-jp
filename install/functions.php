<?php
ini_set('display_errors',1);
function setOption($fieldName,$value='') {
    $_SESSION[$fieldName] = $value;
    return $value;
}

function getOption($fieldName) {
    if(isset($_POST[$fieldName]) &&    $_POST[$fieldName]!=='')        $rs = $_POST[$fieldName];
    elseif(isset($_SESSION[$fieldName]) && $_SESSION[$fieldName]!=='') $rs = $_SESSION[$fieldName];
    elseif(isset($GLOBALS[$fieldName])  && $GLOBALS[$fieldName]!=='')  $rs = $GLOBALS[$fieldName];
    else $rs = false;
    
    return $rs;
}

function autoDetectLang() {
    if(!isset($_SERVER['HTTP_ACCEPT_LANGUAGE']) || empty($_SERVER['HTTP_ACCEPT_LANGUAGE']))
        return 'english';
    $lc = substr($_SERVER['HTTP_ACCEPT_LANGUAGE'], 0, 2);
    switch($lc)
    {
        case 'ja': $lang = 'japanese-utf8'; break;
        case 'ru': $lang = 'russian-utf8' ; break;
        default  : $lang = 'english'      ;
    }
    return $lang;
}

function includeLang($lang_name, $dir='langs/') {
    global $_lang;
    
    # load language file
    $_lang = array ();
    $lang_name = str_replace('\\','/',$lang_name);
    if(strpos($lang_name,'/')!==false) {
         require_once('langs/english.inc.php');
    }
    elseif(is_file("{$dir}{$lang_name}.inc.php")) {
         require_once("{$dir}{$lang_name}.inc.php");
    }
    else require_once("{$dir}english.inc.php");
}

function compare_check($params) {
    global $modx;
    
    $name        = $params['name'];
    $name_field  = 'name';
    $mode        = 'version_compare';
    
    if($params['version']) $new_version = $params['version'];
    
    switch($params['category']) {
        case 'template':
            $tableName  = 'site_templates';
            $name_field = 'templatename';
            $mode       = 'desc_compare';
            break;
        case 'tv':
            $tableName = 'site_tmplvars';
            $mode      = 'desc_compare';
            break;
        case 'chunk':
            $tableName = 'site_htmlsnippets';
            $mode      = 'name_compare';
            break;
        case 'snippet':
            $tableName = 'site_snippets';
            break;
        case 'plugin':
            $tableName = 'site_plugins';
            break;
        case 'module':
            $tableName = 'site_modules';
            break;
    }
    
    $where = "`{$name_field}`='{$name}'";
    if($params['category']=='plugin') $where .= " AND `disabled`='0'";
    
    $rs = $modx->db->select('*', "[+prefix+]{$tableName}", $where);
    if(!$rs)
        return sprintf('An error occurred while executing a query: <div>%s</div><div>%s</div>',$sql,$modx->db->getLastError());
    else
    {
        $count = $modx->db->getRecordCount($rs);
        if($count==1)
        {
            $row = $modx->db->getRow($rs);
            $new_version_str = ($new_version) ? '<strong>' . $new_version . '</strong> ':'';
            $new_desc    = $new_version_str . $params['description'];
            $old_desc    = $row['description'];
            $old_version = substr($old_desc,0,strpos($old_desc,'</strong>'));
            $old_version = strip_tags($old_version);
            if($mode == 'version_compare' && $old_version === $new_version)
            {
                                            $result = 'same';
            }
            elseif($mode == 'name_compare') $result = 'same';
            elseif($old_desc === $new_desc) $result = 'same';
            else                            $result = 'diff';
        }
        elseif($count < 1)                  $result = 'no exists';
    }
    
    return $result;
}

function parse_docblock($fullpath) {
    $params = array();
    if(!is_readable($fullpath)) return false;
    
    $tpl = @fopen($fullpath, 'r');
    if(!$tpl)                   return false;
    
    $docblock_start_found = false;
    $name_found           = false;
    $description_found    = false;
    
    while(!feof($tpl))
    {
        $line = fgets($tpl);
        if(!$docblock_start_found)
        {    // find docblock start
            if(strpos($line, '/**') !== false) $docblock_start_found = true;
            continue;
        }
        elseif(!$name_found)
        {    // find name
            $ma = null;
            if(preg_match("/^\s+\*\s+(.+)/", $line, $ma))
            {
                $params['name'] = trim($ma[1]);
                $name_found = !empty($params['name']);
            }
            continue;
        }
        elseif(!$description_found)
        {    // find description
            $ma = null;
            if(preg_match("/^\s+\*\s+(.+)/", $line, $ma))
            {
                $params['description'] = trim($ma[1]);
                $description_found = !empty($params['description']);
            }
            continue;
        }
        else
        {
            $ma = null;
            if(preg_match("/^\s+\*\s+\@([^\s]+)\s+(.+)/", $line, $ma))
            {
                $param = trim($ma[1]);
                $val   = trim($ma[2]);
                if(!empty($param) && !empty($val))
                {
                    if($param == 'internal')
                    {
                        $ma = null;
                        if(preg_match("/\@([^\s]+)\s+(.+)/", $val, $ma))
                        {
                            $param = trim($ma[1]);
                            $val = trim($ma[2]);
                        }
                        if(empty($param)) continue;
                    }
                    $params[$param] = $val;
                }
            }
            elseif(preg_match("/^\s*\*\/\s*$/", $line))
            {
                break;
            }
        }
    }
    @fclose($tpl);
    return $params;
}

function clean_up($sqlParser) {
    global $modx;
    
    $ids = array();
    $mysqlVerOk = -1;
    
    $table_prefix = $sqlParser->prefix;
    
    $mysqlVerOk = (version_compare($modx->db->getVersion(),"5.0.0")>=0);
    
    // secure web documents - privateweb 
    $modx->db->query("UPDATE `{$table_prefix}site_content` SET privateweb = 0 WHERE privateweb = 1");
    $sql =  "SELECT DISTINCT sc.id 
             FROM `{$table_prefix}site_content` sc
             LEFT JOIN `{$table_prefix}document_groups` dg ON dg.document = sc.id
             LEFT JOIN `{$table_prefix}webgroup_access` wga ON wga.documentgroup = dg.document_group
             WHERE wga.id>0";
    $rs = $modx->db->query($sql);
    if(!$rs)
    {
        echo sprintf('An error occurred while executing a query: <div>%s</div><div>%s</div>',$sql,$modx->db->getLastError());
    }
    else {
        while($row = $modx->db->getRow($rs)) $ids[]=$row["id"];
        if(count($ids)>0) {
            $modx->db->query("UPDATE `{$table_prefix}site_content` SET privateweb = 1 WHERE id IN (".implode(", ",$ids).")");    
            unset($ids);
        }
    }
    
    // secure manager documents privatemgr
    $modx->db->query("UPDATE `{$table_prefix}site_content` SET privatemgr = 0 WHERE privatemgr = 1");
    $sql =  "SELECT DISTINCT sc.id 
             FROM `{$table_prefix}site_content` sc
             LEFT JOIN `{$table_prefix}document_groups` dg ON dg.document = sc.id
             LEFT JOIN `{$table_prefix}membergroup_access` mga ON mga.documentgroup = dg.document_group
             WHERE mga.id>0";
    $rs = $modx->db->query($sql);
    if(!$rs)
    {
        echo sprintf('An error occurred while executing a query: <div>%s</div><div>%s</div>',$sql,$modx->db->getLastError());
    }
    else
    {
        while($row = $modx->db->getRow($rs))
        {
            $ids[] = $row['id'];
        }
        
        if(count($ids)>0) {
            $ids = join(', ',$ids);
            $modx->db->query("UPDATE `{$table_prefix}site_content` SET privatemgr = 1 WHERE id IN ({$ids})");    
            unset($ids);
        }
    }
}

// Property Update function
function propUpdate($new,$old)
{
    // Split properties up into arrays
    $returnArr = array();
    $newArr = explode('&',$new);
    $oldArr = explode('&',$old);
    
    foreach ($newArr as $k => $v)
    {
        if(!empty($v))
        {
            $tempArr = explode('=',trim($v));
            $returnArr[$tempArr[0]] = $tempArr[1];
        }
    }
    foreach ($oldArr as $k => $v)
    {
        if(!empty($v))
        {
            $tempArr = explode('=',trim($v));
            $returnArr[$tempArr[0]] = $tempArr[1];
        }
    }
    
    // Make unique array
    $returnArr = array_unique($returnArr);
    
    // Build new string for new properties value
    foreach ($returnArr as $k => $v)
    {
        $return .= "&{$k}={$v} ";
    }
    global $modx;
    return $modx->db->escape($return);
}

function getCreateDbCategory($category) {
    global $modx;
    
    $category_id = 0;
    if(!empty($category)) {
        $category = $modx->db->escape($category);
        $dbv_category = $modx->db->getObject('categories', "category='{$category}'");
        if($dbv_category) $category_id = $dbv_category->id;
        else
        {
            $category_id = $modx->db->insert(array('category'=>$category), '[+prefix+]categories');
            if(!$category_id) exit('Get category id error');
        }
    }
    return $category_id;
}

function is_webmatrix()
{
    return (isset($_SERVER['WEBMATRIXMODE'])) ? true : false;
}

function is_iis()
{
    return (strpos($_SERVER['SERVER_SOFTWARE'],'IIS')) ? true : false;
}

function isUpGrade()
{
    global $modx,$base_path;
    
    $conf_path = "{$base_path}manager/includes/config.inc.php";
    if (!is_file($conf_path)) return 0;
    
    include($conf_path);
    error_reporting(E_ALL & ~E_NOTICE);
    
    if(!isset($dbase) || empty($dbase)) return 0;
    
    $modx->db->hostname     = $database_server;
    $modx->db->username     = $database_user;
    $modx->db->password     = $database_password;
    $modx->db->dbname       = $dbase;
    $modx->db->charset      = $database_connection_charset;
    $modx->db->table_prefix = $table_prefix;
    $modx->db->connect();
    
    if($modx->db->isConnected() && $modx->db->table_exists('[+prefix+]system_settings')) {
        $collation = $modx->db->getCollation();
        $_SESSION['database_server']            = $database_server;
        $_SESSION['database_user']              = $database_user;
        $_SESSION['database_password']          = $database_password;
        $_SESSION['dbase']                      = trim($dbase,'`');
        $_SESSION['database_charset']           = substr($collation,0,strpos($collation,'_'));
        $_SESSION['database_collation']         = $collation;
        $_SESSION['database_connection_method'] = 'SET CHARACTER SET';
        $_SESSION['table_prefix']               = $table_prefix;
        return 1;
    }
    else
        return 0;
}

function parseProperties($propertyString)
{
    $parameter= array ();
    if (!empty($propertyString))
    {
        $tmpParams= explode('&', $propertyString);
        for ($x= 0; $x < count($tmpParams); $x++)
        {
            if (strpos($tmpParams[$x], '=', 0))
            {
                $pTmp= explode('=', $tmpParams[$x]);
                $pvTmp= explode(';', trim($pTmp[1]));
                if ($pvTmp[1] == 'list' && $pvTmp[3] != '')
                {
                    $parameter[trim($pTmp[0])]= $pvTmp[3]; //list default
                }
                elseif ($pvTmp[1] != 'list' && $pvTmp[2] != '')
                {
                    $parameter[trim($pTmp[0])]= $pvTmp[2];
                }
            }
        }
    }
    return $parameter;
}

function result($status='ok',$ph=array())
{
    global $modx;
    
    $ph['status'] = $status;
    $ph['name']   = ($ph['name']) ? "&nbsp;&nbsp;{$ph['name']} : " : '';
    if(!isset($ph['msg'])) $ph['msg'] = '';
    $tpl = '<p>[+name+]<span class="[+status+]">[+msg+]</span></p>';
    return $modx->parseText($tpl,$ph);
}

function get_langs()
{
    $langs = array();
    foreach(glob('langs/*.inc.php') as $path)
    {
        if(substr($path,6,1)==='.') continue;
        $langs[] = substr($path,6,strpos($path,'.inc.php')-6);
    }
    sort($langs);
    return $langs;
}

function get_lang_options($lang_name)
{
    $langs = get_langs();
    
    foreach ($langs as $lang)
    {
        $abrv_language = explode('-',$lang);
        $option[] = '<option value="' . $lang . '"'. (($lang == $lang_name) ? ' selected="selected"' : null) .'>' . ucwords($abrv_language[0]). '</option>';
    }
    return "\n" . join("\n",$option);
}

function collectTpls($path)
{
    $files1 = glob("{$path}*/*.install_base.tpl");
    $files2 = glob("{$path}*.install_base.tpl");
    $files = array_merge((array)$files1,(array)$files2);
    natcasesort($files);
    
    return $files;
}

function ph()
{
    global $_lang,$cmsName,$cmsVersion,$modx_textdir,$modx_release_date;

    $ph['pagetitle']     = $_lang['modx_install'];
    $ph['textdir']       = ($modx_textdir && $modx_textdir==='rtl') ? ' id="rtl"':'';
    $ph['help_link']     = $_SESSION['installmode'] == 0 ? $_lang['help_link_new'] : $_lang['help_link_upd'];
    $ph['version']       = $cmsName.' '.$cmsVersion;
    $ph['release_date']  = (($modx_textdir && $modx_textdir==='rtl') ? '&rlm;':'') . $modx_release_date;
    $ph['footer1']       = str_replace('[+year+]', date('Y'), $_lang['modx_footer1']);
    $ph['footer2']       = $_lang['modx_footer2'];
    return $ph;
}

function install_sessionCheck()
{
    global $_lang;
    
    $_SESSION['test'] = 1;
    
    if(!isset($_SESSION['test']) || $_SESSION['test']!=1) return false;
    else return true;
}

function getLast($array=array()) {
    $array = (array) $array;
    return end($array);
}
