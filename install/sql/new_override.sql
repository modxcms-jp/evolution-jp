# MODX Database Script for Override Installations


REPLACE INTO `{PREFIX}site_templates` 
(id, templatename, description, content) VALUES ('1','Minimal Template','Default minimal empty template','<!DOCTYPE html PUBLIC \"-//W3C//DTD XHTML 1.0 Transitional//EN\" \"http://www.w3.org/TR/xhtml1/DTD/xhtml1-transitional.dtd\">\n<html xmlns=\"http://www.w3.org/1999/xhtml\">\n<head>\n    <title>[*pagetitle*] | [(site_name)]</title> <!--リソース変数pagetitleとコンフィグ変数site_name-->\n    <meta http-equiv=\"Content-Type\" content=\"text/html; charset=[(modx_charset)]\" /> <!--コンフィグ変数modx_charset-->\n  <base href=\"[(site_url)]\" />                   <!--コンフィグ変数site_url-->\n</head>\n<body>\n    <h1>[*pagetitle*]</h1>                       <!--リソース変数pagetitle-->\n     [*content*]                                 <!--リソース変数content-->\n</body>\n</html>\n');

REPLACE INTO `{PREFIX}site_content` VALUES (1,'document','text/html','最初のページ','これは最初のページです。','','begin','',1,0,0,0,0,'','<h3>MODXへようこそ。</h3>\n<p>MODXの操作は簡単。まずは管理画面左側のサイトツリーを右クリック。操作メニューが表示されます。ページごとにURLを自由に設定したい場合は、フレンドリーURL設定を有効にしてください。</p>\n\n<h3>MODXの使い方</h3>\n<p><a href=\"http://modx.jp/docs.html\">ドキュメントはこちら。</a>\n<p>よくある質問は<a href=\"http://modx.jp/docs/faq.html\">こちら</a>。</p>\n',1,1,0,1,1,1,{DATE_NOW},1,{DATE_NOW},0,0,0,{DATE_NOW},1,'初期ページ',0,0,0,0,0,0,0);

REPLACE INTO `{PREFIX}user_roles` 
(id,name,description) VALUES 
('2','ウェブマスター','全ての権限を持ちます。ロール編集権限を持つため必要に応じて特権ロールに昇格できます。'),
('3','投稿担当者','投稿作業に関する権限のみを持ちます。');

