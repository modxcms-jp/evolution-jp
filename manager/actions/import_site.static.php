<?php
if(IN_MANAGER_MODE!="true") die("<b>INCLUDE_ORDERING_ERROR</b><br /><br />Please use the MODx Content Manager instead of accessing this file directly.");
if(!$modx->hasPermission('import_static'))
{
    $e->setError(3);
    $e->dumpError();
}

// Files to upload
$allowedfiles = array('html','htm','shtml','xml');
?>

<script type="text/javascript">
    parent.tree.ca = "parent";
    function setParent(pId, pName) {
        document.importFrm.parent.value=pId;
        document.getElementById('parentName').innerHTML = pId + " (" + pName + ")";
    }
    function reloadTree() {
        // redirect to welcome
        document.location.href = "index.php?r=1&a=7";
    }
</script>

<h1><?php echo $_lang['import_site_html']; ?></h1>

<div class="sectionBody">
<?php

if(!isset($_POST['import'])) {
    echo "<p>".$_lang['import_site_message']."</p>";
?>

<fieldset style="padding:10px"><legend><?php echo $_lang['import_site']; ?></legend>
<form action="index.php" method="post" name="importFrm">
<input type="hidden" name="import" value="import" />
<input type="hidden" name="a" value="95" />
<input type="hidden" name="parent" value="0" />
<table border="0" cellspacing="0" cellpadding="2">
  <tr>
    <td nowrap="nowrap"><b><?php echo $_lang['import_parent_resource']; ?></b></td>
    <td>&nbsp;</td>
    <td><b><span id="parentName">0 (<?php echo $site_name; ?>)</span></b></td>
  </tr>
  <tr>
    <td nowrap="nowrap" valign="top"><b><?php echo $_lang['import_site_maxtime']; ?></b></td>
    <td>&nbsp;</td>
    <td><input type="text" name="maxtime" value="30" />
        <br />
        <small><?php echo $_lang['import_site_maxtime_message']; ?></small>
    </td>
  </tr>
  <tr>
	<td nowrap="nowrap" valign="top"><b>サイトツリーをリセットする</b></td>
    <td>&nbsp;</td>
    <td><input type="checkbox" name="reset" value="on" />
        <br />
		<small>サイトツリー上のリソースを全削除してからインポートします。リソースIDも初期化されます。</small>
    </td>
  </tr>
  <tr>
    <td nowrap="nowrap" valign="top"><b>インポート対象</b></td>
    <td>&nbsp;</td>
    <td>
    <input type="radio" name="object" value="body" checked="checked" /> body要素のみ
    <input type="radio" name="object" value="all" /> htmlファイルまるごと
        <br />
    </td>
  </tr>
</table>
<ul class="actionButtons">
    <li><a href="#" onclick="document.importFrm.submit();"><img src="<?php echo $_style["icons_save"] ?>" /> <?php echo $_lang["import_site_start"]; ?></a></li>
</ul>
</form>
</fieldset>

<?php
}
else
{
    $maxtime = $_POST['maxtime'];
	if(!is_numeric($maxtime))
	{
        $maxtime = 30;
    }

    @set_time_limit($maxtime);
    $mtime = microtime(); $mtime = explode(" ",$mtime); $mtime = $mtime[1] + $mtime[0]; $importstart = $mtime;

	if ($_POST['reset']=='on')
	{
		$modx->db->query('DELETE FROM ' . $modx->getFullTableName('site_content'));
		$modx->db->query('ALTER TABLE ' . $modx->getFullTableName('site_content') . ' AUTO_INCREMENT = 1');
	}

    $parent = intval($_POST['parent']);
	$filepath = MODX_BASE_PATH . 'assets/import/';
    $filesfound = 0;

    $files = getFiles($filepath);
//	echo '<pre>';
//	print_r($files);
//	echo '</pre>';

uasort($files, 'cmp');
//	echo '<hr /><pre>';
//	print_r($files);
//	echo '</pre>';

    // no. of files to import
    printf('<p>' . $_lang['import_files_found'] . '</p>', $filesfound);

    // import files
	if(count($files)>0)
	{
		$sql = 'UPDATE ' . $modx->getFullTableName('site_content')
               . ' SET isfolder=1 WHERE id=' . $parent . ';';
					$rs = $modx->db->query($sql);
		importFiles($parent,$filepath,$files,'root');
    }

    $mtime = microtime(); $mtime = explode(" ",$mtime); $mtime = $mtime[1] + $mtime[0]; $importend = $mtime;
    $totaltime = ($importend - $importstart);
    printf ("<p>".$_lang['import_site_time']."</p>", round($totaltime, 3));
?>
<ul class="actionButtons">
    <li><a href="#" onclick="reloadTree();"><img src="<?php echo $_style["icons_close"] ?>" /> <?php echo $_lang["close"]; ?></a></li>
</ul>
<script type="text/javascript">
    parent.tree.ca = "";
</script>
<?php
}

