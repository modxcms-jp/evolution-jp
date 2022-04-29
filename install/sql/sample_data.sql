--
-- MODx Database Script for New/Upgrade Installations
--
-- Each sql command is separated by double lines

--
-- Dumping data for table `site_content`
--

TRUNCATE TABLE `{PREFIX}site_content`;

INSERT INTO `{PREFIX}site_content`
VALUES ('1', 'document', 'text/html', 'Home', 'Welcome to MODX', 'このサンプルサイトが個性的なウェブサイトを構築するためのヒントになれば幸いです。', 'index',
        '', '1', '0', '0', '0', '0', 'Create and do amazing things with MODX',
        '<div class=\"row\">\n[[Ditto?\n&display=3\n&parents=8\n&orderBy=''menuindex ASC''\n&tpl=''@CODE:\n    <div class=\"col-md-4\">\n      <h2 class=\"h3\">[+pagetitle+]</h2>\n      <p>[+content:strip_tags:summary+]  </p>\n      <p><a role=\"button\" href=\"[+url+]\" class=\"btn btn-default\">View details »</a></p>\n    </div>\n''\n]]\n</div>\n',
        '0', '2', '1', '1', '1', '1', '1144904400', '1', '1338330033', '0', '0', '0', '1144904400', '0', 'Home', '0',
        '0', '0', '0', '0', '0', '1', '1');

INSERT INTO `{PREFIX}site_content`
VALUES ('2', 'document', 'text/html', '新着情報', '新着情報の一覧', '', 'news', '', '1', '0', '0', '0', '1', '',
        '<dl class=\"dl-horizontal\">\n[[Ditto?\n&parents=2\n&orderBy=publishedon\n&tpl=''@CODE:\n<dt>[+createdon:date=`%Y年%-m月%-d日`+]</dt>\n<dd><a href=\"[+url+]\">[+pagetitle+]</a></dd>\n''\n]]\n</dl>\n',
        '0', '2', '2', '0', '1', '1', '1144904400', '1', '1159818696', '0', '0', '0', '1144904400', '0', '新着情報', '0',
        '0', '0', '0', '0', '0', '0', '1');

INSERT INTO `{PREFIX}site_content`
VALUES ('3', 'document', 'text/html', 'お問い合わせ', 'お問い合わせ [(site_name)]', '', 'contact', '', '1', '0', '0', '0', '1', '',
        '[[eForm\n   &tpl      = `14`              // 【必須】フォーム本体のHTMLコード(リソースIDで指定)\n   &report   = `15`              // 【必須】送信するメールのひな型(リソースIDで指定)\n   &subject  = `[+subject+]`     // 送信するメールのタイトル。任意の文字列または変数を指定できます\n   &to       = `[(emailsender)]` // メールの送り先。メールアドレス文字列または変数を指定できます\n   &ccsender = `1`               // 送信主にもCCを送る場合は値を1に\n   &gotoid   = `13`              // 送信完了後に表示するページのリソースID\n]]\n',
        '0', '2', '5', '1', '1', '1', '1144904400', '1', '1159303922', '0', '0', '0', '1144904400', '0', 'お問い合わせ', '0',
        '0', '0', '0', '0', '0', '0', '1');

INSERT INTO `{PREFIX}site_content`
VALUES ('4', 'document', 'text/html', '404 - Document Not Found', 'お探しのページが見当たりません (Page Not Found)', '',
        'doc-not-found', '', '1', '0', '0', '0', '0', '',
        '<p>\n存在しないページへアクセスしたようです。 ログインするか、 以下のページにアクセスしてください:\n</p>\n<div>[[Wayfinder? &startId=`0` &showDescription=`1`]]</div>\n\n',
        '1', '2', '10', '0', '1', '1', '1144904400', '1', '1159301173', '0', '0', '0', '1144904400', '0', '', '0', '0',
        '0', '0', '0', '0', '1', '1');

INSERT INTO `{PREFIX}site_content`
VALUES ('5', 'document', 'text/html', '新サービスのお知らせ', '新サービスのお知らせ', '', 'newservice', '', '1', '0', '0', '2', '0', '',
        '<p>新サービスのお知らせです。</p>\n', '1', '2', '0', '1', '1', '1', '1300505696', '1', '1300505697', '0', '0', '0',
        '1300505696', '0', '', '0', '0', '0', '0', '0', '0', '1', '1');

INSERT INTO `{PREFIX}site_content`
VALUES ('6', 'document', 'application/rss+xml', 'RSS フィード', '[(site_name)] RSSフィード', 'RSSフィードのサンプルです。', 'feed.rss', '',
        '1', '0', '0', '0', '0', '', '[[Ditto?\n   &parents=`2`\n   &format=`rss`\n]]', '0', '0', '11', '0', '1', '1',
        '1144904400', '1', '1160062859', '0', '0', '0', '1144904400', '0', '', '0', '0', '0', '0', '0', '0', '1', '1');

