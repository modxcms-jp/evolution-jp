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


REPLACE INTO `{PREFIX}site_content` VALUES (1, 'document', 'text/html', 'Home', 'Welcome to MODx', 'Introduction to MODx', 'index', '', 1, 0, 0, 0, 0, 'Create and do amazing things with MODx', '<h3>MODxへようこそ!</h3>\r\n<p>\r\nこのサンプルサイトが個性的なウェブサイトを構築するためのヒントになれば幸いです。このサイトにはあらかじめさまざまなオプションが設定されています。これらの設定はサイトを作る上で参考になることでしょう。\r\n</p>\r\n<ul>\r\n	<li><strong>シンプルなブログ</strong><br />\r\n	サイトにログインし、フロントエンドから新しいエントリーを投稿できます。新着情報の更新に利用するのもよいでしょう。 <a href=\"[~2~]\">サンプルブログを見る</a><br />\r\n	&nbsp;</li>	\r\n	<li><strong>コメント機能</strong><br />\r\n	サイトの登録ユーザーがあなたの記事にコメントすることができます。 <a href=\"[~9~]\">表示例</a><br />\r\n	&nbsp;</li>	\r\n	<li><strong>RSSフィード</strong><br />\r\n	RSSフィードは、あなたのサイトに訪れた人に最新の情報を提供します。 <a href=\"feed.rss\">RSSフィードを見る</a><br />\r\n	&nbsp;</li>	\r\n	<li><strong>自動ユーザー登録</strong><br />\r\n	ブログにコメントする場合は最初にアカウントを作成します。登録フォームには、画像認証によるスパム防止機能があらかじめ装備されています。 <a href=\"[~5~]\">登録フォームを見る</a><br />\r\n	&nbsp;</li>	\r\n	<li><strong>QuickEdit（クイックエディット）</strong><br />\r\n	マネージャーにログインしている状態では、実際に表示されているページを見ながらダイレクトに編集できます。 <a href=\"[~14~]\">コンテンツ管理をもっと見る</a><br />\r\n	&nbsp;</li>	\r\n	<li><strong>先進のサイト検索</strong><br />\r\n	訪問者が検索できる範囲（検索可能ドキュメント）を制限することができます。Ajax機能を使うことで、新しくページを読み込まずに検索結果を表示できます。 <a href=\"javascript:void(0)\" onclick=\"highlight(\'ajaxSearch_input\',\'#ffffff\',2000);\">検索機能はこちら</a><br />\r\n	&nbsp;</li>	\r\n	<li><strong>強力なナビゲーション生成機能</strong><br />\r\n	ダイナミックメニュービルダーを使えば、このサンプルの上部メニューのような様々な種類のナビゲーションを複製・作成することができます。 <a href=\"[~22~]\">メニューについてもっと見る</a><br />\r\n	&nbsp;</li>	\r\n	<li><strong>Mootools</strong><strong>（Ajaxライブラリ）</strong><br />\r\n	Web2.0とAjaxの先端技術がつまってます。 <a href=\"[~16~]\">Ajaxについてもっと見る</a><br />\r\n	&nbsp;</li>	\r\n	<li><strong>エラーページ(page not found[404])をカスタマイズ</strong><br />\r\n	探し物をして迷子になった閲覧者を助けてあげてください。 <a href=\"[~7~]\">404ページを見る</a><br />\r\n	&nbsp;</li>	\r\n	<li><strong>コンタクトフォーム</strong><br />\r\n	コンタクトフォームの高度な設定機能を使って正しいアドレスにメールが配送されるように設定することができます。また、メールフォームへの攻撃防止機能が、スパムメールの踏み台にされることを防ぎます。 <a href=\"[~6~]\">コンタクトフォームを見る</a><br />\r\n	&nbsp;</li>	\r\n	<li><strong>更新情報</strong><br />\r\n	最新の更新ページ一覧を表示できます（設定変更可能） <a href=\"javascript:void(0)\" onclick=\"highlight(\'recentdocsctnr\',\'#e2e2e2\',2000);\">サンプルはこちら</a><br />\r\n	&nbsp;</li>\r\n</ul>\r\n<p>\r\n<strong>MODxのコントロールパネルへログインしてこのサイトをカスタマイズするために、 <a href=\"manager\">[(site_url)]manager/</a>をブラウズしてください。</strong>\r\n</p>', 1, 3, 1, 1, 1, 1, 1144904400, 1, 1160262629, 0, 0, 0, 0, 0, 'Home', 0, 0, 0, 0, 0, 0, 0);


REPLACE INTO `{PREFIX}site_content` VALUES (2, 'document', 'text/html', 'ブログ', 'ブログエントリー', '', 'blog', '', 1, 0, 0, 0, 1, '', '[[Ditto? &amp;startID=`2` &amp;summarize=`2` &amp;removeChunk=`Comments` &amp;tpl=`ditto_blog` &amp;paginate=`1` &amp;extenders=`summary,dateFilter` &amp;paginateAlwaysShowLinks=`1` &amp;tagData=`documentTags`]]\r\n<p>\r\nShowing <strong>[+start+]</strong> - <strong>[+stop+]</strong> of <strong>[+total+]</strong> Articles\r\n</p>\r\n<div id=\"ditto_pages\">\r\n [+previous+] [+pages+] [+next+] \r\n</div>\r\n<div id=\"ditto_pages\">\r\n&nbsp;\r\n</div>\r\n[[Reflect? &amp;dittoSnippetParameters=`startID:2` &amp;groupByYears=`0` &amp;showItems=0` &amp;tplMonth=`reflect_month_tpl`]]', 1, 3, 2, 0, 0, 1, 1144904400, 1, 1159818696, 0, 0, 0, 0, 0, 'ブログ', 0, 0, 0, 0, 0, 0, 0);


REPLACE INTO `{PREFIX}site_content` VALUES (3, 'document', 'text/html', 'ブログエントリーを追加', 'ブログに新しいエントリーを追加する', '', 'add-a-blog-entry', '', 1, 0, 0, 2, 0, '', '[!NewsPublisher? &folder=`2` &canpost=`Site Admins` &formtpl=`FormBlog` &footertpl=`Comments` &makefolder=`1` &rtcontent=`tvblogContent`!]', 0, 3, 2, 0, 0, 1, 1144904400, 3, 1144904400, 0, 0, 0, 0, 0, 'エントリー追加', 1, 0, 0, 1, 0, 0, 0);


REPLACE INTO `{PREFIX}site_content` VALUES (4, 'document', 'text/html', '[*loginName*]', 'コメントを書くにはログインしてください。', '', 'ログイン', '', 1, 0, 0, 0, 0, '', '<p>ブログのエントリーにコメントを残したいときには、[(site_name)]にユーザー登録されている必要があります。まだ登録していないときは、 <a href=\"[~5~]\">申請をしてください。</a>.</p>\r\n<div> [!WebLogin? &tpl=`FormLogin` &loginhomeid=`2`!] </div>', 1, 3, 11, 0, 0, 1, 1144904400, 1, 1144904400, 0, 0, 0, 0, 0, '[*loginName*]', 0, 0, 0, 0, 0, 0, 1);


REPLACE INTO `{PREFIX}site_content` VALUES (5, 'document', 'text/html', 'アカウントの登録申請', 'アカウント情報を入力してください。', '', 'request-an-account', '', 1, 0, 0, 0, 0, '', '[[WebSignup? &tpl=`FormSignup` &groups=`Registered Users`]]', 1, 3, 3, 0, 0, 1, 1144904400, 1, 1158320704, 0, 0, 0, 0, 0, '', 0, 0, 0, 0, 0, 0, 1);


REPLACE INTO `{PREFIX}site_content` VALUES (6, 'document', 'text/html', 'お問い合わせ', 'お問い合わせ [(site_name)]', '', 'contact-us', '', 1, 0, 0, 0, 0, '', '[!eForm? &formid=`ContactForm` &subject=`[+subject+]` &to=`[(email_sender)]` &ccsender=`1` &tpl=`ContactForm` &report=`ContactFormReport` &invalidClass=`invalidValue` &requiredClass=`requiredValue` &cssStyle=`ContactStyles` &gotoid=`46`  !]\r\n', 0, 3, 14, 1, 0, 1, 1144904400, 1, 1159303922, 0, 0, 0, 0, 0, 'お問い合わせ', 0, 0, 0, 0, 0, 0, 0);


REPLACE INTO `{PREFIX}site_content` VALUES (7, 'document', 'text/html', '404 - Document Not Found', 'お探しのページが見当たりません (Page Not Found)', '', 'doc-not-found', '', 1, 0, 0, 0, 0, '', '<p>\r\n存在しないページへアクセスしたようです。 ログインするか、 以下のページにアクセスしてください:\r\n</p>\r\n[[Wayfinder? &amp;startId=`0` &amp;showDescription=`1`]]\r\n<h3>いつもどおりの方法でページを探しますか？それなら、サイト上部の検索機能を使ってお探しのページを検索してください。</h3>', 1, 3, 4, 0, 1, 1, 1144904400, 1, 1159301173, 0, 0, 0, 0, 0, '', 0, 0, 0, 0, 0, 0, 1);


REPLACE INTO `{PREFIX}site_content` VALUES (8, 'document', 'text/html', '検索結果', '検索結果', '', 'search-results', '', 1, 0, 0, 0, 0, '', '[!AjaxSearch? &AS_showForm=`0` &ajaxSearch=`0`!]', 0, 3, 5, 0, 0, 1, 1144904400, 1, 1158613055, 0, 0, 0, 0, 0, '', 1, 0, 0, 0, 0, 0, 1);