function importFiles($parent,$filepath,$files,$mode) {
    global $modx;
    global $_lang, $allowedfiles;
	global $dbase;
    global $default_template, $search_default, $cache_default, $publish_default;


    $createdon = time();
    $createdby = $modx->getLoginUserID();
    if (!is_array($files)) return;
	if ($_POST['object']=='all')
	{
		$default_template = '0';
		$richtext         = '0';
	}
	else
	{
		$richtext         = '1';
	}
	
    foreach($files as $id => $value)
    {
        if(is_array($value))
        {
            // create folder
			$alias = ($modx->config['allow_duplicate_alias']=='1') ? $id:$id.'-'.substr(uniqid(''),-3);
            $modx->documentListing[$alias] = true;
			printf('<span>'.$_lang['import_site_importing_document'].'</span>', $id);
			foreach(array('index.html','index.htm') as $filename)
			{
				if(file_exists($filepath . $id . '/' . $filename))
				{
					$file = getFileContent($filepath . $id . '/' . $filename);
					$file = mb_convert_encoding($file, $modx->config['modx_charset'], 'UTF-8,SJIS,EUC-JP,ASCII');
					if (preg_match("@<title>(.*)</title>@i",$file,$matches))
					{
						$pagetitle = $matches[1];
					}
					else $pagetitle = $id;
					if ((preg_match("@<body[^>]*>(.*)[^<]+</body>@is",$file,$matches)) && $_POST['object']=='body')
					{
						$content = $matches[1];
					}
					else
					{
						$content = $file;
						$pattern = '/(<meta[^>]+charset\s*=+)[^>"\'=]+(.+>)/i';
						$replace = '$1' . $modx->config['modx_charset'] . '$2';
						$content = preg_replace($pattern, $replace, $content);
					}
					$sql = 'INSERT INTO ' . $modx->getFullTableName('site_content') . "
                   (type, contentType, pagetitle, alias, published, parent, isfolder, content, richtext, template, menuindex, searchable, cacheable, createdby, createdon) VALUES
						   ('document', 'text/html', '".$modx->db->escape($pagetitle)."', '".$modx->stripAlias($alias)."', ".$publish_default.", '$parent', 1, '".$modx->db->escape($content)."', '".$richtext."', '".$default_template."', 0, ".$search_default.", ".$cache_default.", $createdby, $createdon);";
					$rs = $modx->db->query($sql);
            if($rs) $new_parent = mysql_insert_id(); // get new parent id
            else
            {
						echo '<span class="fail">'.$_lang["import_site_failed"]."</span> ".$_lang["import_site_failed_db_error"].mysql_error();
                exit;
            }
					echo '<span class="success">'.$_lang["import_site_success"].'</span><br />' . PHP_EOL;
					importFiles($new_parent, $filepath . $id . '/',$value,'sub');
					break;
				}
        }
        }
        else
        {
            // create dcoument
			if($mode=='sub' && $value == 'index.html') continue;
            $filename = $value;
            $fparts = explode(".",$value);
            $value = $fparts[0];
            $ext = (count($fparts)>1)? $fparts[count($fparts)-1]:"";
            printf("<span>".$_lang['import_site_importing_document']."</span>", $filename);
			$alias = ($modx->config['allow_duplicate_alias']=='1') ? $value:$value.'-'.substr(uniqid(''),-3);
            $modx->documentListing[$alias] = true;
			if(!in_array($ext,$allowedfiles)) echo ' - <span class="fail">'.$_lang["import_site_skip"].'</span><br />' . PHP_EOL;
            else
            {
                $file = getFileContent("$filepath/$filename");
                $file = mb_convert_encoding($file, $modx->config['modx_charset'], 'UTF-8,SJIS,EUC-JP,ASCII');
                if (preg_match("@<title>(.*)</title>@i",$file,$matches))
                {
                    $pagetitle = $matches[1];
                }
                else $pagetitle = $value;
                if(!$pagetitle) $pagetitle = $value;
                if ((preg_match("@<body[^>]*>(.*)[^<]+</body>@is",$file,$matches)) && $_POST['object']=='body')
                {
                    $content = $matches[1];
                }
                else
                {
                $content = $file;
                $pattern = '/(<meta[^>]+charset\s*=+)[^>"\'=]+(.+>)/i';
                $replace = '$1' . $modx->config['modx_charset'] . '$2';
                $content = preg_replace($pattern, $replace, $content);
                }
				$sql = 'INSERT INTO ' . $modx->getFullTableName('site_content') . "
                       (type, contentType, pagetitle, alias, published, parent, isfolder, content, richtext, template, menuindex, searchable, cacheable, createdby, createdon) VALUES
                       ('document', 'text/html', '".$modx->db->escape($pagetitle)."', '".$modx->stripAlias($alias)."', ".$publish_default.", '$parent', 0, '".$modx->db->escape($content)."', '".$richtext."', '".$default_template."', 0, ".$search_default.", ".$cache_default.", $createdby, $createdon);";
				$rs = $modx->db->query($sql);
                if(!$rs)
                {
                    echo '<span class="fail">'.$_lang["import_site_failed"]."</span> ".$_lang["import_site_failed_db_error"].mysql_error();
                    exit;
                }
				$is_site_start = false;
				if($filename == 'index.html') $is_site_start = true;
				if($is_site_start==true && $_POST['reset']=='on')
				{
					$newid = mysql_insert_id();
					$sql  = 'REPLACE INTO ' . $modx->getFullTableName('system_settings');
					$sql .= " (setting_name, setting_value) VALUES ('site_start', '" . $newid . "')";
					$modx->db->query($sql);
				}
				echo ' - <span class="success">'.$_lang["import_site_success"]."</span><br />" . PHP_EOL;
            }
        }
    }
}

