<?php if(!defined('IN_MANAGER_MODE') || IN_MANAGER_MODE != 'true') exit();

$mxla = $modx_lang_attribute ? $modx_lang_attribute : 'en';

?><!DOCTYPE html PUBLIC "-//W3C//DTD XHTML 1.1//EN" "http://www.w3.org/TR/xhtml11/DTD/xhtml11.dtd">
<html <?php echo ($modx_textdir==='rtl' ? 'dir="rtl" lang="' : 'lang="').$mxla.'" xml:lang="'.$mxla.'"'; ?>>
<head>
    <title>Document Tree</title>
    <meta http-equiv="Content-Type" content="text/html; charset=<?php echo $modx_manager_charset; ?>" />
    <link rel="stylesheet" type="text/css" href="media/style/<?php echo $manager_theme; ?>/style.css?<?php echo $modx_version;?>" />
    <?php echo $modx->config['manager_inline_style']; ?>
    <script src="media/script/jquery/jquery.min.js" type="text/javascript"></script>
    <script src="media/script/jquery/jquery-migrate.min.js"></script>
    <script type="text/javascript">
    jQuery(function(){
        resizeTree();
        restoreTree();
        jQuery(window).resize(function(){resizeTree();});
    });

    // preload images
    var i = new Image(18,18);
    i.src="<?php echo $_style["tree_page"]?>";
    i = new Image(18,18);
    i.src="<?php echo $_style["tree_globe"]?>";
    i = new Image(18,18);
    i.src="<?php echo $_style["tree_minusnode"]?>";
    i = new Image(18,18);
    i.src="<?php echo $_style["tree_plusnode"]?>";
    i = new Image(18,18);
    i.src="<?php echo $_style["tree_folderopen"]?>";
    i = new Image(18,18);
    i.src="<?php echo $_style["tree_folder"]?>";


    var rpcNode = null;
    var ca = "open";
    var selectedObject = 0;
    var selectedObjectDeleted = 0;
    var selectedObjectName = "";
    var _rc = 0; // added to fix onclick body event from closing ctx menu

    var openedArray = new Array();
<?php
	if(!isset($_SESSION['openedArray']) && $modx->config['allowed_parents'])
    {
    	$allowed_parents = explode(',', $modx->config['allowed_parents']);
		foreach($allowed_parents as $allowed_parent)
		{
			$_ = $modx->getParentIds($allowed_parent);
			if(!$_) break;
			foreach($_ as $v)
			{
				$openedArray[] = $v;
			}
		}
		if($openedArray) $_SESSION['openedArray'] = join('|',$openedArray);
	}
	
	if(isset($_SESSION['openedArray']))      $openedArray = explode('|', $_SESSION['openedArray']);
	else $openedArray = false;
	
    if ($openedArray) {
    	foreach($openedArray as $i=>$v) {
    		$openedArray[$i] = (int) $v;
    	}
            $opened = array_filter($openedArray);

            foreach ($opened as $item) {
                 printf("openedArray[%d] = 1;\n", $item);
            }
    }