REPLACE INTO `{PREFIX}site_content` VALUES (9, 'document', 'text/html', 'ミニブログのハウトゥ', 'MODxでミニブログのエントリーを投稿する方法について', '', 'article-1126081344', '', 1, 0, 0, 2, 1, '', '<p>\r\nミニブログのセットアップは、比較的簡単です。新しい投稿を始めるために必要なことは以下のとおりです:\r\n</p>\r\n<ol>\r\n		\r\n	<li><a href=\"[(site_url)]manager/\">MODxコントロールパネル</a>へログイン。</li>	\r\n	<li><strong>「ユーザー」タブ &gt; 「ウェブユーザー」タブ</strong>をクリックして、新しいユーザーを作成。</li>	\r\n	<li>必ずページ下部で<strong>Site Admins</strong> をチェックするようにしてください。</li>	<!-- splitter -->	\r\n	<li><a href=\"[~4~]\">ログインページ</a>へ移動して新規作成したウェブユーザーアカウントでログインしてください。</li>	\r\n	<li>ブログページの右コラムの上部に、自動的に <a href=\"[~3~]\">「ブログエントリーを追加」</a> メニューが表示されていることに気がつくでしょう。</li>\r\n</ol>\r\n{{Comments}}', 1, 3, 0, 1, 1, -1, 1144904400, 1, 1160171764, 0, 0, 0, 0, 0, '', 0, 0, 0, 0, 0, 0, 1);


REPLACE INTO `{PREFIX}site_content` VALUES (11, 'document', 'text/xml', 'RSS フィード', '[(site_name)] RSS フィード', '', 'feed.rss', '', 1, 0, 0, 0, 0, '', '[[Ditto? &startID=`2` &format=`rss` &summarize=`20` &total=`20` &commentschunk=`Comments`]]', 0, 0, 6, 0, 0, 1, 1144904400, 1, 1160062859, 0, 0, 0, 0, 0, '', 0, 0, 0, 0, 0, 0, 1);


REPLACE INTO `{PREFIX}site_content` VALUES (14, 'document', 'text/html', 'コンテンツ管理', 'コンテンツマネージメント', '', 'cms', '', 1, 0, 0, 15, 0, '', '<h2管理画面からコンテンツ管理</h2>\r\n<p>MODxのコンテンツ管理ツールは、機能満載で、スキンも利用可能です。新たにユーザーを追加して、管理ツールの一部機能のみ操作許可することもできます。MODxのコンテンツ管理ツールを使えば、新規コンテンツを追加したり、テンプレートを調整したり、ウェブサイトのアセット共用も簡単にできます。また、モジュールを追加して、他のデータセットと連動したり、管理業務を簡易化することも可能です。</p>\r\n<h2>ウェブページ側からコンテンツ管理</h2>\r\n<p>QuickEdit（クイックエディット）を使えば、サイトをブラウザーで見ながら、ページの内容を編集できます。ほとんどすべてのコンテンツ要素とテンプレート変数を、早く、しかも簡単に編集することが可能です。</p>\r\n<h2>ウェブユーザーに新規コンテンツの作成を許可できます。（元文章　Enable web users to add content）</h2>\r\n<p>特定のデータ入力作業も、MODxのAPIを利用すれば簡単です。カスタムメイドのデータ入力は、MODx APIを使用しているコードに容易です - フォームをデザインしたり、必要に応じて修正したりできます。（元文章：Custom data entry is easy to code using the MODx API - so you can design forms and collect whatever information you need.）</p>', 0, 3, 3, 1, 1, 1, 1144904400, 1, 1158331927, 0, 0, 0, 0, 0, 'Manage Content', 0, 0, 0, 0, 0, 0, 0);


REPLACE INTO `{PREFIX}site_content` VALUES (15, 'document', 'text/html', 'MODxの主な機能', 'MODxの主な機能', '', 'features', '', 1, 0, 0, 0, 1, '', '[!Wayfinder?startId=`[*id*]` &outerClass=`topnav`!]', 1, 3, 7, 1, 1, 1, 1144904400, 1, 1158452722, 0, 0, 0, 1144777367, 1, 'MODxの機能', 0, 0, 0, 0, 0, 0, 0);


REPLACE INTO `{PREFIX}site_content` VALUES (16, 'document', 'text/html', 'AjaxとWeb2.0', 'AjaxとWeb2.0', '', 'ajax', '', 1, 1159264800, 0, 15, 0, '', '<strong>Ajaxが満載</strong>\r\n<p>\r\nMODxに実装されている <a href=\"http://mootools.net/\" target=\"_blank\">Mootools</a> javascript libraryライブラリによって、魅力的なサイト作成が可能です。\r\n</p>\r\n<p>\r\nAjaxを活用した検索機能で、このサンプルサイトを検索してみてください。Ajax機能は、フロントエンドの編集機能であるクイックエディット機能にも使用されています。\r\n</p>\r\n<p>\r\n洗練された統合機能は、ドキュメントに使用するスクリプトを最小限に抑えます&hellip;単純なページを不必要なスクリプトで膨張させることではありません！\r\n</p>\r\n<strong>最新のWeb2.0</strong>\r\n<p>\r\nMODxなら、アクセシビリティの高い、正しいＣＳＳレイアウトのサイト管理だって朝飯前ですーウェブ標準に則ったサイト作成が簡単にできます。（もし本当に必要なら、過剰に入れ子されたテーブルレイアウトも作れますよ）\r\n</p>', 1, 3, 1, 1, 1, 1, 1144904400, 1, 1159307504, 0, 0, 0, 0, 0, 'AjaxとWeb2.0', 0, 0, 0, 0, 0, 0, 0);


REPLACE INTO `{PREFIX}site_content` VALUES (18, 'document', 'text/html', 'Just a pretend, older post', 'This post should in fact be archived', '', 'article-1128398162', '', 1, 0, 0, 2, 0, '', '<p>Not so exciting, after all, eh?<br /></p>\r\n', 1, 3, 2, 1, 1, -1, 1144904400, 1, 1159306886, 0, 0, 0, 0, 0, '', 0, 0, 0, 0, 0, 0, 1);


REPLACE INTO `{PREFIX}site_content` VALUES (22, 'document', 'text/html', 'メニューとリスト', '柔軟なメニューとリスト', '', 'menus', '', 1, 1159178400, 0, 15, 0, '', '<h2>Your documents - listed how you want them</h2>\r\n<p>\r\nMODxの文書データ構造は、さまざまな情報表示が可能になるように設計されています。たとえばテンプレート上で動的にメニューを生成するなど、あなたのどんなニーズも満たすことでしょう。\r\n</p>\r\n<p>\r\nMODxの最新リリース以来、コンテンツやテンプレート上で使えるあける有益なスニペットが、コミュニティから生み出されました。 - 中でも、最も便利なスニペットとが、Ditto（ディットー）とWayfinder（ウェイファインダー）の2つです。\r\n</p>\r\n<h2>Wayfinder - メニュー生成スニペット</h2>\r\n<p>\r\nどのような種類のメニューでも実現します。このサイトでは、Wayfinderはドロップダウンメニューの生成に用いられていますが、他のどんなタイプのメニューやサイトマップも生成可能です。 <a href=\"http://www.modxcms.com/Wayfinder-868.html\">Wayfinderの最新版とサポート情報はこちら</a>.\r\n</p>\r\n<h2>Ditto（ディットー - 文章のリストアップスニペット）</h2>\r\n<p>\r\nDittoは、ブログから最新のエントリーリストを生成したり、サイトマップを作ったり、テンプレート変数との組み合わせで関連文書をリストアップしたり、RSSフィードの生成を行ったりします。メニューでさえ作成可能です。このサイトでは、ブログページでエントリー一覧の生成に使われています。また、右サイドのいくつかのテンプレートにも使用されています。 <a href=\"http://modxcms.com/Ditto-487.html\">Dittoの最新版とサポート情報はこちら</a>.\r\n</p>\r\n<h2>カスタマイズは無限に可能</h2>\r\n<p>\r\nDittoとWayfinderのオプション、テンプレートを使用しても、満足のいくデザインや効果が得られない場合、独自のルーチンを作ることもできますし、<a href=\"http://modxcms.com/downloads.html\">MODxのリポジトリ</a>から他のスニペットを探すこともできます。MODxのメニュータイトル、要約、メニューの場所、そのほか諸々は、APIを利用することによって思い通りのデザインを作ることができます。\r\n</p>', 1, 3, 2, 1, 1, 1, 1144904400, 1, 1160148522, 0, 0, 0, 0, 0, 'メニューとリスト', 0, 0, 0, 0, 0, 0, 0);


REPLACE INTO `{PREFIX}site_content` VALUES (24, 'document', 'text/html', '意図的な拡張', '意図的な拡張', '', 'extendable', '', 1, 1159092732, 0, 15, 0, '', '<p>\r\nMODxコミュニティでは、イメージギャラリーやeコマース、その他様々なアドオン部品が <a href=\"http://modxcms.com/downloads.html\">リポジトリ</a> で配布されてます。\r\n</p>\r\n<h2>データバインディングが可能なテンプレート変数</h2>\r\n<p>\r\nテンプレート変数（TVs）は、あなたのドキュメントにパワフルなカスタムフィールドを追加します。 コードの実行結果やデータソースによって異なる情報を返す特殊な例をご紹介します。ここではログインメニューを@バインディングで実現する例を示します。次のフィールドを追加することでログイン状態に従ってメニューの表示内容を変化させることができます。:\r\n<code>@EVAL if ($modx-&gt;getLoginUserID()) return \'ログアウト\'; else return \'ログイン\';</code>\r\n</p>\r\n<h2>Scriptaculous</h2>\r\n<p>\r\n簡単な操作でページ上の様々なパーツに注意を向けさせることができる幾つかのシンプルな演出効果を使うことができます。統合的なサイト検索や関連リンク、新しいドキュメントへのヘッダなどをクリックすることで、それらの動きをページ上で確認することができます。\r\n</p>\r\n<h2>カスタムフォーム</h2>\r\n<p>\r\nカスタムフォームとの関連性を示すために、ウェブユーザー登録システムとログインシステムの呼び出し方法をカスタマイズしてあります。\r\n</p>\r\n<h2>その他</h2>\r\n<h3>\r\n<strong>ブログを書くためのリッチテキストエディタ</strong></h3>\r\n<p>\r\nシンプルなテキスト形式で記事が書けるように、カスタムRTE機能を有効にしたテンプレート変数(TV)を使ってブログが書けるようになってます。\r\n</p>\r\n<h3>\r\n<strong>スマートな概要表示</strong></h3>\r\n<p>\r\n区切りたい位置に&quot;&lt;!-- splitter --&gt;&quot;というタグを入れることで、記事を途中で区切ることができます。また、OL, UL, DIVといった重要なタグが前後に分かれてもタグが閉じるように動作するためレイアウトが崩れることはありません。\r\n</p>', 1, 3, 4, 1, 1, 2, 1144904400, 1, 1159309971, 0, 0, 0, 0, 0, '意図的な拡張', 0, 0, 0, 0, 0, 0, 0);


