<script type="text/javascript">
/* <![CDATA[ */

var imanager_url = '[+imanager_url+]';
var fmanager_url = '[+fmanager_url+]';
var prevWin;
var docMode = '[+docMode+]';

// save tree folder state
if (parent.tree) parent.tree.saveFolderState();

jQuery(function(){
	jQuery('#save a').click(function(){
    	documentDirty=false;
    	gotosave = true;
    	jQuery('#mutate').submit();
    	jQuery('#save').hide();
    	jQuery('input,textarea,select').addClass('readonly');
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
		if (confirm("[+lang_confirm_delete_draft_resource+]")==true){
			documentDirty=false;
			document.mutate.a.value=130;
			jQuery('#mutate').submit();
		}
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
    	if(pmode==1) {
            jQuery('#mutate').prop({'action':'[+preview_url+]','target':'prevWin'});
            jQuery('#mutate').submit();
            jQuery('#mutate').prop({'action':'index.php','target':'main'});
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
	jQuery('#pub_date').keydown(function(){
		jQuery('div#pub_datedp_container').hide();
		documentDirty=true;
	});
	jQuery('#pub_date').click(function(){
		jQuery('div#pub_datedp_container').show();
	});
	jQuery('#pub_date').next('a').click(function(){
		jQuery('#pub_date').val('');
		documentDirty=true;
		return true;
	});
	jQuery('#unpub_date').keydown(function(){
		jQuery('div#unpub_datedp_container').hide();
		documentDirty=true;
	});
	jQuery('#unpub_date').click(function(){
		jQuery('div#unpub_datedp_container').show();
	});
	jQuery('#unpub_date').next('a').click(function(){
		jQuery('#unpub_date').val('');
		documentDirty=true;
		return true;
	});
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

function change_url_suffix() {
	var a = document.getElementById("field_alias");
	var s = document.getElementById("url_suffix");
	if(0 < a.value.indexOf('.')) s.innerHTML = '';
	else s.innerHTML = '[+suffix+]';
}

/* ]]> */
</script>
<script type="text/javascript" src="media/browser/browser.js"></script>
