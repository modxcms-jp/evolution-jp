<?php
ini_set('display_errors', 1);
function setOption($fieldName, $value = '')
{
    $_SESSION[$fieldName] = $value;
    return $value;
}

function getOption($fieldName)
{
    return postv($fieldName,
        sessionv($fieldName,
            globalv($fieldName),
            false
        )
    );
}

function browser_lang()
{
    if (!serverv('HTTP_ACCEPT_LANGUAGE')) {
        return 'english';
    }
    $lc = substr(serverv('HTTP_ACCEPT_LANGUAGE'), 0, 2);
    if ($lc === 'ja') {
        return 'japanese-utf8';
    }
    if ($lc === 'ru') {
        return 'russian-utf8';
    }
    return 'english';
}

function includeLang($lang_name, $dir = 'langs/')
{
    global $_lang;

    $_lang = [];
    $lang_name = str_replace('\\', '/', $lang_name ?? '');
    if (strpos($lang_name, '/') !== false) {
        require_once(MODX_SETUP_PATH . 'langs/english.inc.php');
    } elseif (is_file(MODX_SETUP_PATH . $dir . $lang_name . '.inc.php')) {
        require_once(MODX_SETUP_PATH . $dir . $lang_name . '.inc.php');
    } else {
        require_once(MODX_SETUP_PATH . $dir . 'english.inc.php');
    }
    return $_lang;
}

function key_field($category = '')
{
    if ($category === 'template') {
        return 'templatename';
    }
    return 'name';
}

function table_name($category = '')
{
    $table_names = [
        'template' => 'site_templates',
        'tv'       => 'site_tmplvars',
        'chunk'    => 'site_htmlsnippets',
        'snippet'  => 'site_snippets',
        'plugin'   => 'site_plugins',
        'module'   => 'site_modules'
    ];

    return $table_names[$category] ?? '';
}

function mode($category)
{
    if ($category === 'template') {
        return 'desc_compare';
    }
    if ($category === 'tv') {
        return 'desc_compare';
    }
    if ($category === 'chunk') {
        return 'name_compare';
    }
    return 'version_compare';
}

function compare_check($params)
{
    $category = $params['category'] ?? '';

    $where = array(
        sprintf("`%s`='%s'", key_field($category), $params['name'])
    );
    if ($category === 'plugin') {
        $where[] = " AND `disabled`='0'";
    }

    $rs = db()->select('*', "[+prefix+]" . table_name($category), $where);
    if (!$rs) {
        return 'no exists';
    }

    if (mode($category) === 'name_compare') {
        return 'same';
    }

    $row = db()->getRow($rs);

    if (mode($category) === 'version_compare') {
        $old_version = strip_tags(
            substr($row['description'], 0, strpos($row['description'], '</strong>'))
        );
        if ($params['version'] === $old_version) {
            return 'same';
        }
        return 'diff';
    }

    if ($params['version']) {
        $new_desc = sprintf('<strong>%s</strong> ', $params['version']) . $params['description'];
    } else {
        $new_desc = $params['description'];
    }

    if ($row['description'] === $new_desc) {
        return 'same';
    }

    return 'diff';
}

function parse_docblock($fullpath)
{
    $params = [];
    if (!is_readable($fullpath)) {
        return false;
    }

    $tpl = @fopen($fullpath, 'r');
    if (!$tpl) {
        return false;
    }

    $docblock_start_found = false;

    while (!feof($tpl)) {
        $line = fgets($tpl);
        if (!$docblock_start_found) {    // find docblock start
            if (strpos($line, '/**') !== false) {
                $docblock_start_found = true;
            }
            continue;
        }

        if (!isset($params['name'])) {    // find name
            $name = getString($line);
            if ($name) {
                $params['name'] = $name;
            }
            continue;
        }

        if (!isset($params['description'])) {    // find description
            $description = getString($line);
            if ($description) {
                $params['description'] = $description;
            }
        }

        $ma = [];
        if (preg_match("/^\s+\*\s+@([^\s]+)\s+(.+)/", $line, $ma)) {
            $param = trim($ma[1]);
            $val = trim($ma[2]);

            if (!$param || !$val) {
                continue;
            }

            if ($param === 'internal') {
                $ma = null;
                if (preg_match("/@([^\s]+)\s+(.+)/", $val, $ma)) {
                    $param = trim($ma[1]);
                    $val = trim($ma[2]);
                }
                if (!$param) {
                    continue;
                }
            }

            $params[$param] = $val;

            if (!isset($params['description'])) {
                $params['description'] = '';
            }

            continue;
        }

        if (preg_match("/^\s*\*\/\s*$/", $line)) {
            break;
        }
    }
    @fclose($tpl);
    return $params;
}

function getString($line) {
    if (!preg_match("/^\s+\*\s+([^@].+)/", $line, $ma)) {
        return null;
    }
    return trim($ma[1]);
}