REPLACE INTO `{PREFIX}site_content` VALUES (32, 'document', 'text/html', 'サイトデザイン', 'サイトデザイン', '', 'design', '', 1, 0, 0, 0, 0, '', '<h3>クレジット表示</h3>\r\n<p>\r\nデフォルトのサイトテーマは<a href=\"http://andreasviklund.com/\">Andreas     Viklund</a>の <a href=\"http://ziworks.com/\" title=\"Complete web design solutions\">ziworks | Web Solutions</a>と <a href=\"http://www.modxhost.com\">MODxHost</a>によるXHTML/CSSバリッドなデザインを採用しました。  \r\n</p>\r\n<h3>スタイル例</h3>\r\n<p>\r\nこのページでは、テンプレートに組み込まれているいくつかのスタイルをご覧いただけます。また、コンテンツを横幅いっぱいに表示できるように、左サイドバーは取り除いてあります。\r\n</p>\r\n<h3 onclick=\"new Effect.toggle( \'styles\' , \'blind\');\" style=\"cursor: pointer\">スタイルリスト (表示/非表示)</h3>\r\n<blockquote>\r\n	<p>\r\n	&quot;引用(blockquote)。 他の文献からの引用や参照を表現する場合に使います。.box　クラスを使うことで同様のボックス表現が可能です。&quot;\r\n	</p>\r\n</blockquote>\r\n<h1>見出し1 ： &lt;H1&gt;～&lt;/H1&gt;</h1>\r\n<h2>見出し2 ： &lt;H2&gt;～&lt;/H2&gt;</h2>\r\n<h3>見出し3 ： &lt;H3&gt;～&lt;/H3&gt;</h3>\r\n<h4>見出し4 ： &lt;H4&gt;～&lt;/H4&gt;</h4>\r\n<h5>見出し5 ： &lt;H5&gt;～&lt;/H5&gt;</h5>\r\n<h6>見出し6 ： &lt;H6&gt;～&lt;/H6&gt;</h6>\r\n<ul>\r\n	<li>順不同リスト, 1</li>\r\n	<li>順不同リスト, 2</li>\r\n	<li>順不同リスト, 3\r\n	<ul>\r\n		<li>順不同リスト, 3-1</li>\r\n		<li>順不同リスト, 3-2</li>\r\n		<li>順不同リスト, 3-3</li>\r\n	</ul>\r\n	</li>\r\n	<li>順不同リスト, 4</li>\r\n</ul>\r\n<ol>\r\n	<li>順序付きリスト, １</li>\r\n	<li>順序付きリスト, 2</li>\r\n	<li>順序付きリスト, 3\r\n	<ol>\r\n		<li>順序付きリスト, 3-1</li>\r\n		<li>順序付きリスト, 3-2</li>\r\n		<li>順序付きリスト, 3-3</li>\r\n	</ol>\r\n	</li>\r\n	<li>順序付きリスト, 4</li>\r\n</ol>\r\n<p>\r\n<a href=\"#\">標準的なリンク表示</a>\r\n</p>\r\n<p>\r\n<strong>強調(strong)テキスト [左寄せ]</strong>\r\n</p>\r\n<p class=\"important center\">\r\n.important および .centered クラスを適用したテキスト\r\n</p>\r\n<p class=\"textright\">\r\n<em>強調(em)テキスト [右寄せ]</em>\r\n</p>\r\n<p class=\"big\">\r\n.big クラスを適用した拡大テキスト\r\n</p>\r\n<p class=\"small\">\r\n.small クラスを適用した縮小テキスト （<span class=\"green\">.green</span> クラスも使えます）\r\n</p>', 1, 3, 10, 1, 1, 2, 1144904400, 1, 1160112322, 0, 0, 0, 1144912754, 1, 'サイトデザイン', 0, 0, 0, 0, 0, 0, 0);


REPLACE INTO `{PREFIX}site_content` VALUES (33, 'document', 'text/html', 'ヘルプ', 'MODxのヘルプ', '', 'geting-help', '', 1, 0, 0, 0, 0, '', '<p>\r\n<a href=\"http://modxcms.com/modx-team.html\" target=\"_blank\">MODxチームは</a> あなたがMODxを快適に使えるように、日々ドキュメント類の改良に努めています。:\r\n</p>\r\n<ul>\r\n	<li>MODxのテンプレート構築に関する基本的な事柄については、<a href=\"http://modxcms.com/designer-guide.html\" target=\"_blank\">デザイナーズガイドをご覧ください</a>。 </li>\r\n	<li>MODxをを利用したコンテンツの編集方法については、<a href=\"http://modxcms.com/editor-guide.html\" target=\"_blank\">コンテンツエディターガイドをご覧ください</a>。 </li>\r\n	<li>管理ツールの詳細とユーザーやグループの設定については、<a href=\"http://modxcms.com/developers-guide.html\" target=\"_blank\">アドミニストレーションガイドを精読ください</a>。</li>\r\n	<a href=\"http://modxcms.com/administration-guide.html\" target=\"_blank\">デベロッパーズガイドで</a>MODxの構造とAPIについて記述しています。\r\n	<li>もし誰かがこのサイトをインストールしていて、それを見たあなた自身がMODxについて知りたくなったとしたら、<a href=\"http://modxcms.com/getting-started.html\" target=\"_blank\">スタートガイドをご覧ください</a>。</li>\r\n</ul>\r\n<p>\r\nそして<a href=\"http://modxcms.com/forums/index.php#10\" target=\"_blank\">MODxフォーラムを利用すれば、</a>いつでも既知の知識を得たり、質疑応答ができます。 \r\n</p>', 1, 3, 8, 1, 1, 2, 1144904400, 2, 1144904400, 0, 0, 0, 0, 0, 'MODxのヘルプ', 0, 0, 0, 0, 0, 0, 0);


REPLACE INTO `{PREFIX}site_content` VALUES (37, 'document', 'text/html', '[*loginName*]', 'このページへのアクセスにはログインが必要です。', '', 'ブログへログイン', '', 1, 0, 0, 0, 0, '', '<p>ブログのエントリーを書くためには、Site Admin webuserとしてのログインが必要です。 エントリーへのコメントにもログインが必要となります。 <a href=\"[~6~]\">Contact the site owner</a> for permissions to create new post, or <a href=\"[~5~]\">create a web user account</a> to automatically receive commenting privileges. If you already have an account, please login below.</p>\r\n\r\n[!WebLogin? &tpl=`FormLogin` &loginhomeid=`3`!]', 1, 3, 12, 0, 0, 1, 1144904400, 1, 1158599931, 0, 0, 0, 0, 0, '', 0, 0, 0, 0, 0, 0, 1);


REPLACE INTO `{PREFIX}site_content` VALUES (39, 'document', 'text/html', 'テンプレート', 'Templates', 'テンプレートサンプル', 'templates', '', 1, 0, 0, 0, 1, '', '<p>\r\nこのページでは、レイアウトとスタイルシートを交互に試すための簡単な方法をご紹介します。テンプレートの変更は最初のリンク群をクリックしてください。スタイルシートの変更は、2番目のリンク群をクリックしてください。\r\n</p>\r\n<h4>テンプレートの変更:</h4>\r\n[!Wayfinder?startId=`[*id*]` &amp;outerClass=`topnav`!]<br />\r\n<h4>スタイルシートの変更:</h4>\r\n{{styles}}', 1, 3, 9, 1, 0, 1, 1144904400, 1, 1159978559, 0, 0, 0, 1144628721, 1, 'テンプレート', 0, 0, 0, 0, 0, 0, 0);


REPLACE INTO `{PREFIX}site_content` VALUES (42, 'document', 'text/html', 'MODxCSS Wide', 'MODxCSS Wide', 'MODxCSS Wide', 'modxcss_wide', '', 1, 0, 0, 39, 0, '', '<p>\r\nこのページでは、レイアウトとスタイルシートを交互に試すための簡単な方法をご紹介します。テンプレートの変更は最初のリンク群をクリックしてください。スタイルシートの変更は、2番目のリンク群をクリックしてください。\r\n</p>\r\n<h4>テンプレートの変更:</h4>\r\n[!Wayfinder?startId=`[*parent*]` &amp;outerClass=`topnav`!]<br />\r\n<h4>スタイルシートの変更:</h4>\r\n{{styles}}', 1, 2, 9, 1, 0, 1, 1144904400, 1, 1159978559, 0, 0, 0, 1144628721, 1, 'MODxCSS Wide', 0, 0, 0, 0, 0, 0, 0);


