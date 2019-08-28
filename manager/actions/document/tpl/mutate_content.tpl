[+JScripts+]
<form
	name="mutate"
	id="mutate"
	class="content"
	method="post"
	enctype="multipart/form-data"
	action="index.php"
	onsubmit="documentDirty=false;"
>
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
	<h1 class="[+class+]">[+title+][+(ID:%s)+]</h1>

	[+actionButtons+]

	<div class="sectionBody">
	<div class="tab-pane" id="documentPane">
<!-- start main wrapper -->
	<!-- General -->
	<div class="tab-page" id="tabGeneral">
		<h2 class="tab" id="tabGeneralHeader">[+_lang_settings_general+]</h2>
		<table width="99%" border="0" cellspacing="5" cellpadding="0">
			[+fieldPagetitle+]
			[+fieldLongtitle+]
			[+fieldDescription+]
			[+fieldAlias+]
			[+fieldWeblink+]
			[+fieldIntrotext+]
			[+fieldTemplate+]
			[+fieldMenutitle+]
			[+fieldMenuindex+]
			[+renderSplit+]
			[+fieldParent+]
		</table>
		[+sectionContent+]
		[+sectionTV+]
	</div><!-- end #tabGeneral -->
