# ExecPlan: ファイルブラウザの KCFinder 級拡張

## Purpose / Big Picture

管理画面のファイルブラウザ（画像やファイルを選んだり、アップロードしたりする画面）に、KCFinder などの高機能なファイルマネージャーにある操作を足す。対象は、英語の管理画面での表示、右クリックメニュー、コピー・切り取り・貼り付け、zip でのまとめてダウンロード、画像のトリミング・回転・リサイズの 5 つ。これで、FTP や外部の画像編集ソフトを使わなくても、日常のファイル整理が管理画面の中で済むようにする。

Issue: #678（ロードマップ「ファイルブラウザのKCFinder級拡張」）

## Progress

- [x] (2026-10-30) M1: UI 文言の多言語化（言語ファイルに `fb_` のキーを 62 個、`js/i18n.js` を追加。日英の表示・API のエラー・既存操作を Docker で確認）
- [ ] M2: 右クリックメニュー
- [ ] M3: コピー・切り取り・貼り付け
- [ ] M4: zip での一括ダウンロード
- [ ] M5: 画像の簡易編集（トリミング・90° 回転・数値でのリサイズ）

## Surprises & Discoveries

2026-10-30 (M1): ポップアップの `browser.php` は `<html lang>` を `globalv('modx_lang_attribute', 'ja')` で出していたが、`MODX_API_MODE` では言語の読み込み（`loadLexicon()`）が走らず、このグローバル変数が設定されない。そのため英語の画面でも常に `ja` になっていた。言語ファイルの `lang_attribute` を `lang()` で読む形に改めた。言語ファイルに `textdir` のキーは無いので `dir` は付けていない。

2026-10-30 (M1): `js/utils.js` の `escapeHtml()` は DOM の `textContent` から `innerHTML` を取る作りで、`"` と `'` をエスケープしていなかった。`data-name` などの属性値に、FTP で直に置いた名前（`"` を含むもの）が入ると属性が壊れうる。`& < > " '` を置き換える形に改め、名前に `'` と `&` を含むファイルとフォルダで、表示・選択・名前の変更・移動・削除を確かめた。

2026-10-30 (M1): `api.php` の `lang()` は、`getSettings()` が管理ユーザーの `user_settings` を `config` に重ねるので、利用者ごとの `manager_language` で効く（システム設定は日本語のまま、利用者だけ英語にして確かめた）。ウェブユーザーのセッションや未ログインのエラーでは、システム設定の言語になる。

## Decision Log

2026-10-30 (yamamoto/Claude): 5 項目を 5 つの段階（M1〜M5）に分け、1 段階を 1 PR にする。Issue に「一括で実装しなくてよい」とあり、1 段階ずつならレビューしやすく、途中で止めても使える状態が保てるため。

2026-10-30 (yamamoto/Claude): 多言語化を最初にする。後の段階では、メニューの項目やダイアログの文言が増える。言語ファイルの仕組みを先に入れておけば、増える文言を最初から言語ファイルに書ける。後回しにすると、増えた日本語の文字列をもう一度移し替えることになる。代わりの案として、目に見えて便利になる右クリックメニューを先にする順番も考えたが、手戻りがあるので採らなかった。

2026-10-30 (yamamoto/Claude): 右クリックメニュー（M2）は、コピー・切り取り・貼り付け（M3）より先にする。M3 の操作を入れる場所を M2 で用意できるため。M2 のメニューの中身は、今ある処理（選択・プレビュー・名前の変更・削除・移動）を呼ぶだけにする。

2026-10-30 (yamamoto/Claude): 文言は管理画面の言語ファイル `manager/includes/lang/*.inc.php` に、`fb_` で始まるキーで足す。ファイルブラウザ専用の言語ファイルは作らない。今ある `lang()` と `getLexicon()`（利用者の言語を見て、足りないキーを英語で補う）がそのまま使えるため、言語を読む仕組みを 2 つにしないで済む。JS には、`browser.php` が JS で使うキーだけを、すでにある `data-config` の JSON に入れて渡す。JS 側で言語ファイルを取りに行く API は作らない。

2026-10-30 (yamamoto/Claude): 既存の汎用キーと英日の文言が同じもの（`move`・`delete`・`cancel`・`close`・`preview`）は既存のキーを使い、`fb_` はファイルブラウザ固有の言い回しにだけ付ける。同じ単語を 2 か所に持つと、翻訳を直すときにずれるため（M1 の自己レビューで変更）。`fb_rename`（名前を変更）は既存の `rename`（リネーム）と文言が違うので分ける。

