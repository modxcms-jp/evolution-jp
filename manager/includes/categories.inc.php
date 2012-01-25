<?php
//Helper functions for categories
//Kyle Jaebker - 08/07/06

//Create a new category
function newCategory($newCat)
{
	global $modx;
	$useTable = $modx->getFullTableName('categories');
	$newCat = $modx->db->escape($newCat);
	$field['category'] = $newCat;
	$newCatid = $modx->db->insert($field,$useTable);
	if(!$newCatid) $newCatid = 0;
	return $newCatid;
}

	//check if new category already exists
function checkCategory($newCat = '')
{
	global $modx;
	$tbl_categories = $modx->getFullTableName('categories');
	$rs = $modx->db->select('id,category',$tbl_categories,'','category');
	if($rs)
	{
		while($row = $modx->db->getRow($rs))
		{
			if ($row['category'] == $newCat)
			{
				return $row['id'];
			}
		}
	}
	return 0;
}

	//Get all categories
	function getCategories()
	{
		global $modx;
		$tbl_categories = $modx->getFullTableName('categories');
		$cats = $modx->db->select('id, category', $tbl_categories, '', 'category');
		$resourceArray = array();
		if($cats)
		{
			while($row = $modx->db->getRow($cats))
			{
				array_push($resourceArray,array( 'id' => $row['id'], 'category' => stripslashes( $row['category'] ) )); // pixelchutes
			}
		}
		return $resourceArray;
	}

	//Delete category & associations
	function deleteCategory($catId=0)
	{
		global $modx;
		if ($catId)
		{
			$resetTables = array('site_plugins', 'site_snippets', 'site_htmlsnippets', 'site_templates', 'site_tmplvars', 'site_modules');
			foreach ($resetTables as $n=>$v)
			{
				$tbl = $modx->getFullTableName($v);
				$field['category'] = '0';
				$modx->db->update($field, $tbl, "category={$catId}");
			}
			$tbl_categories = $modx->getFullTableName('categories');
			$modx->db->delete($tbl_categories,"id={$catId}");
		}
	}
