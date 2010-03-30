# MODx Database Script for New/Upgrade Installations
#
# Each sql command is separated by double lines


#
# Dumping data for table `keyword_xref`
#


REPLACE INTO `{PREFIX}keyword_xref` VALUES ('3','1');


REPLACE INTO `{PREFIX}keyword_xref` VALUES ('4','1');


#
# Dumping data for table `documentgroup_names`
#


REPLACE INTO `{PREFIX}document_groups` VALUES ('1','1','3');


REPLACE INTO `{PREFIX}documentgroup_names` VALUES ('1','Site Admin Pages','0','0');


#
# Dumping data for table `site_content`
#


REPLACE INTO `{PREFIX}site_content` VALUES (1, 'document', 'text/html', 'Home', 'Welcome to MODx', 'Introduction to MODx', 'index', '', 1, 0, 0, 0, 0, 'Create and do amazing things with MODx', '<h3>MODxへようこそ!</h3>\r\n<p>\r\nこのサンプルサイトが個性的なウェブサイトを構築するためのヒントになれば幸いです。このサイトにはあらかじめさまざまなオプションが設定されています。これらの設定はサイトを作る上で参考になることでしょう。\r\n</p>\r\n<ul>\r\n	<li><strong>シンプルなブログ</strong><br />\r\n	サイトにログインし、フロントエンドから新しいエントリーを投稿できます。新着情報の更新に利用するのもよいでしょう。 <a href=\"[~2~]\">サンプルブログを見る</a><br />\r\n	&nbsp;</li>	\r\n	<li><strong>コメント機能</strong><br />\r\n	サイトの登録ユーザーがあなたの記事にコメントすることができます。 <a href=\"[~9~]\">表示例</a><br />\r\n	&nbsp;</li>	\r\n	<li><strong>RSSフィード</strong><br />\r\n	RSSフィードは、あなたのサイトに訪れた人に最新の情報を提供します。 <a href=\"[(site_url)][~11~]\">RSSフィードを見る</a><br />\r\n	&nbsp;</li>	\r\n	<li><strong>自動ユーザー登録</strong><br />\r\n	ブログにコメントする場合は最初にアカウントを作成します。登録フォームには、画像認証によるスパム防止機能があらかじめ装備されています。 <a href=\"[~5~]\">登録フォームを見る</a><br />\r\n	&nbsp;</li>	\r\n	<li><strong>QuickManager（クイックマネージャー）</strong><br />\r\n	マネージャーにログインしている状態なら、実際に表示されているページを見ながらダイレクトに編集できます。 <a href=\"[~14~]\">コンテンツ管理をもっと見る</a><br />\r\n	&nbsp;</li>	\r\n	<li><strong>先進のサイト検索</strong><br />\r\n	訪問者が検索できる範囲（検索可能ドキュメント）を制限することができます。Ajax機能を使うことで、新しくページを読み込まずに検索結果を表示できます。 <a href=\"javascript:void(0)\" onclick=\"highlight(\'ajaxSearch_input\',\'#ffffff\',2000);\">検索機能はこちら</a><br />\r\n	&nbsp;</li>	\r\n	<li><strong>強力なナビゲーション生成機能</strong><br />\r\n	ダイナミックメニュービルダーを使えば、このサンプルの上部メニューのような様々な種類のナビゲーションを複製・作成することができます。 <a href=\"[~22~]\">メニューについてもっと見る</a><br />\r\n	&nbsp;</li>	\r\n	<li><strong>Mootools</strong><strong>（Ajaxライブラリ）</strong><br />\r\n	Web2.0とAjaxの先端技術がつまってます。 <a href=\"[~16~]\">Ajaxについてもっと見る</a><br />\r\n	&nbsp;</li>	\r\n	<li><strong>エラーページ(page not found[404])をカスタマイズ</strong><br />\r\n	探し物をして迷子になった閲覧者を助けてあげてください。 <a href=\"[~7~]\">404ページを見る</a><br />\r\n	&nbsp;</li>	\r\n	<li><strong>コンタクトフォーム</strong><br />\r\n	コンタクトフォームの高度な設定機能を使って正しいアドレスにメールが配送されるように設定することができます。また、メールフォームへの攻撃防止機能が、スパムメールの踏み台にされることを防ぎます。 <a href=\"[~6~]\">コンタクトフォームを見る</a><br />\r\n	&nbsp;</li>	\r\n	<li><strong>更新情報</strong><br />\r\n	最新の更新ページ一覧を表示できます（設定変更可能） <a href=\"#recentdocsctnr\" onclick=\"highlight(\'recentdocsctnr\',\'#e2e2e2\',2000);\">サンプルはこちら</a><br />\r\n	&nbsp;</li>\r\n</ul>\r\n<p>\r\n<strong>MODxのコントロールパネルへログインしてこのサイトをカスタマイズするために、 <a href=\"manager\">[(site_url)]manager/</a>をブラウズしてください。</strong>\r\n</p>', 1, 4, 1, 1, 1, 1, 1144904400, 1, 1160262629, 0, 0, 0, 0, 0, 'Home', 0, 0, 0, 0, 0, 0, 0);


REPLACE INTO `{PREFIX}site_content` VALUES (2, 'document', 'text/html', 'ブログ', 'ブログエントリー', '', 'blog', '', 1, 0, 0, 0, 1, '', '[[Ditto? &parents=`2` &display=`2` &removeChunk=`Comments` &tpl=`ditto_blog` &paginate=`1` &extenders=`summary,dateFilter` &paginateAlwaysShowLinks=`1` &tagData=`documentTags` &id=`wp`]]\r\n\r\n<p>Showing <strong>[+wp_start+]</strong> - <strong>[+wp_stop+]</strong> of <strong>[+wp_total+]</strong> Articles</p>\r\n\r\n<div id="ditto_pages"> [+wp_previous+] [+wp_pages+] [+wp_next+] </div>\r\n\r\n<div id="ditto_pages">&nbsp;</div>\r\n\r\n[[Reflect? &config=`wordpress` &dittoSnippetParameters=`parents:2` &id=`wp` &getDocuments=`1`]]', 1, 4, 2, 0, 0, 1, 1144904400, 1, 1159818696, 0, 0, 0, 0, 0, 'ブログ', 0, 0, 0, 0, 0, 0, 0);


