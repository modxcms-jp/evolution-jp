# ExecPlan: ファイルブラウザの KCFinder 級拡張

## Purpose / Big Picture

管理画面のファイルブラウザ（画像やファイルを選んだり、アップロードしたりする画面）に、KCFinder などの高機能なファイルマネージャーにある操作を足す。対象は、英語の管理画面での表示、右クリックメニュー、コピー・切り取り・貼り付け、zip でのまとめてダウンロード、画像のトリミング・回転・リサイズの 5 つ。これで、FTP や外部の画像編集ソフトを使わなくても、日常のファイル整理が管理画面の中で済むようにする。

Issue: #678（ロードマップ「ファイルブラウザのKCFinder級拡張」）

## Progress

- [x] (2026-10-30) M1: UI 文言の多言語化（言語ファイルに `fb_` のキーを 62 個、`js/i18n.js` を追加。日英の表示・API のエラー・既存操作を Docker で確認）
- [x] (2026-10-31) M2: 右クリックメニュー（`js/contextmenu.js` を追加。一覧のファイル・フォルダ・何も無い所で今ある操作を呼ぶ。モーダル・ポップアップ・TinyMCE のダイアログ、日英で Docker で確認）
- [x] (2026-10-31) M3: コピー・切り取り・貼り付け（API に `copy`、クリップボードはメモリ、切り取りは既存の `move`。選択バー・ツールバー・右クリック・Ctrl+C/X/V。モーダル・ポップアップ・TinyMCE、日英で Docker で確認）
- [x] (2026-10-31) M4: zip での一括ダウンロード（API に `download`、`lib/ZipService.php`、`js/download.js`。1 件はそのまま、2 件以上は zip。モーダル・ポップアップ・TinyMCE、日英、zip 無しの構成で Docker で確認）
- [x] (2026-10-31) M5: 画像の簡易編集（トリミング・90° 回転・数値でのリサイズ）（API に `saveImage`、`js/imageEditor.js`・`js/overlay.js`。jpg・png、別名が既定。モーダル・ポップアップ・TinyMCE、日英、PHP 7.4 と 8.4 で Docker で確認）

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

2026-10-30 (yamamoto/Claude): 右クリックメニュー（M2）は、管理画面にある既存のメニュー（リソースツリーの `#mx_contextmenu`、`manager/includes/controls/contextmenu.php`）を使わず、ファイルブラウザの中に作る。既存のメニューはテーマごとの CSS と固定の ID に結びついていて、ファイルブラウザが動く 3 つの開かれ方（モーダル、ポップアップ、TinyMCE のダイアログ）では同じように使えないため。見た目は `filebrowser.css` のテーマの変数でそろえる。左のフォルダツリーの右クリックは、M2 では扱わない。ツリーのフォルダの名前の変更や削除には、今の一覧とは別のフォルダを基準にした処理が要り、M2 の「今ある処理を呼ぶだけ」から外れるため。必要になったら別に足す。

2026-10-30 (yamamoto/Claude): M2 の実装と自己レビューで、次を決めた。「プレビュー」の項目は、一覧のプレビューボタンと同じく、画像に限らずすべてのファイルに出す（当初の計画は画像のときだけだったが、既存のボタンとそろえる）。メニューは `#evoFileBrowser` の中に `position: fixed` で置く（モーダルの中でも右クリックした位置に出ることを確かめた）。インラインの名前の変更などの入力欄の上では、ブラウザ標準のメニューを残す（貼り付けなどを使えるように）。フォーカスを戻すのは Esc・Tab・項目を選んだときだけにする。スクロールで閉じたときに戻すと、利用者がスクロールした一覧が元の位置へ引き戻されるため。メニューキーで開いた直後に届くスクロールのイベントで閉じないよう、スクロール位置が変わったときだけ閉じる。読み上げのため、メニューには `aria-label` として右クリックした対象の名前を付ける。何も無い所では `fb_context_menu`（「ファイル操作」）を使う。

2026-10-31 (yamamoto/Claude): M3 の実装と自己レビューで、次を決めた。コピー元は `PathResolver::resolveFile()` で実体のパスを確かめる（サムネイルと同じ）。許可された場所の中に外を指すシンボリックリンクがあっても、外のファイルの中身を公開の場所へ複製させないため。コピー先は `fopen($path, 'xb')`（既にあれば失敗する作り方）で作り、同時の操作でも上書きしない。アップロードの保存（`move_uploaded_file()`）は変えず、同時の操作での上書きの余地は前からの性質として残す。空いている名前の判定は、壊れたシンボリックリンクと同じ名前のフォルダも「使用中」とする（アップロードにも効く）。コピーには容量や件数の上限を設けない。ログインした管理者の操作で、元のファイルは既にサーバーにあるため。失敗は `logEvent()` に元と先のパスを付けて残す。

2026-10-31 (yamamoto/Claude): 新しいファイルのパーミッションの計算を `DocumentParser::newFilePermissions()` にまとめ、本体の `move_uploaded_file()` と `resizeImage()` の 3 か所とコピーから呼ぶ。設定 `new_file_permissions` が空・0・8 進数でない値のときは 0644 にする。これはファイルブラウザの外にも効く変更で、設定が空のとき、これまでアップロードしたファイルのパーミッションが 0 になっていた不具合も直る。同じ計算は `manager/actions/element/files.dynamic.php`（空なら 0666）と `manager/includes/site_exporter.class.inc.php`（空なら 0644）にもあるが、旧ファイル管理の既定が変わるので M3 では触らず、別に扱う。