INSERT INTO `{PREFIX}site_content`
VALUES ('7', 'document', 'text/html', 'サイトをオープンしました。', 'サイトをオープンしました', '', 'begin', '', '1', '0', '0', '2', '0', '',
        '<p>サイトをオープンしました。MODXで作りました。</p>\n', '1', '2', '2', '1', '1', '1', '1299728096', '1', '1299728097', '0', '0',
        '0', '1299728096', '0', '', '0', '0', '0', '0', '0', '0', '1', '1');

INSERT INTO `{PREFIX}site_content`
VALUES ('8', 'document', 'text/html', 'MODXの主な特長', 'MODXの主な特長', 'デザインワークの自由度が高く、動作が軽い。MODXは、スキルに自信があるデザイナーの強い味方です。',
        'features', '', '1', '0', '0', '0', '1', '',
        '<p>コアが直接出力するhtmlコードがほとんどないMODXは、Ajaxテクニックを自由自在に扱うことができます。アクセシビリティの高い、正しいCSSレイアウトのサイト管理も簡単にできます。ウェブ標準に則ったサイト作成が簡単にできます。（もし必要なら、tableタグに依存したレイアウトも簡単です）</p>',
        '0', '2', '3', '1', '1', '1', '1144904400', '1', '1338330246', '0', '0', '0', '1144904400', '1', 'MODXの特長', '0',
        '0', '0', '0', '0', '0', '0', '1');

INSERT INTO `{PREFIX}site_content`
VALUES ('9', 'document', 'text/html', '拡張性豊かなデザインワーク', '拡張性豊かなデザインワーク', '', 'extendable', '', '1', '1159264800', '0',
        '8', '0', '',
        '<p><strong>自由自在なデザインワーク</strong></p>\n<p>コアが直接出力するhtmlコードがほとんどないMODXは、Ajaxテクニックを自由自在に扱うことができます。アクセシビリティの高い、正しいCSSレイアウトのサイト管理も簡単にできます。ウェブ標準に則ったサイト作成が簡単にできます。(もし必要なら、tableタグに依存したレイアウトも簡単です)</p>',
        '1', '2', '1', '1', '1', '1', '1144904400', '1', '1338331550', '0', '0', '0', '1159264800', '0', 'デザインワーク', '0',
        '0', '0', '0', '0', '0', '0', '1');

INSERT INTO `{PREFIX}site_content`
VALUES ('10', 'document', 'text/html', 'メニューとリスト', '自由度が高いメニューとリスト', '', 'menus', '', '1', '1159178400', '0', '8', '0',
        '',
        '<p>ナビゲーション表示はWayfinder（ウェイファインダー）、複数コンテンツのリスト表示はDitto（ディトーまたはディット）を使います。</p>\n<h3>Wayfinder - メニュー生成スニペット</h3>\n<p>このサイトでは、Wayfinderはグローバルナビゲーションの生成に用いていますが、ドロップダウンなどあらゆるタイプのメニューを生成できます。サイトマップやパン屑リストの出力に用いることもできます。</p>\n<h3>Ditto（ディトー - 文章のリストアップスニペット）</h3>\n<p>新着情報の一覧を生成したり、サイトマップを作ったり、テンプレート変数との組み合わせで関連文書をリストアップしたり、RSSフィードの生成を行ったりします。Wayfinderとは異なるアプローチでナビゲーションを作ることもできます。このサイトでは、新着情報の記事一覧の表示に使われています。</p>',
        '1', '2', '2', '1', '1', '1', '1144904400', '1', '1338331594', '0', '0', '0', '1159178400', '0', 'メニューとリスト',
        '0', '0', '0', '0', '0', '0', '0', '1');

INSERT INTO `{PREFIX}site_content`
VALUES ('11', 'document', 'text/html', 'コンテンツ管理', 'コンテンツ管理', '', 'cms', '', '1', '0', '0', '8', '0', '',
        '<h3>管理画面からコンテンツ管理</h3>\n<p>管理画面では、コンテンツを新規追加したり、テンプレートを調整したり、ウェブサイトを構成する各種パーツの管理が簡単にできます。ユーザグループごとに、管理画面の操作権限を設定することもできます。また、モジュールを追加して、他のデータセットと連動したり、管理業務を簡易化することも可能です。</p>\n<h3>ウェブページ側からコンテンツ管理</h3>\n<p>QuickManager（クイックマネージャー）を使えば、サイトをブラウザーで見ながら、ページの内容を編集できます。管理画面を経由せず、ほとんどのコンテンツ要素とテンプレート変数を手軽に編集できます。</p>\n<h3>テンプレート変数はデータバインディングが可能</h3>\n<p>「テンプレート変数」は、高機能なカスタムフィールドです。単なるテキストの入力項目ではなく、PHP処理と連動した柔軟なコントロールが可能です。</p>',
        '1', '2', '3', '1', '1', '1', '1144904400', '1', '1338331537', '0', '0', '0', '1144904400', '0', 'コンテンツ管理', '0',
        '0', '0', '0', '0', '0', '0', '1');

