<?php

/*
 *  Written by: Adam Crownoble
 *  Contact: adam@obledesign.com
 *  Created: 11/18/2005
 *  For: MODx cms (modxcms.com)
 *  Description: Class for the QuickEditor
 *
 *  Modified: 2008/10/13
 *  For: MODx cms (modxcms.com) 0.9.5 -
 *  Encoding: Japanese UTF-8
 */

/*
                             License
//-- JAPANESE LANGUAGE FILE ENCODED IN UTF-8

QuickEdit - A MODx module which allows the editing of content via
            the frontent of the site
Copyright (C) 2005  Adam Crownoble

This program is free software; you can redistribute it and/or modify
it under the terms of the GNU General Public License as published by
the Free Software Foundation; either version 2 of the License, or
(at your option) any later version.

This program is distributed in the hope that it will be useful,
but WITHOUT ANY WARRANTY; without even the implied warranty of
MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
GNU General Public License for more details.

You should have received a copy of the GNU General Public License
along with this program; if not, write to the Free Software
Foundation, Inc., 51 Franklin St, Fifth Floor, Boston, MA  02110-1301  USA

*/
$makesure ='';
if (! is_integer($id)) {
	$makesure = "<p><code>[QuickEditModuleId]</code> をQuickEditのモジュールIDに必ず置き換えてください。</p>";
};

include_once(dirname(__FILE__).'/english.inc.php'); // fall back to English defaults if needed
/* Set locale to Japanese */
setlocale (LC_ALL, "ja_JP.UTF-8");

$QE_doc_lang['QE_doc_content'] = <<< EOT
<!DOCTYPE html PUBLIC "-//W3C//DTD XHTML 1.0 Strict//EN" "http://www.w3.org/TR/xhtml1/DTD/xhtml1-strict.dtd">
<html xmlns="http://www.w3.org/1999/xhtml" lang="{$modx->config['manager_lang_attribute']}" xml:lang="{$modx->config['manager_lang_attribute']}">

<head>
<meta http-equiv="Content-Type" content="text/html; charset={$modx->config['modx_charset']}" />
<meta name="description" content="Documentation for the QuickEdit module" />

<title>QuickEditドキュメント</title>
	<link rel="stylesheet" type="text/css" href="media/style/MODxLight/style.css" />

<style type="text/css">

