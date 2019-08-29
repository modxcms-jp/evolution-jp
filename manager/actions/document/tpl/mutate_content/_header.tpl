[+JScripts+]
<form name="mutate" id="mutate" class="content" method="post" enctype="multipart/form-data" action="index.php" onsubmit="documentDirty=false;">
	<input type="hidden" name="a" value="[+a+]" />
	<input type="hidden" name="id" value="[+id+]" />
	<input type="hidden" name="mode" value="[+mode+]" />
	<input type="hidden" name="MAX_FILE_SIZE" value="[+upload_maxsize+]" />
	<input type="hidden" name="newtemplate" value="" />
	<input type="hidden" name="pid" value="[+pid+]" />
	<input type="hidden" name="token" value="[+token+]" />
	<input type="submit" name="save" style="display:none" />
	[+OnDocFormPrerender+]
	
	<fieldset id="create_edit">
	<h1 class="[+class+]">[+title+]</h1>

	[+actionButtons+]

	<div class="sectionBody">
	<div class="tab-pane" id="documentPane">