REPLACE INTO `{PREFIX}site_content` VALUES (43, 'document', 'text/html', 'MODxCSS', 'MODxCSS', 'MODxCSS', 'modxcss', '', 1, 0, 0, 39, 0, '', '<p>\r\nこのページでは、レイアウトとスタイルシートを交互に試すための簡単な方法をご紹介します。テンプレートの変更は最初のリンク群をクリックしてください。スタイルシートの変更は、2番目のリンク群をクリックしてください。\r\n</p>\r\n<h4>テンプレートの変更:</h4>\r\n[!Wayfinder?startId=`[*parent*]` &amp;outerClass=`topnav`!]<br />\r\n<h4>スタイルシートの変更:</h4>\r\n{{styles}}', 1, 1, 9, 1, 0, 1, 1144904400, 1, 1159978559, 0, 0, 0, 1144628721, 1, 'MODxCSS', 0, 0, 0, 0, 0, 0, 0);


REPLACE INTO `{PREFIX}site_content` VALUES (44, 'reference', 'text/html', 'MODxHost', 'MODxHost', 'MODxHost', 'modxhost_tpl', '', 1, 0, 0, 39, 0, '', 'index.php?id=39', 0, 0, 1, 1, 0, 1, 1144904400, 1, 1158505455, 0, 0, 0, 1144967650, 1, 'MODxHost', 0, 0, 0, 0, 0, 0, 0);


REPLACE INTO `{PREFIX}site_content` VALUES (46, 'document', 'text/html', 'ありがとうございます', '', '', 'thank-you', '', 1, 0, 0, 0, 0, '', '<h3>ありがとうございます!</h3>\r\n<p>\r\nコメントありがとうございます。投稿されたコメントはシステムに保存され、皆が読める状態になりました。 また、あなたの受信トレイにメッセージのコピーが受信されていることでしょう。\r\n</p>\r\n<p>\r\n投稿内容をチェックするよう最善を尽くしておりますのでご安心ください。なお、月曜日の場合は、数日待ってから再試行してください。\r\n</p>', 1, 3, 13, 1, 1, 1, 1159302141, 1, 1159302892, 0, 0, 0, 1159302182, 1, '', 0, 0, 0, 0, 0, 0, 1);


#
# Dumping data for table `site_htmlsnippets`
#


REPLACE INTO `{PREFIX}site_htmlsnippets` VALUES (1, 'WebLoginSideBar', 'サイドバーのウェブログインパーツの表示形式', 0, 2, 0, '<!-- #declare:separator <hr /> --> \r\n<!-- login form section-->\r\n<form method=\"post\" name=\"loginfrm\" action=\"[+action+]\" style=\"margin: 0px; padding: 0px;\"> \r\n<input type=\"hidden\" value=\"[+rememberme+]\" name=\"rememberme\"> \r\n<table border=\"0\" cellspacing=\"0\" cellpadding=\"0\">\r\n<tr>\r\n<td>\r\n<table border=\"0\" cellspacing=\"0\" cellpadding=\"0\">\r\n  <tr>\r\n	<td><b>ユーザー:</b></td>\r\n	<td><input type=\"text\" name=\"username\" tabindex=\"1\" onkeypress=\"return webLoginEnter(document.loginfrm.password);\" size=\"5\" style=\"width: 100px;\" value=\"[+username+]\" /></td>\r\n  </tr>\r\n  <tr>\r\n	<td><b>パスワード:</b></td>\r\n	<td><input type=\"password\" name=\"password\" tabindex=\"2\" onkeypress=\"return webLoginEnter(document.loginfrm.cmdweblogin);\" size=\"5\" style=\"width: 100px;\" value=\"\" /></td>\r\n  </tr>\r\n  <tr>\r\n	<td><label for=\"chkbox\" style=\"cursor:pointer\">ログイン情報を記憶:&nbsp; </label></td>\r\n	<td>\r\n	<table width=\"100%\"  border=\"0\" cellspacing=\"0\" cellpadding=\"0\">\r\n	  <tr>\r\n		<td valign=\"top\"><input type=\"checkbox\" id=\"chkbox\" name=\"chkbox\" tabindex=\"4\" size=\"1\" value=\"\" [+checkbox+] onClick=\"webLoginCheckRemember()\" /></td>\r\n		<td align=\"right\">									\r\n		<input type=\"submit\" value=\"[+logintext+]\" name=\"cmdweblogin\" /></td>\r\n	  </tr>\r\n	</table>\r\n	</td>\r\n  </tr>\r\n  <tr>\r\n	<td colspan=\"2\"><a href=\"#\" onclick=\"webLoginShowForm(2);return false;\">パスワードをお忘れですか？</a></td>\r\n  </tr>\r\n</table>\r\n</td>\r\n</tr>\r\n</table>\r\n</form>\r\n<hr>\r\n<!-- log out hyperlink section -->\r\n<a href=\'[+action+]\'>[+logouttext+]</a>\r\n<hr>\r\n<!-- Password reminder form section -->\r\n<form name=\"loginreminder\" method=\"post\" action=\"[+action+]\" style=\"margin: 0px; padding: 0px;\">\r\n<input type=\"hidden\" name=\"txtpwdrem\" value=\"0\" />\r\n<table border=\"0\">\r\n	<tr>\r\n	  <td>メールアドレスを入力してください。<br />below to receive your password:</td>\r\n	</tr>\r\n	<tr>\r\n	  <td><input type=\"text\" name=\"txtwebemail\" size=\"24\" /></td>\r\n	</tr>\r\n	<tr>\r\n	  <td align=\"right\"><input type=\"submit\" value=\"実行\" name=\"cmdweblogin\" />\r\n	  <input type=\"reset\" value=\"キャンセル\" name=\"cmdcancel\" onclick=\"webLoginShowForm(1);\" /></td>\r\n	</tr>\r\n  </table>\r\n</form>\r\n\r\n', 0);


REPLACE INTO `{PREFIX}site_htmlsnippets` VALUES (2, 'FormBlog', 'ブログエントリーの入力フォーム', 0, 3, 0, '<form name=\"NewsPublisher\" method=\"post\" action=\"[~[*id*]~]\">\r\n    <fieldset>\r\n        <h3>記事の設定</h3>\r\n        <p>ノート: 公開日時を空にするとすぐに公開されます。</p>\r\n        <input name=\"NewsPublisherForm\" type=\"hidden\" value=\"on\" />\r\n    	<label for=\"pagetitle\">ページタイトル <abbr title=\"ウィンドウのタイトル\">?</abbr>: <input name=\"pagetitle\" id=\"pagetitle\" type=\"text\" size=\"40\" value=\"[+pagetitle+]\" /></label><br />\r\n    	<label for=\"longtitle\">ヘッドライン <abbr title=\"記事の題名\">?</abbr>: <input name=\"longtitle\" id=\"longtitle\" type=\"text\" size=\"40\" value=\"[+longtitle+]\" /></label><br />\r\n\r\n    	<label for=\"pub_date\">公開日時: <input name=\"pub_date\" id=\"pub_date\" type=\"text\" value=\"[+pub_date+]\" size=\"40\" readonly=\"readonly\" />\r\n    	<a onclick=\"nwpub_cal1.popup();\" onmouseover=\"window.status=\'Select date\'; return true;\" onmouseout=\"window.status=\'\'; return true;\"><img src=\"manager/media/style/MODxLight/images/icons/cal.gif\" width=\"16\" height=\"16\" alt=\"Select date\" /></a>\r\n    	<a onclick=\"document.NewsPublisher.pub_date.value=\'\'; return true;\" onmouseover=\"window.status=\'Remove date\'; return true;\" onmouseout=\"window.status=\'\'; return true;\"><img src=\"manager/media/style/MODxLight/images/icons/cal_nodate.gif\" width=\"16\" height=\"16\" alt=\"Remove date\" /></a></label>\r\n	</fieldset>\r\n	\r\n	<fieldset>\r\n    	<h3>コンテンツ</h3>\r\n    	<p>要約（序説）はオプションですが、RSSフィードやブログのメインページでサマリ表示されます。</p>\r\n    	<label for=\"introtext\">要約（序説） (オプション：なるべく設定してください):<textarea name=\"introtext\" cols=\"50\" rows=\"5\">[+introtext+]</textarea></label><br />\r\n    	<label for=\"content\">内容:[*blogContent*]</label>\r\n	</fieldset>\r\n	\r\n	<fieldset>\r\n    	<h3>入力はったこれだけ</h3>\r\n		<label>どうです？... 簡単でしょ？</label>\r\n    	<input name=\"send\" type=\"submit\" value=\"投稿！\" class=\"button\" />\r\n	</fieldset>	\r\n</form>\r\n<script language=\"JavaScript\" src=\"manager/media/script/datefunctions.js\"></script>\r\n<script type=\"text/javascript\">\r\n		var elm_txt = {}; // dummy\r\n		var pub = document.forms[\"NewsPublisher\"].elements[\"pub_date\"];\r\n		var nwpub_cal1 = new calendar1(pub,elm_txt);\r\n		nwpub_cal1.path=\"manager/media/\";\r\n		nwpub_cal1.year_scroll = true;\r\n		nwpub_cal1.time_comp = true;	\r\n</script>', 0);


