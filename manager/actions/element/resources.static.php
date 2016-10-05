<?php
if(!isset($modx) || !$modx->isLoggedin()) exit;
?>
<script type="text/javascript">
	function deleteCategory(catid) {
		jConfirm("<?php echo $_lang['confirm_delete_category']; ?>", "<?php echo $_lang['delete_category'];?>",function(r){
			if(r) document.location.href="index.php?a=501&catId="+catid;
			else return false;
		});
	}
</script>

<h1><?php echo $_lang['element_management']; ?></h1>

<div id="actions">
  <ul class="actionButtons">
      <li id="Button5" class="mutate"><a href="#" onclick="document.location.href='index.php?a=2';"><img alt="icons_cancel" src="<?php echo $_style["icons_cancel"] ?>" /> <?php echo $_lang['cancel']?></a></li>
  </ul>
</div>

<div class="sectionBody">
<div class="tab-pane" id="elementsPane">

<!-- Templates -->
<?php 	if($modx->hasPermission('new_template') || $modx->hasPermission('edit_template')) { ?>
    <div class="tab-page" id="tabTemplates">
    	<h2 class="tab"><?php echo $_lang["manage_templates"] ?></h2>
		<div><?php echo $_lang['template_management_msg']; ?></div>
		<ul class="actionButtons">
			<li><a class="default" href="index.php?a=19"><img src="<?php echo $_style["icons_add"] ?>" /> <?php echo $_lang['new_template']; ?></a></li>
		</ul>
		<?php echo createResourceList('site_templates',16,'templatename'); ?>
	</div>
<?php } ?>

<!-- Template variables -->
<?php 	if($modx->hasPermission('new_template') || $modx->hasPermission('edit_template')) { ?>
    <div class="tab-page" id="tabVariables">
    	<h2 class="tab"><?php echo $_lang["tmplvars"] ?></h2>
		<!--//
			Modified By Raymond for Template Variables
			Added by Apodigm 09-06-2004- DocVars - web@apodigm.com
		-->
		<div><?php echo $_lang['tmplvars_management_msg']; ?></div>
			<ul class="actionButtons">
				<li><a class="default" href="index.php?a=300"><img src="<?php echo $_style["icons_add"] ?>" /> <?php echo $_lang['new_tmplvars']; ?></a></li>
            </ul>
            <?php echo createResourceList('site_tmplvars',301); ?>
	</div>
<?php } ?>

<!-- chunks -->
<?php 	if($modx->hasPermission('new_chunk') || $modx->hasPermission('edit_chunk')) { ?>
    <div class="tab-page" id="tabChunks">
    	<h2 class="tab"><?php echo $_lang["manage_htmlsnippets"] ?></h2>
		<div><?php echo $_lang['htmlsnippet_management_msg']; ?></div>

		<ul class="actionButtons">
			<li><a class="default" href="index.php?a=77"><img src="<?php echo $_style["icons_add"] ?>" /> <?php echo $_lang['new_htmlsnippet']; ?></a></li>
		</ul>
		<?php echo createResourceList('site_htmlsnippets',78); ?>
	</div>
<?php } ?>

<!-- snippets -->
<?php 	if($modx->hasPermission('new_snippet') || $modx->hasPermission('edit_snippet')) { ?>
    <div class="tab-page" id="tabSnippets">
    	<h2 class="tab"><?php echo $_lang["manage_snippets"] ?></h2>
		<div><?php echo $_lang['snippet_management_msg']; ?></div>

		<ul class="actionButtons">
			<li><a class="default" href="index.php?a=23"><img src="<?php echo $_style["icons_add"] ?>" /> <?php echo $_lang['new_snippet']; ?></a></li>
		</ul>
		<?php echo createResourceList('site_snippets',22); ?>
	</div>
<?php } ?>

<!-- plugins -->
<?php 	if($modx->hasPermission('new_plugin') || $modx->hasPermission('edit_plugin')) { ?>
    <div class="tab-page" id="tabPlugins">
    	<h2 class="tab"><?php echo $_lang["manage_plugins"] ?></h2>
		<div><?php echo $_lang['plugin_management_msg']; ?></div>

		<ul class="actionButtons">
			<li><a class="default" href="index.php?a=101"><img src="<?php echo $_style["icons_add"] ?>" /> <?php echo $_lang['new_plugin']; ?></a></li>
			<?php if($modx->hasPermission('save_plugin')) { ?><li><a href="index.php?a=100"><img src="<?php echo $_style["sort"] ?>" /> <?php echo $_lang['plugin_priority']; ?></a></li><?php } ?>
		</ul>
		<?php echo createResourceList('site_plugins',102); ?>
	</div>
<?php } ?>