INSERT INTO `{PREFIX}site_content`
VALUES ('12', 'document', 'text/html', 'サポート', 'サポート', '', 'geting-help', '', '1', '0', '0', '0', '0', '',
        '<p>\n<a href=\"http://modx.jp/\" target=\"_blank\">MODX開発チーム</a>はドキュメントの改良に努めています。\n</p>\n<ul>\n	<li>MODXのテンプレート構築に関する基本的なノウハウについては、<a href=\"http://modx.jp/docs.html\" target=\"_blank\">デザイナーズガイドをご覧ください</a>。</li>\n	<li>MODXを利用したコンテンツの編集方法については、<a href=\"http://modx.jp/docs.html\" target=\"_blank\">コンテンツエディターガイドをご覧ください</a>。</li>\n	<li>管理ツールの詳細とユーザーやグループの設定については、<a href=\"http://modx.jp/docs.html\" target=\"_blank\">アドミニストレーションガイドを精読してください</a>。</li>\n	<a href=\"http://modx.jp/docs.html\" target=\"_blank\">デベロッパーズガイドで</a>MODXの構造とAPIについて記述しています。\n	<li>もし誰かがこのサイトをインストールしていて、それを見たあなた自身がMODXについて知りたくなったとしたら、<a href=\"http://modx.jp/docs.html\" target=\"_blank\">スタートガイドをご覧ください</a>。</li>\n</ul>\n<p>\n</p>',
        '1', '2', '4', '1', '1', '1', '1144904400', '1', '1144904400', '0', '0', '0', '1144904400', '0', 'サポート', '0',
        '0', '0', '0', '0', '0', '0', '1');

INSERT INTO `{PREFIX}site_content`
VALUES ('13', 'document', 'text/html', 'ありがとうございます', '', '', 'thanks', '', '1', '0', '0', '3', '0', '',
        '<h3>ありがとうございます!</h3>\n<p>\nお問い合わせを受け付けました。また、あなたのメールアドレスに送信内容のコピーが届いているはずです。\n</p>\n<p>\n内容をチェックし、お返事いたします。\n</p>',
        '1', '2', '12', '1', '1', '1', '1159302141', '1', '1159302892', '0', '0', '0', '1159302141', '1', '', '0', '0',
        '0', '0', '0', '0', '1', '1');

INSERT INTO `{PREFIX}site_content`
VALUES ('14', 'document', 'text/html', 'フォーム本体', '', '', 'formbody', '', '0', '0', '0', '3', '0', '',
        '<style type=\"text/css\">\n	label                                      {width:150px;}\n	.form-inline input[type=text].form-control {width:300px;}\n	.form-inline select.form-control           {width:300px;}\n	.form-inline textarea.form-control         {width:500px;height:150px;}\n	div.errors                                 {color:#f00;}\n</style>\n<p class=\"error\">[+validationmessage+]</p>\n<form method=\"post\" action=\"[~[*id*]~]\">\n<!-- formパーツのname値でemail/subjectの2つだけが特別な値。eform属性は入力必須項目などを設定します -->\n	<fieldset>\n		<h3>お問い合わせフォーム(eFormの機能)</h3>\n		<div class=\"form-group form-inline\">\n		<label for=\"お名前\">お名前</label>\n		<input name=\"お名前\" class=\"text form-control\" type=\"text\" />\n		</div>\n		<div class=\"form-group form-inline\">\n		<label for=\"email\">メールアドレス</label>\n		<input name=\"email\" class=\"text form-control\" type=\"text\" eform=\"メールアドレス:email:1\" />\n		</div>\n		<div class=\"form-group form-inline\">\n		<label>種別</label>\n		<select name=\"subject\" class=\"form-control\">\n			<option value=\"一般的な質問\">一般的な質問</option>\n			<option value=\"取材申し込み\">取材申し込み</option>\n			<option value=\"業務提携のご相談\">業務提携のご相談</option>\n		</select>\n		</div>\n		<div class=\"form-group form-inline\">\n		<label>メッセージ</label>\n		<textarea name=\"問い合わせ内容\" class=\"form-control\" rows=\"4\" cols=\"20\"></textarea>\n		</div>\n		<input type=\"submit\" name=\"contact\" class=\"btn btn-primary\" value=\"送信する\" />\n	</fieldset>\n</form>\n',
        '0', '0', '1', '0', '1', '1', '1329185447', '1', '1338329248', '0', '0', '0', '0', '0', '', '0', '0', '0', '0',
        '0', '0', '1', '1');

INSERT INTO `{PREFIX}site_content`
VALUES ('15', 'document', 'text/html', '送信メールのひな型', '', '', 'tpl', '', '0', '0', '0', '3', '0', '',
        'ウェブサイトの問い合わせフォームからの送信です。\n\n--------------------------------------------------------\nお名前 : [+お名前+] さん\nEmail :  [+email+]\n件 名  :  [+subject+]\n内 容  :\n[+問い合わせ内容+]\n--------------------------------------------------------\n',
        '0', '0', '2', '0', '1', '1', '1329185532', '1', '1329185540', '0', '0', '0', '0', '0', '', '0', '0', '0', '0',
        '0', '0', '0', '1');


-- Dumping data for table `system_settings`

REPLACE INTO `{PREFIX}system_settings` VALUES('error_page', '4');

REPLACE INTO `{PREFIX}system_settings` VALUES('default_template', '2');
