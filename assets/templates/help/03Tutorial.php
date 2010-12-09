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

<h3>MODxのディレクトリ構造</h3>
<h4><b>assets</b>ディレクトリ</h4>
<ul>
<li><b>images / files / flash / media</b> - コンテンツ(記事)で用いるデータが蓄積され、グローバル設定の「アセットディレクトリのパス(物理・相対)」の設定によりファイルブラウザから参照されます。</li>
<li><b>templates</b> - テンプレートで用いるファイルをここで管理します。管理画面のダッシュボード・ログイン画面のデザイン、ヘルプの内容(今ご覧になっている内容)もここで管理されます。サイト構成に関してはシステム的な関連はありませんので、パス記述が長くなることを避けたい場合はあえてこのディレクトリを使う必要はありません。</li>
<li><b>cache</b> - サイトキャッシュ・ページキャッシュを処理します。このディレクトリを移動または削除するとシステムが正常に動かなくなるためご注意ください(※グローバル設定のアセットディレクトリの設定とは連動していません)。</li>
<li><b>import / export</b> - 静的htmlファイルで構成されたサイトをシステムにインポート・エクスポートするために用います。</li>
<li><b>snippets / plugins / modules</b> - 拡張機能が用いるファイルを管理します。システム的な関連はありませんが、慣習的にこれらのディレクトリを利用することになっています。</li>
<li><b>js</b> - サイトで用いるJavaScriptを管理します。システム的な関連はなく、慣習的な使い方も今のところ定まっていません。</li>
<li><b>docs / site</b> - 歴史的な経緯により前身のEtomite時代から引き継がれていますが、現在特に使われていません。</li>
</ul>
<h4><b>manager</b>ディレクトリ</h4>
<ul>
<li><b>includes</b> - システム全体でシェアするファイルが格納されています。</li>
<li><b>actions</b> - 管理画面を構成する各ページが格納されています。</li>
<li><b>processors</b> - actionsディレクトリと連動し、データの入出力処理を行ないます。</li>
<li><b>frames</b> - MODxの管理画面は3つのペインによるフレーム構成になっており、それぞれのペインの構成データがここにあります。ツリーからのデータの受け渡しの実装の参考にするとよいでしょう。</li>
<li><b>media</b> - actionsディレクトリの働きを補います。データの入出力操作に必要なライブラリや、管理画面のテーマファイルが置かれます。</li>
</ul>
</div>