REPLACE INTO `{PREFIX}site_content` VALUES (4, 'document', 'text/html', '[*loginName*]', 'コメントを書くにはログインしてください。', '', 'ログイン', '', 1, 0, 0, 0, 0, '', '<p>ブログのエントリーにコメントを残したいときには、[(site_name)]にユーザー登録されている必要があります。まだ登録していないときは、 <a href=\"[~5~]\">申請をしてください。</a>.</p>\r\n<div> [!WebLogin? &tpl=`FormLogin` &loginhomeid=`2`!] </div>', 1, 4, 11, 0, 0, 1, 1144904400, 1, 1144904400, 0, 0, 0, 0, 0, '[*loginName*]', 0, 0, 0, 0, 0, 0, 1);


REPLACE INTO `{PREFIX}site_content` VALUES (5, 'document', 'text/html', 'アカウントの登録申請', 'アカウント情報を入力してください。', '', 'request-an-account', '', 1, 0, 0, 0, 0, '', '[[WebSignup? &tpl=`FormSignup` &groups=`Registered Users`]]', 1, 4, 3, 0, 0, 1, 1144904400, 1, 1158320704, 0, 0, 0, 0, 0, '', 0, 0, 0, 0, 0, 0, 1);


REPLACE INTO `{PREFIX}site_content` VALUES (6, 'document', 'text/html', 'お問い合わせ', 'お問い合わせ [(site_name)]', '', 'contact-us', '', 1, 0, 0, 0, 0, '', '[!eForm? &formid=`ContactForm` &subject=`[+subject+]` &to=`[(emailsender)]` &ccsender=`1` &tpl=`ContactForm` &report=`ContactFormReport` &invalidClass=`invalidValue` &requiredClass=`requiredValue` &cssStyle=`ContactStyles` &gotoid=`46`  !]\r\n', 0, 4, 14, 1, 0, 1, 1144904400, 1, 1159303922, 0, 0, 0, 0, 0, 'お問い合わせ', 0, 0, 0, 0, 0, 0, 0);


REPLACE INTO `{PREFIX}site_content` VALUES (7, 'document', 'text/html', '404 - Document Not Found', 'お探しのページが見当たりません (Page Not Found)', '', 'doc-not-found', '', 1, 0, 0, 0, 0, '', '<p>\r\n存在しないページへアクセスしたようです。 ログインするか、 以下のページにアクセスしてください:\r\n</p>\r\n[[Wayfinder? &startId=`0` &showDescription=`1`]]\r\n\r\n<h3>または、サイト上部の検索機能を使ってお探しのページを検索してください。</h3>\r\n\r\n', 1, 4, 4, 0, 1, 1, 1144904400, 1, 1159301173, 0, 0, 0, 0, 0, '', 0, 0, 0, 0, 0, 0, 1);


REPLACE INTO `{PREFIX}site_content` VALUES (8, 'document', 'text/html', '検索結果', '検索結果', '', 'search-results', '', 1, 0, 0, 0, 0, '', '[!AjaxSearch? &AS_showForm=`0` &ajaxSearch=`0`!]', 0, 4, 5, 0, 0, 1, 1144904400, 1, 1158613055, 0, 0, 0, 0, 0, '', 1, 0, 0, 0, 0, 0, 1);


REPLACE INTO `{PREFIX}site_content` VALUES (9, 'document', 'text/html', 'Mini-Blog HOWTO', 'How to Start Posting with MODx Mini-Blogs', '', 'article-1126081344', '', 1, 0, 0, 2, 1, '', '<p>Setting up a mini-blog is relatively simple. Here''s what you need to do to get started with making new posts:</p>\r\n<ol>\r\n    <li>Login to the <a href="[(site_url)]manager/">MODx Control Panel</a>.</li>\r\n    <li>Press the plus-sign next to the Blog(2) container resource to see the blog entries posted there.</li>\r\n    <li>To make a new Blog entry, simply right-click the Blog container document and choose the "Create Resource here" menu option. To edit an existing blog article, right click the entry and choose the "Edit Resource" menu option.</li>\r\n    <!-- splitter -->\r\n    <li>Write or edit the content and press save, making sure the document is published.</li>\r\n    <li>Everything else is automatic; you''re done!</li>\r\n</ol>\r\n{{Comments}}', 1, 4, 0, 1, 1, -1, 1144904400, 1, 1160171764, 0, 0, 0, 0, 0, '', 0, 0, 0, 0, 0, 0, 1);


REPLACE INTO `{PREFIX}site_content` VALUES (11, 'document', 'text/xml', 'RSS フィード', '[(site_name)] RSSフィード', '', 'feed.rss', '', 1, 0, 0, 0, 0, '', '[[Ditto? &parents=`2` &format=`rss` &display=`20` &total=`20` &removeChunk=`Comments`]]', 0, 0, 6, 0, 0, 1, 1144904400, 1, 1160062859, 0, 0, 0, 0, 0, '', 0, 0, 0, 0, 0, 0, 1);


REPLACE INTO `{PREFIX}site_content` VALUES (14, 'document', 'text/html', 'コンテンツ管理', 'コンテンツマネージメント', '', 'cms', '', 1, 0, 0, 15, 0, '', '<h2管理画面からコンテンツ管理</h2>\r\n<p>MODxの管理画面は、機能豊富でデザインもスタイリッシュ。コンテンツを新規追加したり、テンプレートを調整したり、ウェブサイトを構成する各種パーツの管理も簡単にできます。ユーザグループごとに、管理画面の操作権限を設定することもできます。また、モジュールを追加して、他のデータセットと連動したり、管理業務を簡易化することも可能です。</p>\r\n<h2>ウェブページ側からコンテンツ管理</h2>\r\n<p>QuickManager（クイックマネージャー）を使えば、サイトをブラウザーで見ながら、ページの内容を編集できます。管理画面を経由せず、ほとんどのコンテンツ要素とテンプレート変数を手軽に編集できます。</p>\r\n<h2>ウェブユーザーに新規コンテンツの作成を許可できます。</h2>\r\n<p>特定のデータ入力作業も、MODxのAPIを利用すれば簡単です。カスタムメイドのデータ入力は、MODx APIを使用しているコードに容易です - フォームをデザインしたり、必要に応じて修正したりできます。（原文：Custom data entry is easy to code using the MODx API - so you can design forms and collect whatever information you need.）</p>', 0, 4, 3, 1, 1, 1, 1144904400, 1, 1158331927, 0, 0, 0, 0, 0, 'Manage Content', 0, 0, 0, 0, 0, 0, 0);