2026-10-31 (yamamoto/Claude): 一覧を描き直すとフォーカスが失われ、Ctrl+V を続けられなかった。一覧に `tabindex="-1"` を付け、何も無い所をクリックしたら一覧へ、描き直す前にフォーカスがファイルブラウザの中にあったときだけ、描き直した後に選択中の項目か一覧へ戻す（スクロールさせない。外の入力欄からは奪わない）。

2026-10-31 (yamamoto): PR の分け方を改める。当初は 1 段階を 1 PR にする予定だったが、実際には exec-plan-runner の作り（1 つの ExecPlan を 1 つの PR に積む）に沿って、M1〜M3 を #965 にまとめた。差分が大きくなってきたので、#965 は M1〜M3 でレビューに出してマージし、M4・M5 はそれぞれ新しい PR にする。M4・M5 の PR では、対象の Issue を `Refs #678` で挙げ、最後の M5 で `Closes #678` にする。

2026-10-31 (yamamoto/Claude): M4 は下調べの結果をもとに、次のとおり決めた。ZipArchive は README の必須拡張ではなく、公式の PHP イメージにも入っていないので、`BrowserConfig::canCreateZip()` を API と画面の両方で使い、使えない環境では 2 件以上のダウンロードを出さない（1 件はそのまま送れる）。上限は件数 200・合計 256MB とし、定数で持つ（設定項目にはしない）。200MB を zip にしてもメモリは 2MB ほどで、圧縮の時間も数秒で済む（実測）。1 つでも条件を満たさないファイルがあれば全体を拒否する。送るときは POST で受け、`fetch` で受け取って保存させる（エラーを画面に出せるため）。ダウンロードは書き込みではないが、ファイルを外へ出すので CSRF トークンを確かめる。thumb・move・delete は拡張子を見ていないが、ダウンロードでは許可された拡張子だけに絞り、`resolveFile()` で実体を確かめる。フォルダごとのダウンロードは扱わない。

2026-10-31 (yamamoto/Claude): M4 の自己レビューで、次を決めた。1 件のダウンロードは、画面が `fetch` で受けたファイル全体をブラウザのメモリに持ってから保存する。数 GB の動画などでは負担が大きいが、別の作り（隠したフォームで送る）にすると画面にエラーを出せなくなるので、今のままにする。シンボリックリンクやハードリンクを使うと、実体がベースディレクトリの中にある別の種類のファイル（例: images の名前で files のファイル）を取り出せるが、リンクを置くにはサーバーのファイルに触れる必要があり、実体も公開の範囲にあるので、扱わない。ファイルブラウザを使える利用者は、その種類の許可された拡張子のファイルを取り出せる（`.htaccess` で Web から読めなくしたフォルダの中身も含む）。M3 のコピーで公開のフォルダへ移すこともできるので、新しく増えた権限ではない。CSRF トークンが合わないときの表示が「HTTP 403」だけになる件は、#983（エラー表示の統一）で扱う。

2026-10-31 (yamamoto): M5 で次を決めた。保存すると EXIF（撮影日時・機種・位置情報など）は消えるのを受け入れ、保存の前にそう表示する（向きは見た目どおりに焼き込む。位置情報が消えるのは、公開サイトでは利点にもなる）。保存の既定は別名で、上書きは選んだときだけ確認を出す（元の画像は取り戻せないため）。編集できる形式は jpg・png だけにする（gif は書き出すと png になり拡張子と中身が食い違い、webp はブラウザによって書き出せず既定の許可リストにも無いため）。`image_limit_width` は編集した画像には当てない（編集で指定した大きさを保つため）。あわせて、書き出しの長い辺は 4096px まで・元より大きくする拡大はしない・保存するファイルにも `upload_maxsize` を当てる・同じオリジンの画像だけ編集できる・別名で保存したときだけ `OnFileBrowserUpload` を発火する・トリミング範囲は描き直しと数値欄で指定する（端をつまむ操作は付けない）・保存は新しい操作 `saveImage` で行う、とする。編集のダイアログには、フォーカスと Esc の扱いをまとめた `js/overlay.js` を作り、プレビューと移動のダイアログへの適用は #984 で行う。

2026-10-31 (yamamoto/Claude): M5 の自己レビューで、次を決めた。上書き保存は、対象がシンボリックリンクなら断る（確かめた名前と書き換える実体が食い違わないように。一覧に見えるファイルそのものだけを上書きできる）。上書きでは、元のファイルのパーミッションを引き継ぐ（FTP と共同で編集しているサイトで、上書きの後に FTP から書き換えられなくならないように。所有者は PHP の利用者に変わる）。受け取った画像の中身は `getimagesize()` で種類と寸法を確かめるだけで、GD で読み直して書き出すことはしない（画質がもう一度落ちるため。拡張子を jpg・png に限っているので、アップロードと同じ水準とする）。画素の数が多すぎる画像には「編集」を出さない（ブラウザのメモリのため）。上書きの途中で異常終了したときの一時ファイル（`.evo-save-*`。一覧には出ず、0600）の残りは受け入れる。別名で保存したときは `OnFileBrowserUpload` を発火するので、アップロードと同じくプラグインの処理（縮小など）を受ける。なお `OnFileBrowserUpload` はイベントの一覧（`system_eventnames`）に登録されておらず、素の環境ではプラグインを付けられない（M5 より前からの状態）。