?>

    // return window dimensions in array
    function getWindowDimension() {
        var width  = 0;
        var height = 0;

        if ( typeof( window.innerWidth ) == 'number' ){
            width  = window.innerWidth;
            height = window.innerHeight;
        }else if ( document.documentElement &&
                 ( document.documentElement.clientWidth ||
                   document.documentElement.clientHeight ) ){
            width  = document.documentElement.clientWidth;
            height = document.documentElement.clientHeight;
        }
        else if ( document.body &&
                ( document.body.clientWidth || document.body.clientHeight ) ){
            width  = document.body.clientWidth;
            height = document.body.clientHeight;
        }

        return {'width':width,'height':height};
    }

    function resizeTree() {

        // get window width/height
        var win = getWindowDimension();

        // set tree height
        var tree = document.getElementById('treeHolder');
        var tmnu = document.getElementById('treeMenu');
        tree.style.width = (win['width']-20)+'px';
        tree.style.height = (win['height']-tree.offsetTop-6)+'px';
        tree.style.overflow = 'auto';
    }

    function getScrollY() {
      var scrOfY = 0;
      if( typeof( window.pageYOffset ) == 'number' ) {
        //Netscape compliant
        scrOfY = window.pageYOffset;
      } else if( document.body && ( document.body.scrollLeft || document.body.scrollTop ) ) {
        //DOM compliant
        scrOfY = document.body.scrollTop;
      } else if( document.documentElement &&
          (document.documentElement.scrollTop ) ) {
        //IE6 standards compliant mode
        scrOfY = document.documentElement.scrollTop;
      }
      return scrOfY;
    }

    function showPopup(id,title,pub,del,e){
        var x,y
        var mnu = document.getElementById('mx_contextmenu');
        var permpub = <?php echo $modx->hasPermission('publish_document') ? 1:0; ?>;
        var permdel = <?php echo $modx->hasPermission('delete_document') ? 1:0; ?>;
        if(permpub==1)
        {
	        document.getElementById('item61').style.display='block';
	        document.getElementById('item62').style.display='block';
	        if(pub==1) document.getElementById('item61').style.display='none';
	        else       document.getElementById('item62').style.display='none';
        }
        else
        {
        	if(document.getElementById('item51') != null)
        		document.getElementById('item51').style.display='none';
        }
        
        if(permdel==1)
        {
	        document.getElementById('item6').style.display='block';
	        document.getElementById('item63').style.display='block';
	        if(document.getElementById('item64') != null)
	        	document.getElementById('item64').style.display='block';
	        if(del==1)
        	{
	        	document.getElementById('item6').style.display='none';
	        	document.getElementById('item61').style.display='none';
	        	document.getElementById('item62').style.display='none';
	        }
	        else
	        {
	        	document.getElementById('item63').style.display='none';
	        	if(document.getElementById('item64') != null)
	        		document.getElementById('item64').style.display='none';
	        }
        }
        var bodyHeight = parseInt(document.body.offsetHeight);
        x = e.clientX > 0 ? e.clientX:e.pageX;
        y = e.clientY > 0 ? e.clientY:e.pageY;
        y = getScrollY()+(y/2);
        if (y + mnu.offsetHeight > bodyHeight) {
            // make sure context menu is within frame
            y = mnu.offsetHeight - bodyHeight + 5;
        }
        itemToChange=id;
        selectedObjectName= title;
        dopopup(x+5,y);
        e.cancelBubble=true;
        return false;
    }

    function dopopup(x,y) {
        if(selectedObjectName.length>20) {
            selectedObjectName = selectedObjectName.substr(0, 20) + "...";
        }
        x = x<?php echo $modx_textdir==='rtl' ? '-190' : '';?>;
        jQuery('#mx_contextmenu').css('left',x); //offset menu to the left if rtl is selected
        jQuery('#mx_contextmenu').css('top' ,y);
        jQuery("#nameHolder").text(selectedObjectName);

        jQuery('#mx_contextmenu').css('visibility','visible');
        _rc = 1;
        setTimeout("_rc = 0;",100);
    }

    function hideMenu() {
        if (_rc) return false;
        jQuery('#mx_contextmenu').css('visibility','hidden');
    }

    function toggleNode(node,indent,parent,expandAll,privatenode) {
        privatenode = (!privatenode || privatenode == '0') ? '0' : '1';
        rpcNode = node.parentNode.lastChild;

        var rpcNodeText;
        var loadText = "<?php echo $_lang['loading_doc_tree'];?>";

        var signImg = document.getElementById('s'+parent);
        var folderImg = document.getElementById('f'+parent);

        if (rpcNode.style.display != 'block') {
            // expand
            if(signImg && signImg.src.indexOf('media/style/<?php echo $manager_theme; ?>/images/tree/plusnode.gif')>-1) {
                signImg.src = '<?php echo $_style["tree_minusnode"]; ?>';
                folderImg.src = (privatenode == '0') ? '<?php echo $_style["tree_folderopen"]; ?>' :'<?php echo $_style["tree_folderopen_secure"]; ?>';
            }

            rpcNodeText = rpcNode.innerHTML;

            if (rpcNodeText=="" || rpcNodeText.indexOf(loadText)>0) {
                var i, spacer='';
                for(i=0;i<=indent+1;i++) spacer+='&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;';
                rpcNode.style.display = 'block';
                //Jeroen set opened
                openedArray[parent] = 1 ;
                //Raymond:added getFolderState()
                var folderState = getFolderState();
                rpcNode.innerHTML = "<span class='emptyNode' style='white-space:nowrap;'>"+spacer+"&nbsp;&nbsp;&nbsp;"+loadText+"...<\/span>";
                jQuery.get('index.php',{'a':'1','f':'nodes','indent':indent,'parent':parent,'expandAll':expandAll+folderState},rpcLoadData);
            } else {
                rpcNode.style.display = 'block';
                //Jeroen set opened
                openedArray[parent] = 1 ;
            }
        }
        else {
            // collapse
            if(signImg && signImg.src.indexOf('media/style/<?php echo $manager_theme; ?>/images/tree/minusnode.gif')>-1) {
                signImg.src = '<?php echo $_style["tree_plusnode"]; ?>';
                folderImg.src = (privatenode == '0') ? '<?php echo $_style["tree_folder"]; ?>' : '<?php echo $_style["tree_folder_secure"]; ?>';
            }
            //rpcNode.innerHTML = '';
            rpcNode.style.display = 'none';
            openedArray[parent] = 0 ;
        }
    }

    function rpcLoadData(response) {
        if(rpcNode != null){
            rpcNode.innerHTML = typeof response=='object' ? response.responseText : response ;
            rpcNode.style.display = 'block';
            rpcNode.loaded = true;
            var elm = top.mainMenu.document.getElementById('buildText');
            if (elm) {
                elm.innerHTML = '';
                elm.style.display = 'none';
            }
            // check if bin is full
            if(rpcNode.id=='treeRoot') {
                var e = document.getElementById('binFull');
                if(e) showBinFull();
                else showBinEmpty();
            }

            // check if our payload contains the login form :)
            e = document.getElementById('mx_loginbox');
            if(e) {
                // yep! the seession has timed out
                rpcNode.innerHTML = '';
                top.location = 'index.php';
            }
        }
    }

    function expandTree() {
        rpcNode = document.getElementById('treeRoot');
        jQuery.get('index.php',{'a':'1','f':'nodes','indent':'1','parent':'0','expandAll':'1'},rpcLoadData);
    }

    function collapseTree() {
        rpcNode = document.getElementById('treeRoot');
        jQuery.get('index.php',{'a':'1','f':'nodes','indent':'1','parent':'0','expandAll':'0'},rpcLoadData);
    }

    // new function used in body onload
    function restoreTree() {
        rpcNode = document.getElementById('treeRoot');
        jQuery.get('index.php',{'a':'1','f':'nodes','indent':'1','parent':'0','expandAll':'2'},rpcLoadData);
    }

    function setSelected(elSel) {
        var all = document.getElementsByTagName( "SPAN" );
        var l = all.length;

        for ( var i = 0; i < l; i++ ) {
            el = all[i];
            cn = el.className;
            if (cn=="treeNodeSelected") {
                el.className="treeNode";
            }
        }
        elSel.className="treeNodeSelected";
    }

    function setHoverClass(el, dir) {
        if(el.className!="treeNodeSelected") {
            if(dir==1) {
                el.className="treeNodeHover";
            } else {
                el.className="treeNode";
            }
        }
    }

    // set Context Node State
    function setCNS(n, b) {
        if(b==1) {
            n.style.backgroundColor="beige";
        } else {
            n.style.backgroundColor="";
        }
    }

    function updateTree() {
        rpcNode = document.getElementById('treeRoot');
        var dt = document.sortFrm.dt.value;
        var t_sortby = document.sortFrm.sortby.value;
        var t_sortdir = document.sortFrm.sortdir.value;
        
        jQuery.get('index.php',{'a':'1','f':'nodes','indent':'1','parent':'0','expandAll':'2','dt':dt,'tree_sortby':t_sortby,'tree_sortdir':t_sortdir},rpcLoadData);
    }

    function emptyTrash() {
        if(confirm("<?php echo $_lang['confirm_empty_trash']; ?>")==true) {
            top.main.document.location.href="index.php?a=64";
        }
    }

    currSorterState="none";
    function showSorter() {
        if(currSorterState=="none") {
            currSorterState="block";
            document.getElementById('floater').style.display=currSorterState;
        } else {
            currSorterState="none";
            document.getElementById('floater').style.display=currSorterState;
        }
    }

    function treeAction(id, name) {
        if(ca=="move") {
            try {
                parent.main.setMoveValue(id, name);
            } catch(oException) {
                alert('<?php echo $_lang['unable_set_parent']; ?>');
            }
        }
        if(ca=="open" || ca=="docinfo" || ca=="doclist" || ca=="") {
            <?php $action = (!empty($modx->config['tree_page_click']) ? $modx->config['tree_page_click'] : '27'); ?>
            if(id==0) {
                // do nothing?
                parent.main.location.href="index.php?a=2";
            } else if(ca=="docinfo") {
                parent.main.location.href="index.php?a=3&id=" + id;
            } else if(ca=="doclist") {
                parent.main.location.href="index.php?a=120&id=" + id;
            } else if(ca=="open") {
                parent.main.location.href="index.php?a=27&id=" + id;
            } else {
                // parent.main.location.href="index.php?a=3&id=" + id + getFolderState(); //just added the getvar &opened=
                parent.main.location.href="index.php?a=<?php echo $action; ?>&id=" + id; // edit as default action
            }
        }
        if(ca=="parent") {
            try {
                parent.main.setParent(id, name);
            } catch(oException) {
                alert('<?php echo $_lang['unable_set_parent']; ?>');
            }
        }
        if(ca=="link") {
            try {
                parent.main.setLink(id);
            } catch(oException) {
                alert('<?php echo $_lang['unable_set_link']; ?>');
            }
        }
    }

    //Raymond: added getFolderState,saveFolderState
    function getFolderState(){
        if (openedArray != [0]) {
                oarray = "&opened=";
                for (key in openedArray) {
                   if (openedArray[key] == 1) {
                      oarray += key+"|";
                   }
                }
        } else {
                oarray = "&opened=";
        }
        return oarray;
    }
    function saveFolderState() {
        var folderState = getFolderState();
        url = 'index.php?a=1&f=nodes&savestateonly=1'+folderState;
        jQuery.get(url);
    }

    // show state of recycle bin
    function showBinFull() {
        var a = document.getElementById('Button10');
        var title = '<?php echo $_lang['empty_recycle_bin']; ?>';
        if (a) {
            if(!a.setAttribute) a.title = title;
        else a.setAttribute('title',title);
        a.innerHTML = '<?php echo $_style['empty_recycle_bin']; ?>';
        a.className = 'treeButton';
        a.onclick = emptyTrash;
    }
    }

    function showBinEmpty() {
        var a = document.getElementById('Button10');
        var title = '<?php echo addslashes($_lang['empty_recycle_bin_empty']); ?>';
        if (a) {
            if(!a.setAttribute) a.title = title;
        else a.setAttribute('title',title);
        a.innerHTML = '<?php echo $_style['empty_recycle_bin_empty']; ?>';
        a.className = 'treeButtonDisabled';
        a.onclick = '';
    }
    }