REPLACE INTO `{PREFIX}site_content` VALUES (15, 'document', 'text/html', 'MODxの主な機能', 'MODxの主な機能', '', 'features', '', 1, 0, 0, 0, 1, '', '[!Wayfinder?startId=`[*id*]` &outerClass=`topnav`!]', 1, 4, 7, 1, 1, 1, 1144904400, 1, 1158452722, 0, 0, 0, 1144777367, 1, 'MODxの機能', 0, 0, 0, 0, 0, 0, 0);


REPLACE INTO `{PREFIX}site_content` VALUES (16, 'document', 'text/html', 'AjaxとWeb2.0', 'AjaxとWeb2.0', '', 'ajax', '', 1, 1159264800, 0, 15, 0, '', '<strong>Ajax技術との相性のよさ</strong>\r\n<p>\r\nMODxに実装されている <a href=\"http://mootools.net/\" target=\"_blank\">Mootools</a> javascript libraryライブラリによって、魅力的なサイト作成が可能です。\r\n</p>\r\n<p>\r\nAjaxを活用した検索機能で、このサンプルサイトを検索してみてください。Ajax機能は、フロントエンドの編集機能であるクイックマネージャー機能にも使用されています。\r\n</p>\r\n<p>\r\n洗練された統合機能は、ドキュメントに使用するスクリプトを最小限に抑えます&hellip;単純なページを不必要なスクリプトで膨張させることではありません！\r\n</p>\r\n<strong>最新のWeb2.0</strong>\r\n<p>\r\nコアが直接出力するhtmlコードがほとんどないMODxなら、アクセシビリティの高い、正しいCSSレイアウトのサイト管理だって朝飯前です。ウェブ標準に則ったサイト作成が簡単にできます。（もし必要なら、tableタグに依存したレイアウトも簡単です）\r\n</p>', 1, 4, 1, 1, 1, 1, 1144904400, 1, 1159307504, 0, 0, 0, 0, 0, 'AjaxとWeb2.0', 0, 0, 0, 0, 0, 0, 0);


REPLACE INTO `{PREFIX}site_content` VALUES (18, 'document', 'text/html', 'Just a pretend, older post', 'This post should in fact be archived', '', 'article-1128398162', '', 1, 0, 0, 2, 0, '', '<p>Not so exciting, after all, eh?<br /></p>\r\n', 1, 4, 2, 1, 1, -1, 1144904400, 1, 1159306886, 0, 0, 0, 0, 0, '', 0, 0, 0, 0, 0, 0, 1);


REPLACE INTO `{PREFIX}site_content` VALUES (22, 'document', 'text/html', 'メニューとリスト', '自由度が高いメニューとリスト', '', 'menus', '', 1, 1159178400, 0, 15, 0, '', '<h2>Your documents - listed how you want them</h2>\r\n<p>\r\n汎用CMSの評価の要となるのが、ナビゲーションコントロールと複数コンテンツのリスト表示。MODxでは、これらのコンテンツコントロールを2つの高機能スニペットに託しました。それがDitto（ディットー）とWayfinder（ウェイファインダー）です。\r\n</p>\r\n<h2>Wayfinder - メニュー生成スニペット</h2>\r\n<p>どのような種類のメニューでも実現します。このサイトでは、Wayfinderはドロップダウンメニューの生成に用いられていますが、他のどんなタイプのメニューやサイトマップも生成可能です。</p>\r\n<h2>Ditto（ディトゥー - 文章のリストアップスニペット）</h2>\r\n<p>新着情報の一覧を生成したり、サイトマップを作ったり、テンプレート変数との組み合わせで関連文書をリストアップしたり、RSSフィードの生成を行ったりします。Wayfinderとは異なるアプローチでナビゲーションを作ることもできます。このサイトでは、簡易ブログのエントリー一覧の生成に使われています。また、サイドバーにも使用されています。</p>\r\n<h2>カスタマイズは無限に可能</h2>\r\n<p>\r\nDittoとWayfinderのオプション、テンプレートを使用しても、満足のいくデザインや効果が得られない場合、独自のルーチンを作ることもできますし、<a href=\"http://modxcms.com/extras.html\">MODxのリポジトリ</a>から他のスニペットを探すこともできます。MODxのメニュータイトル、要約、メニューの場所、そのほか諸々は、APIを利用することによって思い通りのデザインを作ることができます。\r\n</p>', 1, 4, 2, 1, 1, 1, 1144904400, 1, 1160148522, 0, 0, 0, 0, 0, 'メニューとリスト', 0, 0, 0, 0, 0, 0, 0);


REPLACE INTO `{PREFIX}site_content` VALUES (24, 'document', 'text/html', '拡張性豊かなデザインワーク', '拡張性豊かなデザインワーク', '', 'extendable', '', 1, 1159092732, 0, 15, 0, '', '<p>\r\nMODxコミュニティでは、イメージギャラリーやeコマース、その他様々なアドオン部品が <a href=\"http://modxcms.com/extras.html\">リポジトリ</a> で配布されてます。\r\n</p>\r\n<h2>テンプレート変数はデータバインディングが可能</h2>\r\n<p>\r\n「テンプレート変数」は、高機能なカスタムフィールドです。単なるテキストの入力項目ではなく、プログラムと連動した高度なコントロールが可能です。ここでは、コードの実行結果やデータソースによって異なる情報を返す特殊な例をご紹介します。ここではログインメニューを「@バインディング」で実現する例を示します。次のフィールドを追加することでログイン状態に従ってメニューの表示内容を変化させることができます。:\r\n<code>@EVAL if ($modx-&gt;getLoginUserID()) return \'ログアウト\'; else return \'ログイン\';</code>\r\n</p>\r\n<h2>カスタムフォーム</h2>\r\n<p>\r\nカスタムフォームとの関連性を示すために、ウェブユーザー登録システムとログインシステムの呼び出し方法をカスタマイズしてあります。\r\n</p>\r\n<h2>その他</h2>\r\n<h3>\r\n<strong>スマートな概要表示</strong></h3>\r\n<p>\r\n区切りたい位置に&quot;&lt;!-- splitter --&gt;&quot;というタグを入れることで、記事を途中で区切ることができます。また、OL, UL, DIVといった重要なタグが前後に分かれてもタグが閉じるように動作するためレイアウトが崩れることはありません。\r\n</p>', 1, 4, 4, 1, 1, 2, 1144904400, 1, 1159309971, 0, 0, 0, 0, 0, '思い通りの拡張', 0, 0, 0, 0, 0, 0, 0);