2026-10-17 (yamamoto): 画像編集は Cropper.js を使わず自作する（Issue #678 のコメント）。パッケージを小さく保つため。書き出しは canvas の `drawImage` で行い、トリミング範囲のドラッグは `js/marquee.js` の矩形選択の考え方を流用する。自由な角度の回転とズームは作らない。

## Outcomes & Retrospective

2026-10-31: M1〜M5 をすべて終えた。ファイルブラウザは、管理画面の言語での表示、右クリックメニュー、コピー・切り取り・貼り付け、選んだファイルのダウンロード（2 件以上は zip）、画像のトリミング・回転・リサイズができるようになった。PR は #965（M1〜M3）、#993（M4）、M5 の PR の 3 つ。途中で、削除の失敗が表示されない不具合（#969、#988 で修正）も直した。

教訓は 3 つある。1 つ目は、ファイルを読み書きする操作を足すたびに、シンボリックリンクの扱いの穴が見つかったこと（コピー元の実体、削除でリンクの先へ入る、上書きでリンクの先を置き換える）。ファイルの操作を足すときは、最初から `resolveFile()` と「リンクをたどらない」を前提にするのがよい。2 つ目は、PHP の警告が管理画面のエラーハンドラーに拾われて HTML の 500 になり、JSON のエラーに届かない作りが、何度も問題になったこと（削除・一覧の壊れたリンク・コピー）。API ではファイル操作の戻り値を確かめ、警告で止まらないようにそろえるとよい。3 つ目は、当初は 1 段階 1 PR の予定だったのに、M1〜M3 を 1 つの PR にまとめたため、差分が大きくなったこと。M4 からは 1 段階 1 PR に戻した。

残した課題は Issue にした。エラー表示の統一（#983）、プレビュー・移動のダイアログのフォーカス（#984。M5 の `js/overlay.js` を使う）、パーミッションの計算の残り（#985）。

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

`js/contextmenu.js` を新しく作り、一覧のファイルやフォルダ、何もない所を右クリックしたときにメニューを出す（左のツリーは扱わない。Decision Log を参照）。項目は、ファイルなら「選択」「プレビュー」「名前の変更」「移動」「削除」。フォルダなら「開く」「名前の変更」「削除」。何もない所なら「新規フォルダ」「アップロード」。それぞれ `filebrowser.js` にある今の処理を呼ぶ。複数を選んでいる状態で右クリックしたときは、選択中のすべてを対象にする（一括の移動・削除と同じ）。Esc、メニューの外のクリック、スクロールで閉じる。キーボードの上下キーで項目を移れるようにする。文言は M1 の仕組みで言語ファイルに足す。

### M3: コピー・切り取り・貼り付け

API に `copy`（`type`、`folder`、`files[]`、`dest`）を足す。同じ名前のファイルが貼り付け先にあるときは、アップロードと同じく `名前(1).拡張子` のように連番を付ける（`fbActionUpload()` の連番を探す処理を関数に切り出して共用する）。フォルダのコピーは、この段階では扱わない（移動も今はファイルだけのため）。切り取りして貼り付けるときは、今ある `move` を使う。クリップボード（コピーや切り取りしたものを一時的に覚えておく所）は JS のメモリに持ち、フォルダを移っても残す。モーダルを閉じたら消える。右クリックメニューと選択バーに「コピー」「切り取り」「貼り付け」を足し、Ctrl+C / Ctrl+X / Ctrl+V（Mac は Cmd）でも操作できるようにする。入力欄で文字を打っているときは、ショートカットを横取りしない。

### M4: zip での一括ダウンロード

API に `download`（POST。`type`、`folder`、`files[]`。CSRF トークンを確かめる）を足す。選んだファイルを `temp/` の一時ファイルに zip でまとめ、`Content-Disposition: attachment` で送り、送り終えたら消す。1 ファイルだけのときは zip にせず、そのファイルを送る。PHP の ZipArchive（zip を作るための PHP の拡張）が無い環境では、`browser.php` から JS にそのことを伝え、ボタンとメニュー項目を出さない。ファイル数とサイズの上限は、この段階に着手するときに決めて Decision Log に書く。

### M5: 画像の簡易編集

プレビューに「編集」を足し、トリミング、90° 回転（左右）、数値（幅・高さ。縦横比を保つかどうかを選べる）でのリサイズができるようにする。外部ライブラリは使わず、canvas の `drawImage` で書き出す。保存は API に `saveImage`（POST。CSRF トークンを確かめる）を足し、上書きか別名かを選べるようにする。出力の形式は元の拡張子に合わせる（jpg なら `image/jpeg`。png のままで jpg に上書きしない）。保存したら、`temp/thumbs/` の古いサムネイルが使われないようにする。canvas で書き出すと EXIF（写真の向きや撮影の情報）が消える。消えることを受け入れるかどうかは、この段階に着手するときに決めて Decision Log に書く。

## Concrete Steps

M1〜M5 の手順を書く。

### M1