REPLACE INTO `{PREFIX}site_htmlsnippets` VALUES ('3','FormLogin','ウェブログインフォーム','0','2','0','<!-- #declare:separator <hr> --> \r\n<!-- ログインフォームセクション -->\r\n<form method=\"post\" name=\"loginfrm\" action=\"[+action+]\"> \r\n    <input type=\"hidden\" value=\"[+rememberme+]\" name=\"rememberme\" /> \r\n    <fieldset>\r\n        <h3>ログイン情報</h3>\r\n        <label for=\"username\">ユーザー名: <input type=\"text\" name=\"username\" id=\"username\" tabindex=\"1\" onkeypress=\"return webLoginEnter(document.loginfrm.password);\" value=\"[+username+]\" /></label>\r\n    	<label for=\"password\">パスワード: <input type=\"password\" name=\"password\" id=\"password\" tabindex=\"2\" onkeypress=\"return webLoginEnter(document.loginfrm.cmdweblogin);\" value=\"\" /></label>\r\n    	<input type=\"checkbox\" id=\"checkbox_1\" name=\"checkbox_1\" tabindex=\"3\" size=\"1\" value=\"\" [+checkbox+] onclick=\"webLoginCheckRemember()\" /><label for=\"checkbox_1\" class=\"checkbox\">ログイン情報を記憶</label>\r\n    	<input type=\"submit\" value=\"[+logintext+]\" name=\"cmdweblogin\" class=\"button\" />\r\n	<a href=\"#\" onclick=\"webLoginShowForm(2);return false;\" id=\"forgotpsswd\">パスワードをお忘れですか？</a>\r\n	</fieldset>\r\n</form>\r\n<hr>\r\n<!-- ログアウトリンクセクション -->\r\n<h4>ログイン中</h4>\r\n<a href=\"[+action+]\" class=\"button\">[+logouttext+]</a> しますか?\r\n<hr>\r\n<!-- パスワードリマインダーセクション -->\r\n<form name=\"loginreminder\" method=\"post\" action=\"[+action+]\">\r\n    <fieldset>\r\n        <h3>どなたにもよくあること...</h3>\r\n        <input type=\"hidden\" name=\"txtpwdrem\" value=\"0\" />\r\n        <label for=\"txtwebemail\">あなたのメールアドレスを入力するとパスワードがリセットできます。<input type=\"text\" name=\"txtwebemail\" id=\"txtwebemail\" size=\"24\" /></label>\r\n        <label>ログインフォームに戻るにはキャンセルボタンを押してください。</label>\r\n    	<input type=\"submit\" value=\"実行\" name=\"cmdweblogin\" class=\"button\" /> <input type=\"reset\" value=\"キャンセル\" name=\"cmdcancel\" onclick=\"webLoginShowForm(1);\" class=\"button\" style=\"clear:none;display:inline\" />\r\n    </fieldset>\r\n</form>\r\n','0');


REPLACE INTO `{PREFIX}site_htmlsnippets` VALUES ('4','FormSignup','ウェブサインアップフォーム','0','2','0','<!-- #declare:separator <hr> --> \r\n<!-- login form section-->\r\n<p>　* : 必須</p>\r\n<form method=\"post\" name=\"websignupfrm\" action=\"[+action+]\">\r\n  <fieldset>\r\n    <h3>ユーザー情報</h3>\r\n      <label for=\"username\">ID:* <input type=\"text\" name=\"username\" id=\"username\" class=\"inputBox\" size=\"20\" maxlength=\"30\" value=\"[+username+]\" /></label>\r\n      <label for=\"fullname\">フルネーム: <input type=\"text\" name=\"fullname\" id=\"fullname\" class=\"inputBox\" size=\"20\" maxlength=\"100\" value=\"[+fullname+]\" /></label>\r\n      <label for=\"email\">メールアドレス:* <input type=\"text\" name=\"email\" id=\"email\" class=\"inputBox\" size=\"20\" value=\"[+email+]\" /></label>\r\n    </fieldset>\r\n	\r\n  <fieldset>\r\n    <h3>パスワード</h3>\r\n    <label for=\"password\">パスワード:* <input type=\"password\" name=\"password\" id=\"password\" class=\"inputBox\" size=\"20\" /></label>\r\n    <label for=\"confirmpassword\">パスワード（確認）:* <input type=\"password\" name=\"confirmpassword\" id=\"confirmpassword\" class=\"inputBox\" size=\"20\" /></label>\r\n  </fieldset>\r\n	\r\n  <fieldset>\r\n    <h3>オプションプロフィール</h3>\r\n    <label for=\"country\">国:</label>\r\n    <select size=\"1\" name=\"country\" id=\"country\">\r\n      <option value=\"\" selected=\"selected\">&nbsp;</option>\r\n      <option value=\"107\">Japan</option>\r\n      <option value=\"223\">United States</option>\r\n    </select>\r\n  </fieldset>\r\n        \r\n  <fieldset>\r\n    <h3>画像認証</h3>\r\n      <p>見えている文字を入力してください。画像をクリックするとコードを変えることができます。</p>\r\n      <label>認証コード:* <input type=\"text\" name=\"formcode\" class=\"inputBox\" size=\"20\" /></label>\r\n      <a href=\"[+action+]\"><img align=\"top\" src=\"manager/includes/veriword.php\" width=\"148\" height=\"60\" alt=\"見づらい場合は画像をクリックしてください。\" style=\"border: 1px solid #039\" /></a>\r\n  </fieldset>\r\n        \r\n  <fieldset>\r\n    <input type=\"submit\" value=\"登録\" name=\"cmdwebsignup\" />\r\n  </fieldset>\r\n</form>\r\n\r\n<script language=\"javascript\" type=\"text/javascript\"> \r\n  var id = \"[+country+]\";\r\n  var f = document.websignupfrm;\r\n  var i = parseInt(id);	\r\n  if (!isNaN(i)) f.country.options[i].selected = true;\r\n</script>\r\n<hr>\r\n<!-- notification section -->\r\n<p class=\"message\">登録完了！<br />アカウントは正しく作成されました。 登録された情報をあなたのメールアドレスに送信しました。\r\n</p>\r\n','0');


REPLACE INTO `{PREFIX}site_htmlsnippets` VALUES (5, 'FormBlogComments', 'Comment to show up beneath a blog for registered user comments', 0, 3, 0, '<a name="comments"></a>\r\n<p style="margin-top: 1em;font-weight:bold">Enter your comments in the space below (registered site users only):</p>\r\n[!UserComments? &canpost=`Registered Users, Site Admins` &makefolder=`0` &postcss=`comment` &titlecss=`commentTitle` &numbercss=`commentNum` &altrowcss=`commentAlt` &authorcss=`commentAuthor` &ownercss=`commentMe` &sortorder=`0`!]', 0);


REPLACE INTO `{PREFIX}site_htmlsnippets` VALUES (6, 'nl_sidebar', 'Default Template TPL for Ditto', 0, 3, 0, '<strong><a href="[~[+id+]~]" title="[+title+]">[+title+]</a></strong><br />\r\n[+longtitle+]<br /><br />', 0);


REPLACE INTO `{PREFIX}site_htmlsnippets` VALUES (7, 'styles', 'Stylesheet switcher list', 0, 1, 0, '<div id="modxhost">The CSS Themes can only be used on the MODxCSS and MODxCSSW Layouts</div>\r\n<script type="text/javascript">$(''modxhost'').style.display=''none'';</script>\r\n<ul class="links">\r\n<li><a href="#" onclick="setActiveStyleSheet(''Trend''); return false;">Trend (Default)</a></li>\r\n<li><a href="#" onclick="setActiveStyleSheet(''Trend (Alternate)''); return false;" >Trend (Alternate)</a></li>\r\n<li><a href="#" onclick="setActiveStyleSheet(''ZiX''); return false;" >ZiX (Clean)</a></li>\r\n<li><a href="#" onclick="setActiveStyleSheet(''ZiX Background''); return false;" >ZiX (Background)</a></li>\r\n<li><a href="#" onclick="setActiveStyleSheet(''Light''); return false;" >Light</a></li>\r\n<li><a href="#" onclick="setActiveStyleSheet(''Light Green''); return false;" >Light Green</a></li>\r\n<li><a href="#" onclick="setActiveStyleSheet(''Dark''); return false;" >Dark</a></li>\r\n    </ul>', 0);


REPLACE INTO `{PREFIX}site_htmlsnippets` VALUES (8, 'ditto_blog', 'Blog Template', 0, 3, 0, '<div class="ditto_summaryPost">\r\n\  <h3><a href="[~[+id+]~]" title="[+title+]">[+title+]</a></h3>\r\n  <div class="ditto_info" >By <strong>[+author+]</strong> on [+date+]. <a  href="[~[+id+]~]#commentsAnchor">Comments\r\n  ([!Jot?&docid=`[+id+]`&action=`count-comments`!])</a></div><div class="ditto_tags">Tags: [+tagLinks+]</div>\r\n  [+summary+]\r\n  <p class="ditto_link">[+link+]</p>\r\n</div>', 0);


REPLACE INTO `{PREFIX}site_htmlsnippets` VALUES (9, 'footer', 'Site Template Footer', 0, 1, 0, '[(site_name)] is powered by <a href="http://modxcms.com/" title="Powered by MODx, Do more with less.">MODx CMS</a> |\r\n      <span id="andreas">Design by <a href="http://andreasviklund.com/">Andreas Viklund</a></span>\r\n<span id="zi" style="display: none">Designed by <a href="http://ziworks.com/" target="_blank" title="E-Business &amp; webdesign solutions">ziworks</a></span>\r\n\r\n<!-- the modx icon -->\r\n\r\n<div id="modxicon"><h6><a href="http://modxcms.com" title="MODx - The XHTML, CSS and Ajax CMS and PHP Application Framework" id="modxicon32">MODx - The XHTML, CSS and Ajax CMS and PHP Application Framework</a></h6></div>', 0);


REPLACE INTO `{PREFIX}site_htmlsnippets` VALUES (10, 'meta', 'Site Template Meta', 0, 1, 0, '<p><a href="http://validator.w3.org/check/referer" title="This page validates as XHTML 1.0 Transitional">Valid <abbr title="eXtensible HyperText Markup Language">XHTML</abbr></a></p>                	<p><a href="http://jigsaw.w3.org/css-validator/check/referer" title="This page uses valid Cascading Stylesheets" rel="external">Valid <abbr title="W3C Cascading Stylesheets">css</abbr></a></p>				    <p><a href="http://modxcms.com/" title="Powered by MODx, Do more with less.">MOD<strong>x</strong></a></p>', 0);