function clean_up($table_prefix)
{
    $ids = [];

    // secure web documents - privateweb
    db()->query("UPDATE `" . $table_prefix . "site_content` SET privateweb = 0 WHERE privateweb = 1");
    $sql = "SELECT DISTINCT sc.id
            FROM `" . $table_prefix . "site_content` sc
            LEFT JOIN `" . $table_prefix . "document_groups` dg ON dg.document = sc.id
            LEFT JOIN `" . $table_prefix . "webgroup_access` wga ON wga.documentgroup = dg.document_group
            WHERE wga.id>0";
    $rs = db()->query($sql);
    if (!$rs) {
        echo sprintf(
            'An error occurred while executing a query: <div>%s</div><div>%s</div>',
            $sql,
            db()->getLastError()
        );
    } else {
        while ($row = db()->getRow($rs)) $ids[] = $row["id"];
        if (count($ids) > 0) {
            db()->query(
                sprintf(
                    'UPDATE `%ssite_content` SET privateweb = 1 WHERE id IN (%s)',
                    $table_prefix,
                    implode(', ', $ids)
                )
            );
            unset($ids);
        }
    }

    // secure manager documents privatemgr
    db()->query(sprintf('UPDATE `%ssite_content` SET privatemgr = 0 WHERE privatemgr = 1', $table_prefix));
    $sql = sprintf(
        'SELECT DISTINCT sc.id
            FROM `%ssite_content` sc
            LEFT JOIN `%sdocument_groups` dg ON dg.document = sc.id
            LEFT JOIN `%smembergroup_access` mga ON mga.documentgroup = dg.document_group
            WHERE mga.id>0',
        $table_prefix,
        $table_prefix,
        $table_prefix
    );
    $rs = db()->query($sql);
    if (!$rs) {
        echo sprintf(
            'An error occurred while executing a query: <div>%s</div><div>%s</div>',
            $sql,
            db()->getLastError()
        );
    } else {
        while ($row = db()->getRow($rs)) {
            $ids[] = $row['id'];
        }

        if (count($ids) > 0) {
            $ids = implode(', ', $ids);
            db()->query(
                sprintf(
                    'UPDATE `%ssite_content` SET privatemgr = 1 WHERE id IN (%s)',
                    $table_prefix,
                    $ids
                )
            );
            unset($ids);
        }
    }
}

// Property Update function
function propUpdate($new, $old)
{
    // Split properties up into arrays
    $returnArr = [];
    $newArr = explode('&', $new);
    $oldArr = explode('&', $old);

    foreach ($newArr as $k => $v) {
        if ($v) {
            $tempArr = explode('=', trim($v));
            $returnArr[$tempArr[0]] = $tempArr[1];
        }
    }
    foreach ($oldArr as $k => $v) {
        if ($v) {
            $tempArr = explode('=', trim($v));
            $returnArr[$tempArr[0]] = $tempArr[1];
        }
    }

    // Make unique array
    $returnArr = array_unique($returnArr);

    // Build new string for new properties value
    $return = '';
    foreach ($returnArr as $k => $v) {
        $return .= sprintf('&%s=%s ', $k, $v);
    }
    return $return;
}

function getCreateDbCategory($category)
{
    if (!$category) {
        return 0;
    }

    $dbv_category = db()->getObject(
        'categories',
        sprintf("category='%s'", db()->escape($category))
    );
    if ($dbv_category) {
        return $dbv_category->id;
    }
    $category_id = db()->insert(
        array('category' => db()->escape($category)),
        '[+prefix+]categories'
    );
    if (!$category_id) {
        exit('Get category id error');
    }
    return $category_id;
}

function is_webmatrix()
{
    return isset($_SERVER['WEBMATRIXMODE']) ? true : false;
}

function is_iis()
{
    return strpos($_SERVER['SERVER_SOFTWARE'], 'IIS') ? true : false;
}

function isUpGradeable()
{
    error_reporting(E_ALL & ~E_NOTICE);
    $conf_path = MODX_BASE_PATH . 'manager/includes/config.inc.php';
    if (!is_file($conf_path)) {
        return 0;
    }

    if (sessionv('is_upgradeable') !== null) {
        return sessionv('is_upgradeable');
    }


    $dbase = null;
    $database_server = null;
    $database_user = null;
    $database_password = null;
    $table_prefix = null;
    $database_connection_charset = null;
    include($conf_path);

    if (!$dbase) {
    	sessionv('*is_upgradeable', 0);
        return 0;
    }

    global $modx;
    $modx->db->hostname = $database_server;
    $modx->db->username = $database_user;
    $modx->db->password = $database_password;
    $modx->db->dbname = trim($dbase, '`');
    $modx->db->charset = $database_connection_charset;
    $modx->db->table_prefix = $table_prefix;
    db()->connect();

    if (db()->isConnected() && db()->tableExists('[+prefix+]system_settings')) {
        sessionv('*database_server', $database_server);
        sessionv('*database_user', $database_user);
        sessionv('*database_password', $database_password);
        sessionv('*dbase', $modx->db->dbname);
        sessionv('*table_prefix', $table_prefix);
        $collation = db()->getCollation();
        sessionv('*database_charset', substr($collation, 0, strpos($collation, '_')));
        sessionv('*database_collation', $collation);
        sessionv('*database_connection_method', 'SET CHARACTER SET');
        sessionv('*is_upgradeable', 1);
        return 1;
    }
    sessionv('*is_upgradeable', 0);
    return 0;
}

