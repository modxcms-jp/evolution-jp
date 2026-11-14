<?php
/**
 * スタイルガイドの見本の本体（a=202）。a=201 の iframe の中に出す。header を通さず、完全な HTML を返す。
 * theme で指定したテーマの CSS で描く（この要求の中だけ設定を差し替える。DB の設定は変わらない）。
 * proposal=1 のときは、目盛りの案（common/foundation-proposal.css）をテーマの後に重ねる。
 * 部品のマークアップは、今の画面と同じ書き方にする（今のガタつきがそのまま見えるように）。
 */
if (!isset($modx) || !evo()->isLoggedin()) {
    exit;
}
if (!evo()->hasPermission('settings')) {
    exit;
}
require_once MODX_CORE_PATH . 'settings_form.class.inc.php';

$theme = getv('theme', '');
if (array_key_exists($theme, EvoSettingsForm::themes())) {
    evo()->setConfig('manager_theme', $theme);
}
$proposal = getv('proposal') === '1';
// 本文の文字の大きさ（見本の中だけで --font-size-base を差し替える）
$font = in_array(getv('font'), ['12', '13', '14'], true) ? getv('font') : '';
?>
<!DOCTYPE html>
<html lang="<?= globalv('modx_lang_attribute', 'en') ?>">
<head>
    <meta charset="<?= hsc(evo()->config('modx_charset')) ?>">
    <title><?= lang('styleguide_title') ?></title>
    <?= manager_theme_stylesheets() ?>
    <link rel="stylesheet" href="<?= manager_asset('media/style/common/shell.css') ?>">
    <?php if ($proposal) { ?>
        <link rel="stylesheet" href="<?= manager_asset('media/style/common/foundation-proposal.css') ?>">
    <?php } ?>
    <link rel="stylesheet" href="<?= manager_asset('media/style/common/styleguide.css') ?>">
    <script src="<?= manager_asset('media/script/tabpane.js') ?>"></script>
</head>
<body id="mainpane" class="styleguide-sample<?= $font !== '' ? ' sg-font-' . $font : '' ?>">
<main id="mainPane">

<h1>見出し 1（h1）: リソースを編集</h1>

<!-- ボタン -->
<h2 class="sg-title">ボタン</h2>
<div id="actions-sample">
    <ul class="actionButtons">
        <li id="Button1" class="mutate"><a href="#">更新</a></li>
        <li class="mutate"><a href="#">複製</a></li>
        <li class="mutate"><a href="#">削除</a></li>
        <li class="mutate"><a href="#">キャンセル</a></li>
    </ul>
</div>
<p class="sg-row">
    <button type="button" class="button primary">主ボタン（.button.primary）</button>
    <button type="button" class="button">ボタン（.button）</button>
    <button type="button">素の button</button>
    <input type="submit" value="input[type=submit]">
    <input type="button" value="input[type=button]">
</p>

<!-- 入力欄 -->
<h2 class="sg-title">入力欄</h2>
<p class="sg-row">
    <input type="text" value="text">
    <input type="password" value="password">
    <input type="search" value="search">
    <input type="email" value="mail@example.com">
    <input type="number" value="10">
</p>
<p class="sg-row">
    <select><option>select</option><option>2</option></select>
    <input type="text" class="DatePicker" value="2026/11/14 10:00:00">
    <label><input type="checkbox" checked> checkbox</label>
    <label><input type="radio" name="sg-radio" checked> radio</label>
    <label><input type="radio" name="sg-radio"> radio</label>
</p>
<p class="sg-row">
    <textarea rows="3" cols="40">textarea</textarea>
</p>
<p class="sg-row">
    <input type="search" value="検索欄とボタンを並べる">
    <button type="submit" class="button primary">検索</button>
    <input type="submit" value="送信">
</p>