<!-- category view -->
    <div class="tab-page" id="tabCategory">
    	<h2 class="tab"><?php echo $_lang["element_categories"] ?></h2>
		<div><?php echo $_lang['category_msg']; ?></div>
		<br />
		<ul>
		<?php echo createCategoryList(); ?>
		</ul>
	</div>
</div>
</div>
<script type="text/javascript">
	var tpstatus = <?php echo $modx->config['remember_last_tab'] == 0 ? 'false' : 'true'; ?>;
	tpElements = new WebFXTabPane( document.getElementById( "elementsPane" ), tpstatus );
</script>
<?php
function createResourceList($element_name,$action,$nameField = 'name')
{
	global $modx, $_lang, $modx_textdir;
	
	$preCat = '';
	$insideUl = 0;
	$output = '<ul>';
	$rows = getArray($element_name,$action,$nameField);
	$tpl  = '<span [+class+]><a href="index.php?id=[+id+]&amp;a=[+action+]">[+name+]<small>([+id+])</small></a>[+rlm+]</span>';
	$tpl .= ' [+description+][+locked+]';
	
	if(is_array($rows) && 0<count($rows)):
		$ph['action'] = $action;
		$ph['rlm']    = $modx_textdir==='rtl' ? '&rlm;' : '';
		foreach($rows as $row):
			$row['category'] = stripslashes($row['category']); //pixelchutes
			if ($preCat !== $row['category']):
				$output .= $insideUl ? '</ul>': '';
				$output .= '<li><strong>'.$row['category'].'</strong><ul>';
				$insideUl = 1;
			endif;
			$preCat = $row['category'];
			
			if ($element_name === 'site_plugins')
				$class = $row['disabled'] ? 'class="disabledPlugin"' : '';
			elseif ($element_name === 'site_htmlsnippets')
				$class = ($row['published']==='0') ? 'class="unpublished"' : '';
			else $class = '';
			
			$ph['id']          = $row['id'];
			$ph['class']       = $class;
			$ph['name']        = htmlspecialchars($row['name'], ENT_QUOTES, $modx->config['modx_charset']);
			$ph['description'] = $row['description'];
			$ph['locked']      = $row['locked'] ? ' <em>('.$_lang['locked'].')</em>' : '';
			$src = "<li>{$tpl}</li>";
			foreach($ph as $k=>$v):
				$k = "[+{$k}+]";
				$src = str_replace($k,$v,$src);
			endforeach;
			$output .= $src;
		endforeach;
	else:
		$output .= $rows;
	endif;
	$output .= $insideUl? '</ul>': '';
	$output .= '</ul>';
	return $output;
}

function getArray($element_name,$action,$nameField = 'name')
{
	global $modx, $_lang;
	
	$tbl_element_name = $modx->getFullTableName($element_name);
	$tbl_categories = $modx->getFullTableName('categories');
	
	switch($element_name)
	{
		case 'site_plugins':
			$f[] = "{$tbl_element_name}.disabled";
			break;
		case 'site_htmlsnippets':
			$f[] = "{$tbl_element_name}.published";
			break;
	}
	$f[] = "{$tbl_element_name}.{$nameField} as name";
	$f[] = "{$tbl_element_name}.id";
	$f[] = "{$tbl_element_name}.description";
	$f[] = "{$tbl_element_name}.locked";
	$f[] = "if(isnull({$tbl_categories}.category),'{$_lang['no_category']}',{$tbl_categories}.category) as category";
	$fields = implode(',', $f);
	$from   ="{$tbl_element_name} left join {$tbl_categories} on {$tbl_element_name}.category = {$tbl_categories}.id";
	$orderby = 'category,name';

	$rs = $modx->db->select($fields,$from,'',$orderby);
	$limit = $modx->db->getRecordCount($rs);
	if($limit<1)
		return $_lang['no_results'];
	
	$rows = array();
	while($row = $modx->db->getRow($rs)) {
		$rows[$row['id']] = $row;
	}
	return $rows;
}