REPLACE INTO `{PREFIX}site_content` VALUES (32, 'document', 'text/html', 'デザイン', 'テンプレートデザイン', '', 'design', '', 1, 0, 0, 0, 0, '', '<h3>Credits</h3>\r\n<p>このサンプルコンテンツのテンプレートデザインについて。<a href="http://andreasviklund.com/">Andreas Viklund</a>と<a title="Complete web design solutions" href="http://ziworks.com/">ziworks | Web Solutions</a> and <a href="http://www.modxhost.com">MODxHost</a>によるvalidなXHTML/CSSデザインです。</p>', 1, 4, 10, 1, 1, 2, 1144904400, 1, 1160112322, 0, 0, 0, 1144912754, 1, 'Design', 0, 0, 0, 0, 0, 0, 0);


REPLACE INTO `{PREFIX}site_content` VALUES (33, 'document', 'text/html', 'サポート', 'サポート', '', 'geting-help', '', 1, 0, 0, 0, 0, '', '<p>\r\n<a href=\"http://modxcms.com/modx-team.html\" target=\"_blank\">MODxチームは</a> あなたがMODxを快適に使えるように、日々ドキュメント類の改良に努めています。:\r\n</p>\r\n<ul>\r\n	<li>MODxのテンプレート構築に関する基本的な事柄については、<a href=\"http://modxcms.com/designer-guide.html\" target=\"_blank\">デザイナーズガイドをご覧ください</a>。 </li>\r\n	<li>MODxをを利用したコンテンツの編集方法については、<a href=\"http://modxcms.com/editor-guide.html\" target=\"_blank\">コンテンツエディターガイドをご覧ください</a>。 </li>\r\n	<li>管理ツールの詳細とユーザーやグループの設定については、<a href=\"http://modxcms.com/developers-guide.html\" target=\"_blank\">アドミニストレーションガイドを精読ください</a>。</li>\r\n	<a href=\"http://modxcms.com/administration-guide.html\" target=\"_blank\">デベロッパーズガイドで</a>MODxの構造とAPIについて記述しています。\r\n	<li>もし誰かがこのサイトをインストールしていて、それを見たあなた自身がMODxについて知りたくなったとしたら、<a href=\"http://modxcms.com/getting-started.html\" target=\"_blank\">スタートガイドをご覧ください</a>。</li>\r\n</ul>\r\n<p>\r\nそして<a href=\"http://modxcms-jp.com/bb/\" target=\"_blank\">MODxフォーラムを利用すれば、</a>いつでもノウハウを得たり、質疑応答ができます。 \r\n</p>', 1, 4, 8, 1, 1, 2, 1144904400, 2, 1144904400, 0, 0, 0, 0, 0, 'サポート', 0, 0, 0, 0, 0, 0, 0);


REPLACE INTO `{PREFIX}site_content` VALUES (37, 'document', 'text/html', '[*loginName*]', 'The page you''re trying to reach requires a login', '', 'blog-login', '', 1, 0, 0, 0, 0, '', '<p>In order to add a blog entry, you must be logged in as a Site Admin webuser. Also, commenting on posts requires a login. <a href="[~6~]">Contact the site owner</a> for permissions to create new post, or <a href="[~5~]">create a web user account</a> to automatically receive commenting privileges. If you already have an account, please login below.</p>\r\n\r\n[!WebLogin? &tpl=`FormLogin` &loginhomeid=`3`!]', 1, 4, 12, 0, 0, 1, 1144904400, 1, 1158599931, 0, 0, 0, 0, 0, '', 0, 0, 0, 0, 0, 0, 1);


REPLACE INTO `{PREFIX}site_content` VALUES (46, 'document', 'text/html', 'ありがとうございます', '', '', 'thank-you', '', 1, 0, 0, 0, 0, '', '<h3>ありがとうございます!</h3>\r\n<p>\r\nコメントありがとうございます。投稿されたコメントを受け付けました。また、あなたの受信トレイにメッセージのコピーが受信されていることでしょう。\r\n</p>\r\n<p>\r\n投稿内容をチェックするよう最善を尽くしておりますのでご安心ください。\r\n</p>', 1, 4, 13, 1, 1, 1, 1159302141, 1, 1159302892, 0, 0, 0, 1159302182, 1, '', 0, 0, 0, 0, 0, 0, 1);


#
# Dumping data for table `site_htmlsnippets`
#


