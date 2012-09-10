<?php
if(!defined('IN_MANAGER_MODE') || IN_MANAGER_MODE != 'true') exit();

if(!$modx->hasPermission('manage_metatags')) {
	$e->setError(3);
	$e->dumpError();
}

$tbl_site_keywords = $modx->getFullTableName('site_keywords');
$tbl_site_metatags = $modx->getFullTableName('site_metatags');
$tbl_keyword_xref  = $modx->getFullTableName('keyword_xref');
// get op code
$opcode = isset($_POST['op']) ? $_POST['op'] : "keys" ;

// add tag
if($opcode=="addtag") {
	list($tag,$http_equiv) = explode(";",$_POST["tag"]);
	$f = array(
		name => $modx->db->escape($_POST["tagname"]),
		tag => $modx->db->escape($tag),
		tagvalue => $modx->db->escape($_POST["tagvalue"]),
		http_equiv => intval($http_equiv)
	);
	if($f["name"] && $f["tagvalue"]) {
		$modx->db->insert($f,$tbl_site_metatags);
	}
}
// edit tag
else if($opcode=="edttag") {
	$id = intval($_POST["id"]);
	list($tag,$http_equiv) = explode(";",$_POST["tag"]);
	$f = array(
		name => $modx->db->escape($_POST["tagname"]),
		tag => $modx->db->escape($tag),
		tagvalue => $modx->db->escape($_POST["tagvalue"]),
		http_equiv => intval($http_equiv)
	);
	if($f["name"] && $f["tagvalue"]) {
		$modx->db->update($f,$tbl_site_metatags,"id='{$id}'");
	}
}
// delete
elseif($opcode=="deltag") {
	$f = $_POST["tag"];
	if(is_array($f) && count($f)>0)
	{
		foreach($f as $i=>$v)
		{
			$f[$i] = $modx->db->escape($v);
		}
		$tags = join(',',$f);
		$modx->db->delete($tbl_site_metatags,"id IN('{$tags}')");
	}
}
else {
	$delete_keywords = isset($_POST['delete_keywords']) ? $_POST['delete_keywords'] : array() ;
	$orig_keywords = isset($_POST['orig_keywords']) ? $_POST['orig_keywords'] : array() ;
	$rename_keywords = isset($_POST['rename_keywords']) ? $_POST['rename_keywords'] : array() ;

	// do any renaming that has to be done
	foreach($orig_keywords as $key => $value)
	{
		if($rename_keywords[$key]!=$value)
		{
			$keyword = $modx->db->escape($rename_keywords[$key]);
			$rs = $modx->db->select('*',$tbl_site_keywords,"BINARY keyword='{$keyword}'");
			$limit = $modx->db->getRecordCount($rs);
			if($limit > 0)
			{
				echo "  - This keyword has already been defined!";
				exit;
			}
			else
			{
				$value = $modx->db->escape($value);
				$rs = $modx->db->update("keyword='{$keyword}'", $tbl_site_keywords, "keyword='{$value}'");
			}
		}
	}

	// delete any keywords that need to be deleted
	if(count($delete_keywords)>0)
	{
		$keywords_array = array();
		foreach($delete_keywords as $key => $value)
		{
			$keywords_array[] = $key;
		}

		$keywords = join(',', $keywords_array);
		
		$rs = $modx->db->delete($tbl_keyword_xref, "keyword_id IN('{$keywords}')");
		if(!$rs)
		{
			echo "Failure on deletion of xref keys: ".$modx->db->getLastError();
			exit;
		}

		$rs = $modx->db->delete($tbl_site_keywords, "id IN('{$keywords}')");
		if(!$rs)
		{
			echo "Failure on deletion of keywords ".$modx->db->getLastError();
			exit;
		}

	}

	// add new keyword
	if(!empty($_POST['new_keyword'])) {
		$nk = $modx->db->escape($_POST['new_keyword']);

		$rs = $modx->db->select('*',$tbl_site_keywords,"keyword='{$nk}'");
		$limit = $modx->db->getRecordCount($rs);
		if($limit > 0)
		{
			echo "Keyword {$nk} already exists!";
			exit;
		}
		else
		{
			$rs = $modx->db->insert("keyword='{$nk}'",$tbl_site_keywords);
		}
	}
}

// empty cache
$modx->clearCache();

header("Location: index.php?a=81");