</script>
</head>
<body onclick="hideMenu();" class="<?php echo $modx_textdir==='rtl' ? ' rtl':''?>">
<?php
    // invoke OnTreePrerender event
    $evtOut = $modx->invokeEvent('OnManagerTreeInit',$_REQUEST);
    if (is_array($evtOut))
        echo implode("\n", $evtOut);
?>
<div class="treeframebody">
<div id="treeSplitter"></div>

<table id="treeMenu" width="100%"  border="0" cellpadding="0" cellspacing="0">
  <tr>
    <td>
        <table cellpadding="0" cellspacing="0" border="0">
            <tr>
            <td><a href="#" class="treeButton" id="Button1" onclick="expandTree();" title="<?php echo $_lang['expand_tree']; ?>"><?php echo $_style['expand_tree']; ?></a></td>
            <td><a href="#" class="treeButton" id="Button2" onclick="collapseTree();" title="<?php echo $_lang['collapse_tree']; ?>"><?php echo $_style['collapse_tree']; ?></a></td>
            <?php if ($modx->hasPermission('new_document')) { ?>
                <td><a href="#" class="treeButton" id="Button3a" onclick="top.main.document.location.href='index.php?a=4';" title="<?php echo $_lang['add_resource']; ?>"><?php echo $_style['add_doc_tree']; ?></a></td>
                <td><a href="#" class="treeButton" id="Button3c" onclick="top.main.document.location.href='index.php?a=72';" title="<?php echo $_lang['add_weblink']; ?>"><?php echo $_style['add_weblink_tree']; ?></a></td>
            <?php } ?>
            <td><a href="#" class="treeButton" id="Button4" onclick="top.mainMenu.reloadtree();" title="<?php echo $_lang['refresh_tree']; ?>"><?php echo $_style['refresh_tree']; ?></a></td>
            <td><a href="#" class="treeButton" id="Button5" onclick="showSorter();" title="<?php echo $_lang['sort_tree']; ?>"><?php echo $_style['sort_tree']; ?></a></td>
            <?php if ($modx->hasPermission('empty_trash')) { ?>
                <td><a href="#" id="Button10" class="treeButtonDisabled" title="<?php echo $_lang['empty_recycle_bin_empty'] ; ?>"><?php echo $_style['empty_recycle_bin_empty'] ; ?></a></td>
            <?php } ?>
            </tr>
        </table>
    </td>
    <td align="right">
        <table cellpadding="0" cellspacing="0" border="0">
            <tr>
            <td><a href="#" class="treeButton" id="Button6" onclick="top.mainMenu.hideTreeFrame();" title="<?php echo $_lang['hide_tree']; ?>"><?php echo $_style['hide_tree']; ?></a></td>
            </tr>
        </table>
    </td>
  </tr>