1. 文言の洗い出し。コメント以外で日本語が書かれている所を一覧にする。

        grep -nP "[ぁ-んァ-ヶ一-龠]" manager/media/browser/evo/*.php manager/media/browser/evo/*.js manager/media/browser/evo/js/*.js manager/media/browser/evo/lib/*.php | grep -vP "^\S+:\d+:\s*(//|\*|/\*)"

   期待する結果は、`browser.php`、`api.php`、`filebrowser.js`、`js/grid.js`・`js/tree.js`・`js/dialogs.js`・`js/upload.js` の文言が並ぶこと。`lib/*.php` に出るのはコメントだけのはず。

2. 言語ファイル。`manager/includes/lang/english.inc.php` と `japanese-utf8.inc.php` の配列の末尾に、`fb_` のキーを同じ順で足す。英語の文言は短く書く。

3. `browser.php`。`$labels` と HTML の文言を `lang()` と `hsc()` に置き換える。`$jsConfig['lang']` に、JS で使うキーの配列を入れる。キーの一覧は `browser.php` に配列で 1 か所にまとめて持つ。

4. `js/i18n.js` を作り、`filebrowser.js` と `js/*.js` の文字列を `t()` に置き換える。

5. `api.php`。`fbError()` と、`errors[]` の `message` を `lang('fb_err_…')` に置き換える。

6. 確認（「Validation and Acceptance」の手順）。

### M2

1. `manager/media/browser/evo/js/contextmenu.js` を作る。`createContextMenu({ rootEl, scrollEl })` で、メニューの DOM を 1 つだけ作って使い回し、`open(x, y, items, label)`（`label` は読み上げ用の `aria-label`）と `close()` を持たせる。`scrollEl` は、スクロールしたら閉じる対象の一覧（`dropzoneEl`）。項目は `{ label, onSelect, danger }` の配列で受け取り、メニューは中身を知らない。DOM は `role="menu"`、項目は `role="menuitem"` の `<button>` にし、文字は `textContent` で入れる（`innerHTML` を使わない）。

2. `filebrowser.js` で一覧（`gridEl`）の `contextmenu` イベントを受け、`preventDefault()` してから、右クリックした所で項目を決める。
   - ファイルの上: そのファイルが選択に入っていなければ、選択をそのファイルだけにする（エクスプローラーと同じ）。選択が 1 件なら「選択」（項目名は既存の `fb_pick_this_file`）「プレビュー」「名前の変更」「移動」「削除」。2 件以上なら「移動」「削除」だけにし、選択バーのボタンと同じ処理を呼ぶ。
   - フォルダの上: 「開く」「名前の変更」「削除」。
   - 何も無い所: 「新規フォルダ」「アップロード」。
   - 各項目は、今ある `pickFile`・`preview.open`・`renameEntry`・`moveDialog.open`・`deleteEntry`・一括削除・`navigateTo`・新規フォルダとアップロードのボタンの処理を呼ぶ。同じ処理を書き直さない（必要なら、ボタンのハンドラーを名前の付いた関数に切り出して共用する）。

3. 閉じ方と操作。Esc、メニューの外のクリック、一覧のスクロール、ウィンドウのリサイズ、モーダルを閉じたとき（`evoshell:modalclose`）に閉じる。開いたら最初の項目にフォーカスし、上下キーで移り、Enter と Space で選ぶ。Esc・Tab・項目を選んで閉じたときは、開く前にフォーカスのあった要素へ戻す（スクロールさせない）。スクロール・外のクリック・リサイズで閉じたときは戻さない。スクロールで閉じるのは、一覧のスクロール位置が実際に変わったときだけにする。画面の端からはみ出すときは、内側に寄せて出す。キーボードのメニューキーと Shift+F10 でも `contextmenu` イベントが来るので、そのときはフォーカスのある項目の位置に出す。

4. 見た目は `filebrowser.css` に足す。色・余白・枠線は、今の `filebrowser.css` と同じく `var(--color-…, 既定値)` のテーマの変数で書く。削除の項目は、一覧の削除ボタンと同じ「危険」の色にする。

5. 文言。項目名は既存のキー（`delete`・`move`・`preview` など）か、M1 で足した `fb_` のキー（`fb_new_folder`・`fb_upload`・`fb_rename` など）を使い、足りないものだけ言語ファイルに `fb_` で足す。JS で使うキーは `browser.php` の `$jsLangKeys` にも足す。

6. 確認（「Validation and Acceptance」の M2 の手順）。

### M3

1. `api.php` の `fbActionUpload()` にある「空いている名前を探す」処理（`名前(1).拡張子` のように連番を付け、候補ごとに `$modx->sanitizeUploadedFilename()` をかける）を、`fbFindFreePath($modx, $dirReal, $safeName)` のような関数に切り出す。見つからなければ `null` を返す。upload はこの関数を呼ぶ形にし、動きは変えない。

2. `api.php` に `copy`（POST。`type`・`folder`・`files[]`・`dest`）を足す。`checkCsrfToken()` を通してから呼ぶ。
   - `folder` と `dest` は、ほかの操作と同じく `$resolver->resolveDir()` で解決し、無ければ `not_found`。`dest` が `folder` と同じでもよい（同じフォルダに複製すると連番の名前になる）。
   - 各ファイル名は `move` と同じく `PathResolver::sanitizeSegment()` で 1 要素に直し、`is_file()` で確かめる。フォルダは扱わない（`errors` に入れる）。
   - コピー先の名前は、元の名前に `$config->isAllowedFilename($type, …)` をかけてから（rename・upload と同じ許可リスト）、1 の関数で空いている名前を探す。
   - `copy()` で複製し、`new_file_permissions` の設定で `chmod` する（`move_uploaded_file()` と同じ設定を使う）。
   - 返り値は `{copied:[{from, name}], errors:[{name, message}]}`。エラーの文言は言語ファイルに `fb_err_…` で足す。
   - 新しいイベントは足さない（Interfaces and Dependencies のとおり）。ファイルの先頭のコメントにある API の一覧に `copy` を書き足す。

3. `filebrowser.js` にクリップボードを持たせる。`{ mode: 'copy' | 'cut', folder, names }` をメモリに持ち、フォルダを移っても残す。モーダルを閉じたら消える（ページの状態ごと消えるので、特別な処理は要らない）。
   - コピー・切り取り: 選択中のファイル（フォルダは除く）をクリップボードに入れる。
   - 貼り付け: 今のフォルダへ貼る。フォルダの上の右クリックメニューから貼るときは、そのフォルダへ貼る。切り取りなら今ある `moveFilesTo(dest, names, sourceFolder)` を呼び、成功したらクリップボードを空にする（元のフォルダと同じ所へは貼れない）。コピーなら API の `copy` を呼び、クリップボードは残す（何度でも貼れる）。`errors` があれば、移動と同じ形でまとめて表示する。
   - 切り取り中のファイルは、一覧で薄く表示する（`filebrowser.css` にクラスを足す）。

4. 操作の入口。
   - 選択バーに「コピー」「切り取り」を足す。ツールバーに「貼り付け」を足し、クリップボードが空のときは隠す。
   - 右クリックメニュー: ファイルに「コピー」「切り取り」、フォルダと何も無い所に「貼り付け」（クリップボードが空なら出さない）。
   - キーボード: Ctrl+C / Ctrl+X / Ctrl+V（Mac は Cmd）。ファイルブラウザ（`#evoFileBrowser`）の中にフォーカスがあるときだけ受け、入力欄（`input`・`textarea`・`select`・`contenteditable`）で打っているときは横取りしない。

5. 文言は、既存のキーで足りなければ `fb_copy`・`fb_cut`・`fb_paste` などを言語ファイルに足し、JS で使うものは `$jsLangKeys` にも足す。

6. 確認（「Validation and Acceptance」の M3 の手順）。

### M4

1. `manager/media/browser/evo/lib/BrowserConfig.php` に `canCreateZip()`（中身は `extension_loaded('zip')`。旧ファイル管理の `files.dynamic.php` の判定と同じ）と、上限の定数（件数 200、合計 256MB）を足す。設定項目は増やさない。

2. `manager/media/browser/evo/lib/ZipService.php` を新しく作る（`ThumbnailService` と同じ位置づけ）。一時ファイルは `MODX_BASE_PATH . 'temp/zip/'` に `tempnam()` で作り、`ZipArchive::OVERWRITE` で開く。各 `addFile()` と `close()` の戻り値を確かめ、1 件でも失敗すれば一時ファイルを消して失敗を返す（存在しないファイルを `addFile()` すると、戻り値を見ないと中身の無い zip ができるため）。エントリ名はファイル名だけ（フォルダを入れない）で、UTF-8 の原名のまま（libzip が UTF-8 の印を付ける）。同じ名前は呼び出し側で重複を除く。圧縮済みの拡張子（jpg・jpeg・png・gif・webp・mp4・zip など）は `setCompressionName(…, ZipArchive::CM_STORE)` にする。古い一時ファイルの掃除は、`ThumbnailService` のごみ集め（確率で古いファイルを消す）と同じ作りにする。

3. `manager/media/browser/evo/api.php` に `download`（POST。`type`・`folder`・`files[]`）を足す。`checkCsrfToken()` に加えて `is_post()` で POST だけを受ける（`csrf_token` は GET でも受け取れてしまうため）。手順は次の順に行う。
   - `folder` を `resolveDir()` で解決する。
   - `files[]` の重複を除き、件数の上限を確かめる。
   - 各名前を `PathResolver::sanitizeSegment()` で 1 要素にし、ドットで始まる名前を除き、許可された拡張子（`BrowserConfig::allowedExtensions()`）だけにする。一覧に出ないファイル（`.php` など）を取り出させないため。
   - `$resolver->resolveFile()` で実体のパスを確かめ（許可された場所の外を指すシンボリックリンクは拒否）、`is_readable()` も確かめる。名前の文字コードが UTF-8 でないものは `fbNotFoundMessage()` と同じく `bad_encoding` で拒否する。
   - 合計サイズの上限を確かめる。
   - 1 つでも満たさないものがあれば、全体を拒否する（中身が黙って欠けた zip を作らない）。
   - 1 件だけなら zip にせず、そのファイルを `readfile()` で送る。ファイル名は日本語を含みうるので、`Content-Disposition` に `filename*=UTF-8''…`（RFC 5987）を付ける。
   - 2 件以上なら `ZipService` で作って送る。zip の名前は `{type}-{YYYYMMDD-HHMMSS}.zip`（英数字だけ）にし、`filename=` だけで足りるようにする。`Content-Type`・`Content-Length` を付け、送り終えたら（途中で切れても）`register_shutdown_function()` で一時ファイルを消す。
   - zip が使えない環境で 2 件以上を求められたら、`fbError('zip_unavailable', …, 501)` を返す。そのほかのエラーも `fbError()` で JSON を返し、文言は言語ファイルに `fb_err_…` で足す。

4. `manager/media/browser/evo/browser.php` の `$jsConfig` に `zip`（`canCreateZip()` の結果）を入れる。選択バーに「ダウンロード」を足す（zip が使えない環境では、2 件以上を選んでいるときは出さない）。右クリックメニューのファイルの項目にも「ダウンロード」を足す。文言は言語ファイルに `fb_` で足し、JS で使うものは `$jsLangKeys` にも足す。

5. `manager/media/browser/evo/js/download.js` を新しく作る。`FormData`（`csrf_token` を含む）で POST し、応答の `Content-Type` が JSON ならエラーとして表示し、そうでなければ `res.blob()` を `a.download` で保存させる。保存する名前は `Content-Disposition` から取る（`filename*=` を先に見る）。作っている間は「準備中」を表示する。`filebrowser.js` は配線だけにする。ポップアップ（単独のページ）でも動くよう、管理画面の `shell.js` には頼らない。

6. 確認（「Validation and Acceptance」の M4 の手順）。

### M5

1. `manager/media/browser/evo/lib/ThumbnailService.php` に `forget($sourceFile)` を足す。キャッシュのキーは `md5(絶対パス)_更新時刻` なので、同じ元ファイルのキャッシュを、今ある `cleanupStale()` の先頭一致の処理で消す（キーの作りを知っているのはこのクラスだけにする）。

2. `manager/includes/document.parser.class.inc.php` の `move_uploaded_file()` に、既定が `true` の任意の引数（例: `$resize = true`）を足し、`false` のときは `image_limit_width` による縮小（`resizeImage()`）を行わない。今ある呼び出しは変えない。

3. `manager/media/browser/evo/api.php` に `saveImage`（POST。`checkCsrfToken()`）を足す。入力は `type`・`folder`・`image`（`$_FILES` の 1 ファイル）・`mode`（`copy` か `overwrite`）・`target`（元のファイル名）。
   - 編集できる拡張子は jpg・jpeg・png だけ。`BrowserConfig` に 1 か所で持ち（例: `EDITABLE_EXTENSIONS`）、画面にも `$jsConfig` で渡す。`target` の拡張子が許可された拡張子（`isAllowedFilename()`）で、かつ編集できる拡張子であることを確かめる。
   - `target` は `rename` と同じく、既にある名前として `PathResolver::sanitizeSegment()` で 1 要素に直し、`resolveFile()` で実体を確かめる（`sanitizeFilename()` を通すと、FTP で置いた名前が別の名前になるため）。
   - 受け取った画像は `getimagesize()` で確かめ、種類（`IMAGETYPE_JPEG`・`IMAGETYPE_PNG`）が `target` の拡張子と一致すること、長い辺が 4096px 以下であること、大きさが `upload_maxsize`（`BrowserConfig::maxUploadSize()`）以下であることを確かめる。画面の判定は信用しない。
   - `copy` のときは、M3 の `fbFindFreePath()` で `名前(1).拡張子` のような空いている名前を探し、2 の引数で縮小せずに `$modx->move_uploaded_file()` で保存する。新しいファイルなので、アップロードと同じく `OnFileBrowserUpload` を発火する。
   - `overwrite` のときは、`target` の実体のパスへ保存する。`move_uploaded_file()` は保存先の名前を無害化しなおすので、名前が変わらないことを確かめてから使うか、`move_uploaded_file()`（PHP の関数）で一時ファイルに受けてから `rename()` で置き換える。イベントは発火しない。パーミッションは `newFilePermissions()` にする。
   - 保存したら 1 の `forget()` で古いサムネイルを消す。
   - 返り値は `{name}`（保存した名前）。エラーは `fbError()` で、文言は言語ファイルに `fb_err_…` で足す。PHP の警告で HTML の 500 にならないようにする（M3・#969 と同じ）。

4. `manager/media/browser/evo/js/overlay.js` を新しく作る。ダイアログを開く・閉じる共通の関数（例: `openOverlay(overlayEl, panelEl, initialEl)` が `close(restoreFocus)` を返す）を置く。開いたら、開く前にフォーカスのあった要素を覚えて中の最初の要素へフォーカスし、document の keydown を capture で受けて、Esc はダイアログだけを閉じ（`stopPropagation` で管理画面の `shell.js` に届かせない）、Tab は中で循環させる。閉じたら覚えた要素へ `focus({ preventScroll: true })` で戻す。フォーカスを戻す処理は、右クリックメニュー（`js/contextmenu.js`）と共通の小さな関数にまとめる。プレビューと移動のダイアログへの適用は #984 で行う（M5 では編集のダイアログだけに使う）。

5. `manager/media/browser/evo/js/imageEditor.js` を新しく作る（`createImageEditor(ctx)`）。
   - 編集のダイアログは、`browser.php` の既存の `.evo-fb-overlay` と同じ作りで、プレビューの後ろに置く。`role="dialog"`・`aria-modal="true"`・見出しの `aria-labelledby` を付ける。背景のクリックでは閉じない（編集中の内容が消えるため）。
   - 中身: 画像の表示、トリミング範囲（マウスのドラッグで描き直す。`js/marquee.js` と同じ座標の換算の考え方）、トリミングの x・y・幅・高さの数値欄、左右 90° の回転のボタン、幅・高さの数値欄（縦横比を保つかのチェック。元より大きくはできない）、保存のしかた（別名が既定、上書きは選んだときだけ確認を出す）、「撮影情報は消えます」の注意、キャンセルと保存。
   - 書き出しは、回転 → トリミング → リサイズ の順に canvas の `drawImage` で行う。座標の基準は回転後の作業用の canvas の幅・高さにする（`naturalWidth` をそのまま使わない）。`imageSmoothingQuality = 'high'` にする。
   - `toBlob()` の形式は元の拡張子に合わせる（jpg は `image/jpeg` で品質 0.92、png は `image/png`）。保存の前に `blob.type` が求めた形式か確かめる。
   - 保存は `FormData`（`csrf_token` を含む）で `saveImage` に送り、成功したら一覧を読み直し、保存した名前を選択する。
   - 画像が管理画面と同じオリジンでない（`rb_base_url` に別のホストが入っている）ときは、canvas から書き出せないので、編集のボタンを出さない。

6. 入口。プレビューに「編集」のボタンを足す（編集できる拡張子のときだけ）。右クリックメニューのファイル 1 件の項目にも「編集」を足す。`js/dialogs.js` の `createPreview` に `onEdit` を足し、`filebrowser.js` で結線する。

7. 本体の `move_uploaded_file()` に足す引数は既定が `true` なので、今ある呼び出し（旧ファイル管理・マネージャー API・アップロード）の動きは変わらない。この関数は DocumentParser の `executeParser()`・`prepareResponse()`・`parseDocumentSource()`・`postProcess()` のどこからも呼ばれず、ページの描画の流れにもキャッシュにも影響しない。

8. サムネイルとプレビューの URL に、一覧の `mtime` を `&v=<mtime>` として付ける（`js/grid.js` の `thumbSrc()` と、プレビューの画像の URL）。上書きした後に、ブラウザのキャッシュで古い画像が出ないようにするため。

9. 文言は言語ファイルに `fb_` で足し（`fb_edit`・`fb_edit_rotate_left` など）、JS で使うものは `$jsLangKeys` にも足す。既存のキー（`cancel`・`save` など）で足りるものは既存を使う。

10. 確認（「Validation and Acceptance」の M5 の手順）。
PHP の構文は、検証用イメージと、zip 拡張を入れた `php:7.4-cli`（`libzip-dev` を入れて `docker-php-ext-install zip`）の両方で確かめ、7.4 でも実際に zip ができることを確かめる。

## Validation and Acceptance

確認は Docker の検証環境で行う（`compose.yml`。手順は `adaptive-validation-runner` スキル）。

M1 は次をすべて満たせば完了とする。

- Concrete Steps 1 の grep で、コメントでない日本語が `manager/media/browser/evo/` に 1 件も出ない
- 検証用イメージと `php:7.4-cli` の両方で、変えた PHP ファイルに `php -l` をかけると `No syntax errors detected` が出る（main は PHP 7.4 以上で動く必要があるため）
- 管理画面の言語が日本語のとき、ファイルブラウザ（モーダルとポップアップの両方）の見た目と文言が、変更前と同じ
- 管理画面の言語を英語にすると、見出し、ツールバー、選択バー（`2 selected` など）、移動ダイアログ、プレビュー、空のフォルダの案内、アップロードの進み具合、削除の確認、名前の変更の入力欄がすべて英語で出る。Playwright で画面写真を撮って確かめる
- ログインした状態で `api.php?action=foo&type=images` を curl で呼ぶ。英語にしたときは `{"error":{"code":"invalid_action","message":"<英語の文言>"}}` が返る。`code` は変わっていない
- 今ある操作（アップロード、新規フォルダ、名前の変更、移動、削除、TV の入力欄からのファイルの選択）が、日本語のときも英語のときもこれまでどおり動く

M2 は次をすべて満たせば完了とする。確認は M1 と同じく Docker の検証環境で、Playwright（ヘッドレス）で行う。

- ファイル・フォルダ・何も無い所を右クリックすると、Concrete Steps の M2 の 2 に書いた項目のメニューが出る。ブラウザ標準のメニューは出ない
- 各項目を選ぶと、ボタンやダブルクリックで操作したときと同じ結果になる（選択したパスの受け渡し、プレビュー、名前の変更、移動、削除、フォルダを開く、新規フォルダ、アップロードのファイル選択）
- 選択に入っていないファイルを右クリックすると、選択がそのファイルだけになる。2 件以上を選んだまま、その 1 つを右クリックすると、「移動」「削除」が選択中のすべてに効く
- Esc・外のクリック・スクロールで閉じる。上下キーと Enter で選べ、メニューキー（Shift+F10）でも開く。画面の右下の端で右クリックしても、メニューがはみ出さない
- モーダル（TV の入力欄などから開くもの）とポップアップの両方で、日本語と英語の画面のどちらでも動く。画面に言語のキーの名前が出ない
- 今ある操作（ボタン、ダブルクリック、ドラッグでの移動、矩形選択）がこれまでどおり動く

M3 は次をすべて満たせば完了とする。確認は Docker の検証環境で、Playwright（ヘッドレス）と curl で行う。

- ファイルをコピーして別のフォルダに貼ると、元のファイルは残り、貼り付け先に同じ名前の複製ができる。同じ名前があれば `名前(1).拡張子` になる。同じフォルダに貼っても連番の名前で複製される
- 切り取って貼ると、ファイルが移り、クリップボードは空になる。切り取り中のファイルは薄く表示される
- 選択バー・ツールバー・右クリックメニュー・Ctrl（Cmd）+C/X/V のどれからでも同じ結果になる。入力欄で文字を打っているときの Ctrl+C/V は、ふつうのコピー・貼り付けのまま
- 日本語名のファイルも、名前を保ったままコピー・切り取りできる
- curl で `copy` を呼ぶとき、CSRF トークンが無ければ拒否される。`files[]` や `dest` に `../` を入れても、許可された場所の外には書けない。許可されていない拡張子のファイルは複製されず、`errors` に入る
- 日本語と英語の画面で文言が合い、言語のキーの名前が画面に出ない。今ある操作（M1・M2 の確認項目）がこれまでどおり動く

M4 は次をすべて満たせば完了とする。確認は Docker の検証環境で、Playwright（ヘッドレス）と curl で行う。

- 2 件以上を選んでダウンロードすると zip が保存され、開くと中の件数が選んだ数と同じで、各ファイルの中身（sha256）が元と一致する。日本語のファイル名はそのまま入る（Linux の `unzip -l` で確かめる。Windows では確かめていないと記録する）
- 1 件だけなら zip にならず、元と同じ中身のファイルが元の名前（日本語を含む）で保存される
- zip 拡張の無い構成（公式の `php:8.4-apache` など）では、2 件以上のときのボタンと右クリックの項目が出ず、API に直接 POST すると `zip_unavailable`（501）が返る。1 件のダウンロードはできる
- 201 件や合計 256MB を超えると拒否され、`temp/zip/` に一時ファイルが残らない。ダウンロードの途中で切っても残らない
- GET、CSRF トークンが無い、`../`、許可されていない拡張子（`.php` など）、ドットで始まる名前、外を指すシンボリックリンク、文字コードが UTF-8 でない名前は、どれも拒否され、中身は返らない
- 日本語と英語の画面で文言が合い、言語のキーの名前が画面に出ない。モーダル・ポップアップ・TinyMCE のダイアログで動き、今ある操作（M1〜M3 と #969 の確認項目）がこれまでどおり動く

M5 は次をすべて満たせば完了とする。確認は Docker の検証環境で、Playwright（ヘッドレス）と curl で行う。

- jpg と png の画像を、トリミング・左右の回転・数値でのリサイズの組み合わせで編集して保存すると、画面で見たとおりの向きと範囲と大きさで保存される（`getimagesize()` で寸法を、画面写真で見た目を確かめる）。jpg は jpg、png は png のまま保存される
- 向きの情報（EXIF の Orientation）付きの JPEG を編集して保存すると、表示どおりの向きで保存され、Orientation は残らない
- 既定の「別名で保存」では `名前(1).jpg` のような名前で保存され、元のファイルは残る。上書きは確認の後に元のファイルが新しくなり、一覧のサムネイルとプレビューも新しい画像になる（読み直しても古い画像が出ない）
- `image_limit_width` を設定していても、編集した画像は縮まない。元より大きいリサイズと、長い辺が 4096px を超える書き出しはできない
- gif・webp・svg などでは編集のボタンが出ず、API に直接送っても断られる。png の中身を jpg の名前で保存しようとしても断られる。CSRF トークンが無い、`target` に `../` を含む、外を指すシンボリックリンクの `target` も断られる
- 編集のダイアログは、開くとフォーカスが中へ移り、Tab が中で循環し、Esc で編集のダイアログだけが閉じ（管理画面のモーダルは残る）、閉じると元の要素にフォーカスが戻る
- 日本語と英語の画面で文言が合い、言語のキーの名前が画面に出ない。モーダル・ポップアップ・TinyMCE のダイアログで動き、今ある操作（M1〜M4 と #969 の確認項目）がこれまでどおり動く

## Idempotence and Recovery

各段階は 1 つの PR で閉じる。途中で止まったときは、その段階のブランチを main に合わせ直してやり直せる。言語ファイルにキーを足す作業は、同じキーがすでにあれば足さない（`grep -n '"fb_' manager/includes/lang/english.inc.php` で確かめる）。M3 以降で API に操作を足すときも、今ある操作の動きは変えない。どの段階で止めても、ファイルブラウザは使える状態のままになる。

## Artifacts and Notes

- ブランチ: `feat/678-filebrowser-i18n`、PR: #965（M1〜M3、マージ済み）。M4 はブランチ `feat/678-filebrowser-zip`、PR: #993（マージ済み）。M5 はブランチ `feat/678-filebrowser-image-edit`（Decision Log の 2026-10-31）
- Issue: https://github.com/modxcms-jp/evolution-jp/issues/678 （2026-10-17 のコメントに画像編集の決定と、足りないもの（保存の API、拡張子、サムネイル、EXIF）の一覧がある）
- 前の計画: `.agent/plans/2026-07-11-file-browser-modal-rewrite.md`
- 参考: TinyMCE7 プラグインの画像編集 `assets/plugins/tinymce7/js/tinymce-cropper.js`（モーダルの組み立て方の参考にする。Cropper.js は使わない）

想定するコミットの分け方:

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
