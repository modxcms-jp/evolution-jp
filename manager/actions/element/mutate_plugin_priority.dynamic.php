<?php
if(!isset($modx) || !$modx->isLoggedin()) exit;
if(!$modx->hasPermission('save_plugin')) {
	$e->setError(3);
	$e->dumpError();
}

$updateMsg = '';

if(isset($_POST['listSubmitted']))
{
	$updateMsg .= '<span class="success" id="updated">Updated!<br /><br /> </span>';
	
	foreach ($_POST as $listName=>$listValue)
	{
		if ($listName == 'listSubmitted') continue;
		$orderArray = explode(',', $listValue);
		if(substr($listName,0,5)==='list_') $listName = substr($listName,5);
		if (count($orderArray) > 0)
		{
			foreach($orderArray as $key => $item)
			{
				if ($item == '') continue;
				$pluginId = ltrim($item, 'item_');
				$field['priority'] = $key;
				$modx->db->update($field,'[+prefix+]site_plugin_events',"pluginid={$pluginId} AND evtid='{$listName}'");
			}
		}
	}
	// empty cache
	$modx->clearCache(); // first empty the cache
}

$f['evtname'] = 'sysevt.name';
$f['evtid']   = 'sysevt.id';
$f[]          = 'pe.pluginid';
$f[]          = 'plugs.name';
$f[]          = 'pe.priority';
$from[] = '[+prefix+]system_eventnames sysevt';
$from[] = 'INNER JOIN [+prefix+]site_plugin_events pe ON pe.evtid = sysevt.id';
$from[] = 'INNER JOIN [+prefix+]site_plugins plugs ON plugs.id = pe.pluginid';
$rs = $modx->db->select($f,$from,'plugs.disabled=0','sysevt.name,pe.priority');

$insideUl = 0;
$preEvt = '';
$evtLists = '';
$sortables = array();
while ($row = $modx->db->getRow($rs)) {
	if ($preEvt !== $row['evtid'])
	{
		$sortables[] = $row['evtid'];
		$evtLists .= $insideUl ? '</ul><br />': '';
		$evtLists .= '<strong>'.$row['evtname'].'</strong><br /><ul id="'.$row['evtid'].'" class="sortableList">';
		$insideUl = 1;
	}
	$evtLists .= '<li id="item_'.$row['pluginid'].'">'.$row['name'].'</li>';
	$preEvt = $row['evtid'];
}

$evtLists .= '</ul>';

$header = '
<!doctype html>
<head>
	<title>MODX</title>
	<meta http-equiv="Content-Type" content="text/html; charset=' . $modx_manager_charset . '" />
	<link rel="stylesheet" type="text/css" href="media/style/' . $manager_theme . '/style.css" />
	<script type="text/javascript" src="media/script/jquery/jquery.min.js"></script>
	<script type="text/javascript" src="media/script/mootools/mootools.js"></script>

	<style type="text/css">
        .topdiv {border: 0;}
		.subdiv {border: 0;}
		li {list-style:none;}
		.tplbutton {text-align: right;}
		ul.sortableList
		{
			padding-left: 20px;
			margin: 0px;
			width: 300px;
		}

		ul.sortableList li
		{
			font-weight: bold;
			cursor: move;
            color: #444444;
            padding: 3px 5px;
			margin: 4px 0px;
            border: 1px solid #CCCCCC;
			background-repeat: repeat-x;
		}
        #sortableListForm {display:none;}
	</style>
    <script type="text/javascript">
        function save() {
        	setTimeout("document.sortableListForm.submit()",1000);
    	}
    	
    	window.addEvent(\'domready\', function() {';
foreach ($sortables as $list)
{
	$header .= 'new Sortables($(\''.$list.'\'), {
	               initialize: function() {
                        $$(\'#'.$list.' li\').each(function(el, i)
                        {
                            el.setStyle(\'padding\', \'3px 5px\');
                            el.setStyle(\'font-weight\', \'bold\');
                            el.setStyle(\'width\', \'300px\');
                            el.setStyle(\'background-color\', \'#ccc\');
                            el.setStyle(\'cursor\', \'move\');
                        });
                    }
                    ,onComplete: function() {
           	var id = null;
           	var list = this.serialize(function(el) {
            id = el.getParent().id;
           	return el.id;
           });
           $(\'list_\' + id).value = list;
                    }
                });' ."\n";
}
	$header .= '});
</script>
</head>
<body ondragstart="return false;">

<h1>'.$_lang['plugin_priority_title'].'</h1>

<div id="actions">
   <ul class="actionButtons">
       	<li class="mutate"><a href="#" onclick="save();"><img src="'.$_style["icons_save"].'" /> '.$_lang['update'].'</a></li>
		<li class="mutate"><a href="#" onclick="document.location.href=\'index.php?a=76\';"><img src="'.$_style["icons_cancel"].'" /> '.$_lang['cancel'].'</a></li>
	</ul>
</div>

<div class="section">
<div class="sectionHeader">'.$_lang['plugin_priority'].'</div>
<div class="sectionBody">
<p>'.$_lang['plugin_priority_instructions'].'</p>
';

echo $header;

echo $updateMsg . '<span class="warning" style="display:none;" id="updating">Updating...<br /><br /> </span>';

echo $evtLists;

echo '<form action="" method="post" name="sortableListForm" style="display: none;">
            <input type="hidden" name="listSubmitted" value="true" />';
            
foreach ($sortables as $list)
{
	echo '<input type="text" id="list_'.$list.'" name="list_'.$list.'" value="" />';
}
            
echo '	</form>
	</div>
</div>
';
