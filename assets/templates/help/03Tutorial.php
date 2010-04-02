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
<h3>チャンクを利用してテンプレート構成を整理</h3>
<p>
テンプレートを複数のチャンクでパーツ分解すると分かりやすくなります。微妙にデザインが異なるテンプレートを複数作る場合なども、チャンク単位でコードを共有できるため便利です。
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
デザインワークとの親和性の高さもスニペットの特長のひとつで、CMSとしてのMODxを象徴する「Ditto」「Wayfinder」などの代表的なアドオンはスニペットとして構成されています。ぜひお試しください。</p>

<h3>プラグインを作ってみよう</h3>
<p>最もシンプルなプラグインの作例を以下に示します。</p>
<pre>
$output = &amp; $modx-&gt;documentOutput;
$now = date(ただいまH時i分s秒です。);
$output = str_replace('現在時刻を表示' , $now, $output);
</pre>
<p>システムイベント「OnParseDocument」にチェックを入れ、プラグイン名「現在時刻」として保存します。リソースまたはテンプレート中に「<strong>現在時刻を表示</strong>」という文字列を記述すると、これを現在時刻に変換して出力します。呼び出し場所の記述が必要なスニペットと違い、プラグインはMODxの機能(イベント)に関連付けてプログラムを実行します。一般的なCMSのプラグイン機能のイメージに近いものと言えます。</p>
<p>MODxの機能を拡張する上で、スニペット以上に自由度が高く対象を幅広く持つ実装が可能です。もともとMODxにはプラグイン機能はなく、コアファイルを改造する形で機能を拡張していましたが、より安全で手間のかからない拡張を実現するためにプラグイン機能が実装されました。スニペットと違い管理画面の拡張も可能で、TinyMCEやManagerManagerなどはプラグインとして構成されています。</p>

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