REPLACE INTO `{PREFIX}site_htmlsnippets` VALUES (1, 'WebLoginSideBar', '「WebLogin」のサイドバーテンプレート', 0, 2, 0, '<!-- #declare:separator <hr> --> \r\n<!-- login form section-->\r\n<form method="post" name="loginfrm" action="[+action+]" style="margin: 0px; padding: 0px;"> \r\n<input type="hidden" value="[+rememberme+]" name="rememberme" /> \r\n<table border="0" cellspacing="0" cellpadding="0">\r\n<tr>\r\n<td>\r\n<table border="0" cellspacing="0" cellpadding="0">\r\n  <tr>\r\n	<td><b>ユーザー:</b></td>\r\n	<td><input type="text" name="username" tabindex="1" onkeypress="return webLoginEnter(document.loginfrm.password);" size="5" style="width: 100px;" value="[+username+]" /></td>\r\n  </tr>\r\n  <tr>\r\n	<td><b>パスワード:</b></td>\r\n	<td><input type="password" name="password" tabindex="2" onkeypress="return webLoginEnter(document.loginfrm.cmdweblogin);" size="5" style="width: 100px;" value="" /></td>\r\n  </tr>\r\n  <tr>\r\n	<td><label for="chkbox" style="cursor:pointer">ログイン情報を記憶:&nbsp; </label></td>\r\n	<td>\r\n	<table width="100%"  border="0" cellspacing="0" cellpadding="0">\r\n	  <tr>\r\n		<td valign="top"><input type="checkbox" id="chkbox" name="chkbox" tabindex="4" size="1" value="" [+checkbox+] onClick="webLoginCheckRemember()" /></td>\r\n		<td align="right">									\r\n		<input type="submit" value="[+logintext+]" name="cmdweblogin" /></td>\r\n	  </tr>\r\n	</table>\r\n	</td>\r\n  </tr>\r\n  <tr>\r\n	<td colspan="2"><a href="#" onclick="webLoginShowForm(2);return false;">パスワードをお忘れですか？</a></td>\r\n  </tr>\r\n</table>\r\n</td>\r\n</tr>\r\n</table>\r\n</form>\r\n<hr>\r\n<!-- log out hyperlink section -->\r\n<a href=''[+action+]''>[+logouttext+]</a>\r\n<hr>\r\n<!-- Password reminder form section -->\r\n<form name="loginreminder" method="post" action="[+action+]" style="margin: 0px; padding: 0px;">\r\n<input type="hidden" name="txtpwdrem" value="0" />\r\n<table border="0">\r\n	<tr>\r\n	  <td>メールアドレスを入力してください。<br />below to receive your password:</td>\r\n	</tr>\r\n	<tr>\r\n	  <td><input type="text" name="txtwebemail" size="24" /></td>\r\n	</tr>\r\n	<tr>\r\n	  <td align="right"><input type="submit" value="実行" name="cmdweblogin" />\r\n	  <input type="reset" value="キャンセル" name="cmdcancel" onclick="webLoginShowForm(1);" /></td>\r\n	</tr>\r\n  </table>\r\n</form>\r\n\r\n', 0);


REPLACE INTO `{PREFIX}site_htmlsnippets` VALUES (3, 'FormLogin', 'ウェブログインフォーム', 0, 2, 0, '<!-- #declare:separator <hr> --> \r\n<!-- ログインフォームセクション -->\r\n<form method="post" name="loginfrm" action="[+action+]"> \r\n    <input type="hidden" value="[+rememberme+]" name="rememberme" /> \r\n    <fieldset>\r\n        <h3>ログイン情報</h3>\r\n        <label for="username">ユーザー名: <input type="text" name="username" id="username" tabindex="1" onkeypress="return webLoginEnter(document.loginfrm.password);" value="[+username+]" /></label>\r\n    	<label for="password">パスワード: <input type="password" name="password" id="password" tabindex="2" onkeypress="return webLoginEnter(document.loginfrm.cmdweblogin);" value="" /></label>\r\n    	<input type="checkbox" id="checkbox_1" name="checkbox_1" tabindex="3" size="1" value="" [+checkbox+] onclick="webLoginCheckRemember()" /><label for="checkbox_1" class="checkbox">ログイン情報を記憶</label>\r\n    	<input type="submit" value="[+logintext+]" name="cmdweblogin" class="button" />\r\n	<a href="#" onclick="webLoginShowForm(2);return false;" id="forgotpsswd">パスワードをお忘れですか？</a>\r\n	</fieldset>\r\n</form>\r\n<hr>\r\n<!-- ログアウトリンクセクション -->\r\n<h4>ログイン中</h4>\r\n<a href="[+action+]" class="button">[+logouttext+]</a>しますか？\r\n<hr>\r\n<!-- パスワードリマインダーセクション -->\r\n<form name="loginreminder" method="post" action="[+action+]">\r\n    <fieldset>\r\n        <h3>誰にでもよくあること</h3>\r\n        <input type="hidden" name="txtpwdrem" value="0" />\r\n        <label for="txtwebemail">メールアドレスを入力するとパスワードをリセットできます。<input type="text" name="txtwebemail" id="txtwebemail" size="24" /></label>\r\n        <label>ログインフォームに戻るにはキャンセルボタンを押してください。</label>\r\n    	<input type="submit" value="実行" name="cmdweblogin" class="button" /> <input type="reset" value="キャンセル" name="cmdcancel" onclick="webLoginShowForm(1);" class="button" style="clear:none;display:inline" />\r\n    </fieldset>\r\n</form>\r\n', 0);