</table>

<div id="floater">
<?php
$fieldtype = strpos($modx->config['resource_tree_node_name'], 'edon')!==false ? 'date' : 'str';
if(isset($_REQUEST['tree_sortby']))  $_SESSION['tree_sortby']  = $_REQUEST['tree_sortby'];
else                                 $_SESSION['tree_sortby']  = ($fieldtype==='str') ? 'menuindex' : $modx->config['resource_tree_node_name'];
if(isset($_REQUEST['tree_sortdir'])) $_SESSION['tree_sortdir'] = $_REQUEST['tree_sortdir'];
else                                 $_SESSION['tree_sortdir'] = $fieldtype == 'date' ? 'DESC' : 'ASC';
?>
<form name="sortFrm" id="sortFrm" action="menu.php">
<table width="100%"  border="0" cellpadding="0" cellspacing="0">
  <tr>
    <td style="padding-left: 10px;padding-top: 1px;" colspan="2">
        <select name="sortby" style="font-size: 12px;">
            <option value="<?php echo $modx->config['resource_tree_node_name'];?>"></option>
            <option value="isfolder" <?php echo select($_SESSION['tree_sortby']=='isfolder');?>><?php echo $_lang['folder']; ?></option>
            <option value="pagetitle" <?php echo select($_SESSION['tree_sortby']=='pagetitle');?>><?php echo $_lang['pagetitle']; ?></option>
            <option value="id" <?php echo select($_SESSION['tree_sortby']=='id');?>><?php echo $_lang['id']; ?></option>
            <option value="menuindex" <?php echo select($_SESSION['tree_sortby']=='menuindex');?>><?php echo $_lang['resource_opt_menu_index'] ?></option>
            <option value="createdon" <?php echo select($_SESSION['tree_sortby']=='createdon');?>><?php echo $_lang['createdon']; ?></option>
            <option value="editedon" <?php echo select($_SESSION['tree_sortby']=='editedon');?>><?php echo $_lang['editedon']; ?></option>
        </select>
    </td>
  </tr>
  <tr>
    <td width="99%" style="padding-left: 10px;padding-top: 1px;">
        <select name="sortdir" style="font-size: 12px;">
            <option value="DESC" <?php echo select($_SESSION['tree_sortdir']=='DESC');?>><?php echo $_lang['sort_desc']; ?></option>
            <option value="ASC" <?php echo select($_SESSION['tree_sortdir']=='ASC');?>><?php echo $_lang['sort_asc']; ?></option>
        </select>
        <input type="hidden" name="dt" value="<?php echo htmlspecialchars($_REQUEST['dt']); ?>" />
    </td>
    <td width="1%"><a href="#" class="treeButton" id="button7" style="text-align:right" onclick="updateTree();showSorter();" title="<?php echo $_lang['sort_tree']; ?>"><?php echo $_lang['sort_tree']; ?></a></td>
  </tr>
