<script type="text/javascript">
	var imanager_url = '[+imanager_url+]';
	var fmanager_url = '[+fmanager_url+]';
</script>
<script type="text/javascript" src="media/browser/browser.js"></script>

<script type="text/javascript" src="media/calendar/datepicker.js"></script>
<script src="media/script/jquery/jquery.maskedinput.min.js" type="text/javascript"></script>
<script type="text/javascript">
/* <![CDATA[ */
function openprev(actionurl)
{
    window.open(actionurl,"prevWin");
    var pmode = [+preview_mode+];
	if(pmode==1)
	{
        document.mutate.target = "prevWin";
        document.mutate.method = "post";
        document.mutate.action = actionurl;
        document.mutate.mode.value = 'prev';
        document.mutate.submit();
	}
}

$j(function(){
	var dpOffset = [+datepicker_offset+];
	var dpformat = "[+datetime_format+]" + ' hh:mm:00';
	var dayNames = [+dayNames+];
	var monthNames = [+monthNames+];
	new DatePicker($('pub_date'),   {'yearOffset': dpOffset,'format':dpformat,'dayNames':dayNames,'monthNames':monthNames});
	new DatePicker($('unpub_date'), {'yearOffset': dpOffset,'format':dpformat,'dayNames':dayNames,'monthNames':monthNames});
	$j("#pub_date").mask(dpformat.replace(/[0Ya-z]/g,'9'));
	$j("#unpub_date").mask(dpformat.replace(/[0Ya-z]/g,'9'));
});

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

function deletedocument() {
	if (confirm("[+lang_confirm_delete_resource+]")==true) {
		document.location.href="index.php?id=" + document.mutate.id.value + "&a=6";
	}
}

function undeletedocument() {
	if (confirm("[+lang_confirm_undelete+]")==true) {
		document.location.href="index.php?id=" + document.mutate.id.value + "&a=63";
	}
}

function movedocument() {
	document.location.href="index.php?id=[+id+]&a=51";
}

function duplicatedocument(){
    if(confirm("[+lang_confirm_resource_duplicate+]")==true) {
        document.location.href="index.php?id=[+id+]&a=94";
    }
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

function clearKeywordSelection() {
	var opt = document.mutate.elements["keywords[]"].options;
	for (i = 0; i < opt.length; i++) {
		opt[i].selected = false;
	}
}

function clearMetatagSelection() {
	var opt = document.mutate.elements["metatags[]"].options;
	for (i = 0; i < opt.length; i++) {
		opt[i].selected = false;
	}
}

var curTemplate = -1;
var curTemplateIndex = 0;
function storeCurTemplate() {
	var dropTemplate = document.getElementById('template');
	if (dropTemplate) {
		for (var i=0; i<dropTemplate.length; i++) {
			if (dropTemplate[i].selected) {
				curTemplate = dropTemplate[i].value;
				curTemplateIndex = i;
			}
		}
	}
}
function changeTemplate() {
	var dropTemplate = document.getElementById('template');
	if (dropTemplate) {
		for (var i=0; i<dropTemplate.length; i++) {
			if (dropTemplate[i].selected) {
				newTemplate = dropTemplate[i].value;
				break;
			}
		}
	}
	if (curTemplate == newTemplate) {return;}

	documentDirty=false;
	document.mutate.a.value = [+action+];
	document.mutate.newtemplate.value = newTemplate;
	document.mutate.submit();
}

// Added for RTE selection
function changeRTE() {
	var whichEditor = document.getElementById('which_editor');
	if (whichEditor) {
		for (var i = 0; i < whichEditor.length; i++) {
			if (whichEditor[i].selected) {
				newEditor = whichEditor[i].value;
				break;
			}
		}
	}
	var dropTemplate = document.getElementById('template');
	if (dropTemplate) {
		for (var i = 0; i < dropTemplate.length; i++) {
			if (dropTemplate[i].selected) {
				newTemplate = dropTemplate[i].value;
				break;
			}
		}
	}

	documentDirty=false;
	document.mutate.a.value = [+action+];
	document.mutate.newtemplate.value = newTemplate;
	document.mutate.which_editor.value = newEditor;
	document.mutate.submit();
}

/* ]]> */
</script>
