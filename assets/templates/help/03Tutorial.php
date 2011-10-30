<?php
if(IN_MANAGER_MODE!="true") die("<b>INCLUDE_ORDERING_ERROR</b><br /><br />Please use the MODx Content Manager instead of accessing this file directly.");
?>
<style type="text/css">
h3 {font-weight:bold;letter-spacing:2px;font-size:1;margin-top:10px;}
pre {border:1px dashed #ccc;background-color:#fcfcfc;padding:15px;}
ul {margin-bottom:15px;}
</style>

<div class="sectionHeader">チュートリアル</div>
<div class="sectionBody" style="padding:10px 20px;">
<h3>新規リソースを作る</h3>

<p>管理画面メニューバーの「メイン」から<a href="index.php?a=4">「リソースの作成」</a>をクリックします。リソース作成画面が表示されるので、リソース名「テスト」・使用テンプレート「(blank)」・内容「こんにちは」として「保存」ボタンをクリックしてください。</p>
<p>管理画面左側のサイトツリーペインにリソース「テスト」が追加されるので、これを右クリック、「プレビュー(別窓)」を実行すると新規作成したリソースが表示されます。htmlソースの内容も確認してみてください。</p>

<h3>テンプレートを作る</h3>
<p>最もシンプルなテンプレートの作例を以下に示します。</p>
<pre>
&lt;html&gt;
&lt;head&gt;
    &lt;title&gt;[*pagetitle*]&lt;/title&gt;
&lt;/head&gt;
&lt;body&gt;
    &lt;h1&gt;[*pagetitle*]&lt;/h1&gt;
    [*content*]
&lt;/body&gt;
&lt;/html&gt;
</pre>
<p>
[*pagetitle*]や[*content*]はリソース変数と呼ばれるもので、リソース編集画面の個々の項目と関連付いています。たとえば[*pagetitle*]は「リソース名」ですし[*content*]は「本文」です。入門レベルとしては、まずはこの2つを使いこなせるようになるとよいでしょう。実際、この2つのリソース変数の扱いに慣れるだけで、通常のサイトなら十分実用的に構築できます。
</p>

<h3>オリジナルの入力フィールドを追加する</h3>
<p>投稿画面に入力フィールドを追加したい場合は、「テンプレート変数」を作成します。</p>
<p>テンプレート変数の新規作成画面を開き、任意のテンプレート変数名を入力し、「テンプレートとの関連付け」で任意のテンプレートと関連付けてください。</p>
<pre>
&lt;html&gt;
&lt;head&gt;
    &lt;title&gt;[*pagetitle*]&lt;/title&gt;
&lt;/head&gt;
&lt;body&gt;
    &lt;h1&gt;[*pagetitle*]&lt;/h1&gt;
    [*今日の天気*]
    [*今日の気温*]
    [*content*]
&lt;/body&gt;
&lt;/html&gt;
</pre>
<p>
たとえば「今日の天気」「今日の気温」という入力フィールドを追加したい場合は、作成したテンプレート変数を上記のようにテンプレートに記述します。
</p>
<p>「晴れ・曇り・雨」という形であらかじめ値を用意しておき、これをラジオボタンやセレクトボックスなどで選択させる形のフィールドを作ることもできます。また、画像やファイルを選択することもできます。詳しくはTipsを参照してください。
</p>

<h3>チャンクを利用してテンプレート構成を整理</h3>
<p>
テンプレートを複数のチャンクでパーツ分解すると構成が整理されて分かりやすくなります。微妙にデザインが異なるテンプレートを複数作る場合なども、チャンク単位でコードを共有できるため便利です。
</p>
<pre>
&lt;!DOCTYPE html PUBLIC &quot;-//W3C//DTD XHTML 1.0 Transitional//EN&quot;
&quot;http://www.w3.org/TR/xhtml1/DTD/xhtml1-transitional.dtd&quot;&gt;
&lt;html xmlns=&quot;http://www.w3.org/1999/xhtml&quot;&gt;
&lt;head&gt;
{{head要素}}
&lt;/head&gt;
&lt;body&gt;
&lt;div class=&quot;wrap&quot;&gt;
    &lt;div id=&quot;header&quot;&gt;{{ヘッダー}}&lt;/div&gt;
    &lt;div id=&quot;gNavi&quot; &gt;{{ナビゲーション}}&lt;/div&gt;
    &lt;div id=&quot;main&quot;  &gt;{{メイン}}&lt;/div&gt;
    &lt;div id=&quot;footer&quot;&gt;{{フッタ}}&lt;/div&gt;
&lt;/div&gt;
&lt;/body&gt;
&lt;/html&gt;
</pre>


<h3>スニペットを作ってみよう</h3>
<p>最もシンプルなスニペットの作例を以下に示します。</p>
<pre>
&lt;?php
    echo &quot;ハロー・ワールド。&quot;;
    echo date(ただいまH時i分s秒です。);
?&gt;
</pre>
<p>スニペット名「ハローワールド」として保存し、リソースまたはテンプレートで<b>[[ハローワールド]]</b>と記述して呼び出します。この時、MODxのデフォルトのリソース設定では出力がキャッシュされるため、2回目以降のアクセスでは、このスニペットで表示されるはずのリアルタイムな時刻が得られません。このスニペットのみ出力をキャッシュしたくない場合は<b>[!ハローワールド!]</b>と記述してください。MODxではこのようにブロック単位でキャッシュする・しないをコントロールすることができます。</p>
<p>
スニペットはプラグインと違い、html上の任意の場所に動的出力を実装します。プラグインの実装対象を限定し扱いやすくしたものと考えることもできます。ブログパーツ感覚の手軽さが特長で、プログラミング初心者がphpに親しむにはちょうどいいでしょう。便利なスニペットがすでに多数配布されていますが、ぜひ自作に挑戦し、MODxを通じてプログラミングの楽しさを体感してみてください。</p>
<p>
デザインワークとの親和性の高さもスニペットの特長のひとつで、CMSとしてのMODxを象徴する「Ditto」「Wayfinder」などの代表的なアドオンはスニペットとして構成されています。ぜひお試しください。<br />
※上記の例では初心者が理解しやすいecho文を使っていますが、できればreturn文で最後にまとめて値を返すのがよいでしょう。</p>

<h3>プラグインを作ってみよう</h3>
<p>最もシンプルなプラグインの作例を以下に示します。</p>
<pre>
$output = &amp; $modx-&gt;documentOutput;
$now = date(ただいまH時i分s秒です。);
$output = str_replace('現在時刻を表示' , $now, $output);
</pre>
<p>システムイベント「OnParseDocument」にチェックを入れ、プラグイン名「現在時刻」として保存します。リソースまたはテンプレート中に「<strong>現在時刻を表示</strong>」という文字列を記述すると、これを現在時刻に変換して出力します。呼び出し場所の記述が必要なスニペットと違い、プラグインはMODxの機能(イベント)に関連付けてプログラムを実行します。一般的なCMSのプラグイン機能のイメージに近いものと言えます。</p>
<p>MODxの機能を拡張する上で、スニペット以上に自由度が高く対象を幅広く持つ実装が可能です。もともとMODxにはプラグイン機能はなく、コアファイルを改造する形で機能を拡張していましたが、より安全で手間のかからない拡張を実現するためにプラグイン機能が実装されました。スニペットと違い管理画面の拡張も可能で、TinyMCEやManagerManagerなどはプラグインとして構成されています。<br />
※スニペットのように値を返す場合は$modx-&gt;Event-&gt;output(返す値)メソッドを用います。</p>

<h3>基本的なAPI</h3>
<ul>
<li><b>$modx-&gt;documentObject[フィールド名]</b> - カレントリソース上の任意のフィールドの値を参照します。テンプレート変数の値にアクセスするには[フィールド名][1]とします。</li>
<li><b>$modx-&gt;config[設定名]</b> - グローバル設定上の任意の設定名を参照します。サイト名(site_name)などを参照できます。</li>
<li><b>$modx-&gt;documentIdentifier</b> - カレントリソースのidを参照します。</li>
<li><b>$modx-&gt;getDocumentObject(メソッド, ID)</b> - 任意のリソース上のフィールドの値を参照します。テンプレート変数の場合は要素[1]を参照します。$method はid・aliasいずれかを指定しますが、通常はidを指定するとよいでしょう。</li>
<li><b>$modx-&gt;getChunk(チャンク名)</b> - 任意のチャンクの内容を参照します。</li>
<li><b>$modx-&gt;setPlaceholder(プレイスホルダー名, 値)</b> - プレイスホルダーを作ります。</li>
<li><b>$modx-&gt;runSnippet(スニペット名,パラメータ)</b> - 任意のスニペットを実行し、その結果を取得します。パラメータは連想配列形式で指定する必要があります。</li>
<li><b>$modx-&gt;getLoginUserID(mgrまたはweb)</b> - ログインしているかどうかを調べることができます。</li>
<li><b>$modx-&gt;logEvent(イベントID,タイプ,メッセージ)</b> - イベントログを追加します。</li>
<li><b>$modx-&gt;getUserData()</b> - アクセス解析などに用いると便利です。</li>
<li><b>$modx-&gt;makeUrl(リソースID,エイリアス,付加するクエリ,形式)</b> - 与えられたリソースIDからURLを生成します。</li>
<li><b>$modx-&gt;clearCache()</b> - キャッシュをクリアします。</li>
<li><b>$modx-&gt;regClientCSS(文字列)</b> - head要素内にCSSを出力します。引数とする文字列が&lt;link・&lt;styleどちらで始まるかによって挙動が異なります。</li>
<li><b>$modx-&gt;regClientStartupScript(文字列)</b> - head要素内に任意のJavaScriptを挿入します。引数とする文字列が&lt;scriptから始まるかどうかで挙動が異なります。</li>
<li><b>$modx-&gt;regClientScript(文字列)</b> - &lt;/body&gt;タグの直前に任意のJavaScriptを挿入します。引数とする文字列が&lt;scriptから始まるかどうかで挙動が異なります。</li>
</ul>
<p>
その他、SQL文を簡潔に記述するDBAPIが使用できます。
</p>

<h3>モジュールを作ってみよう(上級者向け)</h3>
<p>
モジュールを作ること自体は簡単にできます。
</p>
<pre>
echo 'これは自作モジュールです';
</pre>
<p>
上記のように書くだけで実行できます。
</p>
<pre>
global $modx_lang_attribute,$modx_textdir, $manager_theme, $modx_manager_charset;
global $_lang, $_style, $e,$SystemAlertMsgQueque,$incPath,$content;

include($incPath . 'header.inc.php');
?&gt;
&lt;h1&gt;自作モジュール&lt;/h1&gt;
&lt;script type=&quot;text/javascript&quot; src=&quot;media/script/tabpane.js&quot;&gt;&lt;/script&gt;
&lt;div class=&quot;sectionHeader&quot;&gt;チュートリアル&lt;/div&gt;
&lt;div class=&quot;sectionBody&quot; style=&quot;padding:10px 20px;&quot;&gt;
これは自作モジュールです
&lt;/div&gt;
&lt;/div&gt;
&lt;?php
include($incPath .'footer.inc.php');
</pre>
<p>
MODxの管理画面スタイルに合わせたい場合は上記のように記述します。
</p>

<h3>サイトツリーからリソースIDを取得する(モジュール作成)</h3>
<p>
サイトツリーのリソースをクリックした時、JavaScriptでsetMoveValueメソッドが実行されます。このsetMoveValueメソッドの処理内容をモジュール側で実装します。リソースID・リソース名を値として受け取ることができます。同梱モジュールDocManagerが参考になります。
</p>

<h3>MODxのAPIを外部PHPアプリから利用する(上級者向け)</h3>
<pre>
define('MODX_API_MODE', true);
include('/real_path/manager/includes/config.inc.php');
include(MODX_MANAGER_PATH . 'includes/document.parser.class.inc.php');
startCMSSession();
$modx = new DocumentParser;
$modx-&gt;db-&gt;connect();
$modx-&gt;getSettings();
</pre>
<p>
上記のように記述することで、$modx オブジェクトに自由にアクセスできるようになります。任意のチャンクやリソースの参照・スニペットの実行など、MODxの拡張機能と同等の機能を外部PHPアプリに持たせることができます。<br />
※<a href="http://www.google.com/cse?cx=007286147079563201032%3Aigbcdgg0jyo&ie=UTF-8&q=api+library" target="_blank">MODxAPI Library</a>を用いると、さらに手軽・具体的に実装できます。全APIにアクセスできるため、独自の管理画面・投稿画面を作ることも可能です。
</p>

<h3>Ajax処理を組み込む(スニペット作成)</h3>
<p>
MODxインストールディレクトリに配置されているindex-ajax.phpを、任意のスニペットなどに持たせたAjaxハンドラのエントリーポイントとして用いることができます。<a href="http://www.google.com/search?q=xmlhttp&lr=lang_ja" target="_blank">xmlHttpオブジェクト</a>を用いて簡易に実装することもできますが、具体的な活用例として公式ドキュメント<a href="http://wiki.modxcms.com/index.php/Use_AJAX_with_modxAPI" target="_blank">「Use AJAX with modxAPI」</a>(英語)が参考になります。同梱スニペットではajaxSearchがこのメソッドを利用しています。</p>
<pre>
var url    = MODX_BASE_URL . 'index-ajax.php';
var params = 'q=entry.php';
xmlHttp.open('POST',url,true);
xmlHttp.setRequestHeader('Content-type', 'application/x-www-form-urlencoded');
xmlHttp.setRequestHeader('Content-length', params.length);
xmlHttp.setRequestHeader('Connection', 'close');
xmlHttp.send(params);
</pre>
<p>
たとえば接続部分に関しては上記のように記述します。任意のボタンをクリックした時などに、この処理を実行させます。entry.phpの内部では実際の処理内容を記述しますが、スニペットを埋め込んだリソースを用いると、よりスマートに実装でき、メンテナンス性にも優れます。</p>
<p>
Ajax実装は技巧的には難しくありませんが、PHP・JavaScript・MySQL各種の技術を組み合わせるため複雑になりがちです。MODxが持つ各種メソッドを活用すると、シンプルにまとめることができます。
</p>

<h3>スニペット・プラグイン製作の参考情報</h3>
<ul>
<li><a href="http://wiki.modxcms.com/index.php/Ja:MODx%E3%81%AE%E3%82%B9%E3%83%8B%E3%83%9A%E3%83%83%E3%83%88%E9%96%8B%E7%99%BA%E3%81%AE%E3%82%AC%E3%82%A4%E3%83%89%E3%83%A9%E3%82%A4%E3%83%B3" target="_blank">MODxのスニペット開発のガイドライン</a> (プラグイン・モジュールも共通)</li>
<li><a href="http://wiki.modxcms.com/index.php/Ja:API:DocumentParser" target="_blank">DocumentParser(API)</a></li>
<li><a href="http://wiki.modxcms.com/index.php/Ja:API:DBAPI" target="_blank">DBAPI(API)</a></li>
<li><a href="http://wiki.modxcms.com/index.php/Ja:System_Events" target="_blank">システムイベント(主にプラグイン)</a></li>
</ul>

<h3>サイト構築の流れ</h3>
<p>
MODxのイメージをおおまかに把握できたら、実際にサイトを作ってみましょう。まず、リソースを3つ作ってください。
</p>
<ul>
<li>トップページ</li>
<li>エラーページ(404 not foundページ)</li>
<li>ただいま製作中ページ</li>
</ul>
<p>
いずれもほとんど白紙に近いダミー的な内容でかまいません。トップ・404・製作中であることが分かる程度で十分です。</p>
<p>次に<a href="index.php?a=76">エレメント管理</a>を開いてテンプレートを作ります。この時点では内容は適当でかまいません。この次のステップで利用テンプレートを指定するため、テンプレート名のみ必要です。テンプレート名はいつでも自由に変えられるので、標準でセットされている「Minimal Template」を流用してもかまいません。</p>
<p>
次に<a href="index.php?a=17">グローバル設定</a>を開いて、サイトの設定を入力します。
</p>
<ul>
<li>サイト名</li>
<li>公開ステータス → メンテナンスモードに設定。ログイン時のみサイトにアクセスできます。完成時にオンラインに戻します。</li>
<li>サイトスタート → トップページのリソースID。IDはサイトツリーに表示されています。</li>
<li>エラーページ → エラーページ(404 not foundページ)のリソースID</li>
<li>サイト閉鎖中ページ → ただいま製作中ページのリソースID</li>
<li>デフォルトテンプレート → 先ほど作ったテンプレートを指定します。</li>
</ul>
<p>
とりあえずこれだけ指定しておけば、サイトとしての最低限の構造を持つことができます。これをベースとしてサイトを構築していきます。
</p>
</div>