</table>
</form>
</div>

<div id="treeHolder">
<?php
    // invoke OnTreeRender event
    $evtOut = $modx->invokeEvent('OnManagerTreePrerender', $modx->db->escape($_REQUEST));
    if (is_array($evtOut))
        echo implode("\n", $evtOut);
?>
    <div><?php echo $_style['tree_showtree']; ?>&nbsp;<span class="rootNode" onclick="treeAction(0, '<?php echo addslashes($site_name); ?>');"><b><?php echo $site_name; ?></b></span><div id="treeRoot"></div></div>
<?php
    // invoke OnTreeRender event
    $evtOut = $modx->invokeEvent('OnManagerTreeRender', $modx->db->escape($_REQUEST));
    if (is_array($evtOut))
        echo implode("\n", $evtOut);
?>
</div>

<script type="text/javascript">
// Set 'treeNodeSelected' class on document node when editing via Context Menu
function setActiveFromContextMenu( doc_id ){
    jQuery('.treeNodeSelected').removeClass('treeNodeSelected');
    jQuery('#node'+doc_id+' span:first').attr('class','treeNodeSelected');
}

// Context menu stuff
function menuHandler(action) {
    switch (action) {
        case '3' : // view
            setActiveFromContextMenu(itemToChange);
            top.main.document.location.href="index.php?a=3&id=" + itemToChange;
            break;
        case '27' : // edit
            setActiveFromContextMenu(itemToChange);
            top.main.document.location.href="index.php?a=27&id=" + itemToChange;
            break;
        case '4' : // new Resource
            setActiveFromContextMenu(itemToChange);
            top.main.document.location.href="index.php?a=4&pid=" + itemToChange;
            break;
        case '6' : // delete
            if(selectedObjectDeleted==0) {
                if(confirm("'" + selectedObjectName + "'\n\n<?php echo $_lang['confirm_delete_resource']; ?>")==true) {
                    top.main.document.location.href="index.php?a=6&id=" + itemToChange;
                }
            } else {
                alert("'" + selectedObjectName + "' <?php echo $_lang['already_deleted']; ?>");
            }
            break;
        case '51' : // move
            setActiveFromContextMenu(itemToChange);
            top.main.document.location.href="index.php?a=51&id=" + itemToChange;
            break;
        case '72' : // new Weblink
            setActiveFromContextMenu(itemToChange);
            top.main.document.location.href="index.php?a=72&pid=" + itemToChange;
            break;
        case '94' : // duplicate
            if(confirm("<?php echo $_lang['confirm_resource_duplicate'] ?>")==true) {
                   setActiveFromContextMenu(itemToChange);
                   top.main.document.location.href="index.php?a=94&id=" + itemToChange;
               }
            break;
        case '63' : // undelete
            if(selectedObjectDeleted==0) {
                alert("'" + selectedObjectName + "' <?php echo $_lang['not_deleted']; ?>");
            } else {
                if(confirm("'" + selectedObjectName + "' <?php echo $_lang['confirm_undelete']; ?>")==true) {
                    top.main.document.location.href="index.php?a=63&id=" + itemToChange;
                }
            }
            break;
        case '64' : // delete
            if(selectedObjectDeleted==1) {
                if(confirm("'" + selectedObjectName + "'\n\n<?php echo $_lang['confirm_delete_resource']; ?>")==true) {
                    top.main.document.location.href="index.php?a=64&id=" + itemToChange;
                }
            } else {
                alert("'" + selectedObjectName + "' <?php echo $_lang['already_deleted']; ?>");
            }
            break;
        case '61' : // publish
            if(confirm("'" + selectedObjectName + "' <?php echo $_lang['confirm_publish']; ?>")==true) {
                setActiveFromContextMenu(itemToChange);
                top.main.document.location.href="index.php?a=61&id=" + itemToChange;
            }
            break;
        case '62' : // unpublish
            if (itemToChange != <?php echo $modx->config['site_start']?>) {
                if(confirm("'" + selectedObjectName + "' <?php echo $_lang['confirm_unpublish']; ?>")==true) {
                    setActiveFromContextMenu(itemToChange);
                    top.main.document.location.href="index.php?a=62&id=" + itemToChange;
                }
            } else {
                alert('Document is linked to site_start variable and cannot be unpublished!');
            }
            break;
        case 'pv' : // preview
            setActiveFromContextMenu(itemToChange);
            window.open(selectedObjectUrl,'previeWin'); //re-use 'new' window
            break;
        case '120' : // resources list
            setActiveFromContextMenu(itemToChange);
            top.main.document.location.href="index.php?a=120&id=" + itemToChange;
            break;

        default :
            alert('Unknown operation command.');
    }
}