2026-10-17 (yamamoto): 画像編集は Cropper.js を使わず自作する（Issue #678 のコメント）。パッケージを小さく保つため。書き出しは canvas の `drawImage` で行い、トリミング範囲のドラッグは `js/marquee.js` の矩形選択の考え方を流用する。自由な角度の回転とズームは作らない。

## Outcomes & Retrospective

## Context and Orientation

ファイルブラウザは `manager/media/browser/evo/` にある。2026-07-11 に作り直したもので、そのときの計画は `.agent/plans/2026-07-11-file-browser-modal-rewrite.md`。3 通りの開かれ方がある。EvoShell モーダル（管理画面の中に重ねて開く小窓。TV の入力欄などから開く）、ポップアップウィンドウ（QuickManager など）、TinyMCE（本文エディター）のダイアログの 3 つ。

主なファイルの役割は次のとおり。

- `browser.php`: 画面の HTML を出す。`?modal=1` ならモーダル用の断片を、そうでなければ単独のページを出す。JS に渡す設定（API の URL、ファイルの種類、CSRF トークン）を `$jsConfig` にまとめ、`data-config` 属性に JSON で入れている。ツールバー・選択バー・移動ダイアログ・プレビューの文言は、日本語が直に書かれている
- `api.php`: JSON を返す API。`action` に `list` / `thumb` / `upload` / `mkdir` / `rename` / `delete` / `move` がある。書き込みの操作は `checkCsrfToken()` で CSRF トークン（なりすましの送信を防ぐための合言葉）を確かめる。エラーは `fbError($code, $message, $status)` で `{error:{code,message}}` を返す。メッセージは日本語が直に書かれている
- `filebrowser.js`: 入口。状態（今のフォルダ、選択中のファイルなど）を持ち、各モジュールをつなぐ。`window.prompt` / `alert` / `confirm` の文言が日本語で書かれている
- `js/grid.js`（一覧）、`js/tree.js`（左のフォルダツリー）、`js/dialogs.js`（移動ダイアログとプレビュー）、`js/upload.js`（アップロード）、`js/dnd.js`（ドラッグ＆ドロップ）、`js/marquee.js`（矩形選択）、`js/api.js`（通信）、`js/utils.js`（HTML のエスケープやパンくず）
- `lib/PathResolver.php`（フォルダやファイルの名前を安全なパスに直す）、`lib/BrowserConfig.php`（設定の読み取り）、`lib/ThumbnailService.php`（サムネイルを `temp/thumbs/` に作る）

言語ファイルは `manager/includes/lang/english.inc.php` と `japanese-utf8.inc.php`。どちらも `return [ 'キー' => '文言', ... ];` の形をとる。`lang($key)`（`manager/includes/helpers.php`）は `evo()->getLexicon()` を呼ぶ。`getLexicon()` は英語の言語ファイルを読み、設定 `manager_language` の言語ファイルで上書きする。キーが無ければ、キーの名前をそのまま返す。PHP から `<script>` の中へ文言を入れる例は、`manager/actions/header.inc.php` の `js_literal(lang('close'))` などにある。

## Plan of Work

### M1: UI 文言の多言語化

ファイルブラウザに書かれている日本語の文言を、言語ファイルのキーに置き換える。コメントは日本語のままにする。

PHP 側では、`browser.php` の画面の文言（見出しの `$labels`、検索欄のプレースホルダ、並べ替えの選択肢、ボタン、ダイアログの見出し、「読み込み中...」、ドロップの案内）を `lang('fb_…')` に置き換え、出力の直前で `hsc()` をかける。`api.php` の `fbError()` と、エラーの配列に入れる `message` も `lang('fb_err_…')` にする。`error.code` は変えない（JS や外部のコードが判定に使っているかもしれないため）。

JS 側には `js/i18n.js` を新しく作る。この中に、`setMessages(obj)`（文言の表を受け取る）と `t(key, ...args)`（キーの文言を返す）を置く。`t()` は文言の中の `%s` を、引数で順に置き換える。PHP の言語ファイルの書き方と同じにするため。キーが無ければキーの名前を返す。`browser.php` は JS で使うキーを `$jsConfig['lang']` に入れる。`filebrowser.js` の `init()` の最初で `setMessages(config.lang || {})` を呼ぶ。各モジュールの日本語の文字列は `t('fb_…')`（汎用のキーで足りるものは `t('delete')` など）にする。`innerHTML` に入れる箇所では、今と同じく `escapeHtml()` を通す（翻訳の文言に `<` や `&` が入ってもよいように）。