<!-- 高さの比較 -->
<h2 class="sg-title">高さの比較（本文の文字の大きさから計算した高さ・一段上・今のテーマの値）</h2>
<div class="sectionBody">
    <div class="sg-size-calc">
        <p class="sg-size-caption">計算した高さ（本文の 1 行の高さ ＋ 上下 4px × 2 ＋ 枠 1px × 2 を 4px の目盛りに切り上げ）</p>
        <p class="sg-row">
            <label>ラベル</label>
            <input type="text" value="text">
            <select><option>select</option></select>
            <input type="text" class="DatePicker" value="2026/11/14 10:00:00">
            <input type="search" value="検索欄">
            <button type="submit" class="button primary">検索</button>
            <input type="submit" value="送信">
        </p>
        <div class="form-row">
            <label class="form-label">ファイルの URL</label>
            <div class="form-field">
                <div class="input-group">
                    <span class="input-group-text">http://localhost/</span>
                    <input type="text" value="content/">
                    <button type="button" class="button">リセット</button>
                </div>
                <div class="form-text">上の置き場所を、ブラウザから開くときの URL です。</div>
            </div>
        </div>
    </div>
    <div class="sg-size-up">
        <p class="sg-size-caption">一段上（計算した高さ ＋ 4px）</p>
        <p class="sg-row">
            <label>ラベル</label>
            <input type="text" value="text">
            <select><option>select</option></select>
            <input type="text" class="DatePicker" value="2026/11/14 10:00:00">
            <input type="search" value="検索欄">
            <button type="submit" class="button primary">検索</button>
            <input type="submit" value="送信">
        </p>
        <div class="form-row">
            <label class="form-label">ファイルの URL</label>
            <div class="form-field">
                <div class="input-group">
                    <span class="input-group-text">http://localhost/</span>
                    <input type="text" value="content/">
                    <button type="button" class="button">リセット</button>
                </div>
                <div class="form-text">上の置き場所を、ブラウザから開くときの URL です。</div>
            </div>
        </div>
    </div>
    <div class="sg-size-now">
        <p class="sg-size-caption">今のテーマの値（--control-height）</p>
        <p class="sg-row">
            <label>ラベル</label>
            <input type="text" value="text">
            <select><option>select</option></select>
            <input type="text" class="DatePicker" value="2026/11/14 10:00:00">
            <input type="search" value="検索欄">
            <button type="submit" class="button primary">検索</button>
            <input type="submit" value="送信">
        </p>
        <div class="form-row">
            <label class="form-label">ファイルの URL</label>
            <div class="form-field">
                <div class="input-group">
                    <span class="input-group-text">http://localhost/</span>
                    <input type="text" value="content/">
                    <button type="button" class="button">リセット</button>
                </div>
                <div class="form-text">上の置き場所を、ブラウザから開くときの URL です。</div>
            </div>
        </div>
    </div>
</div>

<!-- ラベルと入力欄の行 -->
<h2 class="sg-title">ラベルと入力欄の行</h2>
<div class="section">
    <div class="sectionHeader">リソース編集の欄（render_tr.tpl）</div>
    <div class="sectionBody">
        <table>
            <tr class="mutate-field-row">
                <td class="mutate-field-label"><span class="mutate-field-title">リソース名</span></td>
                <td><input type="text" class="inputBox" value="Home"></td>
            </tr>
            <tr class="mutate-field-row">
                <td class="mutate-field-label"><span class="mutate-field-title">使用テンプレート</span></td>
                <td><select class="inputBox"><option>Bootstrap</option></select></td>
            </tr>
            <tr class="mutate-field-row">
                <td class="mutate-field-label"><span class="mutate-field-title">公開開始日時</span></td>
                <td><input type="text" class="DatePicker imeoff" id="pub_date" value=""></td>
            </tr>
        </table>
    </div>
</div>
<div class="section">
    <div class="sectionHeader">ユーザー編集の表（table.settings）</div>
    <div class="sectionBody">
        <table class="settings">
            <tr>
                <th>ユーザー名:</th>
                <td><input type="text" class="inputBox" value="admin"></td>
            </tr>
            <tr>
                <th>メールアドレス:</th>
                <td><input type="text" class="inputBox" value="admin@example.com"></td>
            </tr>
        </table>
    </div>
</div>
<div class="section">
    <div class="sectionHeader">グローバル設定の表（settings_form）</div>
    <div class="sectionBody">
        <h3 class="settings-section">サイト</h3>
        <table class="settings">
            <tr>
                <th>サイト名</th>
                <td><input type="text" value="My MODX Site"><div class="comment">サイトの名前を入れます。</div></td>
            </tr>
            <tr>
                <th>サイトの状態</th>
                <td><label><input type="radio" name="sg-status" checked> 公開</label> <label><input type="radio" name="sg-status"> 非公開</label></td>
            </tr>
        </table>
    </div>
</div>