</script>
<?php
function getTplCtxMenu() {
	$tpl = <<< EOT
<!-- Contextual Menu Popup Code -->
<div id="mx_contextmenu" onselectstart="return false;">
    <div id="nameHolder">&nbsp;</div>
		[+itemEditDoc+]
		[+itemDocList+]
		[+itemNewDoc+]
		[+itemMoveDoc+]
		[+itemDuplicateDoc+]
		[+=========1+]
		[+itemPubDoc+][+itemUnPubDoc+]
		[+itemDelDoc+][+itemUndelDoc+][+itemDelDocComplete+]
		[+=========2+]
		[+itemWebLink+]
		[+=========3+]
		[+itemDocInfo+]
		[+itemViewPage+]
	</div>
</div>
EOT;
	return $tpl;
}

$ph = array();
$ph['itemEditDoc']      = itemEditDoc(); // edit
$ph['itemDocList']      = itemDocList(); // Resource list
$ph['itemNewDoc']       = itemNewDoc(); // new Resource
$ph['itemMoveDoc']      = itemMoveDoc(); // move
$ph['itemDuplicateDoc'] = itemDuplicateDoc(); // duplicate
$ph['=========1']       = itemSeperator1();
$ph['itemPubDoc']       = itemPubDoc(); // publish
$ph['itemUnPubDoc']     = itemUnPubDoc(); // unpublish
$ph['itemDelDoc']       = itemDelDoc(); // delete
$ph['itemUndelDoc']     = itemUndelDoc(); // undelete
$ph['itemDelDocComplete']     = itemDelDocComplete(); // undelete
$ph['=========2']       = itemSeperator2();
$ph['itemWebLink']      = itemWebLink(); //  new Weblink
$ph['=========3']       = itemSeperator3();
$ph['itemDocInfo']      = itemDocInfo(); // undelete
$ph['itemViewPage']     = itemViewPage(); // preview