function getFiles($directory,$listing = array(), $count = 0){
    global $_lang;
    global $filesfound;
    $dummy = $count;
    if (@$handle = opendir($directory)) {
        while ($file = readdir($handle)) {
            if ($file=='.' || $file=='..') continue;
            else if ($h = @opendir($directory.$file."/")) {
                closedir($h);
                $count = -1;
                $listing["$file"] = getFiles($directory.$file."/",array(), $count + 1);
            }
            else {
                $listing[$dummy] = $file;
                $dummy = $dummy + 1;
                $filesfound++;
            }
        }
    }
    else {
		echo '<p><span class="fail">'.$_lang["import_site_failed"]."</span> ".$_lang["import_site_failed_no_open_dir"].$directory.".</p>";
    }
    @closedir($handle);
    return ($listing);
}

function getFileContent($file) {
    global $_lang;
    // get the file
    if(@$handle = fopen($file, "r")) {
        $buffer = "";
        while (!feof ($handle)) {
           $buffer .= fgets($handle, 4096);
        }
        fclose ($handle);
    }
    else {
		echo '<p><span class="fail">' . $_lang['import_site_failed']."</span> ".$_lang["import_site_failed_no_retrieve_file"].$file.".</p>";
    }
    return $buffer;
}

/**
 * @deprecated Use $modx->stripAlias()
 */
function stripAlias($alias) {
    return $GLOBALS['modx']->stripAlias($alias);
}

function cmp($a, $b)
{
    if ($a == 'index.html')    return -1;
    elseif ($a == 'index.htm') return -1;
    elseif ($a < $b)           return -1;
    elseif ($a == $b)          return  0;
    elseif ($a > $b)           return  1;
}

?>