REPLACE INTO `{PREFIX}site_htmlsnippets` VALUES (4, 'FormSignup', 'ウェブサインアップフォーム', 0, 2, 0, '<!-- #declare:separator <hr> --> \r\n<!-- login form section-->\r\n<form id=\"websignupfrm\" method=\"post\" name=\"websignupfrm\" action=\"[+action+]\">\r\n    <fieldset>\r\n        <h3>ユーザー情報</h3>\r\n        <p> * : 必須</p>\r\n		<label for=\"su_username\">ユーザーID:* <input type=\"text\" name=\"username\" id=\"su_username\" class=\"inputBox\" size=\"20\" maxlength=\"30\" value=\"[+username+]\" /></label>\r\n        <label for=\"fullname\">フルネーム: <input type=\"text\" name=\"fullname\" id=\"fullname\" class=\"inputBox\" size=\"20\" maxlength=\"100\" value=\"[+fullname+]\" /></label>\r\n		<label for=\"email\">メールアドレス:* <input type=\"text\" name=\"email\" id=\"email\" class=\"inputBox\" size=\"20\" value=\"[+email+]\" /></label>\r\n	</fieldset>\r\n	\r\n	<fieldset>\r\n	    <h3>パスワード</h3>\r\n	    <label for=\"su_password\">パスワード:* <input type=\"password\" name=\"password\" id=\"su_password\" class=\"inputBox\" size=\"20\" /></label>\r\n	    <label for=\"confirmpassword\">パスワード（確認）:* <input type=\"password\" name=\"confirmpassword\" id=\"confirmpassword\" class=\"inputBox\" size=\"20\" /></label>\r\n	</fieldset>\r\n	\r\n	<fieldset>\r\n		<h3>オプションプロフィール</h3>\r\n		<label for=\"country\">Country:</label>\r\n		<select size=\"1\" name=\"country\" id=\"country\">\r\n			<option value=\"\" selected=\"selected\">&nbsp;</option>\r\n			<option value=\"107\">Japan</option>\r\n			<option value=\"223\">United States</option>\r\n			<option value=\"224\">United States Minor Outlying Islands</option>\r\n			</select>\r\n        </fieldset>\r\n        \r\n        <fieldset>\r\n            <h3>画像認証</h3>\r\n            <p>見えている文字を入力してください。読みづらい場合は、画像をクリックするとコードを変えることができます。</p>\r\n            <p><a href=\"[+action+]\"><img align=\"top\" src=\"manager/includes/veriword.php\" width=\"148\" height=\"60\" alt=\"If you have trouble reading the code, click on the code itself to generate a new random code.\" style=\"border: 1px solid #039\" /></a></p>\r\n        <label>認証コード:* \r\n            <input type=\"text\" name=\"formcode\" class=\"inputBox\" size=\"20\" /></label>\r\n            </fieldset>\r\n        \r\n        <fieldset>\r\n            <input type=\"submit\" value=\"登録\" name=\"cmdwebsignup\" />\r\n	</fieldset>\r\n</form>\r\n\r\n<script language=\"javascript\" type=\"text/javascript\"> \r\n	var id = \"[+country+]\";\r\n	var f = document.websignupfrm;\r\n	var i = parseInt(id);	\r\n	if (!isNaN(i)) f.country.options[i].selected = true;\r\n</script>\r\n<hr>\r\n<!-- notification section -->\r\n<p class=\"message\">登録完了！<br />アカウントは正しく作成されました。 登録された情報をあなたのメールアドレスに送信しました。</p>\r\n', 0);


REPLACE INTO `{PREFIX}site_htmlsnippets` VALUES (6, 'nl_sidebar', 'Ditto用のデフォルトTPLテンプレート', 0, 1, 0, '<strong><a href="[~[+id+]~]" title="[+title+]">[+title+]</a></strong><br />\r\n[+longtitle+]<br /><br />', 0);


REPLACE INTO `{PREFIX}site_htmlsnippets` VALUES (7, 'styles', 'スタイルシート切り替え用のリスト', 0, 1, 0, '<div id="modxhost">The CSS Themes can only be used on the MODxCSS and MODxCSSW Layouts</div>\r\n<script type="text/javascript">$(''modxhost'').style.display=''none'';</script>\r\n<ul class="links">\r\n<li><a href="#" onclick="setActiveStyleSheet(''Trend''); return false;">Trend (Default)</a></li>\r\n<li><a href="#" onclick="setActiveStyleSheet(''Trend (Alternate)''); return false;" >Trend (Alternate)</a></li>\r\n<li><a href="#" onclick="setActiveStyleSheet(''ZiX''); return false;" >ZiX (Clean)</a></li>\r\n<li><a href="#" onclick="setActiveStyleSheet(''ZiX Background''); return false;" >ZiX (Background)</a></li>\r\n<li><a href="#" onclick="setActiveStyleSheet(''Light''); return false;" >Light</a></li>\r\n<li><a href="#" onclick="setActiveStyleSheet(''Light Green''); return false;" >Light Green</a></li>\r\n<li><a href="#" onclick="setActiveStyleSheet(''Dark''); return false;" >Dark</a></li>\r\n    </ul>', 0);


REPLACE INTO `{PREFIX}site_htmlsnippets` VALUES (8, 'ditto_blog', 'ブログテンプレート', 0, 1, 0, '<div class="ditto_summaryPost">\r\n\  <h3><a href="[~[+id+]~]" title="[+title+]">[+title+]</a></h3>\r\n  <div class="ditto_info" >By <strong>[+author+]</strong> on [+date+]. <a  href="[~[+id+]~]#commentsAnchor">Comments\r\n  ([!Jot?&docid=`[+id+]`&action=`count-comments`!])</a></div><div class="ditto_tags">Tags: [+tagLinks+]</div>\r\n  [+summary+]\r\n  <p class="ditto_link">[+link+]</p>\r\n</div>', 0);


REPLACE INTO `{PREFIX}site_htmlsnippets` VALUES (9, 'footer', 'サイトテンプレートのフッター', 0, 1, 0, '[(site_name)] is powered by <a href="http://modxcms.com/" title="Powered by MODx, Do more with less.">MODx CMS</a> |\r\n      <span id="andreas">Design by <a href="http://andreasviklund.com/">Andreas Viklund</a></span>\r\n<span id="zi" style="display: none">Designed by <a href="http://ziworks.com/" target="_blank" title="E-Business &amp; webdesign solutions">ziworks</a></span>\r\n\r\n<!-- the modx icon -->\r\n\r\n<div id="modxicon"><h6><a href="http://modxcms.com" title="MODx - The XHTML, CSS and Ajax CMS and PHP Application Framework" id="modxicon32">MODx - The XHTML, CSS and Ajax CMS and PHP Application Framework</a></h6></div>', 0);


REPLACE INTO `{PREFIX}site_htmlsnippets` VALUES (10, 'meta', 'サイトテンプレートのメタ情報', 0, 1, 0, '<p><a href="http://validator.w3.org/check/referer" title="This page validates as XHTML 1.0 Transitional">Valid <abbr title="eXtensible HyperText Markup Language">XHTML</abbr></a></p>                	<p><a href="http://jigsaw.w3.org/css-validator/check/referer" title="This page uses valid Cascading Stylesheets" rel="external">Valid <abbr title="W3C Cascading Stylesheets">css</abbr></a></p>				    <p><a href="http://modxcms.com/" title="Powered by MODx, Do more with less.">MOD<strong>x</strong></a></p>', 0);


REPLACE INTO `{PREFIX}site_htmlsnippets` VALUES (11, 'mh.InnerRowTpl', 'ModxHostのトップメニュー用の内枠の行テンプレート', 0, 1, 0, '<li[+wf.classes+]><a href="[+wf.link+]" title="[+wf.title+]">[+wf.linktext+]</a>[+wf.wrapper+]</li>', 0);