$tpl = getTplCtxMenu();
echo $modx->parseText($tpl,$ph);

?>
</body>
</html>
<?php
function select($cond=false)
{
	return ($cond) ? ' selected="selected"' : '';
}

function tplMenuItem() {
	$tpl = <<< EOT
<div class="menuLink" id="item[+action+]" onclick="menuHandler('[+action+]'); hideMenu();">
	<img src="[+img+]" />[+text+]
</div>
EOT;
	return $tpl;
}

function itemEditDoc() {
	global $modx,$_style,$_lang;
	
	if(!$modx->hasPermission('edit_document')) return '';
	$tpl = tplMenuItem();
	$ph['action'] = '27';
	$ph['img']    = $_style['icons_edit_document'];
	$ph['text']   = $_lang['edit_resource'];
	return $modx->parseText($tpl, $ph);
}

function itemDocList() {
	global $modx,$_style,$_lang;
	
	if(!$modx->hasPermission('view_document')) return '';
	$tpl = tplMenuItem();
	$ph['action'] = '120';
	$ph['img']    = $_style['icons_table'];
	$ph['text']   = $_lang['view_child_resources_in_container'];
	return $modx->parseText($tpl, $ph);
}

function itemNewDoc() {
	global $modx,$_style,$_lang;
	
	if(!$modx->hasPermission('new_document')) return '';
	$tpl = tplMenuItem();
	$ph['action'] = '4';
	$ph['img']    = $_style['icons_new_document'];
	$ph['text']   = $_lang['create_resource_here'];
	return $modx->parseText($tpl, $ph);
}