REPLACE INTO `{PREFIX}site_htmlsnippets` VALUES (11, 'mh.InnerRowTpl', 'Inner row template for ModxHost top menu', 0, 8, 0, '<li[+wf.classes+]><a href="[+wf.link+]" title="[+wf.title+]">[+wf.linktext+]</a>[+wf.wrapper+]</li>', 0);


REPLACE INTO `{PREFIX}site_htmlsnippets` VALUES (12, 'mh.InnerTpl', 'Inner nesting template for ModxHost top menu', 0, 8, 0, '<ul style="display:none">\r\n  [+wf.wrapper+]\r\n</ul>', 0);


REPLACE INTO `{PREFIX}site_htmlsnippets` VALUES (13, 'mh.OuterTpl', 'Outer nesting template for ModxHost top menu', 0, 8, 0, '  <ul id="myajaxmenu">\r\n    [+wf.wrapper+]\r\n  </ul>', 0);


REPLACE INTO `{PREFIX}site_htmlsnippets` VALUES (14, 'mh.RowTpl', 'Row template for ModxHost top menu', 0, 8, 0, '<li class="category [+wf.classnames+]"><a href="[+wf.link+]" title="[+wf.title+]">[+wf.linktext+]</a>[+wf.wrapper+]</li>', 0);


REPLACE INTO `{PREFIX}site_htmlsnippets` VALUES (15, 'Comments', 'Comments (Jot) showing beneath a blog entry.', 0, 3, 0, '<div id="commentsAnchor">\r\n[!Jot? &customfields=`name,email` &subscribe=`1` &pagination=`4` &badwords=`dotNet` &canmoderate=`Site Admins` !]\r\n</div>', 0);


REPLACE INTO `{PREFIX}site_htmlsnippets` VALUES (16, 'ContactForm', '', 0, 5, 0, '<p class="error">[+validationmessage+]</p>\r\n\r\n<form method="post" action="[~[*id*]~]" id="EmailForm" name="EmailForm">\r\n\r\n	<fieldset>\r\n		<h3> Contact Form</h3>\r\n\r\n		<input name="formid" type="hidden" value="ContactForm" />\r\n\r\n		<label for="cfName">Your name:\r\n		<input name="name" id="cfName" class="text" type="text" eform="Your Name::1:" /> </label>\r\n\r\n		<label for="cfEmail">Your Email Address:\r\n		<input name="email" id="cfEmail" class="text" type="text" eform="Email Address:email:1" /> </label>\r\n\r\n		<label for="cfRegarding">Regarding:</label>\r\n		<select name="subject" id="cfRegarding" eform="Form Subject::1">\r\n			<option value="General Inquiries">General Inquiries</option>\r\n			<option value="Press">Press or Interview Request</option>\r\n			<option value="Partnering">Partnering Opportunities</option>\r\n		</select>\r\n\r\n		<label for="cfMessage">Message: \r\n		<textarea name="message" id="cfMessage" rows="4" cols="20" eform="Message:textarea:1"></textarea>\r\n		</label>\r\n\r\n		<label>&nbsp;</label><input type="submit" name="contact" id="cfContact" class="button" value="Send This Message" />\r\n\r\n	</fieldset>\r\n\r\n</form>\r\n', 0);


REPLACE INTO `{PREFIX}site_htmlsnippets` VALUES (17, 'ContactFormReport', '', 0, 5, 0, '<p>This is a response sent by <b>[+name+]</b> using the feedback form on the website. The details of the message follow below:</p>\r\n\r\n\r\n<p>Name: [+name+]</p>\r\n<p>Email: [+email+]</p>\r\n<p>Regarding: [+subject+]</p>\r\n<p>comments:<br />[+message+]</p>\r\n\r\n<p>You can use this link to reply: <a href="mailto:[+email+]?subject=RE: [+subject+]">[+email+]</a></p>\r\n', 0);


REPLACE INTO `{PREFIX}site_htmlsnippets` VALUES (18, 'reflect_month_tpl', 'For the yearly archive. Use with Ditto.', 0, 3, 0, '<a href="[+url+]" title="[+month+] [+year+]" class="reflect_month_link">[+month+] [+year+]</a>', 0);


REPLACE INTO `{PREFIX}site_htmlsnippets` VALUES (19, 'ContactStyles', 'Styles for form validation', 0, 5, 0, '<style type="text/css">\r\ndiv.errors{ color:#F00; }\r\n#EmailForm .invalidValue{ background: #FFDFDF; border:1px solid #F00; }\r\n#EmailForm .requiredValue{ background: #FFFFDF; border:1px solid #F00; }\r\n</style>', 0);



#
# Dumping data for table `site_keywords`
#


REPLACE INTO `{PREFIX}site_keywords` VALUES ('1','MODx');


REPLACE INTO `{PREFIX}site_keywords` VALUES ('2','content management system');


REPLACE INTO `{PREFIX}site_keywords` VALUES ('3','Front End Editing');


REPLACE INTO `{PREFIX}site_keywords` VALUES ('4','login');


#
# Dumping data for table `site_templates`
#


REPLACE INTO `{PREFIX}site_templates` VALUES ('1','MODxCSS','MODx CSS template','0','1','','0','<!DOCTYPE html PUBLIC \"-//W3C//DTD XHTML 1.1//EN\" \"http://www.w3.org/TR/xhtml11/DTD/xhtml11.dtd\">\r\n<html xmlns=\"http://www.w3.org/1999/xhtml\" xml:lang=\"en\">\r\n<head>\r\n<meta http-equiv=\"content-type\" content=\"text/html; charset=iso-8859-1\" />\r\n<base href=\"[(site_url)]\" />\r\n<link rel=\"alternate\" type=\"application/rss+xml\" title=\"RSS 2.0\" href=\"[(site_url)][~11~]\" />\r\n<link title=\"Trend\" rel=\"stylesheet\" type=\"text/css\" href=\"[(base_url)]assets/templates/default/fashion-modx-clear.css\" />\r\n<link rel=\"stylesheet\" type=\"text/css\" href=\"[(base_url)]assets/templates/default/modx.css\" />\r\n<link rel=\"stylesheet\" type=\"text/css\" href=\"[(base_url)]assets/templates/default/print.css\" media=\"print\" />\r\n<link title=\"ZiX\" rel=\"alternate stylesheet\" type=\"text/css\" href=\"[(base_url)]assets/templates/default/zi-modx-1.css\" />\r\n<link title=\"ZiX Background\" rel=\"alternate stylesheet\" type=\"text/css\" href=\"[(base_url)]assets/templates/default/zi-modx-2.css\" />\r\n<link title=\"Trend (Alternate)\" rel=\"alternate stylesheet\" type=\"text/css\" href=\"[(base_url)]assets/templates/default/default.css\" />\r\n<link title=\"Light\" rel=\"alternate stylesheet\" type=\"text/css\" href=\"[(base_url)]assets/templates/default/light_green.css\" />\r\n<link title=\"Light Green\" rel=\"alternate stylesheet\" type=\"text/css\" href=\"[(base_url)]assets/templates/default/light.css\" />\r\n<link title=\"Dark\" rel=\"alternate stylesheet\" type=\"text/css\" href=\"[(base_url)]assets/templates/default/dark.css\" />\r\n<script src=\"[(base_url)]assets/templates/default/styleswitcher.js\" type=\"text/javascript\"></script>\r\n<title>[(site_name)] | [*pagetitle*]</title>\r\n<script src=\"manager/media/script/scriptaculous/prototype.js\" type=\"text/javascript\"></script>\r\n<script src=\"manager/media/script/scriptaculous/scriptaculous.js\" type=\"text/javascript\"></script>\r\n<script type=\"text/javascript\">\r\nfunction highlight(el,endcolor,duration) {\r\n$(el).style.background = ''#ffd700'';\r\nvar fx = $(el).effects({duration: 1000, transition: Fx.Transitions.linear});\r\nfx.start.delay(duration/2,fx,{\r\n''background-color'': endcolor\r\n});\r\nreturn false;\r\n}\r\n</script>\r\n</head>\r\n<body>\r\n<div id=\"wrap\">\r\n  <div id=\"header\">\r\n    <div id=\"title\">\r\n      <h1><a href=\"[~[(site_start)]~]\">[(site_name)]</a></h1>\r\n      <p id=\"slogan\">{{slogan}}</p>\r\n    </div>\r\n    <h2 class=\"hide\">Main menu</h2>\r\n    <div id=\"mainmenu\">[!Wayfinder?startId=`0` &hereClass=`current` &level=`1` &outerClass=`topnav`!]</div>\r\n    <div class=\"clear\"></div>\r\n  </div>\r\n  <div id=\"leftside\">\r\n    <div id=\"lmenu\" style=\"display: none;\">\r\n      <h2>Menu</h2>\r\n      [!Wayfinder?startId=`0` &hereClass=`` &selfClass=`current` &outerClass=`sidemenu`!]\r\n    </div>\r\n    <h2>Search</h2>\r\n    [[AjaxSearch? &AS_landing=`8` &moreResultsPage=`8` &showMoreResults=`1` &addJscript=`0` &extract=`0` &AS_showResults=`0`]]\r\n    <h2>News</h2>\r\n    [[Ditto? &startID=`2` &summarize=`1` &total=`1` &commentschunk=`Comments` &tpl=`nl_sidebar` &showarch=`0` &truncLen=`100` &truncSplit=`0`]]\r\n    <h2>Login</h2>\r\n    <div id=\"sidebarlogin\">[!WebLogin? &tpl=`FormLogin` &loginhomeid=`[(site_start)]`!]</div>\r\n    <h2>Styles</h2>\r\n    {{styles}} </div>\r\n  <div id=\"contentwide\">\r\n    <h2>[*longtitle*]</h2>\r\n    [*#content*] </div>\r\n  <div id=\"footer\">\r\n    <p> {{footer}}</p>\r\n    <p>MySQL: [^qt^], [^q^] request(s), PHP: [^p^], total: [^t^], document retrieved\r\n      from [^s^].</p>\r\n  </div>\r\n</div>\r\n</body>\r\n</html>\r\n','0');


