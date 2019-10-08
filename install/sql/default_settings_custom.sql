# MODX Database Script for Override Installations


REPLACE INTO `{PREFIX}site_templates` 
(id, templatename, description, editor_type, category, icon, template_type, content, locked) VALUES ('1','Minimal Template','Default minimal empty template','0','0','','0','<!DOCTYPE html PUBLIC \"-//W3C//DTD XHTML 1.0 Transitional//EN\" \"http://www.w3.org/TR/xhtml1/DTD/xhtml1-transitional.dtd\">\n<html xmlns=\"http://www.w3.org/1999/xhtml\">\n<head>\n    <title>[*pagetitle*] | [(site_name)]</title> <!--リソース変数pagetitleとコンフィグ変数site_name-->\n    <meta http-equiv=\"Content-Type\" content=\"text/html; charset=[(modx_charset)]\" /> <!--コンフィグ変数modx_charset-->\n  <base href=\"[(site_url)]\" />                   <!--コンフィグ変数site_url-->\n</head>\n<body>\n    <h1>[*pagetitle*]</h1>                       <!--リソース変数pagetitle-->\n     [*content*]                                 <!--リソース変数content-->\n</body>\n</html>\n','0');

TRUNCATE TABLE `{PREFIX}site_content`;

INSERT INTO `{PREFIX}site_content` VALUES (1,'document','text/html','最初のページ','これは最初のページです。','','begin','',1,0,0,0,0,'','<h3>MODXへようこそ。</h3>\n<p>MODXの操作は簡単。まずは管理画面左側のサイトツリーを右クリック。操作メニューが表示されます。ページごとにURLを自由に設定したい場合は、フレンドリーURL設定を有効にしてください。</p>\n\n<h3>MODXの使い方</h3>\n<p><a href=\"http://modx.jp/docs.html\">ドキュメントはこちら。</a>\n<p>よくある質問は<a href=\"http://modx.jp/docs/faq.html\">こちら</a>。</p>\n',1,1,0,1,1,1,{DATE_NOW},1,{DATE_NOW},0,0,0,{DATE_NOW},1,'初期ページ',0,0,0,0,0,0,0,1);

REPLACE INTO `{PREFIX}user_roles` 
(id,name,description,frames,home,view_document,new_document,save_document,move_document,publish_document,delete_document,empty_trash,action_ok,logout,help,messages,new_user,edit_user,logs,edit_parser,save_parser,edit_template,settings,credits,new_template,save_template,delete_template,edit_snippet,new_snippet,save_snippet,delete_snippet,edit_chunk,new_chunk,save_chunk,delete_chunk,empty_cache,edit_document,change_password,error_dialog,about,file_manager,save_user,delete_user,save_password,edit_role,save_role,delete_role,new_role,access_permissions,bk_manager,new_plugin,edit_plugin,save_plugin,delete_plugin,new_module,edit_module,save_module,exec_module,delete_module,view_eventlog,delete_eventlog,manage_metatags,edit_doc_metatags,new_web_user,edit_web_user,save_web_user,delete_web_user,web_access_permissions,view_unpublished,import_static,export_static,remove_locks,view_schedule) VALUES 
('2','ウェブマスター','全ての権限を持ちます。ロール編集権限を持つため必要に応じて特権ロールに昇格できます。',1,1,1,1,1,1,1,1,1,1,1,1,1,1,1,1,1,1,1,1,1,1,1,1,1,1,1,1,1,1,1,1,1,1,1,1,1,1,1,1,1,1,1,1,1,1,1,1,1,1,1,1,1,1,1,1,1,1,1,1,1,1,1,1,1,1,1,1,1,1),
('3','投稿担当者','Limited to managing content',1,1,1,1,1,1,1,0,1,1,1,0,0,0,0,0,0,0,0,1,0,0,0,0,0,0,0,1,0,1,0,1,1,1,1,1,1,0,0,1,0,0,0,0,0,0,0,0,0,0,0,0,0,1,0,0,0,0,0,0,0,0,0,0,1,0,0,1,1,1);