*{font-size:small;border-width:1px;border-color:#ccc;color:#333;}
h1, h2, h3{margin-bottom:0;color:#4d6788;}
h1{
margin-top:0;
margin-bottom:20px;
font-size:22px;
font-weight:bold;
letter-spacing:2px;
text-align:center;
border-bottom-style:solid;
border-color:#e78900;
}

h2{font-size:14px;font-weight:bold;border-left:7px solid;padding-left:8px;line-height:1;margin-bottom:10px;}
h3{font-size:13px;font-weight:bold;}
hr{color:#fff;border-style:none none solid none;border-color:#e78900;}
p{margin-top:0;margin-bottom:12px;}
ul{margin:0 0 0 10px;padding:0;}

code{
display:block;
font-family:monospace;
color:#444;
background-color:#f7fafe;
border:1px dotted;
margin: 5px 0;
margin-right:255px;
padding:4px;
}
code strong {font-weight:normal;font-family:monospace;color:#cd4747;}
strong {color:#cd4747;}

a{color:#e78900;text-decoration:none;}
#qe_logo{float:right;margin:0 57px 20px 57px;}
#qe_toc{
width:200px;
margin:0 0 20px 20px;
padding:20px;
float:right;
clear:right;
border-style:solid;
background-color:#edf5f9;
}

#qe_toc h1{
margin-top:0;
font-size:14px;
text-align:center;
font-weight:bold;
}
div.qe_box{margin:30px;padding:30px;border-style:solid;background-color:#fff;}
div.qe_level_2{margin-left:20px;}
.qe_salutation{margin-left:30px;}
.qe_signature{font-size:18px;}
</style>
</head>

<body>
<div class="qe_box">

<h1>QuickEdit ドキュメント</h1>
<img id="qe_logo" src="../assets/modules/quick_edit/images/logo.png" alt="QuickEdit" />

<div id="qe_toc">

<h1>コンテンツ一覧</h1>

<ul>
 <li><a href="#who">制作・サポート</a></li>
 <li><a href="#what">QuickEditで出来ること</a></li>
 <li><a href="#why">管理画面を使わないわけ</a></li>
 <li><a href="#how">QuickEditの使い方</a>
  <ul>
   <li><a href="#how-tag">MODxタグ形式</a></li>
   <li><a href="#how-html">HTMLタグ形式</a></li>
   <li><a href="#how-links">カスタムリンク</a></li>
  </ul>
 </li>
 <li><a href="#faq">FAQ</a>
  <ul>
   <li><a href="#custom_styles">スタイルは変更できますか？</a></li>
   <li><a href="#no_add">ページの公開／削除／移動ができないのはなぜですか？</a></li>
   <li><a href="#highlight">ハイライト機能が必要以上に効いてしまいます。</a></li>
   <li><a href="#link_cache">QuickEditがキャッシュされたりしませんか？</a></li>
   <li><a href="#not_visible">非表示コンテンツの編集方法を教えて下さい。</a></li>
   <li><a href="#link_cache">特定なユーザにQuickEditを見せないようにしたいのですが・・・</a></li>
   <li><a href="#cant_see">QuickEditが表示されません。何が悪いのでしょう？</a></li>
  </ul>
 </li>
</ul>

</div>
<div style="">
<div class="qe_level_1">
<a name="who"></a>
<h2>制作・サポート</h2>
<p>QuickEditは、<a href="mailto:adam@obledisgn.com">Adam Crownoble</a>が制作しました。QuickEditはMODxのコアモジュールとして公式にサポートされています。このモジュールに関するお問い合わせは、<a href="mailto:adam@obledesign.com">Adam</a> に直接メールを送るか、<a href="http://www.modxcms.com/forums/" target="_blank">MODxフォーラム</a>への投稿を通じてどうぞ。</p>
</div>

<div class="qe_level_1">
<a name="what"></a>
<h2>QuickEditでできること</h2>
<p>QuickEditは、QuickEditバーとQuickEditor(クイックエディタ)の2つにより構成されます。管理画面を開くことなく、今見ているページを手軽に編集できます(※あらかじめログインしている必要があります)。操作は簡単。QuickEditバーをクリックするだけ。</p>
</div>

<div class="qe_level_1">
<a name="why"></a>
<h2>管理画面を使わない理由</h2>
<p>今見ているページの誤字を修正するためだけに管理画面を開くのはおおげさです。あるいは、間違って他の似ているページをうっかり書き換えてしまうかもしれません。ページ上に表示されているQuickEditバーをクリックし、今すぐにページを修正しましょう。</p>
</div>

<div class="qe_level_1">
<a name="how"></a>
<h2>QuickEditバーの実装方法</h2>
<p>QuickEditの実装方法は３種類。手軽な方法・柔軟な方法それぞれありますので、お好みに応じて選んでください。１つのページに３つを混在させることもできます。</p>
<p>テンプレート内に以下のように記述します。</p>
</div>

<div class="qe_level_2">
<a name="how-tag"></a>
<h3>MODxタグ形式</h3>
<p>最もシンプルな使い方。<code>[*xxxxx*]</code> を <code>[*<strong>#</strong>xxxxx*]</code> のように置き換えます。<code>[*#content*]</code> たったこれだけ。テンプレートを保存し、該当ページを確認してみてください。そこにはコンパクトなQuickEditバーが表示されているはずです。</p>
</div>

<div class="qe_level_2">
<a name="how-html"></a>
<h3>独自のHTMLタグ形式</h3>
<p>この方法を用いると、任意の領域にQuickEditバーを表示できます。もちろんユーザーの権限に従って表示／非表示が切り替わります。</p>
<p>任意の領域に &lt;quickedit&gt; というカスタムタグを記述します。<code>&lt;quickedit:content /&gt;</code> という形式で指定します。「content」には、編集したいフィールドを指定します。</p>
</div>

<div class="qe_level_2">
<a name="how-links"></a>
<h3>カスタムリンク</h3>
<p>上級者向けの使い方です。この方法は敢えてお勧めしません。しかし、ドキュメントIDなどを指定でき柔軟に扱えるため、アイデア次第で最適な実装方法になる場合があります。カスタムリンクは、テンプレート内の任意の場所に一般的なリンクを追加します。下に２つの例を示します。この方法では、QuickEditor(※クイックエディタ)へのリンクが他の一般的なリンクと同様に<strong>すべての閲覧者に表示される</strong>ためご注意ください。</p>

<p>
Javascript (推奨): <code>window.open('index.php?a=112&amp;id={$id}&amp;doc=[*id*]&amp;var=content', 'QuickEditor', 'width=525, height=300, toolbar=0, menubar=0, status=0, alwaysRaised=1, dependent=1');</code><br />
Link Tag: <code>&lt;a href="index.php?a=112&amp;id={$id}&amp;doc=[*id*]&amp;var=content" target="_blank"&gt;Edit&lt;/a&gt;</code>
</p>

{$makesure}

</div>

<div class="qe_level_1">
<a name="faq"></a>
<h2>よくある質問と答え</h2>
</div>

<div class="qe_level_2">
<a name="custom_styles"></a>
<h3>スタイルは変更できますか？</h3>
<p>勿論可能です。QuickEditは、quick_editモジュールフォルダ内のoutput.cssファイルの内容に従って表示されます。output.cssファイルの内容を変更することで任意のデザインに変更することができます。他の場所にスタイル情報を保存したい場合は、ファイルの内容を削除することで任意のスタイルシートを適用することもできます。QuickEditは、<code>QuickEditLink</code> と <code>QuickEditParent</code>の２つのクラスで装飾されます。QuickEditLinkでは、実際の編集用リンクのスタイルを定義します。一方、QuickEditParentでは、リンクの操作で表示される編集用領域に適用するスタイルを定義します。</p>
</div>

<div class="qe_level_2">
<a name="no_add"></a>
<h3>ページの公開／削除／移動ができないのはなぜですか？</h3>
<p>QuickEditは、現時点では更新に関する基本的な機能しかサポートしていません。将来的には他の機能と共にこれらの機能も統合する予定です。</p>
</div>

<div class="qe_level_2">
<a name="highlight"></a>
<h3>ハイライト機能が必要以上に効いてしまいます。</h3>
<p>テンプレート変数をspanタグかdivタグで囲ってみてください。 <code>&lt;div&gt;[*#longtitle*]&lt;/div&gt;</code></p>
</div>

<div class="qe_level_2">
<a name="not_visible"></a>
<h3>非表示コンテンツの編集方法を教えて下さい。</h3>
<p><a href="#how-html">HTMLタグ</a> または <a href="#how-links">カスタムリンク</a> を使って、それらのページへのリンクを作成してください。</p>
</div>

<div class="qe_level_2">
<a name="hide_links"></a>
<h3>特定ユーザにQuickEditを見せないようにしたいのですが・・・</h3>
<p>標準的なテンプレート変数に権限を設定するだけでダイナミックに動作します。</p>
</div>

<div class="qe_level_2">
<a name="link_cache"></a>
<h3>QuickEditがキャッシュされたりしませんか？</h3>
<p>ご心配には及びません。サイトにログインしていない訪問者には、QuickEditなしのページが間違いなく表示されます。</p>
</div>

<div class="qe_level_2">
<a name="cant_see"></a>
<h3>QuickEditが表示されません。何が悪いのでしょう？</h3>
<p>まずは<a href="#how-tag">MODxタグ形式</a> による方法を試してください。これは最も簡単な方法で最初のアプローチに向いてます。</p>
<p>MODxタグ形式による記述で動作しない場合は、管理画面から<strong>リフレッシュサイト（キャッシュクリア）</strong>を行ってキャッシュをクリアしてください。QuickEditプラグインをインストールしたり有効化する前にキャッシュされたページがあると問題になることがあります。</p>
<p>QuickEditがまだ一度も表示されてない場合は、管理ユーザーでログインすれば見えるようになるはずです。さもなくば、あなたのユーザの権限設定に問題があるのかもしれません。QuickEditに関係する権限設定はチェックすべき項目が多いので、次の手順で正しく設定されていることを確認してください。まず、ドキュメントの編集、保存、そして、モジュールの実行権限がチェックされていることを確認してください。次に、QuickEditで編集したいページにアクセスする権限があるか（ドキュメントグループの設定）を確認してください。次に、QuickEditモジュールへのアクセス権限とページのテンプレート変数へのアクセス権限を確認してください。</p>
<p>それでもまだ表示されない場合は、<a href="http://www.modxcms.com/forums" target="_blank">MODxフォーラム</a>を訪れて既出の問題がないか検索してください。該当するトピックがない場合は、<a href="http://modxcms.com/forums/index.php/board,10.0.html" target="_blank">General Support</a> に新しいトピックを立ててください。</p>
</div>
</div>

<hr />

<p>QuickEditに興味を持ってくれてありがとう。あなたのコメントや質問、機能的なリクエストを真摯に受け止めて、この先も開発を続けていきます。</p>

<p class="qe_salutation"><span class="qe_signature">Adam Crownoble</span><br />
<strong>MODx Code Team Member</strong></p>

</div>

</body>
</html>
EOT;