REPLACE INTO `{PREFIX}site_htmlsnippets` VALUES (12, 'mh.InnerTpl', 'ModxHostのトップメニュー用の内枠の入れ子テンプレート', 0, 1, 0, '<ul style="display:none">\r\n  [+wf.wrapper+]\r\n</ul>', 0);


REPLACE INTO `{PREFIX}site_htmlsnippets` VALUES (13, 'mh.OuterTpl', 'ModxHostのトップメニュー用の外枠の入れ子テンプレート', 0, 1, 0, '  <ul id="myajaxmenu">\r\n    [+wf.wrapper+]\r\n  </ul>', 0);


REPLACE INTO `{PREFIX}site_htmlsnippets` VALUES (14, 'mh.RowTpl', 'ModxHostのトップメニュー用の行テンプレート', 0, 1, 0, '<li class="category [+wf.classnames+]"><a href="[+wf.link+]" title="[+wf.title+]">[+wf.linktext+]</a>[+wf.wrapper+]</li>', 0);


REPLACE INTO `{PREFIX}site_htmlsnippets` VALUES (15, 'Comments', 'ブログエントリーの下に表示するコメント(Jot)', 0, 1, 0, '<div id="commentsAnchor">\r\n[!Jot? &customfields=`name,email` &subscribe=`1` &pagination=`4` &badwords=`dotNet` &canmoderate=`Site Admins` !]\r\n</div>', 0);


REPLACE INTO `{PREFIX}site_htmlsnippets` VALUES (16, 'ContactForm', '', 0, 1, 0, '<p class="error">[+validationmessage+]</p>\r\n\r\n<form method="post" action="[~[*id*]~]" id="EmailForm" name="EmailForm">\r\n\r\n	<fieldset>\r\n		<h3> Contact Form</h3>\r\n\r\n		<input name="formid" type="hidden" value="ContactForm" />\r\n\r\n		<label for="cfName">Your name:\r\n		<input name="name" id="cfName" class="text" type="text" eform="Your Name::1:" /> </label>\r\n\r\n		<label for="cfEmail">Your Email Address:\r\n		<input name="email" id="cfEmail" class="text" type="text" eform="Email Address:email:1" /> </label>\r\n\r\n		<label for="cfRegarding">Regarding:</label>\r\n		<select name="subject" id="cfRegarding" eform="Form Subject::1">\r\n			<option value="General Inquiries">General Inquiries</option>\r\n			<option value="Press">Press or Interview Request</option>\r\n			<option value="Partnering">Partnering Opportunities</option>\r\n		</select>\r\n\r\n		<label for="cfMessage">Message: \r\n		<textarea name="message" id="cfMessage" rows="4" cols="20" eform="Message:textarea:1"></textarea>\r\n		</label>\r\n\r\n		<label>&nbsp;</label><input type="submit" name="contact" id="cfContact" class="button" value="Send This Message" />\r\n\r\n	</fieldset>\r\n\r\n</form>\r\n', 0);


REPLACE INTO `{PREFIX}site_htmlsnippets` VALUES (17, 'ContactFormReport', '', 0, 1, 0, 'ウェブサイトの問い合わせフォームからの送信です。\r\n\r\n--------------------------------------------------------\r\nお名前 : [+name+] さん\r\nEmail :  [+email+]\r\n件 名  :  [+subject+]\r\n内 容  :\r\n[+message+]\r\n--------------------------------------------------------\r\n', 0);


REPLACE INTO `{PREFIX}site_htmlsnippets` VALUES (18, 'reflect_month_tpl', 'Dittoと共に使用する月別アーカイブ', 0, 1, 0, '<a href="[+url+]" title="[+year+] [+month+]" class="reflect_month_link">[+month+] [+year+]</a>', 0);


REPLACE INTO `{PREFIX}site_htmlsnippets` VALUES (19, 'ContactStyles', 'フォーム検証用のスタイル', 0, 1, 0, '<style type="text/css">\r\ndiv.errors{ color:#F00; }\r\n#EmailForm .invalidValue{ background: #FFDFDF; border:1px solid #F00; }\r\n#EmailForm .requiredValue{ background: #FFFFDF; border:1px solid #F00; }\r\n</style>', 0);



#
# Dumping data for table `site_keywords`
#


REPLACE INTO `{PREFIX}site_keywords` VALUES ('1','MODx');


REPLACE INTO `{PREFIX}site_keywords` VALUES ('2','content management system');


REPLACE INTO `{PREFIX}site_keywords` VALUES ('3','Front End Editing');


REPLACE INTO `{PREFIX}site_keywords` VALUES ('4','login');


#
# Dumping data for table `site_tmplvars`
#


REPLACE INTO `{PREFIX}site_tmplvars` VALUES ('1','richtext','blogContent','ブログコンテンツ','新規ブログエントリー用のリッチテキストエディター','0','1','0','','0','richtext','&w=383px&h=450px&edt=TinyMCE','');


REPLACE INTO `{PREFIX}site_tmplvars` VALUES ('2','text','loginName','ログイン名','ログインメニュー用のユーザー名','0','1','0','','0','','','@EVAL if ($modx->getLoginUserID()) return \'Logout\'; else return \'Login\';');


REPLACE INTO `{PREFIX}site_tmplvars` VALUES ('3','text','documentTags','タグ','タグ(半角スペースで区切って複数入力)','0','1','0','','0','','','');


#
# Dumping data for table `modx2352_site_tmplvar_contentvalues`
#


REPLACE INTO `{PREFIX}site_tmplvar_contentvalues` VALUES ('1','3','9','demo miniblog howto tutorial posting');


REPLACE INTO `{PREFIX}site_tmplvar_contentvalues` VALUES ('2','3','18','demo older posting');


#
# Dumping data for table `site_tmplvar_templates`
#


REPLACE INTO `{PREFIX}site_tmplvar_templates` VALUES ('1','1','1');


REPLACE INTO `{PREFIX}site_tmplvar_templates` VALUES ('1','3','2');


REPLACE INTO `{PREFIX}site_tmplvar_templates` VALUES ('1','4','3');


REPLACE INTO `{PREFIX}site_tmplvar_templates` VALUES ('2','1','1');


REPLACE INTO `{PREFIX}site_tmplvar_templates` VALUES ('2','3','2');