<div class="section">
    <div class="sectionHeader">グローバル設定のファイルの欄（既定値・リセット・前に付く文字）</div>
    <div class="sectionBody">
        <?php if (!$proposal) { ?>
            <!-- 今の書き方（settings_form.class.inc.php の path と、before の付いた text と同じ） -->
            <table class="settings">
                <tr>
                    <th>ファイルの置き場所</th>
                    <td>既定値 <span id="default_rb_base_dir">[(base_path)]content/</span> (/var/www/html/content/)<br/>
                        <input type="text" name="rb_base_dir" value="[(base_path)]content/" maxlength="255" id="rb_base_dir" />
                        <input type="button" value="リセット" name="reset_rb_base_dir"><br/>
                        ファイルブラウザで扱うファイルを置く場所です。サーバーの中の位置で書きます。
                    </td>
                </tr>
                <tr>
                    <th>ファイルの URL</th>
                    <td>http://localhost/<input type="text" name="rb_base_url" value="content/" maxlength="255" /><br/>
                        上の置き場所を、ブラウザから開くときの URL です。
                    </td>
                </tr>
            </table>
        <?php } else { ?>
            <!-- 案の書き方: 行の部品（ラベルの列と欄の列）、前に付く文字とボタンをつなぐ input-group、説明文の form-text -->
            <div class="form-row">
                <label class="form-label" for="rb_base_dir">ファイルの置き場所</label>
                <div class="form-field">
                    <div class="form-text">既定値 [(base_path)]content/ (/var/www/html/content/)</div>
                    <div class="input-group">
                        <input type="text" name="rb_base_dir" value="[(base_path)]content/" maxlength="255" id="rb_base_dir" />
                        <button type="button" class="button">リセット</button>
                    </div>
                    <div class="form-text">ファイルブラウザで扱うファイルを置く場所です。サーバーの中の位置で書きます。</div>
                </div>
            </div>
            <div class="form-row">
                <label class="form-label" for="rb_base_url">ファイルの URL</label>
                <div class="form-field">
                    <div class="input-group">
                        <span class="input-group-text">http://localhost/</span>
                        <input type="text" name="rb_base_url" value="content/" maxlength="255" id="rb_base_url" />
                    </div>
                    <div class="form-text">上の置き場所を、ブラウザから開くときの URL です。</div>
                </div>
            </div>
        <?php } ?>
    </div>
</div>

<!-- タブ -->
<h2 class="sg-title">タブ</h2>
<div class="sectionBody">
    <div class="tab-pane" id="sgTabs">
        <div class="tab-page" id="sgTab1">
            <h2 class="tab">一般</h2>
            <p>タブの中の段落です。</p>
            <div class="section">
                <div class="sectionHeader">タブの中のセクション</div>
                <div class="sectionBody"><p>本体の箱です。</p></div>
            </div>
        </div>
        <div class="tab-page" id="sgTab2">
            <h2 class="tab">ページ設定</h2>
            <p>2 つ目のタブです。</p>
        </div>
    </div>
</div>
<script>
    (function () {
        const pane = new WebFXTabPane(document.getElementById('sgTabs'), false);
        pane.addTabPage(document.getElementById('sgTab1'));
        pane.addTabPage(document.getElementById('sgTab2'));
    })();
</script>

<!-- 表 -->
<h2 class="sg-title">表とページ送り</h2>
<div class="section">
    <div class="sectionBody">
        <table class="grid">
            <thead>
                <tr><th class="is-shrink">ID</th><th>名前</th><th class="is-center">状態</th><th>更新日時</th></tr>
            </thead>
            <tbody>
                <tr class="gridItem"><td>1</td><td><a href="#">Home</a></td><td class="is-center">公開</td><td>2026/11/14 10:00</td></tr>
                <tr class="gridAltItem"><td>2</td><td><a href="#">新着情報</a></td><td class="is-center">非公開</td><td>2026/11/13 09:30</td></tr>
                <tr class="gridItem"><td>3</td><td><a href="#">お問い合わせ</a></td><td class="is-center">公開</td><td>2026/11/12 18:20</td></tr>
            </tbody>
        </table>
        <div class="pagination">
            <ul>
                <li><a href="#">&laquo;</a></li>
                <li><span>1</span></li>
                <li><a href="#" aria-current="page">2</a></li>
                <li><a href="#">3</a></li>
                <li><a href="#">&raquo;</a></li>
            </ul>
        </div>
    </div>
</div>

<!-- メッセージ -->
<h2 class="sg-title">メッセージ</h2>
<p><span class="success">保存しました（.success）</span></p>
<p><span class="warning">確かめてください（.warning）</span></p>
<p><span class="fail">保存できませんでした（.fail）</span></p>

<!-- 文字 -->
<h2 class="sg-title">文字の大きさ</h2>
<div class="sectionBody">
    <h1>見出し 1（h1）</h1>
    <h2>見出し 2（h2）</h2>
    <h3>見出し 3（h3）</h3>
    <h4>見出し 4（h4）</h4>
    <h5>見出し 5（h5）</h5>
    <h6>見出し 6（h6）</h6>
    <p>本文の段落です。管理画面の多くの説明文はこの大きさで書かれています。</p>
    <p><small>小さい文字（small）です。</small> <span class="comment">補足の文字（.comment）です。</span></p>
</div>

</main>
<script>
    // 入力欄・セレクト・ボタンの横に、測った高さを小さく出す（ガタつきを数字でも見えるようにする）
    window.addEventListener('load', function () {
        const targets = document.querySelectorAll(
            '.sg-row :is(input, select, textarea, button), .actionButtons a, .settings :is(input, select), .mutate-field-row :is(input, select), .form-row .input-group'
        );
        targets.forEach(function (el) {
            if (el.type === 'checkbox' || el.type === 'radio') {
                return;
            }
            const badge = document.createElement('span');
            badge.className = 'sg-dim';
            badge.textContent = Math.round(el.getBoundingClientRect().height) + 'px';
            el.after(badge);
        });
    });
</script>
</body>
</html>
