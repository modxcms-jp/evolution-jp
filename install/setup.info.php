<?php
#:: MODx Installer Setup file 
#:::::::::::::::::::::::::::::::::::::::::

	$moduleName = "MODx";
	$moduleVersion = " 0.9.6.3";
	$moduleSQLBaseFile = "setup.sql";
	$moduleSQLDataFile = "setup.data.sql";
	$moduleSQLUpdateFile = "setup.updates.sql";

	# setup chunks template files - array : name, description, type - 0:file or 1:content, file or content
	$mc = &$moduleChunks;
	$mc[] = array("WebLoginSideBar","サイドバーのウェブログインフォーム",0,"$setupPath/chunk.weblogin.sidebar.tpl");

	# setup snippets template files - array : name, description, type - 0:file or 1:content, file or content,properties
	$ms = &$moduleSnippets;
	$ms[] = array("AjaxSearch","<strong>1.8.1</strong> 検索結果のハイライト表示とAjax機能が利用可能な検索フォーム",0,"$setupPath/snippet.ajaxSearch.tpl","");
	$ms[] = array("Breadcrumbs","<strong>1.0.1</strong> 設定が変更可能なパンくずリスト",0,"$setupPath/snippet.breadcrumbs.tpl","");
	$ms[] = array("Ditto","<strong>2.1</strong>+ ブログ、索引・目録、広報、履歴の一覧表示などのためにページを要約・一覧表示。ソートのバグとデフォルト表示の動作を修正するための2.1リリース後のパッチを含む",0,"$setupPath/snippet.ditto.tpl","");
	$ms[] = array("eForm","<strong>1.4.4.5</strong> 検証機能、複数送信機能、フォームと通知用のチャンク・ページ、ファイルのアップロード機能が利用可能な多機能フォームパーサー・プロセッサー",0,"$setupPath/snippet.eform.tpl","");
	$ms[] = array("FirstChildRedirect","<strong>1.0</strong> フォルダドキュメントの最初の子ドキュメントへ自動的にリダイレクト",0,"$setupPath/snippet.firstchild.tpl","");
	$ms[] = array("Jot","<strong>1.1.4</strong> 承認機能とメール購読機能が利用可能なユーザーコメント",0,"$setupPath/snippet.jot.tpl","");
	$ms[] = array("ListIndexer","<strong>1.0.1</strong> 最新のドキュメントを柔軟に表示",0,"$setupPath/snippet.listindexer.tpl","");
	$ms[] = array("MemberCheck","<strong>1.0</strong> ウェブユーザーのログイン状態に基づいてチャンクを振り分けて表示",0,"$setupPath/snippet.membercheck.tpl","");
	$ms[] = array("NewsPublisher","<strong>1.4</strong> ウェブから直接ニュース記事を投稿",0,"$setupPath/snippet.newspublisher.tpl","");
	$ms[] = array("Personalize","<strong>2.0</strong> ウェブユーザーのための基本的なパーソナライズ化",0,"$setupPath/snippet.personalize.tpl","");
	$ms[] = array("Reflect","<strong>2.1</strong> Ditto共に使用することで記事・ブログエントリー・イメージギャラリーなどのアーカーブを作成",0,"$setupPath/snippet.reflect.tpl","");
	$ms[] = array("UltimateParent","<strong>2.0 beta</strong> - 指定されたドキュメントからドキュメントツリーを辿り、ルート直下の親ドキュメントを返す",0,"$setupPath/snippet.ultparent.tpl","");
	$ms[] = array("Wayfinder","<strong>2.0</strong> テンプレートと共に動作するシンプルで設定が容易なメニュービルダー",0,"$setupPath/snippet.wayfinder.tpl","");
	$ms[] = array("WebChangePwd","<strong>1.0</strong> ウェブユーザーのパスワード変更スニペット",0,"$setupPath/snippet.webchangepwd.tpl","&tpl=Template;string;");
	$ms[] = array("WebLogin","<strong>1.0</strong> ウェブユーザーのログインスニペット",0,"$setupPath/snippet.weblogin.tpl","&loginhomeid=Login Home Id;string; &logouthomeid=Logout Home Id;string; &logintext=Login Button Text;string; &logouttext=Logout Button Text;string; &tpl=Template;string;");
	$ms[] = array("WebSignup","<strong>1.1</strong> ウェブユーザーのサインアップスニペット",0,"$setupPath/snippet.websignup.tpl","&tpl=Template;string;");

	# setup plugins template files - array : name, description, type - 0:file or 1:content, file or content,properties
	$mp = &$modulePlugins;
	$mp[] = array("Bottom Button Bar","各種設定画面の下端に更新ボタンセットを表示",0,"$setupPath/plugin.bottombuttonbar.tpl","","OnChunkFormRender,OnDocFormRender,OnModFormRender,OnPluginFormRender,OnSnipFormRender,OnTVFormRender,OnTempFormRender,OnUserFormRender,OnWUsrFormRender");
	$mp[] = array("Forgot Manager Login","管理画面のログインパスワードを再発行",0,"$setupPath/plugin.ForgotManagerPassword.tpl","","OnBeforeManagerLogin,OnManagerAuthentication,OnManagerLoginFormRender");
	$mp[] = array("Inherit Parent Template","ドキュメント新規作成時に親フォルダのテンプレート設定を継承",0,"$setupPath/plugin.inherit-parent-tpl.tpl","","OnDocFormPrerender");
	$mp[] = array("QuickEdit","表示しているページを手軽に編集するためのツールバー",0,"$setupPath/quickedit.plugin.tpl","","OnParseDocument,OnWebPagePrerender","f888bac76e1537ca8e0cbec772b4624a");
	$mp[] = array("TinyMCE","<strong>3.2.2:</strong>TinyMCE リッチテキストエディター",0,"$setupPath/plugin.tinymce.tpl","&customparams=Custom Parameters;textarea; &tinyFormats=Block Formats;text;p,h1,h2,h3,h4,h5,h6,div,blockquote,code,pre,address &entity_encoding=Entity Encoding;list;named,numeric,raw;named &entities=Entities;text; &tinyPathOptions=Path Options;list;rootrelative,docrelative,fullpathurl;docrelative &tinyCleanup=Cleanup;list;enabled,disabled;enabled &tinyResizing=Advanced Resizing;list;true,false;true &advimage_styles=Advanced Image Styles;text; &advlink_styles=Advanced Link Styles;text; &disabledButtons=Disabled Buttons;text; &tinyLinkList=Link List;list;enabled,disabled;enabled &webtheme=Web Theme;list;simple,advanced,editor,custom;simple &webPlugins=Web Plugins;text;style,advimage,advlink,searchreplace,print,contextmenu,paste,fullscreen,nonbreaking,xhtmlxtras,visualchars,media &webButtons1=Web Buttons 1;text;undo,redo,selectall,separator,pastetext,pasteword,separator,search,replace,separator,nonbreaking,hr,charmap,separator,image,link,unlink,anchor,media,separator,cleanup,removeformat,separator,fullscreen,print,code,help &webButtons2=Web Buttons 2;text;bold,italic,underline,strikethrough,sub,sup,separator,separator,blockquote,bullist,numlist,outdent,indent,separator,justifyleft,justifycenter,justifyright,justifyfull,separator,styleselect,formatselect,separator,styleprops &webButtons3=Web Buttons 3;text; &webButtons4=Web Buttons 4;text; &webAlign=Web Toolbar Alignment;list;ltr,rtl;ltr","OnRichTextEditorRegister,OnRichTextEditorInit,OnInterfaceSettingsRender");
	$mp[] = array("Search Highlighting","<strong>1.3</strong> - 検索結果ページ中のキーワードを分かりやすくハイライト表示 (AjaxSearchが必要)",0,"$setupPath/plugin.searchhighlight.tpl","","OnWebPagePrerender");
	$mp[] = array("Image TV Preview","<strong>1.2.0.4</strong> - イメージ入力用テンプレート変数に設定されたイメージをプレビュー",0,"$setupPath/plugin.imageTVpreview.tpl","","OnDocFormRender");

	# setup modules - array : name, description, type - 0:file or 1:content, file or content,properties, guid,enable_sharedparams
	$mm = &$moduleModules;
	$mm[] = array("Doc Manager","テンプレート・権限・公開／非公開などのドキュメント設定を一括変更",0,"$setupPath/module.docmanager.tpl","","",1);
	$mm[] = array("QuickEdit","表示しているページを手軽に編集するためのツールバー",0,"$setupPath/quickedit.module.tpl","&mod_path=Module Path (from site root);string;assets/modules/quick_edit &show_manager_link=Show Manager Link;int;1 &show_help_link=Show Help Link;int;1 &editable=Editable Fields;string;pagetitle,longtitle,description,content,alias,introtext,menutitle,published,hidemenu,menuindex,searchable,cacheable,template","f888bac76e1537ca8e0cbec772b4624a",1);

	# setup callback function
	$callBackFnc = "clean_up";
	
	function clean_up($sqlParser) {
		$ids = array();
		$mysqlVerOk = -1;

		if(function_exists("mysql_get_server_info")) {
			$mysqlVerOk = (version_compare(mysql_get_server_info(),"4.0.2")>=0);
		}	
		
		// secure web documents - privateweb 
		mysql_query("UPDATE `".$sqlParser->prefix."site_content` SET privateweb = 0 WHERE privateweb = 1",$sqlParser->conn);
		$sql =  "SELECT DISTINCT sc.id 
				 FROM `".$sqlParser->prefix."site_content` sc
				 LEFT JOIN `".$sqlParser->prefix."document_groups` dg ON dg.document = sc.id
				 LEFT JOIN `".$sqlParser->prefix."webgroup_access` wga ON wga.documentgroup = dg.document_group
				 WHERE wga.id>0";
		$ds = mysql_query($sql,$sqlParser->conn);
		if(!$ds) {
			echo "An error occurred while executing a query: ".mysql_error();
		}
		else {
			while($r = mysql_fetch_assoc($ds)) $ids[]=$r["id"];
			if(count($ids)>0) {
				mysql_query("UPDATE `".$sqlParser->prefix."site_content` SET privateweb = 1 WHERE id IN (".implode(", ",$ids).")");	
				unset($ids);
			}
		}
		
		// secure manager documents privatemgr
		mysql_query("UPDATE `".$sqlParser->prefix."site_content` SET privatemgr = 0 WHERE privatemgr = 1");
		$sql =  "SELECT DISTINCT sc.id 
				 FROM `".$sqlParser->prefix."site_content` sc
				 LEFT JOIN `".$sqlParser->prefix."document_groups` dg ON dg.document = sc.id
				 LEFT JOIN `".$sqlParser->prefix."membergroup_access` mga ON mga.documentgroup = dg.document_group
				 WHERE mga.id>0";
		$ds = mysql_query($sql);
		if(!$ds) {
			echo "An error occurred while executing a query: ".mysql_error();
		}
		else {
			while($r = mysql_fetch_assoc($ds)) $ids[]=$r["id"];
			if(count($ids)>0) {
				mysql_query("UPDATE `".$sqlParser->prefix."site_content` SET privatemgr = 1 WHERE id IN (".implode(", ",$ids).")");	
				unset($ids);
			}		
		}

		/**** Add Quick Plugin to Module ***/
		// get quick edit module id
		$ds = mysql_query("SELECT id FROM `".$sqlParser->prefix."site_modules` WHERE name='QuickEdit'");
		if(!$ds) {
			echo "An error occurred while executing a query: ".mysql_error();
		}
		else {
			$row = mysql_fetch_assoc($ds);
			$moduleid=$row["id"];
		}		
		// get plugin id
		$ds = mysql_query("SELECT id FROM `".$sqlParser->prefix."site_plugins` WHERE name='QuickEdit'");
		if(!$ds) {
			echo "An error occurred while executing a query: ".mysql_error();
		}
		else {
			$row = mysql_fetch_assoc($ds);
			$pluginid=$row["id"];
		}		
		// setup plugin as module dependency
		$ds = mysql_query("SELECT module FROM `".$sqlParser->prefix."site_module_depobj` WHERE module='$moduleid' AND resource='$pluginid' AND type='30' LIMIT 1"); 
		if(!$ds) {
			echo "An error occurred while executing a query: ".mysql_error();
		}
		elseif (mysql_num_rows($ds)==0){
			mysql_query("INSERT INTO `".$sqlParser->prefix."site_module_depobj` (module, resource, type) VALUES('$moduleid','$pluginid',30)");
		}
	}
?>