REPLACE INTO `{PREFIX}site_templates` VALUES ('2','MODxCSS Wide','Wide Template','0','1','','0','<!DOCTYPE html PUBLIC \"-//W3C//DTD XHTML 1.1//EN\" \"http://www.w3.org/TR/xhtml11/DTD/xhtml11.dtd\">\r\n<html xmlns=\"http://www.w3.org/1999/xhtml\" xml:lang=\"en\">\r\n<head>\r\n<meta http-equiv=\"content-type\" content=\"text/html; charset=iso-8859-1\" />\r\n<base href=\"[(site_url)]\" />\r\n<link rel=\"alternate\" type=\"application/rss+xml\" title=\"RSS 2.0\" href=\"[(site_url)][~11~]\" />\r\n<link title=\"Trend\" rel=\"stylesheet\" type=\"text/css\" href=\"[(base_url)]assets/templates/default/fashion-modx-clear.css\" />\r\n<link rel=\"stylesheet\" type=\"text/css\" href=\"[(base_url)]assets/templates/default/modx.css\" />\r\n<link rel=\"stylesheet\" type=\"text/css\" href=\"[(base_url)]assets/templates/default/print.css\" media=\"print\" />\r\n<link title=\"ZiX\" rel=\"alternate stylesheet\" type=\"text/css\" href=\"[(base_url)]assets/templates/default/zi-modx-1.css\" />\r\n<link title=\"ZiX Background\" rel=\"alternate stylesheet\" type=\"text/css\" href=\"[(base_url)]assets/templates/default/zi-modx-2.css\" />\r\n<link title=\"Trend (Alternate)\" rel=\"alternate stylesheet\" type=\"text/css\" href=\"[(base_url)]assets/templates/default/default.css\" />\r\n<link title=\"Light\" rel=\"alternate stylesheet\" type=\"text/css\" href=\"[(base_url)]assets/templates/default/light_green.css\" />\r\n<link title=\"Light Green\" rel=\"alternate stylesheet\" type=\"text/css\" href=\"[(base_url)]assets/templates/default/light.css\" />\r\n<link title=\"Dark\" rel=\"alternate stylesheet\" type=\"text/css\" href=\"[(base_url)]assets/templates/default/dark.css\" />\r\n<script src=\"[(base_url)]assets/templates/default/styleswitcher.js\" type=\"text/javascript\"></script>\r\n<title>[(site_name)] | [*pagetitle*]</title>\r\n<script src=\"manager/media/script/scriptaculous/prototype.js\" type=\"text/javascript\"></script>\r\n<script src=\"manager/media/script/scriptaculous/scriptaculous.js\" type=\"text/javascript\"></script>\r\n<script type=\"text/javascript\">\r\nfunction highlight(el,endcolor,duration) {\r\n$(el).style.background = ''#ffd700'';\r\nvar fx = $(el).effects({duration: 1000, transition: Fx.Transitions.linear});\r\nfx.start.delay(duration/2,fx,{\r\n''background-color'': endcolor\r\n});\r\nreturn false;\r\n}\r\n</script>\r\n</head>\r\n<body>\r\n<div id=\"wrap\">\r\n  <div id=\"header\">\r\n    <div id=\"title\">\r\n      <h1><a href=\"[~[(site_start)]~]\">[(site_name)]</a></h1>\r\n      <p id=\"slogan\">{{slogan}}</p>\r\n    </div>\r\n    <h2 class=\"hide\">Main menu</h2>\r\n    <div id=\"mainmenu\">[!Wayfinder?startId=`0` &hereClass=`current` &level=`1` &outerClass=`topnav`!]</div>\r\n    <div class=\"clear\"></div>\r\n  </div>\r\n  <div id=\"contentfull\">\r\n    <h2>[*longtitle*]</h2>\r\n    [*#content*] </div>\r\n  <div id=\"footer\">\r\n    <p> {{footer}}</p>\r\n    <p>MySQL: [^qt^], [^q^] request(s), PHP: [^p^], total: [^t^], document retrieved\r\n      from [^s^].</p>\r\n  </div>\r\n</div>\r\n</body>\r\n</html>\r\n','0');


REPLACE INTO `{PREFIX}site_templates` VALUES ('3','MODxHost','MODxHost Template','0','1','','0','<!DOCTYPE html PUBLIC \"-//W3C//DTD XHTML 1.0 Transitional//EN\" \"http://www.w3.org/TR/xhtml1/DTD/xhtml1-transitional.dtd\">\r\n<html xmlns=\"http://www.w3.org/1999/xhtml\">\r\n\r\n<head>\r\n  <title>[(site_name)] | [*pagetitle*]</title>\r\n  <meta http-equiv=\"Content-Type\" content=\"text/html; charset=[(modx_charset)]\" />\r\n  <base href=\"[(site_url)]\"></base>\r\n  <link rel=\"stylesheet\" href=\"assets/templates/modxhost/layout.css\" type=\"text/css\" media=\"screen\" />\r\n  <link rel=\"stylesheet\" href=\"assets/templates/modxhost/modxmenu.css\" type=\"text/css\" media=\"screen\" />\r\n  <link rel=\"stylesheet\" href=\"assets/templates/modxhost/form.css\" type=\"text/css\" media=\"screen\" />\r\n  <link rel=\"stylesheet\" href=\"assets/templates/modxhost/modx.css\" type=\"text/css\" media=\"screen\" />\r\n  <link rel=\"stylesheet\" href=\"assets/templates/modxhost/print.css\" type=\"text/css\" media=\"print\" />\r\n  <link rel=\"alternate\" type=\"application/rss+xml\" title=\"RSS 2.0\" href=\"[(site_url)][~11~]\" />\r\n  <script src=\"manager/media/script/mootools/mootools.js\" type=\"text/javascript\"></script>\r\n  <script src=\"assets/templates/modxhost/drop_down_menu.js\" type=\"text/javascript\"></script>\r\n<script type=\"text/javascript\">\r\nfunction setActiveStyleSheet() {\r\nvar el= $(''modxhost'');\r\nel.style.background = ''#ffd700'';\r\nel.style.padding = ''10px'';\r\nel.style.display=''block'';\r\nvar bgEffect = new Fx.Styles(''modxhost'', {duration: 1000,transition: Fx.Transitions.linear});\r\nbgEffect.start( {''background-color'': ''#f9f9f9''});\r\nreturn false;\r\n}\r\n\r\nfunction highlight(el,endcolor,duration) {\r\n$(el).style.background = ''#ffd700'';\r\nvar fx = $(el).effects({duration: 1000, transition: Fx.Transitions.linear});\r\nfx.start.delay(duration/2,fx,{\r\n''background-color'': endcolor\r\n});\r\nreturn false;\r\n}\r\n</script>\r\n</head>\r\n<body>\r\n<div id=\"wrapper\">\r\n  <div id=\"minHeight\"></div>\r\n  <div id=\"outer\">\r\n    <div id=\"inner\">\r\n      <div id=\"right\">\r\n        <div id=\"right-inner\">\r\n          <h1 style=\"text-indent: -5000px;padding: 0px; margin:0px; font-size: 1px;\">[(site_name)]</h1>\r\n          <div id=\"sidebar\">\r\n            <h2>News:</h2>\r\n            [[Ditto? &startID=`2` &summarize=`2` &total=`20` &commentschunk=`Comments` &tpl=`nl_sidebar` &showarch=`0` &truncLen=`100` &truncSplit=`0`]]\r\n            <div id=\"recentdocsctnr\">\r\n              <h2>Most Recent:</h2>\r\n              <a name=\"recentdocs\"></a>[[ListIndexer?LIn_root=0]] </div>\r\n            <h2>Login:</h2>\r\n            <div id=\"sidebarlogin\">[!WebLogin? &tpl=`FormLogin` &loginhomeid=`[(site_start)]`!]</div>\r\n            <h2>Meta:</h2>\r\n            <p><a href=\"http://validator.w3.org/check/referer\" title=\"This page validates as XHTML 1.0 Transitional\">Valid <abbr title=\"eXtensible HyperText Markup Language\">XHTML</abbr></a></p>\r\n            <p><a href=\"http://jigsaw.w3.org/css-validator/check/referer\" title=\"This page uses valid Cascading Stylesheets\" rel=\"external\">Valid <abbr title=\"W3C Cascading Stylesheets\">css</abbr></a></p>\r\n            <p><a href=\"http://modxcms.com\" title=\"Ajax CMS and PHP Application Framework\">MODx</a></p>\r\n          </div>\r\n          <!-- close #sidebar -->\r\n        </div>\r\n        <!-- end right inner-->\r\n      </div>\r\n      <!-- end right -->\r\n      <div id=\"left\">\r\n        <div id=\"left-inner\">\r\n          <div id=\"content\">\r\n            <div class=\"post\">\r\n              <h2>[*longtitle*]</h2>\r\n              [*#content*] </div>\r\n            <!-- close .post (main column content) -->\r\n          </div>\r\n          <!-- close #content -->\r\n        </div>\r\n        <!-- end left-inner -->\r\n      </div>\r\n      <!-- end left -->\r\n    </div>\r\n    <!-- end inner -->\r\n    <div id=\"clearfooter\"></div>\r\n    <div id=\"header\">\r\n      <h1><a id=\"logo\" href=\"[~[(site_start)]~]\" title=\"[(site_name)]\">[(site_name)]</a></h1>\r\n      <div id=\"search\"><!--search_terms--><span id=\"search-txt\">SEARCH</span><a name=\"search\"></a>[!AjaxSearch? ajaxSearch=`1` &AS_landing=`8` &moreResultsPage=`8` &showMoreResults=`1` &addJscript=`0` &extract=`0` &AS_showResults=`0`!]</div>\r\n      <div id=\"ajaxmenu\"> [[Wayfinder?startId=`0` &outerTpl=`mh.OuterTpl` &innerTpl=`mh.InnerTpl` &rowTpl=`mh.RowTpl` &innerRowTpl=`mh.InnerRowTpl` &firstClass=`first` &hereClass=``]] </div>\r\n      <!-- end topmenu -->\r\n    </div>\r\n    <!-- end header -->\r\n    <br style=\"clear:both;height:0;font-size: 1px\" />\r\n    <div id=\"footer\">\r\n      <p> <a href=\"http://modxcms.com\" title=\"Ajax CMS and PHP Application Framework\">Powered\r\n          by MODx</a> &nbsp;<a href=\"http://www.modxhost.com/\" title=\"Template Designed by modXhost.com\">Template &copy; 2006\r\n          modXhost.com</a><br />\r\n        MySQL: [^qt^], [^q^] request(s), PHP: [^p^], total: [^t^], document retrieved\r\n        from [^s^]. </p>\r\n    </div>\r\n    <!-- end footer -->\r\n  </div>\r\n  <!-- end outer div -->\r\n</div>\r\n<!-- end wrapper -->\r\n</body>\r\n</html>','0');