function createCategoryList()
{
	global $modx, $_lang;
	
	$displayInfo = array();
	$hasPermission = 0;
	if($modx->hasPermission('edit_plugin') || $modx->hasPermission('new_plugin'))
	{
		$displayInfo['plugin'] = array('table'=>'site_plugins','action'=>102,'name'=>$_lang['manage_plugins']);
		$hasPermission = 1;
	}
	if($modx->hasPermission('edit_snippet') || $modx->hasPermission('new_snippet'))
	{
		$displayInfo['snippet'] = array('table'=>'site_snippets','action'=>22,'name'=>$_lang['manage_snippets']);
		$hasPermission = 1;
	}
	if($modx->hasPermission('edit_chunk') || $modx->hasPermission('new_chunk'))
	{
		$displayInfo['htmlsnippet'] = array('table'=>'site_htmlsnippets','action'=>78,'name'=>$_lang['manage_htmlsnippets']);
		$hasPermission = 1;
	}
	if($modx->hasPermission('edit_template') || $modx->hasPermission('new_template'))
	{
		$displayInfo['templates'] = array('table'=>'site_templates','action'=>16,'name'=>$_lang['manage_templates']);
		$displayInfo['tmplvars'] = array('table'=>'site_tmplvars','action'=>301,'name'=>$_lang['tmplvars']);
		$hasPermission = 1;
	}
	if($modx->hasPermission('edit_module') || $modx->hasPermission('new_module'))
	{
		$displayInfo['modules'] = array('table'=>'site_modules','action'=>108,'name'=>$_lang['modules']);
		$hasPermission = 1;
	}
	
	//Category Delete permission check
	$delPerm = 0;
	if($modx->hasPermission('save_plugin') ||
		$modx->hasPermission('save_snippet') ||
		$modx->hasPermission('save_chunk') ||
		$modx->hasPermission('save_template') ||
		$modx->hasPermission('save_module'))
	{
		$delPerm = 1;
	}
	
	if($hasPermission)
	{
		$finalInfo = array();
		
		foreach ($displayInfo as $n => $v)
		{
			$tbl_elm = $modx->getFullTableName($v['table']);
			$tbl_categories = $modx->getFullTableName('categories');
			if($v['table'] == 'site_templates')   $fields = 'templatename as name, ';
			elseif($v['table'] == 'site_plugins') $fields = "{$tbl_elm}.disabled, name, ";
			elseif($v['table'] == 'site_htmlsnippets') $fields = "{$tbl_elm}.published, name, ";
			else                                  $fields = 'name, ';
			$fields .= "{$tbl_elm}.id, description, locked, {$tbl_categories}.category, {$tbl_categories}.id as catid";
			
			$from = "{$tbl_elm} left join {$tbl_categories} on {$tbl_elm}.category = {$tbl_categories}.id";
			$orderby = ($v['table'] == 'site_plugins') ? "{$tbl_elm}.disabled ASC,6,2" : '5,1';
			$rs = $modx->db->select($fields,$from,'',$orderby);
			$limit = $modx->db->getRecordCount($rs);
			if($limit>0)
			{
				while($row = $modx->db->getRow($rs))
				{
					$row['type'] = $v['name'];
					$row['action'] = $v['action'];
					if (empty($row['category']))
					{
						$row['category'] = $_lang['no_category'];
					}
					$finalInfo[] = $row;
				}
			}
		}
		
		foreach($finalInfo as $n => $v)
		{
			$category[$n] = $v['category'];
			$name[$n] = $v['name'];
		}
		
		array_multisort($category, SORT_ASC, $name, SORT_ASC, $finalInfo);
		
		$preCat = '';
		$insideUl = 0;
		foreach($finalInfo as $n => $v)
		{
			if ($preCat !== $v['category'])
			{
				echo $insideUl? '</ul>': '';
				if ($v['category'] == $_lang['no_category'] || !$delPerm)
				{
					echo '<li><strong>'.$v['category'].'</strong><ul>';
				}
				else
				{
					echo '<li><strong>'.$v['category'].'</strong> (<a href="javascript:deleteCategory(\'' . $v['catid'] . '\');">'.$_lang['delete_category'].'</a>)<ul>';
				}
				$insideUl = 1;
			}
			$ph = array();
			if(array_key_exists('disabled',$v) && $v['disabled'])
			{
				$ph['class'] = ' class="disabledPlugin"';
			}
			if(array_key_exists('published',$v) && $v['published']==='0')
			{
				$ph['class'] = ' class="unpublished"';
			}
			else $ph['class'] = '';
			$ph['id'] = $v['id'];
			$ph['action'] = $v['action'];
			$ph['name'] = htmlspecialchars($v['name'], ENT_QUOTES, $modx->config['modx_charset']);
			$ph['type'] = $v['type'];
			$ph['description'] = (!empty($v['description'])) ? ' - '.$v['description'] : '';
			$ph['locked'] = ($v['locked']) ? ' <em>(' . $_lang['locked'] . ')</em>' : '';
			$tpl = '<li><span [+class+]><a href="index.php?id=[+id+]&amp;a=[+action+]">[+name+]</a></span> ([+type+])[+description+][+locked+]</li>';
			foreach($ph as $k=>$value)
			{
				$k = '[+' . $k . '+]';
				$tpl = str_replace($k,$value,$tpl);
			}
			echo $tpl;
			$preCat = $v['category'];
		}
		echo $insideUl? '</ul>': '';
	}
}
