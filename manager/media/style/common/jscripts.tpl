<script type="text/javascript">
	var imanager_url = '[+imanager_url+]';
	var fmanager_url = '[+fmanager_url+]';
</script>
<script type="text/javascript" src="media/browser/browser.js"></script>
<script type="text/javascript">
jQuery(function(){
	var prevWin;
	var docMode = '[+docMode+]';

	jQuery('#save a').click(function(){
    	documentDirty=false;
    	gotosave = true;
    	jQuery('#mutate').submit();
	});
	jQuery('#createdraft').click(function(){
    	document.location.href='index.php?a=132&id=[+id+]';
	});
	jQuery('#opendraft').click(function(){
    	document.location.href='index.php?a=131&id=[+id+]';
	});
	jQuery('#delete').click(function(){
    	if (confirm("[+lang_confirm_delete_resource+]")==true)
    		document.location.href="index.php?id=[+id+]&a=6";
	});
	jQuery('#undelete').click(function(){
    	if (confirm("[+lang_confirm_undelete+]")==true)
    		document.location.href="index.php?id=[+id+]&a=63";
	});
	jQuery('#deletedraft').click(function(){
		documentDirty=false;
		document.mutate.a.value=130;
		jQuery('#mutate').submit();
	});
	jQuery('#move').click(function(){
    	document.location.href="index.php?id=[+id+]&a=51";
	});
	jQuery('#duplicate').click(function(){
        if(confirm("[+lang_confirm_resource_duplicate+]")==true)
            document.location.href="index.php?id=[+id+]&a=94";
	});

	jQuery('#preview').click(function(){
            if( prevWin && !prevWin.closed ) {
                prevWin.close();
            }
        prevWin = window.open('[+preview_url+]','prevWin');
        var pmode = [+preview_mode+];
    	if(pmode==1)
    	{
        	jQuery('#mutate').attr({'action':'[+preview_url+]','target':'prevWin'});
            jQuery('#mutate').submit();
        	jQuery('#mutate').attr({'action':'index.php','target':'main'});
    	}
    	return false;
	});
	jQuery('#cancel').click(function(){
		var docIsFolder = '[+docIsFolder+]';
		var docParent   = '[+docParent+]';
		if(docMode=='draft')   document.location.href = 'index.php?a=3&id=' + '[+id+]';
    	else if(docIsFolder==1)document.location.href = 'index.php?a=120&id=' + '[+id+]';
    	else if(docParent!=0)  document.location.href = 'index.php?a=120&id=' + '[+docParent+]';
    	else                   document.location.href = 'index.php?a=2';
	});
	jQuery('#pub_date a').click(function(){
		jQuery('#pub_date').val('');
		documentDirty=true;
		return true;
	});
	var curTemplate = -1;
	curTemplate = jQuery('#template').val();
	jQuery('#template').change(function(){
		newTemplate = jQuery('#template').val();
		if (curTemplate != newTemplate) {
        	documentDirty=false;
        	jQuery('#mutate input[name="a"]').val([+action+]);
        	jQuery('#mutate input[name="newtemplate"]').val(newTemplate);
        	jQuery('#mutate').submit();
		}
	});
	jQuery('#which_editor').change(function(){
		newTemplate = jQuery('#template').val();
		newEditor   = jQuery('#which_editor').val();
    	documentDirty=false;
    	jQuery('#mutate input[name="a"]').val([+action+]);
    	jQuery('#mutate input[name="newtemplate"]').val(newTemplate);
    	jQuery('#which_editor').val(newEditor);
    	jQuery('#mutate').submit();
	});
});
</script>
<script type="text/javascript">
/* <![CDATA[ */

// save tree folder state
if (parent.tree) parent.tree.saveFolderState();

function changestate(element) {
	currval = eval(element).value;
	if (currval==1) {
		eval(element).value=0;
	} else {
		eval(element).value=1;
	}
	documentDirty=true;
}

function resetpubdate() {
	if(document.mutate.pub_date.value!=''||document.mutate.unpub_date.value!='') {
		if (confirm("[+lang_mutate_content.dynamic.php1+]")==true) {
			document.mutate.pub_date.value='';
			document.mutate.unpub_date.value='';
		}
	}
	documentDirty=true;
}

var allowParentSelection = false;
var allowLinkSelection = false;

function enableLinkSelection(b) {
	parent.tree.ca = "link";
	var closed = "[+style_tree_folder+]";
	var opened = "[+style_icons_set_parent+]";
	if (b) {
		document.images["llock"].src = opened;
		allowLinkSelection = true;
	}
	else {
		document.images["llock"].src = closed;
		allowLinkSelection = false;
	}
}

function setLink(lId) {
	if (!allowLinkSelection) {
		window.location.href="index.php?a=3&id="+lId;
		return;
	}
	else {
		documentDirty=true;
		document.mutate.ta.value=lId;
	}
}

function enableParentSelection(b) {
	parent.tree.ca = "parent";
	var opened = "[+style_icons_set_parent+]";
	var closed = "[+style_tree_folder+]";
	if (b) {
		document.images["plock"].src = opened;
		allowParentSelection = true;
	}
	else {
		document.images["plock"].src = closed;
		allowParentSelection = false;
	}
}

function setParent(pId, pName) {
	if (!allowParentSelection) {
		window.location.href="index.php?a=3&id="+pId;
		return;
	}
	else {
		if (pId==0 || checkParentChildRelation(pId, pName)) {
			documentDirty=true;
			document.mutate.parent.value=pId;
			var elm = document.getElementById('parentName');
			if (elm) {
				elm.innerHTML = (pId + " (" + pName + ")");
			}
		}
	}
}

// check if the selected parent is a child of this document
function checkParentChildRelation(pId, pName) {
	var sp;
	var id = document.mutate.id.value;
	var tdoc = parent.tree.document;
	var pn = (tdoc.getElementById) ? tdoc.getElementById("node"+pId) : tdoc.all["node"+pId];
	if (!pn) return;
	if (pn.id.substr(4)==id) {
		alert("[+lang_illegal_parent_self+]");
		return;
	}
	else {
		while (pn.getAttribute("p")>0) {
			pId = pn.getAttribute("p");
			pn = (tdoc.getElementById) ? tdoc.getElementById("node"+pId) : tdoc.all["node"+pId];
			if (pn.id.substr(4)==id) {
				alert("[+lang_illegal_parent_child+]");
				return;
			}
		}
	}
	return true;
}

/* ]]> */
</script>