REPLACE INTO `{PREFIX}site_templates` VALUES ('4','MODxHostWithComments','MODxHost Template with comments','0','3','','0','<!DOCTYPE html PUBLIC \"-//W3C//DTD XHTML 1.0 Transitional//EN\" \"http://www.w3.org/TR/xhtml1/DTD/xhtml1-transitional.dtd\">\r\n<html xmlns=\"http://www.w3.org/1999/xhtml\">\r\n\r\n<head>\r\n  <title>[(site_name)] | [*pagetitle*]</title>\r\n  <meta http-equiv=\"Content-Type\" content=\"text/html; charset=[(modx_charset)]\" />\r\n  <base href=\"[(site_url)]\"></base>\r\n  <link rel=\"stylesheet\" href=\"assets/templates/modxhost/layout.css\" type=\"text/css\" media=\"screen\" />\r\n  <link rel=\"stylesheet\" href=\"assets/templates/modxhost/modxmenu.css\" type=\"text/css\" media=\"screen\" />\r\n  <link rel=\"stylesheet\" href=\"assets/templates/modxhost/form.css\" type=\"text/css\" media=\"screen\" />\r\n  <link rel=\"stylesheet\" href=\"assets/templates/modxhost/modx.css\" type=\"text/css\" media=\"screen\" />\r\n  <link rel=\"stylesheet\" href=\"assets/templates/modxhost/print.css\" type=\"text/css\" media=\"print\" />\r\n  <link rel=\"alternate\" type=\"application/rss+xml\" title=\"RSS 2.0\" href=\"[(site_url)][~11~]\" />\r\n  <script src=\"manager/media/script/mootools/mootools.js\" type=\"text/javascript\"></script>\r\n  <script src=\"assets/templates/modxhost/drop_down_menu.js\" type=\"text/javascript\"></script>\r\n<script type=\"text/javascript\">\r\nfunction highlight(el,endcolor,duration) {\r\n$(el).style.background = ''#ffd700'';\r\nvar fx = $(el).effects({duration: 1000, transition: Fx.Transitions.linear});\r\nfx.start.delay(duration/2,fx,{\r\n''background-color'': endcolor\r\n});\r\nreturn false;\r\n}\r\n</script>\r\n</head>\r\n\r\n<body>\r\n<div id=\"wrapper\">\r\n  <div id=\"minHeight\"></div>\r\n  <div id=\"outer\">\r\n    <div id=\"inner\">\r\n      <div id=\"right\">\r\n        <div id=\"right-inner\">\r\n          <h1 style=\"text-indent: -5000px;padding: 0px; margin:0px; font-size: 1px;\">[(site_name)]</h1>\r\n          <div id=\"sidebar\">\r\n            <h2>News:</h2>\r\n            [[Ditto? &startID=`2` &summarize=`2` &total=`20` &commentschunk=`Comments` &tpl=`nl_sidebar` &showarch=`0` &truncLen=`100` &truncSplit=`0`]]\r\n            <div id=\"recentdocsctnr\">\r\n              <h2>Most Recent:</h2>\r\n              <a name=\"recentdocs\"></a>[[ListIndexer?LIn_root=0]] </div>\r\n            <h2>Login:</h2>\r\n            <div id=\"sidebarlogin\">[!WebLogin? &tpl=`FormLogin` &loginhomeid=`[(site_start)]`!]</div>\r\n            <h2>Meta:</h2>\r\n            <p><a href=\"http://validator.w3.org/check/referer\" title=\"This page validates as XHTML 1.0 Transitional\">Valid <abbr title=\"eXtensible HyperText Markup Language\">XHTML</abbr></a></p>\r\n            <p><a href=\"http://jigsaw.w3.org/css-validator/check/referer\" title=\"This page uses valid Cascading Stylesheets\" rel=\"external\">Valid <abbr title=\"W3C Cascading Stylesheets\">css</abbr></a></p>\r\n            <p><a href=\"http://modxcms.com\" title=\"Ajax CMS and PHP Application Framework\">MODx</a></p>\r\n          </div>\r\n          <!-- close #sidebar -->\r\n        </div>\r\n        <!-- end right inner-->\r\n      </div>\r\n      <!-- end right -->\r\n      <div id=\"left\">\r\n        <div id=\"left-inner\">\r\n          <div id=\"content\">\r\n            <div class=\"post\">\r\n              <h2>[*longtitle*]</h2>\r\n              [*#content*]\r\n            </div>\r\n            <!-- close .post (main column content) -->\r\n[!Jot? &customfields=`name,email` &subscribe=`1` &pagination=`10`!]\r\n          </div>\r\n          <!-- close #content -->\r\n        </div>\r\n        <!-- end left-inner -->\r\n      </div>\r\n      <!-- end left -->\r\n    </div>\r\n    <!-- end inner -->\r\n    <div id=\"clearfooter\"></div>\r\n    <div id=\"header\">\r\n      <h1><a id=\"logo\" href=\"[~[(site_start)]~]\" title=\"[(site_name)]\">[(site_name)]</a></h1>\r\n      <div id=\"search\"><!--search_terms--><span id=\"search-txt\">SEARCH</span><a name=\"search\"></a>[!AjaxSearch? ajaxSearch=`1` &AS_landing=`8` &moreResultsPage=`8` &showMoreResults=`1` &addJscript=`0` &extract=`0` &AS_showResults=`0`!]</div>\r\n      <div id=\"ajaxmenu\"> [[Wayfinder?startId=`0` &outerTpl=`mh.OuterTpl` &innerTpl=`mh.InnerTpl` &rowTpl=`mh.RowTpl` &innerRowTpl=`mh.InnerRowTpl` &firstClass=`first` &hereClass=``]] </div>\r\n      <!-- end topmenu -->\r\n    </div>\r\n    <!-- end header -->\r\n    <br style=\"clear:both;height:0;font-size: 1px\" />\r\n    <div id=\"footer\">\r\n      <p> <a href=\"http://modxcms.com\" title=\"Ajax CMS and PHP Application Framework\">Powered\r\n          by MODx</a> &nbsp;<a href=\"http://www.modxhost.com/\" title=\"Template Designed by modXhost.com\">Template &copy; 2006\r\n          modXhost.com</a><br />\r\n        MySQL: [^qt^], [^q^] request(s), PHP: [^p^], total: [^t^], document retrieved\r\n        from [^s^]. </p>\r\n    </div>\r\n    <!-- end footer -->\r\n  </div>\r\n  <!-- end outer div -->\r\n</div>\r\n<!-- end wrapper -->\r\n</body>\r\n</html>','0');


#
# Dumping data for table `site_tmplvars`
#


REPLACE INTO `{PREFIX}site_tmplvars` VALUES ('1','richtext','blogContent','blogContent','RTE for the new blog entries','0','0','0','','0','richtext','&w=383px&h=450px&edt=TinyMCE','');


REPLACE INTO `{PREFIX}site_tmplvars` VALUES ('2','text','loginName','loginName','Conditional name for the Login menu item','0','0','0','','0','','','@EVAL if ($modx->getLoginUserID()) return \'Logout\'; else return \'Login\';');


REPLACE INTO `{PREFIX}site_tmplvars` VALUES ('3','text','documentTags','Tags','Space delimited tags for the current document','0','3','0','','0','','','');


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


REPLACE INTO `{PREFIX}site_tmplvar_templates` VALUES ('3','5','0');


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
# Dumping data for table `categories`
#


REPLACE INTO `{PREFIX}categories` VALUES ('1','MODx default templates');


REPLACE INTO `{PREFIX}categories` VALUES ('2','User Management');


REPLACE INTO `{PREFIX}categories` VALUES ('3','News, Blogs and Catalogs');


REPLACE INTO `{PREFIX}categories` VALUES ('4','Navigation');


REPLACE INTO `{PREFIX}categories` VALUES ('5','Forms and Mail');


REPLACE INTO `{PREFIX}categories` VALUES ('6','Core and Manager');


REPLACE INTO `{PREFIX}categories` VALUES ('7','Frontend');


REPLACE INTO `{PREFIX}categories` VALUES ('8','MODxHost Menu');


REPLACE INTO `{PREFIX}categories` VALUES ('9','Demo Content');


REPLACE INTO `{PREFIX}categories` VALUES ('10','Search');


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