function itemMoveDoc() {
	global $modx,$_style,$_lang;
	
	if(!$modx->hasPermission('save_document')) return '';
	$tpl = tplMenuItem();
	$ph['action'] = '51';
	$ph['img']    = $_style['icons_move_document'];
	$ph['text']   = $_lang['move_resource'];
	return $modx->parseText($tpl, $ph);
}

function itemDuplicateDoc() {
	global $modx,$_style,$_lang;
	
	if(!$modx->hasPermission('new_document')||!$modx->hasPermission('save_document')) return '';
	$tpl = tplMenuItem();
	$ph['action'] = '94';
	$ph['img']    = $_style['icons_resource_duplicate'];
	$ph['text']   = $_lang['resource_duplicate'];
	return $modx->parseText($tpl, $ph);
}

function itemSeperator1() {
	global $modx;
	
	if($modx->hasPermission('edit_document') || $modx->hasPermission('new_document')|| $modx->hasPermission('save_document'))
		return '<div class="seperator"></div>';
	else return '';
}

function itemPubDoc() {
	global $modx,$_style,$_lang;
	
	if(!$modx->hasPermission('publish_document')) return '';
	$tpl = tplMenuItem();
	$ph['action'] = '61';
	$ph['img']    = $_style['icons_publish_document'];
	$ph['text']   = $_lang['publish_resource'];
	return $modx->parseText($tpl, $ph);
}

function itemUnPubDoc() {
	global $modx,$_style,$_lang;
	
	if(!$modx->hasPermission('publish_document')) return '';
	$tpl = tplMenuItem();
	$ph['action'] = '62';
	$ph['img']    = $_style['icons_unpublish_resource'];
	$ph['text']   = $_lang['unpublish_resource'];
	return $modx->parseText($tpl, $ph);
}

function itemDelDoc() {
	global $modx,$_style,$_lang;
	
	if(!$modx->hasPermission('delete_document')) return '';
	$tpl = tplMenuItem();
	$ph['action'] = '6';
	$ph['img']    = $_style['icons_delete'];
	$ph['text']   = $_lang['delete_resource'];
	return $modx->parseText($tpl, $ph);
}

function itemUndelDoc() {
	global $modx,$_style,$_lang;
	
	if(!$modx->hasPermission('delete_document')) return '';
	$tpl = tplMenuItem();
	$ph['action'] = '63';
	$ph['img']    = $_style['icons_undelete_resource'];
	$ph['text']   = $_lang['undelete_resource'];
	return $modx->parseText($tpl, $ph);
}

function itemDelDocComplete() {
	global $modx,$_style,$_lang;
	
	if(!$modx->hasPermission('empty_trash')) return '';
	$tpl = tplMenuItem();
	$ph['action'] = '64';
	$ph['img']    = $_style['icons_delete_complete'];
	$ph['text']   = $_lang['delete_resource_complete'];
	return $modx->parseText($tpl, $ph);
}

function itemSeperator2() {
	global $modx;
	
	if($modx->hasPermission('publish_document') || $modx->hasPermission('delete_document'))
		return '<div class="seperator"></div>';
	else return '';
}

function itemWebLink() {
	global $modx,$_style,$_lang;
	
	if(!$modx->hasPermission('new_document')) return '';
	$tpl = tplMenuItem();
	$ph['action'] = '72';
	$ph['img']    = $_style['icons_weblink'];
	$ph['text']   = $_lang['create_weblink_here'];
	return $modx->parseText($tpl, $ph);
}

function itemSeperator3() {
	global $modx;
	
	if($modx->hasPermission('new_document'))
		return '<div class="seperator"></div>';
	else return '';
}

function itemDocInfo() {
	global $modx,$_style,$_lang;
	
	if(!$modx->hasPermission('view_document')) return '';
	$tpl = tplMenuItem();
	$ph['action'] = '3';
	$ph['img']    = $_style['icons_information'];
	$ph['text']   = $_lang['resource_overview'];
	return $modx->parseText($tpl, $ph);
}

function itemViewPage() {
	global $modx,$_style,$_lang;
	
	$tpl = tplMenuItem();
	$ph['action'] = 'pv';
	$ph['img']    = $_style['icons_information'];
	$ph['text']   = $_lang['preview_resource'];
	return $modx->parseText($tpl, $ph);
}