キーの名前は `fb_` に続けて、短い英語で中身を表す（例: `fb_loading`、`fb_new_folder`、`fb_confirm_delete_file`、`fb_err_folder_not_found`）。件数や名前が入る文言は `%s` を使う（例: `fb_selected_count` => `'%s件選択中'` / `'%s selected'`）。「ファイル」と「フォルダ」で文が変わる確認文は、言語によって語順が違うため、キーを分ける（`fb_confirm_delete_file` と `fb_confirm_delete_folder`）。

この段階は、DocumentParser の処理の流れ（`executeParser()` / `prepareResponse()` / `parseDocumentSource()` / `postProcess()`）に関わらない。API と画面は `MODX_API_MODE` で設定と言語を読むだけ。キャッシュにも関わらない。JS と CSS のキャッシュバスター（更新時に古いファイルを使わせないため、URL に付ける更新時刻）は、今と同じく filemtime で効く。ただし新しく足す `js/i18n.js` は `import` で読まれるので、キャッシュバスターが付かない。ほかの `js/*.js` と扱いは同じなので、ここでは変えない。

### M2: 右クリックメニュー

`js/contextmenu.js` を新しく作り、一覧のファイルやフォルダ、ツリーのフォルダ、何もない所を右クリックしたときにメニューを出す。項目は、ファイルなら「選択」「プレビュー」「名前の変更」「移動」「削除」。フォルダなら「開く」「名前の変更」「削除」。何もない所なら「新規フォルダ」「アップロード」。それぞれ `filebrowser.js` にある今の処理を呼ぶ。複数を選んでいる状態で右クリックしたときは、選択中のすべてを対象にする（一括の移動・削除と同じ）。Esc、メニューの外のクリック、スクロールで閉じる。キーボードの上下キーで項目を移れるようにする。文言は M1 の仕組みで言語ファイルに足す。

### M3: コピー・切り取り・貼り付け

API に `copy`（`type`、`folder`、`files[]`、`dest`）を足す。同じ名前のファイルが貼り付け先にあるときは、アップロードと同じく `名前(1).拡張子` のように連番を付ける（`fbActionUpload()` の連番を探す処理を関数に切り出して共用する）。フォルダのコピーは、この段階では扱わない（移動も今はファイルだけのため）。切り取りして貼り付けるときは、今ある `move` を使う。クリップボード（コピーや切り取りしたものを一時的に覚えておく所）は JS のメモリに持ち、フォルダを移っても残す。モーダルを閉じたら消える。右クリックメニューと選択バーに「コピー」「切り取り」「貼り付け」を足し、Ctrl+C / Ctrl+X / Ctrl+V（Mac は Cmd）でも操作できるようにする。入力欄で文字を打っているときは、ショートカットを横取りしない。

### M4: zip での一括ダウンロード

API に `download`（POST。`type`、`folder`、`files[]`。CSRF トークンを確かめる）を足す。選んだファイルを `temp/` の一時ファイルに zip でまとめ、`Content-Disposition: attachment` で送り、送り終えたら消す。1 ファイルだけのときは zip にせず、そのファイルを送る。PHP の ZipArchive（zip を作るための PHP の拡張）が無い環境では、`browser.php` から JS にそのことを伝え、ボタンとメニュー項目を出さない。ファイル数とサイズの上限は、この段階に着手するときに決めて Decision Log に書く。

### M5: 画像の簡易編集

プレビューに「編集」を足し、トリミング、90° 回転（左右）、数値（幅・高さ。縦横比を保つかどうかを選べる）でのリサイズができるようにする。外部ライブラリは使わず、canvas の `drawImage` で書き出す。保存は API に `saveImage`（POST。CSRF トークンを確かめる）を足し、上書きか別名かを選べるようにする。出力の形式は元の拡張子に合わせる（jpg なら `image/jpeg`。png のままで jpg に上書きしない）。保存したら、`temp/thumbs/` の古いサムネイルが使われないようにする。canvas で書き出すと EXIF（写真の向きや撮影の情報）が消える。消えることを受け入れるかどうかは、この段階に着手するときに決めて Decision Log に書く。

## Concrete Steps

M1 の手順を書く。M2 以降の手順は、その段階に着手するときにこの節へ足す。