REPLACE INTO `{PREFIX}site_tmplvar_templates` VALUES ('2','4','3');


REPLACE INTO `{PREFIX}site_tmplvar_templates` VALUES ('3','3','0');


REPLACE INTO `{PREFIX}site_tmplvar_templates` VALUES ('3','4','0');


REPLACE INTO `{PREFIX}site_tmplvar_templates` VALUES ('3','1','0');


#
# Dumping data for table `system_settings`
#


REPLACE INTO `{PREFIX}system_settings` VALUES('error_page', '7');


REPLACE INTO `{PREFIX}system_settings` VALUES('unauthorized_page', '4');


#
# Dumping data for table `web_groups`
#


REPLACE INTO `{PREFIX}web_groups` VALUES ('1','1','1');


#
# Dumping data for table `web_user_attributes`
#


REPLACE INTO `{PREFIX}web_user_attributes` VALUES ('1','1','Site Admin','0','you@example.com','','','0','0','0','25','1129049624','1129063123','0','f426f3209310abfddf2ee00e929774b4','0','0','','','','','','');


#
# Dumping data for table `web_users`
#


REPLACE INTO `{PREFIX}web_users` VALUES ('1','siteadmin','5f4dcc3b5aa765d61d8327deb882cf99','');


#
# Dumping data for table `webgroup_access`
#


REPLACE INTO `{PREFIX}webgroup_access` VALUES ('1','1','1');


#
# Dumping data for table `webgroup_names`
#


REPLACE INTO `{PREFIX}webgroup_names` VALUES ('1','Site Admins');


REPLACE INTO `{PREFIX}webgroup_names` VALUES ('2','Registered Users');



#
# Table structure for table `jot_content`
#


CREATE TABLE IF NOT EXISTS `{PREFIX}jot_content` (`id` int(10) NOT NULL auto_increment, `title` varchar(255) default NULL, `tagid` varchar(50) default NULL, `published` int(1) NOT NULL default '0', `uparent` int(10) NOT NULL default '0', `parent` int(10) NOT NULL default '0', `flags` varchar(25) default NULL, `secip` varchar(32) default NULL, `sechash` varchar(32) default NULL, `content` mediumtext, `customfields` mediumtext, `mode` int(1) NOT NULL default '1', `createdby` int(10) NOT NULL default '0', `createdon` int(20) NOT NULL default '0', `editedby` int(10) NOT NULL default '0', `editedon` int(20) NOT NULL default '0', `deleted` int(1) NOT NULL default '0', `deletedon` int(20) NOT NULL default '0', `deletedby` int(10) NOT NULL default '0', `publishedon` int(20) NOT NULL default '0', `publishedby` int(10) NOT NULL default '0', PRIMARY KEY  (`id`), KEY `parent` (`parent`), KEY `secip` (`secip`), KEY `tagidx` (`tagid`), KEY `uparent` (`uparent`)) TYPE=MyISAM;


#
# Dumping data for table `jot_content`
#


REPLACE INTO `{PREFIX}jot_content` VALUES ('9','The first comment','','1','9','0','','87.211.130.14','edb75dab198ff302efbf2f60e548c0b3','This is the first comment.','<custom><name></name><email></email></custom>','0','0','1160420310','0','0','0','0','0','0','0');


REPLACE INTO `{PREFIX}jot_content` VALUES ('10','Second comment','','1','9','0','','87.211.130.14','edb75dab198ff302efbf2f60e548c0b3','This is the second comment and uses an alternate row color. I also supplied a name, but i\'m not logged in.','<custom><name>Armand</name><email></email></custom>','0','0','1160420453','0','0','0','0','0','0','0');


REPLACE INTO `{PREFIX}jot_content` VALUES ('11','No abuse','','1','9','0','','87.211.130.14','edb75dab198ff302efbf2f60e548c0b3','Notice that I can\'t abuse <b>html</b>, ,  or [+placeholder+] tags.\r\n\r\nA new line also doesn\'t come unnoticed.','<custom><name>Armand</name><email></email></custom>','0','0','1160420681','0','0','0','0','0','0','0');


REPLACE INTO `{PREFIX}jot_content` VALUES ('12','Posting when logged in','','1','9','0','','87.211.130.14','58fade927c1df50ba6131f2b0e53c120','When you are logged in your own posts have a special color so you can easily spot them from the comment view. \r\n\r\nThe form also does not display any guest fields when logged in.','<custom></custom>','0','-1','1160421310','0','0','0','0','0','0','0');


REPLACE INTO `{PREFIX}jot_content` VALUES ('13','Managers','','1','9','0','','87.211.130.14','91e230cf219e3ade10f32d6a41d0bd4d','Comments posted when only logged in as a manager user will use your manager name.\r\n\r\nModerators options are always shown when you are logged in as manager user.','<custom></custom>','0','1','1160421487','0','0','0','0','0','0','0');


REPLACE INTO `{PREFIX}jot_content` VALUES ('14','Moderation','','1','9','0','','87.211.130.14','58fade927c1df50ba6131f2b0e53c120','In this setup the Site Admins group is defined as being the moderator for this particular comment view. These users will have extra moderation options \r\n\r\nManager users, Moderators or Trusted users can post bad words like: dotNet.','<custom></custom>','0','-1','1160422081','0','0','0','0','0','0','0');


REPLACE INTO `{PREFIX}jot_content` VALUES ('15','I\'m untrusted','','0','9','0','','87.211.130.14','edb75dab198ff302efbf2f60e548c0b3','Untrusted users however can NOT post bad words like: dotNet. When they do the posts will be unpublished.','<custom><name></name><email></email></custom>','0','0','1160422167','0','0','0','0','0','0','0');


#
# Table structure for table `jot_subscriptions`
#


CREATE TABLE IF NOT EXISTS `{PREFIX}jot_subscriptions` (`id` mediumint(10) NOT NULL auto_increment, `uparent` mediumint(10) NOT NULL default '0', `tagid` varchar(50) NOT NULL default '', `userid` mediumint(10) NOT NULL default '0', PRIMARY KEY  (`id`), KEY `uparent` (`uparent`), KEY `tagid` (`tagid`), KEY `userid` (`userid`)) TYPE=MyISAM;