function parseProperties($propertyString)
{
    if (!$propertyString) {
        return [];
    }

    $tmpParams = explode('&', $propertyString);
    $parameter = [];
    foreach ($tmpParams as $xValue) {
        if (strpos($xValue, '=', 0)) {
            $pTmp = explode('=', $xValue);
            $pvTmp = explode(';', trim($pTmp[1]));
            if ($pvTmp[1] === 'list' && $pvTmp[3] != '') {
                $parameter[trim($pTmp[0])] = $pvTmp[3]; //list default
            } elseif ($pvTmp[1] !== 'list' && $pvTmp[2] != '') {
                $parameter[trim($pTmp[0])] = $pvTmp[2];
            }
        }
    }
    return $parameter;
}

function result($status = 'ok', $ph = [])
{
    global $modx;

    $ph['status'] = $status;
    if ($ph['name']) {
        $ph['name'] = sprintf('&nbsp;&nbsp;%s : ', $ph['name']);
    } else {
        $ph['name'] = '';
    }
    if (!isset($ph['msg'])) {
        $ph['msg'] = '';
    }
    $tpl = '<p>[+name+]<span class="[+status+]">[+msg+]</span></p>';
    return $modx->parseText($tpl, $ph);
}

function get_langs()
{
    $langs = [];
    foreach (glob('langs/*.inc.php') as $path) {
        if (substr($path, 6, 1) === '.') continue;
        $langs[] = substr($path, 6, strpos($path, '.inc.php') - 6);
    }
    sort($langs);
    return $langs;
}

function get_lang_options($lang_name)
{
    $langs = get_langs();

    foreach ($langs as $lang) {
        $abrv_language = explode('-', $lang);
        $option[] = sprintf(
            '<option value="%s" %s>%s</option>',
            $lang,
            $lang == $lang_name ? 'selected="selected"' : '',
            ucwords($abrv_language[0])
        );
    }
    return "\n" . implode("\n", $option);
}

function collectTpls($path)
{
    $files1 = glob($path . '*/*.install_base.tpl');
    $files2 = glob($path . '*.install_base.tpl');
    $files = array_merge((array)$files1, (array)$files2);
    natcasesort($files);

    return $files;
}

function ph()
{
    global $cmsName, $cmsVersion, $modx_textdir, $modx_release_date;

    $ph['site_url'] = MODX_SITE_URL;
    $ph['pagetitle'] = lang('modx_install');
    $ph['textdir'] = ($modx_textdir && $modx_textdir === 'rtl') ? ' id="rtl"' : '';
    $ph['help_link'] = !sessionv('is_upgradeable') ? lang('help_link_new') : lang('help_link_upd');
    $ph['version'] = $cmsName . ' ' . $cmsVersion;
    $ph['release_date'] = ($modx_textdir && $modx_textdir === 'rtl' ? '&rlm;' : '') . $modx_release_date;
    $ph['footer1'] = str_replace('[+year+]', date('Y'), lang('modx_footer1'));
    $ph['footer2'] = lang('modx_footer2');
    return $ph;
}

function install_sessionCheck()
{
    $_SESSION['test'] = 1;

    if (!isset($_SESSION['test']) || $_SESSION['test'] != 1) {
        return false;
    }
    return true;
}

function getLast($array = [])
{
    $array = (array)$array;
    return end($array);
}

function lang_name()
{
    if (postv('install_language')) {
        sessionv('*install_language', postv('install_language'));
        return postv('install_language');
    }

    return sessionv('install_language', browser_lang());
}

function withSample($installset)
{
    if (sessionv('is_upgradeable')) {
        return false;
    }
    if (!sessionv('installdata')) {
        return false;
    }
    if (!in_array('sample', $installset)) {
        return false;
    }
    return true;
}

function convert2utf8mb4() {
    include MODX_SETUP_PATH . 'convert2utf8mb4.php';
    $convert = new convert2utf8mb4();

    if (!$convert->isAvailable()) {
        echo "<p>'utf8mb4 is not available.'</p>";
        return;
    }

    $charset = $convert->getDefaultCharset();
    if (!$charset) {
        echo "<p>'Database default charset is not available.'</p>";
        return;
    }

    $collation = db()->getCollation();
    echo "<p>tableのcollationをutf8mb4_general_ciに変換します。</p>";
    if ($collation !== 'utf8mb4_general_ci') {
        $convert->convertDb();
    }

    $count = $convert->convertTablesWithPrefix(sessionv('table_prefix', 'modx_'));
    if ($count) {
        echo sprintf(
            "<p>Database and tables collation have been changed to utf8mb4_general_ci. %d tables have been converted.</p>",
            $count
        );
    } else {
        echo "<p>utf8mb4_general_ciに変換されたテーブルはありません。</p>";
    }

    $convert->updateConfigIncPhp();
    echo "<p>config.inc.php has been updated.</p>";
}