1. 文言の洗い出し。コメント以外で日本語が書かれている所を一覧にする。

        grep -nP "[ぁ-んァ-ヶ一-龠]" manager/media/browser/evo/*.php manager/media/browser/evo/*.js manager/media/browser/evo/js/*.js manager/media/browser/evo/lib/*.php | grep -vP "^\S+:\d+:\s*(//|\*|/\*)"

   期待する結果は、`browser.php`、`api.php`、`filebrowser.js`、`js/grid.js`・`js/tree.js`・`js/dialogs.js`・`js/upload.js` の文言が並ぶこと。`lib/*.php` に出るのはコメントだけのはず。

2. 言語ファイル。`manager/includes/lang/english.inc.php` と `japanese-utf8.inc.php` の配列の末尾に、`fb_` のキーを同じ順で足す。英語の文言は短く書く。

3. `browser.php`。`$labels` と HTML の文言を `lang()` と `hsc()` に置き換える。`$jsConfig['lang']` に、JS で使うキーの配列を入れる。キーの一覧は `browser.php` に配列で 1 か所にまとめて持つ。

4. `js/i18n.js` を作り、`filebrowser.js` と `js/*.js` の文字列を `t()` に置き換える。

5. `api.php`。`fbError()` と、`errors[]` の `message` を `lang('fb_err_…')` に置き換える。

6. 確認（「Validation and Acceptance」の手順）。

## Validation and Acceptance

確認は Docker の検証環境で行う（`compose.yml`。手順は `adaptive-validation-runner` スキル）。

M1 は次をすべて満たせば完了とする。

- Concrete Steps 1 の grep で、コメントでない日本語が `manager/media/browser/evo/` に 1 件も出ない
- 検証用イメージと `php:7.4-cli` の両方で、変えた PHP ファイルに `php -l` をかけると `No syntax errors detected` が出る（main は PHP 7.4 以上で動く必要があるため）
- 管理画面の言語が日本語のとき、ファイルブラウザ（モーダルとポップアップの両方）の見た目と文言が、変更前と同じ
- 管理画面の言語を英語にすると、見出し、ツールバー、選択バー（`2 selected` など）、移動ダイアログ、プレビュー、空のフォルダの案内、アップロードの進み具合、削除の確認、名前の変更の入力欄がすべて英語で出る。Playwright で画面写真を撮って確かめる
- ログインした状態で `api.php?action=foo&type=images` を curl で呼ぶ。英語にしたときは `{"error":{"code":"invalid_action","message":"<英語の文言>"}}` が返る。`code` は変わっていない
- 今ある操作（アップロード、新規フォルダ、名前の変更、移動、削除、TV の入力欄からのファイルの選択）が、日本語のときも英語のときもこれまでどおり動く

## Idempotence and Recovery

各段階は 1 つの PR で閉じる。途中で止まったときは、その段階のブランチを main に合わせ直してやり直せる。言語ファイルにキーを足す作業は、同じキーがすでにあれば足さない（`grep -n '"fb_' manager/includes/lang/english.inc.php` で確かめる）。M3 以降で API に操作を足すときも、今ある操作の動きは変えない。どの段階で止めても、ファイルブラウザは使える状態のままになる。

## Artifacts and Notes

- ブランチ: `feat/678-filebrowser-i18n`（M1〜M5 で同じブランチと PR を使う）
- Issue: https://github.com/modxcms-jp/evolution-jp/issues/678 （2026-10-17 のコメントに画像編集の決定と、足りないもの（保存の API、拡張子、サムネイル、EXIF）の一覧がある）
- 前の計画: `.agent/plans/2026-07-11-file-browser-modal-rewrite.md`
- 参考: TinyMCE7 プラグインの画像編集 `assets/plugins/tinymce7/js/tinymce-cropper.js`（モーダルの組み立て方の参考にする。Cropper.js は使わない）

想定するコミットの分け方（1 段階 1 PR）:

- M1: `feat(filebrowser): UI 文言を言語ファイルへ移す`
- M2: `feat(filebrowser): 右クリックメニューを追加`
- M3: `feat(filebrowser): コピー・切り取り・貼り付けを追加`
- M4: `feat(filebrowser): 選んだファイルを zip でまとめてダウンロードできるようにする`
- M5: `feat(filebrowser): 画像のトリミング・回転・リサイズを追加`

## Interfaces and Dependencies

- 言語: `lang()`（`manager/includes/helpers.php`）、`evo()->getLexicon()`（`manager/includes/traits/document.parser.subparser.trait.php`）、`manager/includes/lang/*.inc.php`
- 出力のエスケープ: PHP は `hsc()`、JS は `js/utils.js` の `escapeHtml()`
- API の呼び出し元: `filebrowser.js` と `js/*.js` のほか、TinyMCE の `mcpuk-picker.js`（`postMessage` で選んだ URL を受け取る）や、TV の入力欄（`window.EvoFileBrowserPick`）、ポップアップ（`window.opener.SetUrl`）がある。これらの受け渡しの形は変えない
- イベント: アップロードの後の `OnFileBrowserUpload`。M3〜M5 で増えるファイルの書き込みに新しいイベントは足さない（足すなら別のタスクで、今あるイベントの置き場所を調べてから決める）
- PHP の拡張: M4 で ZipArchive、M5 で GD（サムネイルの作成ですでに使っている）
