<?php
if(IN_MANAGER_MODE!="true") die("<b>INCLUDE_ORDERING_ERROR</b><br /><br />Please use the MODx Content Manager instead of accessing this file directly.");
?>
<style type="text/css">
h3 {font-weight:bold;letter-spacing:2px;font-size:1;margin-top:10px;}
pre {border:1px dashed #ccc;background-color:#fcfcfc;padding:15px;}
ul {margin-bottom:15px;}
</style>

<div class="sectionHeader">知っておくと便利</div>
<div class="sectionBody" style="padding:10px 20px;">
<h3>フレンドリーURLを有効にする</h3>
<p>
各ページのURLはindex.php?id=xxxという形式になっていますが、サーバ側にインストールされているmod_rewriteモジュールの機能を利用し、静的構成のサイトのような /dir/page.html という形式でURLを扱うこともできます。</p>
<p>
まず、インストールディレクトリにある「ht.access」を「.htaccess」にリネームします(※サーバによってはOptions +FollowSymlinks記述を有効にしないとサーバのURL書き換え機能が働かないことがあります)。次にMODx側のフレンドリーURL出力を有効にするために<a href="index.php?a=17">グローバル設定</a>を開き、「フレンドリーURLの使用」を「はい」にしてください。すると関連する設定項目が追加表示されます。
</p>
<ul>
<li>フレンドリーURLの接頭辞 → 空白</li>
<li>フレンドリーURLの接尾辞 →「 .html」</li>
<li>フレンドリエイリアス →「はい」</li>
<li>エイリアスパスを使用 →「はい」</li>
<li>重複エイリアスを許可 →「はい」</li>
<li>エイリアス自動生成 →「いいえ」</li>
</ul>
<p>
一般的にはこのように設定します。拡張子を自由にコントロールしたい場合は「フレンドリーURLの接尾辞」を空白にしておいて、リソースのエイリアスで拡張子込みのファイル名を指定するとよいでしょう。たとえばCSSファイルやXMLファイルもリソースとして管理したいが、拡張子がhtmlになるのを避けたい場合に有効です。また、MODxのURLコントロール機能を補佐する<a href="http://www.google.com/cse?cx=007286147079563201032%3Aigbcdgg0jyo&ie=UTF-8&q=SEO+Strict+URLs" target="_blank">「SEO Strict URLs」</a>プラグインが知られています。
</p>

<h3>ナビゲーションを設置する</h3>

<p>
標準で同梱されているWayfinderを利用します。テンプレート中に[[Wayfinder]]と記述するだけで、とりあえずその時点で作られているリソースのリンク一覧を動的に出力します。</p>
<pre>
[[Wayfinder?startId=0&hideSubMenus=true]]
</pre>
<p>
一般的にはこのように記述します。Wayfinderには他にも豊富なオプションがあり、親子関係の表現なども自由にできます。ナビゲーションに関してはなんでもできる万能型のスニペットです。サイトマップも作れます。詳細については<a href="http://www.google.com/cse?cx=007286147079563201032%3Aigbcdgg0jyo&ie=UTF-8&q=wayfinder" target="_blank">ドキュメント</a>を確認してください。<br />
※規模が小さく構成変更も少ないサイトなら、スニペットを利用せず静的にナビゲーションを記述するのもよいでしょう。
</p>
<h3>パン屑リストを設置する</h3>
<p>標準で同梱されているBreadcrumbsスニペットを利用します。[[Breadcrumbs]]と記述するだけで利用できますが、パラメータを追加して細かくカスタマイズすることもできます。詳細については<a href="http://www.google.com/cse?cx=007286147079563201032%3Aigbcdgg0jyo&ie=UTF-8&q=Breadcrumbs" target="_blank">ドキュメント</a>を確認してください。</p>
<h3>新着情報の一覧を設置する</h3>
<p>
標準で同梱されている<a href="http://www.google.com/cse?cx=007286147079563201032%3Aigbcdgg0jyo&ie=UTF-8&q=Ditto" target="_blank">Ditto</a>を利用します。リソースの一覧出力に関してはあらゆることができる、万能型スニペットです。新着情報の一覧だけでなく、ブログの実装も可能です。
</p>
<pre>
&lt;h3&gt;&lt;a href=&quot;[~[+id+]~]&quot;&gt;[+date+] - [+pagetitle+]&lt;/a&gt;&lt;/h3&gt;
&lt;div&gt;[+introtext+]&lt;/div&gt;
</pre>
<p>まず、上記のように記事一件あたりの出力パターンをチャンクで作ります。</p>
<pre>
[[Ditto?tpl=パターン名]]
</pre>
<p>任意のページに上記のように記述すると、サブリソースの一覧を指定パターンで出力します。</p>


<h3>問い合わせフォームを設置する</h3>
<p>
標準で同梱されているeFormスニペットが利用できます。</p>
<pre>
[!eForm?formid=form1&tpl=form&report=mailtpl!]
</pre>
<ul>
<li><b>formid</b> … フォームを識別するためのID。ページ内にフォームをひとつしか設置しない場合でも必ず記述します。</li>
<li><b>tpl</b> … フォームの本体。&lt;form&gt;～&lt;/form&gt;の部分を普通のhtmlで記述します。eFormはinput要素のname属性を読み取って処理します。基本的にはプレイスホルダーなどの専用タグを用いず実装できますが、細かい制御が必要な場合は各種のプレイスホルダーや、inputタグ内にeform属性などを記述します。</li>
<li><b>report</b> … 管理人が受け取るメールのテンプレート。自由にデザインできます。フォーム中のinput要素のname属性値を名前としてプレイスホルダーを記述してください。たとえばname="問い合わせ内容"となっていれば、[+問い合わせ内容+]と記述します。</li>
</ul>
<p>
この3つのパラメータは必須です。また、スニペットの性格上、出力のキャッシュを回避する必要があります。詳細は<a href="http://www.google.com/cse?cx=007286147079563201032%3Aigbcdgg0jyo&ie=UTF-8&q=eform" target="_blank">ドキュメント</a>を確認してください。
</p>
<p>さらに高機能なスニペットとしては<a href="http://www.google.com/cse?cx=007286147079563201032%3Aigbcdgg0jyo&ie=UTF-8&q=cfformmailer" target="_blank">cfFormMailer</a>が知られています。eFormでは実装が難しい確認画面を作ることもできます。
</p>

<h3>投稿画面をカスタマイズする(1)</h3>
<p>
標準で同梱されているManagerManagerプラグインを用いると投稿画面を自由にカスタマイズできます。カスタマイズルールを設定用のチャンク(デフォルトではmm_rules)に記述します。</p>
<pre>
mm_hideFields('longtitle,description,alias,link_attributes,introtext');
mm_hideFields('template,menutitle,menuindex,show_in_menu,hide_menu,parent');
mm_hideFields('published,pub_date,unpub_date');
mm_hideFields('is_folder,is_richtext,log,searchable,cacheable,clear_cache');
mm_hideFields('resource_type,content_type,content_dispo');
</pre>
<p>たとえば上記のように記述すると「リソース名」「内容(本文)」以外のほとんどのフィールドを隠すことができます。フィールド名の変更や他タブへの移動・デフォルト値のセットなど、他にも18種類のコマンドを利用できます。詳細については<a href="http://www.google.com/cse?cx=007286147079563201032%3Aigbcdgg0jyo&ie=UTF-8&q=ManagerManager" target="_blank">ドキュメント</a>を確認してください。</p>

<h3>投稿画面をカスタマイズする(2)</h3>
<p>
プラグインを自作します。
</p>
<pre>
$css = $modx-&gt;getChunk('管理画面用スタイルシート');
$modx-&gt;Event-&gt;output($css);
</pre>
<p>
プラグイン新規作成画面を開いて上記のようなコードを書き、システムイベントは「OnDocFormPrerender」にチェックを入れてプラグインを新規保存します。プラグイン名はなんでもかまいません。次に「管理画面用スタイルシート」という名前のチャンクを作り、スタイルシートを任意に記述します。OnDocFormPrerenderは投稿画面に関連付けられたシステムイベントなので、投稿画面のhtmlソースを参考にスタイルを記述します。
</p>

<h3>配布されているアドオン(スニペット・プラグイン)をインストールする</h3>
<p>
<a href="http://modxcms.com/extras/repository/10" target="_blank">MODx開発元のアドオン配布コーナー</a>ではさまざまなスニペット・プラグイン・モジュールが配布されています。現在のMODxはこれらのアドオンをシンプルで一律な手順でインストールする仕組みを持っていないため、基本的には配布ページに記述されている手順に沿ってインストールします。プラグインやモジュールに関してはインストーラが提供されていることもあります。連携ファイルを持たない小さなアドオンの場合は、管理画面の<a href="index.php?a=76">「エレメント管理」</a>で新規作成フォームを開き、コードをコピー・ペーストするだけで使えるようになります。</p>
<p>プラグインの場合はさらにシステムイベント設定タブでシステムイベントを設定する必要がありますが、MODx 1.0.3J以降はコードを貼り付けてtextarea以外の領域をクリックするだけで自動的にセットすることができるようになっています。
</p>

<h3>ログイン時に管理画面にアクセスさせない</h3>
<p>
<a href="index.php?a=75">ユーザ管理</a>の対象ユーザのアカウント編集ページの「詳細」タブを開き、「管理画面ログイン開始ページ」としてトップページのリソースIDを指定します。こうすると、対象ユーザがログインした時、自動的にサイトのトップページにリダイレクトされます。QuickManagerを利用すれば、管理画面を使わずフロントエンドだけで基本的なコンテンツ管理ができます。
</p>

<h3>Googleマップを貼り付ける(1)</h3>
<p>投稿画面を開き、「使用エディター」で「なし」を選びます。次に「ページ設定」のタブを開き「リッチテキストで編集」のチェックを外します。このチェックを外しておかないと、次に編集画面を開いた時にRTEがタグを削除してしまうことがあります。これでhtmlタグを自由に記述できるようになったので、Googleマップの「埋め込み地図」のタグを貼り付けます。</p>

<h3>Googleマップを貼り付ける(2)</h3>
<p>
Googleマップを貼り付ける方法はもうひとつあります。MODx投稿画面のTinyMCEツールバーの「HTMLソース編集」ボタンをクリックし、開いたダイアログに貼り付けます。</p>

<h3>YouTubeの動画を貼り付ける</h3>
<p>そのまま貼り付けられます。TinyMCEで開いている場合は「埋め込みメディアの挿入／編集」アイコンをクリックし、YouTubeの動画URLを貼り付けます。タイプは「Flash」を選んでください。HTMLソース編集ダイアログでプレイヤー展開コードをそのまま貼り付けることもできます。</p>

<h3>改行はシフト＋エンターキーで</h3>
<p>TinyMCEやCKEditorでリソース編集画面を開いている場合、エンターキーを押すとp要素で段落整形されるため、意図しない空行が挿入されたように見えることがあります。Validな文書を作るには便利な機能ですが、改行のみですませたいこともあります。シフトを押しながらエンターキーを押すと、改行(&lt;br /&gt;)のみが挿入されます。</p>

<h3>文字コードeuc-jpで運用する</h3>
<p>
管理画面に関しては標準でeuc言語ファイルを同梱しており、エンコード設定とセットで設定を変更することでMODxをeuc-jpで運用できます。設定を変更する前に必ずデータベースのバックアップをとってください。</p>
<p>MySQLのバージョンが4.1以上の場合は、グローバル設定の設定変更と共に、manager/includes/config.inc.phpの$database_connection_charsetの値をujisに書き換えてください。4.0系の場合はデータベースの中身をeuc-jpに変換する必要があります。</p>
<p>
スニペットやプラグインなどアドオンに関しては自前で環境を整える必要があります。すでにjapanese-utf8.inc.phpが用意されているケースが多いと思いますので、これをテキストエディタで開いて「japanese-euc.inc.php」というファイル名で、文字コードeuc-jpとして別名保存してください。これがeuc-jp言語ファイルとして、該当アドオンで利用できます。
</p>
<p>
euc-jp運用はポイントさえ押さえていれば難しくありませんが、エンコードの扱いに慣れない場合はリスクをしっかりと意識する必要があります。
</p>

<h3>日常の運用で気をつけるべきこと</h3>
<p>
<a href="index.php?a=114">イベントログ</a>に時々目を通し、想定外のエラーが発生してないか確認しましょう。また、データベースのバックアップはできるだけ定期的にとるようにします。ログは蓄積される一方なので、時々削除しましょう。将来的には上限を設定できるようになる予定です。
</p>

<h3>携帯電話向けコンテンツを作る</h3>

<p>
<a href="http://www.google.com/cse?cx=007286147079563201032%3Aigbcdgg0jyo&ie=UTF-8&q=MobileConverter" target="_blank">MobileConverterプラグイン</a>が便利です。携帯電話向けに、エンコードや画像形式の変換などを行ないます。

<h3>投稿画面のtextareaのスタイルシートをカスタマイズする</h3>
<p>
たとえば暗い背景に明るい文字色のサイトを管理するのに、白い背景の投稿画面だとイメージをつかみにくいです。この場合、投稿画面のテキストエリアのスタイルを自由にカスタマイズできます。<a href="index.php?a=17">グローバル設定</a>の「CSSファイルへのパス」で指定されているスタイルシートで、投稿画面を自由にカスタマイズできます。
</p>
<p>TinyMCEプラグイン設定タブ「Custom Parameters」の値に「 body_class : "content", 」を追記すると(contentは任意)、投稿画面中ではCSSセレクタ「body.content」の上にコンテンツを展開しているように見立てることができます。</p>

<h3>テンプレート変数の便利な使い方</h3>
<p>
テンプレート変数は「カスタムフィールド」のようなもので、投稿画面にオリジナルの入力項目を追加することができます。MODxでは十分に実用的な仕様となっており、入力・出力のそれぞれにおいて様々な制御ができるようになっています。</p>
<p>
まず入力においては、通常のテキスト入力以外に、ドロップダウンメニュー・チェックボックス・ラジオボタン・ファイルアップロード(画像も可)など一般的なフォーム要素と、リッチテキストやカレンダー入力などさらに高度なGUIを持つものを選ぶことができます。</p>
<p>
ドロップダウンメニューやチェックボックスなど、複数の選択肢を持つタイプのテンプレート変数は下記のように設置します。</p>
<pre>
<b>入力時のオプション値</b>
チャーリー||ハドソン||ダニエル||タイチ||ユーリー

<b>既定値</b>
ハドソン
</pre>
<p>このように「|| 」で区切ります。</p>

<h3>ウィジェット</h3>
<p>テンプレート変数の出力においては、ウィジェットを利用すると高度な制御が可能です。特に「HTML Generic Tag」や「Image」は実用的で、これらのウィジェットを適用したテンプレート変数は、値が何も入力されなかった場合は何も出力しません。</p>
<pre>
&lt;img src=&quot; &quot; /&gt;
</pre>
<p>
このような、属性が空のままのimg要素を出力してしまうことがありません。
</p>

<h3>ウィジェット処理を自作する(1)</h3>
<p>
標準実装のウィジェット機能は基本的な処理しかできません。しかしこれがMODxの限界ではありません。スニペットを通じてテンプレート変数にアクセスするとよいでしょう。
</p>
<pre>
&lt;?php
$imagePath = $modx-&gt;documentObject[$tv][1];
$width = ($width) ? '&amp;w=' . $width : '';
$height = ($height) ? '&amp;h=' . $height : '';
if($imagePath &amp;&amp; file_exists(getenv('DOCUMENT_ROOT'). $imagePath))
{
	$site_url = rtrim($modx-&gt;getConfig('site_url'), '/');
	$imagePath = str_replace($site_url, '', $imagePath);
	$str  = '&lt;img src=&quot;/ajaxlib/phpthumb/phpThumb.php?src=';
	$str .= $imagePath . $width . $height . '&amp;q=90&amp;fltr[]=usm|80|0.5|3&amp;fltr[]=wb';
	$str .= '&quot; /&gt;';
}
return $str;
?&gt;
</pre>
<p>
たとえば上記のようなコードを「画像ウィジェット」というスニペット名で保存し、</p>
<pre>
[[画像ウィジェット?tv=テンプレート変数名&width=300&height=225]]
</pre>
<p>
このように呼び出すと、phpThumbライブラリを利用して横幅・縦幅を揃えつつガンマ調整・ホワイトバランス処理まで施す高度な画像処理を行なうウィジェットを実現できます。
</p>

<h3>ウィジェット処理を自作する(2)</h3>
<pre>
&lt;?php
if (empty($modx-&gt;documentObject['longtitle']))
{
    $title = $modx-&gt;documentObject['pagetitle'];
}
else
{
    $title = $modx-&gt;documentObject['longtitle'];
}
return $title;
?&gt;
</pre>
<p>
たとえば上記のようなスニペットを作って「タイトル」などの適当な名前で保存します。
</p>
<pre>
&lt;h1&gt;[[タイトル]]&lt;/h1&gt;
</pre>
<p>
テンプレートなどに上記のように記述すると、投稿画面の「タイトル」に入力がある場合は「タイトル」を、ない場合はリソース名を出力します。
</p>

<h3>Dreamweaverなどで作ったhtmlファイルをそのままテンプレートにする</h3>
<p>
Dreamweaverなどでhtmlを組みテンプレート編集画面に貼り付けるのは、細部の調整が続く場合は面倒に感じます。Dreamweaverの高度なテンプレート管理機能を利用して複数のテンプレートを一括管理している場合も、少しの変更のたびに全てのテンプレートを貼り付け直すのは手間がかかります。この場合、Dreamweaverで作ったhtmlファイルをそのまま読み込んでテンプレートとして解釈する方法を用いると便利です。残念ながらdwtファイルは解釈できませんが(スニペットを作ってパス変換すれば可能)、htmlファイルを読み込む前提で以下に方法を説明します。</p>
<p>
詳細は<a href="http://modxcms.com/forums/index.php/topic,10351.msg88947.html#msg88947" target="_blank">こちらのトピック</a>をご覧ください。テンプレート内にphpコードなどを書いて外部ファイルを直接呼び出すことはできないため、テンプレート変数の機能を経由します(<a href="http://modxcms.com/forums/index.php?topic=15438.0" target="_blank">スニペットを利用する方法</a>もあります)。MODxのテンプレート変数には<a href="http://wiki.modxcms.com/index.php/Bindings" target="_blank">「@Bindings」(アットバインディング)</a>と呼ばれる機能があり、これを利用します。「@Bindings」は、リソース編集画面でテンプレート変数に入力する値(通常は文字列)を、他のソースに差し替えるものです。「他のソース」としてどんなものがあるのかというと、htmlファイルやCSVファイルなどの「外部ファイル」、任意のリソース(旧称ドキュメント)、チャンク、php文のインライン実行結果、データベースからの抽出結果などが利用できるようになっています。つまりテキストを入力する代わりに、これらのソースから値を動的に引っ張ってくることができます。</p>
<p>実験的なスニペットとして<a href="http://www.google.com/cse?cx=007286147079563201032%3Aigbcdgg0jyo&ie=UTF-8&q=dwtinc" target="_blank">dwtinc</a>というものもあります。</p>
<p>
テンプレート製作にDreamweaverを用いる場合は<a href="http://www.google.com/cse?cx=007286147079563201032%3Aigbcdgg0jyo&ie=UTF-8&q=MODx+for+Dreamweaver" target="_blank">MODx for Dreamweaver</a>を利用すると便利です。
</p>

<h3>サイトをバックアップ・リストアしたい</h3>
<p>
<a href="index.php?a=93">バックアップマネージャー</a>を用いてデータベースをバックアップします。この時、データサイズの総計に気をつけてください。数MBものサイズに及ぶ場合はリストアに失敗することがあります。データが肥大する原因としてはログが考えられます。この場合は_event_logと_manager_logをバックアップ対象から外すか、いったんログをクリアするとよいでしょう。</p>
<p>サイト全体のページ数が多い時は、それでもサイズが大きいことがあります。この場合は複数のtableごとに分けてバックアップファイルを取得すると安全です。バックアップファイルはテキストファイルなので、テキストエディタで編集してください。
</p>
<p>バックアップしたデータを用いてサイトをリストアするには、phpMyAdminなどを利用してデータをインポートします。
</p>

<h3>検索エンジン対策を充実させたい</h3>
<p>
MODxは検索エンジンとの相性がよいCMSですが、システム自体はSEOを特に意識した画期的な仕組みを備えているわけではありません。MODxに限らず多くのCMSは、整合性の高いコンテンツ構成を維持しやすいため検索エンジンとの相性に優れています。MODxはCMSとしては冗長な出力を行なわない特性がありますので、コンテンツ的に純度の高いサイト作りをしやすいです。このため検索エンジンとの相性のよさも高まります。欲張らず、本当に伝えたいメッセージ作りに努めることが検索エンジン対策につながります。
</p>
<p>
<b>フレンドリーURL設定で運用する</b><br />
最近の検索エンジンは精度が高いため、<a href="http://www.google.com/search?q=seo+%E5%8B%95%E7%9A%84URL" target="_blank">動的URLであるという理由だけでインデックスに不利になることはありませんが</a>、見た目の分かりやすさは「クリックしやすさ」につながります。クリックされやすいURLがネットに浸透することにより、大局的には検索エンジンとの相性も向上します。
</p>
<p>
<b>canonical属性を設定する</b><br />
ページごとにcanonical属性を設定し、検索エンジンに渡すURLを確定します。
<pre>
&lt;link rel=&quot;canonical&quot; href=&quot;[(site_url)][~[*id*]~]&quot; /&gt;
</pre>
上記のように記述します。トップページにもパスが付加されますが実用的には問題ありません。
</p>
<p>
<b>title要素の工夫</b><br />
<pre>
&lt;title&gt;[*pagetitle*]|[(site_url)]&lt;/title&gt;
</pre>
上記のように、ページ名・サイト名の順に出力するとよいでしょう。欲張らずシンプルにまとめると効果があります。
</p>
<p>
<b>description属性を積極的に使う</b><br />
<pre>
&lt;meta name=&quot;description&quot; content=&quot;[*description*]&quot; /&gt;
</pre>
上記のように記述します。分かりやすく好感を持てる説明文を書くことで「クリックされやすさ」を高めることができます。
</p>
<p>
<b>リンク切れをなくす</b><br />
モジュール<a href="http://www.google.com/cse?cx=007286147079563201032%3Aigbcdgg0jyo&ie=UTF-8&q=Error+404+Logger" target="_blank">「Error 404 Logger」</a>を用いるとリンク切れを効率よく管理できます。
</p>
<p>
<b>サイトマッププロトコルへの対応</b><br />
検索エンジンに渡すためのサイトマップを作成し、<a href="http://www.google.com/support/webmasters/bin/answer.py?answer=183669" target="_blank">検索エンジンに送信</a>します。<a href="http://modxcms.com/extras/package/410" target="_blank">sitemapスニペット</a>を利用すると手軽に作成できます。
</p>

<h3>ダッシュボードとヘルプのカスタマイズ</h3>
<p>
/assets/templates/manager/welcome.htmlを独自にカスタマイズできます。これは管理画面にログインした時に最初に表示される<a href="index.php?a=2">ダッシュボード</a>にあたるファイルです。このファイルはコア領域に属するものではないので、サイト運用の目的に応じて自由にカスタマイズできます。また、現在ご覧いただいているこの「ヘルプ」もカスタマイズ自由なエレメント領域に設置されています。つまり、個別の案件に応じたオンラインヘルプの同梱も簡単に実現できます。御社の電話番号・担当者名・サポート期間・その他保守条件などを記述しておくとよいでしょう。</p>

<h3>MODxの技術情報</h3>
<p>
<a href="http://wiki.modxcms.com/index.php/Ja:main" target="_blank">Ja:main - MODx Wiki</a><br />
MODx開発元のドキュメントサイトに日本語のコーナーを設けています。
</p>

</div>
