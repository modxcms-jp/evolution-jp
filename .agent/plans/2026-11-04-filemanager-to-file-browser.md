# ExecPlan: 「ファイル管理」をファイルブラウザに置き換える

## Purpose / Big Picture

管理画面のメニューの「ファイル管理」（`index.php?a=31`）を開くと、画像を選ぶときと同じファイルブラウザが管理画面のメインの領域に表示され、サイトのファイルをドラッグ＆ドロップ・範囲選択・zip でのダウンロードなどで扱えるようにする。削除やアップロードの安全確認を 1 か所にまとめ、旧ファイル管理（`manager/actions/element/files.dynamic.php`）を削除して、同じ操作が 2 か所に別々にある状態をなくす。

Issue: #1046

## Progress

- [x] (2026-11-04) ExecPlan を作成（方針・マイルストーンの分け方・対象外はユーザーと合意済み）
- [ ] M1: 安全確認の共通化（`lib/PathGuard.php` を作り、旧ファイル管理がそれを使う）
- [ ] M2: 管理モード（`lib/BrowserScope.php`・`lib/BrowserView.php`、API の全操作で範囲と権限をサーバー側で決める）
- [ ] M3: テキストの表示・編集、新しいファイル、zip の展開、変更の記録
- [ ] M4: `a=31` でファイルブラウザを表示、リンクの置き換え、ツリーの開閉とイベントの入口、`files.dynamic.php` の削除と言語キーの整理

## Surprises & Discoveries

2026-11-04（計画時）: 旧ファイル管理のフォルダ削除（`mode=deletefolder`）は、指定したフォルダ自体が守る場所か・その配下かだけを確かめている。そのため、守る場所を中に含むフォルダ（`assets/plugins` を中に持つ `assets` など）は、`save_plugin` の権限が無い利用者でも中身ごと消せる。M1 で、中に守る場所を含むフォルダの削除を断るようにする。新しい API でもフォルダの名前の変更で同じことが起きうるので、M2 で同じ確認を通す。

2026-11-04（計画時）: 旧ファイル管理の `logFileChange()` は `logHandler::initAndWriteLog($string, '', '', '', $type, $filename)` を呼ぶ。第 4 引数の `$type`（`'upload'` など）は `writeToLog()` で `(int)` され、管理操作ログ（`manager_log`）の `action` は 0 になる。つまり今の記録は、どの画面の操作かが分からない形で残っている。

2026-11-04（計画時）: 旧ファイル管理の拡張子の一覧（`is_editable_file()` と一覧の表示用）は `'...htaccess' . config('alias_suffix')` と、区切りの `,` を挟まずに設定をつないでいる。設定 `alias_suffix` は本体のどこにも無いので、常に空の文字列がつながるだけで、実害は無い。新しい一覧には持ち込まない。

2026-11-04（計画時）: 旧ファイル管理のテキストの保存は、`<form>` の送信で `textarea` の中身を送る。HTML の仕様では、フォームの送信で改行が CRLF にそろえられるため、LF のファイルを保存すると CRLF に変わるはず（要確認。M3 で main の旧ファイル管理を使って確かめ、結果をここに書く）。ファイルブラウザの `js/api.js` の `postForm()` は `URLSearchParams` で送るので、改行はそろえられない。

2026-11-04（計画時）: ファイルブラウザの API（`manager/media/browser/evo/api.php`）は、管理画面のセッション（またはウェブユーザーのセッションと設定 `rb_webuser`）と設定 `use_browser` だけを確かめ、ロールの権限（`hasPermission()`）は見ていない。守る場所の確認も無い。ピッカーのルート（`rb_base_dir`、既定は `content/`）が守る場所と重ならない前提で成り立っている。

2026-11-04（計画時）: 2026-07-11 のファイルブラウザ刷新の ExecPlan（`.agent/plans/2026-07-11-file-browser-modal-rewrite.md`）は全段階が完了しているが、`archive/` へ移されていない。この計画では動かさない。

## Decision Log

2026-11-04 (yamamoto): 方針として次を合意した。メニューの「ファイル管理」（`a=31`）は、ファイルブラウザをメインの領域に表示する。モーダル（`browser.php?modal=1`）は、投稿画面などでファイルを選ぶ用途（ピッカー）に残す。段階は 4 つ（安全確認の共通化 → 管理モード → テキストの表示・編集と zip の展開と変更の記録 → `a=31` の差し替え）。左のリソースツリーとの連携は対象外とし、フォルダツリーを畳めることと、選択・移動を JS から受け取れるイベントだけを用意する。古い作法の互換は残さず、`files.dynamic.php` は移し終えたら削除する。

2026-11-04 (yamamoto/Claude): 範囲の違い（ルート、扱う拡張子、サムネイル、隠しファイル、発火するイベント）を `lib/BrowserScope.php` の 1 つのクラスに集め、API の各操作は「今の範囲」に問い合わせる形にする。操作の関数を管理モード用に複製しない（SSOT）。範囲は `picker`（今のファイルブラウザ。ルートは `rb_base_dir` の下の `images` / `media` / `files`）と `manage`（ルートは `filemanager_path`、空ならサイトのルート）の 2 つ。代わりの案として、管理モード用に別の API ファイルを作る案も考えたが、同じ操作が 2 か所に分かれる今の問題を繰り返すので採らない。

2026-11-04 (yamamoto/Claude) 要相談: 管理モードを決める仕組み。案は、`a=31` の画面だけが、サーバーで作る JS の設定（`data-config`）に `scope: 'manage'` を入れ、JS（`js/api.js`）はすべての要求にこれを付ける。API は `scope=manage` を受けたら、その都度、管理画面のセッションで `file_manager` の権限があるかを確かめ、無ければ 403 で断る（ピッカーへは戻さない）。ピッカーの画面（`browser.php`）は、URL に何を付けても `scope` を設定に入れない。画面の切り替えは URL の値ではできず、API に手で `scope=manage` を付けても、権限のある人が `a=31` でできることしかできない。代わりの案は 2 つ。(a) `a=31` を開いたときにセッションへ使い捨ての合言葉を置き、API はそれと照合する。権限のある人が自分で付ける場合と比べて守れるものが増えず、タブを複数開いたときの扱いが増える。(b) 管理モード用の入口を別に作る。上の SSOT の判断と食い違う。案のとおりでよいか、着手前に確かめる。

2026-11-04 (yamamoto/Claude) 要相談: 管理モードでの拡張子の許可リスト。案は、旧ファイル管理と同じ水準を保つ。新しい名前のファイルを作る操作（アップロード、新しいファイル、zip の展開の各エントリ）は、`upload_files`・`upload_images`・`upload_media` を合わせた許可リスト（旧 `uploadablefiles()`）で確かめる。名前の変更は、拡張子が変わらないなら許し、変わるときは同じ許可リストで確かめる（旧ファイル管理には名前の変更が無く、`.php` のファイルの名前を変えられないと困るため）。一覧・表示・編集・削除・移動・コピー・ダウンロードは拡張子を問わない（コピーは元と同じ拡張子になる）。代わりの案は、管理モードでは許可リストを一切当てない（`file_manager` の権限を持つ人は `.php` を編集できるので、拡張子で止める意味は薄い）。案のとおりでよいか、着手前に確かめる。

2026-11-04 (yamamoto/Claude) 要相談: 変更の記録の置き場所。合意どおり、旧 `logFileChange()` の代わりに `evo()->logEvent()`（システムログ。`temp/logs/system/` の JSON Lines で、利用者の ID と IP は自動で入る）へ書く。そのため、管理操作ログ（`a=13`、`manager_log`）には個々のファイル操作が載らなくなる（`a=31` を開いたことは、今までどおり `manager/index.php` が「Using file manager」として記録する）。記録するのは成功した変更（アップロード、新しいフォルダ、新しいファイル、名前の変更、削除、移動、コピー、画像の保存、テキストの保存、zip の展開）で、表示（旧の「Viewing File」）は記録しない（一覧やプレビューのたびに増え、変更の追跡には要らないため）。ピッカーの範囲でも同じ関数で記録する（同じ操作なので、記録するかどうかを範囲で分けない）。この 3 点（管理操作ログに載らなくなること、表示を記録しないこと、ピッカーでも記録すること）でよいか、着手前に確かめる。

2026-11-04 (yamamoto/Claude): 管理モードのアップロードでは、旧ファイル管理と同じ `OnFileManagerUpload` を発火し、ピッカーでは今までどおり `OnFileBrowserUpload` を発火する。旧ファイル管理に付けたプラグインが動き続けるようにするため（AGENTS.md「既存フック位置を無視して新イベントを追加する」を避ける）。渡す値は `filepath`（保存先のフォルダの確かめた実パス）と `filename`。旧ファイル管理ではリクエストで受けたパスだったので、`assets/docs/events-and-plugins.md` にその違いを書く。

2026-11-04 (yamamoto/Claude): 管理モードは設定 `use_browser` に左右されない（`file_manager` の権限だけで決める）。旧ファイル管理は `use_browser` を見ていなかったため。ウェブユーザーのセッションは管理モードを使えない（`hasPermission()` は管理画面のセッションの権限しか見ない）。

2026-11-04 (yamamoto/Claude): 守る場所の判定（旧 `proteted_path()` と `is_protected_path()`）は、管理モードだけでなくピッカーにも当てる。判定を 1 か所に置いて両方が通る形にするため（SSOT）。ピッカーのルートは既定で守る場所と重ならないので、普通のサイトでは動きは変わらない。`rb_base_dir` をサイトのルートにしているサイトでは、権限によって `manager/` などがピッカーから見えなくなる。

2026-11-04 (yamamoto/Claude): 守る場所は一覧とツリーに出さない（旧ファイル管理は灰色で名前だけ出していた）。出すと、一覧・ツリー・移動のダイアログ・右クリックメニューのそれぞれで「開けない・操作できない項目」の扱いが要り、どれか 1 つの漏れが操作の入口になるため。API は一覧に出さないだけでなく、名前を指定された操作でも断る。

2026-11-04 (yamamoto/Claude): 管理モードの一覧は、サムネイルを作らない（`thumb` を返さず、一覧はファイルの種類のアイコンを出す。SVG も同じ）。`vendor` のように数の多いフォルダを開いたとき、`temp/thumbs/` へ大量に書き出さないため。画像の寸法（`getimagesize()`、ファイルの先頭だけを読む）は今までどおり返し、プレビューと画像の編集は使えるようにする。ドットで始まる名前（`.htaccess` など）も出す（旧ファイル管理と同じ）。

2026-11-04 (yamamoto/Claude): 管理モードのルートがサイトのルート（`MODX_BASE_PATH`）の外にあるときは、ファイルの公開 URL が無いので `url` を `null` にする。その場合、画像のプレビューは出さず、取り出しはダウンロード（API の `download`）で行う。

2026-11-04 (yamamoto/Claude): テキストの表示・編集は、ファイルブラウザの中のダイアログ（`js/overlay.js` を使う、画像の編集と同じ作り）で行う。中身は、エレメントの編集画面と同じコードの欄（`div.code-editor` の見出しと「行を折り返す」の切り替え、`textarea.phptextarea`）で出し、テーマの CSS をそのまま使う。対象の拡張子は旧 `is_editable_file()` と表示用の一覧を合わせたもの（`txt`、`php`、`tpl`、`shtml`、`html`、`htm`、`xml`、`js`、`css`、`pagecache`、`htaccess`、`sample`）にし、`BrowserConfig::TEXT_EXTENSIONS` の 1 か所に置く。`.htaccess` のような拡張子だけの名前は、名前全体を拡張子として照合する（旧と同じ）。`json`・`md` などは足さない（必要になったら、この定数に足すだけで済む）。

2026-11-04 (yamamoto/Claude): テキストとして開くのは 2MB（`BrowserConfig::TEXT_MAX_BYTES = 2097152`）までで、NUL を含むものは開かない（バイナリとみなす）。UTF-8 でない中身（Shift_JIS・EUC-JP など）は、旧ファイル管理と同じく変換して表示するが、保存はさせない（表示のみ）。変換した UTF-8 で書き戻すと、ファイルの文字コードが黙って変わるため。保存のときは、開いたときの中身のハッシュ（`sha1`）を送り、今のファイルと違えば 409 で断る（FTP などで同時に書き換えられたものを上書きしないため）。改行は、保存する前のファイルが CRLF なら CRLF に、そうでなければ LF にそろえて書く。

2026-11-04 (yamamoto/Claude): zip の展開は管理モードだけにし、旧 `unzip()` とその下請け（`unzip_entry_name()`・`unzip_entry_path()`・`unzip_unique_path()`・`unzip_free_name()`・`unzip_copy_entry()`）を `lib/ZipService.php` の `extract()` へ移す。動きは旧と同じ（全エントリを先に確かめ、ルートの外・守る場所へ書くものが 1 つでもあれば何も展開しない。許可リストに無い拡張子は飛ばす。展開先に前からあるファイルは上書きする。Shift_JIS の名前を UTF-8 にする）。新しいファイル（空のテキストファイルを作る）も管理モードだけにする。

2026-11-04 (yamamoto/Claude): `a=31` の画面は新しい `manager/actions/element/file_manager.static.php` に置き、`manager/index.php` の `case 31` からこれを読む。画面の HTML は `lib/BrowserView.php` にまとめ、`browser.php`（ピッカーのモーダルと単独のページ）と `a=31` の両方がこれを使う（今は `browser.php` に直に書かれている）。

2026-11-04 (yamamoto/Claude): フォルダツリーの開閉は、ツールバーのボタンで切り替え、状態を `localStorage` の `evoFbTree` に残す（表示の切り替え `evoFbView` と同じ作り）。ピッカーでも使える。外の JS 向けには、ファイルブラウザの要素（`#evoFileBrowser`）から、泡立つ（`bubbles: true`）`CustomEvent` を 2 つ出す。`evofb:navigate`（一覧を読み終えたとき。`detail` は `{ scope, folder }`）と `evofb:selectionchange`（選択が変わったとき。`detail` は `{ scope, folder, names }`）。外からファイルブラウザを操作する入口（フォルダを開かせるなど）は、リソースツリーとの連携を作るときに足す（今は使う所が無い）。

2026-11-04 (yamamoto/Claude): `a=31` の URL は `index.php?a=31&folder=<ルートからの相対パス>&select=<ファイル名>` にする（ピッカーの `initialFolder` / `initialSelect` と同じ）。旧の `mode=drill&path=<絶対パス>` は受け付けない（互換を残さない。付いていてもルートが開く）。

2026-11-04 (yamamoto/Claude): M1 は、旧ファイル管理の動きを変えない置き換え（関数を `PathGuard` へ移すだけ）を基本にし、変えるのは「中に守る場所を含むフォルダの削除を断る」の 1 点だけにする。ピッカーの API が `PathGuard` を使い始めるのは M2 で、範囲の導入と同時にする（M1 でピッカーの動きを変えない）。

## Outcomes & Retrospective

実装後に記載。

## Context and Orientation

パスはすべてリポジトリのルートからの相対。言葉の意味は次のとおり。

- ファイル管理: メニューの「ファイル管理」（`index.php?a=31`）。今は `manager/actions/element/files.dynamic.php` の 1 枚の PHP が、`mode` の値で一覧・表示・編集・アップロードなどを分け、HTML とインラインの JS を出している。対象は設定 `filemanager_path`（既定はサイトのルート `MODX_BASE_PATH`）の下で、ロールの権限 `file_manager` が要る。
- ファイルブラウザ: `manager/media/browser/evo/` にある、ファイルを選ぶための画面。JSON を返す API（`api.php`）と、ES モジュールに分けた JS（`filebrowser.js` と `js/*.js`）でできている。ルートは設定 `rb_base_dir`（既定は `content/`）の下の `images` / `media` / `files` のどれか（種類、`type`）で、種類ごとの拡張子の許可リスト（設定 `upload_images` / `upload_media` / `upload_files`）にあるファイルだけを扱う。
- ピッカー: ファイルブラウザを、テンプレート変数（TV）の画像の欄や TinyMCE などから「ファイルを選ぶ」ために開く使い方。管理画面のシェルのモーダル（EvoShell が `browser.php?modal=1` の HTML の断片を重ねて出す小窓）、ポップアップ、TinyMCE のダイアログの 3 通りがある。
- EvoShell: `manager/media/script/shell.js`。メニューとツリーを残したまま、メインの領域（`#mainPane`）だけを fetch で差し替える。サーバー（`manager/index.php`）は fetch の要求には header・footer を付けない断片を `X-Evo-Pane: 1` 付きで返す。断片の中の `<script>` は `executeScripts()` が順に動かす。ES モジュールは同じ URL を 2 度目には実行しないので、ファイルブラウザは断片の最後のインラインの `<script>` から `window.EvoFileBrowserInit()` を呼び直している。差し替えの前に `evoshell:unload`、モーダルを閉じたときに `evoshell:modalclose` のイベントが `document` に出る。
- 守る場所: 旧ファイル管理の `proteted_path()` が返す、権限によって触らせないパスの一覧。`manager()->isAdmin()`（`access_permissions` と `web_access_permissions` の両方の権限を持つ）なら空。そうでなければ `manager`、`temp/backup`、`assets/backup` と、権限が無いものに応じて `assets/plugins`（`save_plugin`）、`assets/snippets`（`save_snippet`）、`assets/templates`（`save_template`）、`assets/modules`（`save_module`）、キャッシュのディレクトリ（`empty_cache`）、`temp/import`・`assets/import`（`import_static`）、`temp/export`・`assets/export`（`export_static`）。照合は大文字小文字を区別しない。
- CSRF トークン: なりすましの送信を防ぐための合言葉。管理画面の POST は `manager/includes/accesscontrol.inc.php` が確かめ、GET で状態を変える操作は `manager/includes/csrf_token.php` の一覧（`a=31` は `mode` が `delete`・`deletefolder`・`newfolder`・`newfile`・`unzip` のとき）で確かめる。ファイルブラウザの API は、書き込みの操作で `checkCsrfToken()` を自分で呼ぶ。

旧ファイル管理の機能と、ファイルブラウザの今の状態、この計画で行うことの対応は次のとおり。

| 機能 | 旧ファイル管理（`files.dynamic.php`） | ファイルブラウザの今（`api.php` の action） | この計画 |
|---|---|---|---|
| 一覧・フォルダの移動 | `mode=drill`（既定）。`scandir()` で全件、ドットで始まる名前も出す。守る場所は灰色。件数・合計サイズ・書き込みの可否を出す | `list`。種類の許可リストの拡張子だけ、ドットで始まる名前は出さない。サムネイルと画像の寸法を返す | M2: 管理モードは全件・ドットで始まる名前も出し、サムネイルなし。守る場所は出さない。今のフォルダに書き込めるか（`writable`）を返す（両方の範囲） |
| 画像の表示 | jpg・gif・png・ico を画面の下の `img` に出す | プレビュー（`url` の画像） | 既存のプレビュー。ルートが公開の範囲の外なら出さない |
| ダウンロード | 許可リストの拡張子だけ、公開 URL への直のリンク | `download`（POST。1 件はそのまま、2 件以上は zip） | M2: 管理モードは拡張子を問わない |
| テキストの表示 | `mode=view`（`txt,php,tpl,html,htm,xml,js,css,pageCache,htaccess,sample`） | なし | M3: `readText` |
| テキストの編集 | `mode=save`（`is_editable_file()`、ファイルとフォルダに書き込めるとき） | なし | M3: `saveText` |
| アップロード | 1 件ずつ。合わせた許可リスト。`$modx->move_uploaded_file()`、`OnFileManagerUpload`、`logFileChange('upload')` | `upload`（複数）。種類の許可リスト。`OnFileBrowserUpload` | M2: 管理モードは合わせた許可リスト（要相談）と `OnFileManagerUpload` |
| 新しいフォルダ | `mode=newfolder`。`sanitizeFilename()`、`newFolderPermissions()` | `mkdir` | M2: 守る場所の確認を通す |
| 新しいファイル | `mode=newfile`。合わせた許可リスト、空のファイル、`newFilePermissions()` | なし | M3: `newfile`（管理モードだけ） |
| zip の展開 | `mode=unzip`（`unzip()`） | なし | M3: `unzip`（管理モードだけ） |
| ファイルの削除 | `mode=delete`（1 件）。`logFileChange('delete')` | `delete`（`files[]`） | M2: 守る場所の確認を通す |
| フォルダの削除 | `mode=deletefolder`（`rrmdir()`。ルートは消さない） | `delete`（`folders[]`。`fbDeleteTree()`、リンクはたどらない） | M1: 旧で中に守る場所を含むものを断る。M2: 新でも同じ |
| 名前の変更・移動・コピー | なし | `rename`・`move`・`copy` | M2: 管理モードでも使える。拡張子の扱いは要相談 |
| 画像の編集 | なし | `saveImage` | 管理モードでも使える |
| パーミッション | 新しいフォルダ・ファイルに設定の値を当てるだけ（変える画面は無い） | `mkdir`・`upload`・`copy` で同じ | 変えない |
| 変更の記録 | `logFileChange()`（表示・編集・アップロード・削除を `manager_log` へ。`action` は 0 になる） | 失敗だけ `evo()->logEvent()` | M3: 成功した変更を `evo()->logEvent()` |
| 権限 | `file_manager`。要求のすべてのパスを、絶対パスか・`..` が無いか・ルートの中か・守る場所でないかで照合 | 管理画面のセッション（またはウェブユーザーと `rb_webuser`）と `use_browser`。`PathResolver` が `realpath()` でルートの中かを確かめる | M2: 管理モードは要求のたびに `file_manager` を確かめる。守る場所は両方の範囲 |

安全確認に関わる今のコード:

- `manager/actions/element/files.dynamic.php` の 1150 行目付近から: `proteted_path()`、`is_protected_path()`、`normalize_path()`（存在する部分は `realpath()`、存在しない残りは文字列で `.` と `..` を畳む）、`is_absolute_local_path()`、`has_parent_reference()`、`filemanager_base_path()`、`is_filemanager_root()`、`is_within_filemanager_path()`、`uploadablefiles()`。ファイルの先頭（20〜57 行目）で、受け取ったパスをすべてこれらで照合している。`unzip()`（800 行目付近）も使う。
- `manager/media/browser/evo/lib/PathResolver.php`: `resolveDir($type, $folder)`（`rb_base_dir/<type>/<folder>` を `realpath()` で解決し、ベースの中か確かめる）、`resolveFile($dirReal, $name)`、`sanitizeSegment($value)`（`/`・`\`・NUL を含む名前と、ドットと空白だけの名前を拒む）。
- `manager/media/browser/evo/lib/BrowserConfig.php`: 種類と拡張子、`baseDir()`、`baseUrl()`、`urlPrefix()`、zip と画像の編集の定数。
- `manager/media/browser/evo/api.php`: 入口で認証と `use_browser` と `type` を確かめ、`action` で関数に分ける。書き込みは `checkCsrfToken()`、`download` と `saveImage` は POST だけ。警告で HTML の 500 にならないよう、ファイル操作は `fbAttempt()` で包む。

画面の表示に関わるコード:

- `manager/media/browser/evo/browser.php`: ピッカーの画面。HTML（ツールバー、選択バー、ツリー、一覧、ダイアログ）を文字列で組み立て、JS の設定（`apiUrl`、`type`、`csrfToken`、`initialFolder`、`initialSelect`、`zip`、画像の編集の上限、言語の文言）を `data-config` の JSON に入れる。`modal=1` なら断片（`X-Evo-Pane: 1`、`<h1>`、CSS、HTML、モジュールの `<script>`、`EvoFileBrowserInit()` を呼ぶインラインの `<script>`）、そうでなければ単独のページ。モーダルは `/manager/index.php` の上で動くので、`apiUrl` は `media/browser/evo/api.php` になる。
- `manager/media/browser/evo/filebrowser.js`: `init()` が `#evoFileBrowser` を探して状態を作り、各モジュールをつなぐ。選んだファイルの確定は `pickFile()`。
- `manager/media/browser/evo/js/tree.js`: 左のフォルダツリー（#1043 でフォルダだけを出す形になった。展開には `list` の `foldersOnly=1` を使う）。
- `manager/media/browser/evo/js/overlay.js`: ダイアログのフォーカスの閉じ込めと Esc（#984・#1017 で、プレビューと移動のダイアログにも使うようになった）。
- `manager/index.php` 705 行目: `case 31` が `files.dynamic.php` を読む。`a=31` は header・footer を付けるアクションの一覧（483・851 行目付近）に入っている。
- 編集画面のコードの欄の例: `manager/actions/element/mutate_snippet.dynamic.php` 172〜192 行目（`div.code-editor`、`code-editor-header`、`textarea.phptextarea`、`setTextWrap()`）。`setTextWrap()` は `mutate_plugin`・`mutate_module`・`mutate_snippet` に同じものが複製されている。

`a=31` を指しているもの（`grep -rn "a=31"` と `item(..., 31, ...)` の棚卸し）:

- `manager/frames/menu.php` 383 行目（メニュー）、`manager/actions/main/welcome.static.php` 128 行目と `manager/media/style/common/welcome.php` 92 行目（ダッシュボードのアイコン）: `a=31` を開くだけなので変えなくてよい。
- `manager/includes/lang/english.inc.php` 1204 行目と `japanese-utf8.inc.php` 1206 行目の `configcheck_mgr_tpl_msg`: `a=31&mode=drill&path=[+path+]assets/templates`。`manager/includes/config_check.inc.php` 199〜208 行目が `path` に `urlencode(MODX_BASE_PATH)` を入れている。M4 で置き換える。
- `japanese-utf8.inc.php` 355 行目の `filemanager_path_message` と 677 行目の `rb_message`: `a=31` へのリンクと、ファイル管理とファイルブラウザの違いの説明。リンクはそのまま使えるが、説明の文を M4 で今の形に合わせる。
- `manager/includes/csrf_token.php` 249 行目: `31 => ['mode' => [...]]`。M4 で消す。
- `manager/includes/actionlist.inc.php` 38 行目の `'31' => 'Using file manager'`: 管理操作ログの文言。そのまま使う。

関連する計画と Issue:

- `.agent/plans/2026-07-11-file-browser-modal-rewrite.md`: ファイルブラウザを作り直した計画。モーダルの断片と `EvoFileBrowserInit()` の作り、`temp/thumbs/` のサムネイル、`evo/.htaccess` の `Cache-Control: no-cache`（`js/` のモジュールにキャッシュバスターが付かないため）の経緯がある。
- `.agent/plans/archive/2026-11-01-file-browser-kcfinder-extension.md`: 多言語化（`fb_` の言語キーと `js/i18n.js`）、右クリックメニュー、コピー、zip のダウンロード（`lib/ZipService.php`）、画像の編集（`js/overlay.js`）。教訓として「ファイルを読み書きする操作を足すときは、最初から `resolveFile()` と『リンクをたどらない』を前提にする」「ファイル操作の戻り値を確かめ、警告で止まらないようにする（`fbAttempt()`）」がある。M2・M3 の操作はこれに従う。
- #1043（ツリーにはフォルダだけ）、#1041（選んだファイルまでスクロール）、#1017（移動のダイアログのフォーカス）: どれも閉じている。M4 のツリーの開閉は #1043 の形のツリーに足す。`a=31&select=` の選択は #1041 の `scrollToSelected()` で見える位置に出る。M3 のテキストのダイアログは #1017 と同じく `js/overlay.js` でフォーカスを扱う。
- `.agent/plans/2026-10-10-manager-input-and-csrf-hardening.md`: `a=31` のサブ操作を CSRF の一覧に入れた経緯。

DocumentParser の処理の流れ（`executeParser()` / `prepareResponse()` / `parseDocumentSource()` / `postProcess()`）には、どの段階も関わらない。管理画面とファイルブラウザの API だけを変える。キャッシュは、サイトのキャッシュ（`temp/cache/`）に関わらない。サムネイルのキャッシュ（`temp/thumbs/`）は、管理モードでは作らない。新しく足す `js/textEditor.js` には、ほかの `js/*.js` と同じくキャッシュバスターが付かないが、`manager/media/browser/evo/.htaccess` の `no-cache` で古いものが使われない。

## Plan of Work

### M1: 安全確認の共通化

`manager/media/browser/evo/lib/PathGuard.php` を新しく作り、旧ファイル管理の安全確認の関数をメソッドとして移す。中身（判定の仕方）は変えない。

- `__construct($baseDir, array $protectedPaths = [])`: ルート（旧 `filemanager_base_path()` と同じく `normalize()` してから末尾の `/` を除いて持つ）と守る場所の一覧。
- `static protectedPathsFor(DocumentParser $modx)`: 旧 `proteted_path()`。
- `static normalize($path)`: 旧 `normalize_path()`。
- `static isAbsoluteLocal($path)`: 旧 `is_absolute_local_path()`。
- `static hasParentReference($path)`: 旧 `has_parent_reference()`。
- `isWithinBase($path)`: 旧 `is_within_filemanager_path()`。
- `isBase($path)`: 旧 `is_filemanager_root()`。
- `isProtected($path)`: 旧 `is_protected_path()`。
- `containsProtected($path)`: 新しく足す。守る場所のどれかが `$path` の配下にあれば真（`normalize()` した上で、大文字小文字を区別せずに `$path . '/'` で始まるかを見る）。

`files.dynamic.php` は先頭で `require_once MODX_MANAGER_PATH . 'media/browser/evo/lib/PathGuard.php';` をし、`$guard = new PathGuard(config('filemanager_path') ?: MODX_BASE_PATH, PathGuard::protectedPathsFor(evo()));` を作って、移した関数の呼び出しをすべて `$guard` のメソッドに置き換える。`ls()` と `unzip()` は今 `proteted_path()` を自分で呼んでいるので、`global $guard` などで同じものを使う（`files.dynamic.php` は M4 で消すので、引数の形をきれいにすることにこだわらない）。移した関数の定義は消す。フォルダの削除（`mode=deletefolder`）では、`is_filemanager_root()` の確認の後に `$guard->containsProtected($folder)` を足し、真なら `file_folder_not_deleted` を出して消さない。

PHP 7.4 で動く書き方にする（引数と戻り値の型の宣言に `string|false` のような共用型を使わない、`str_starts_with()` を使わない）。

### M2: 管理モード

範囲の違いを `lib/BrowserScope.php` に集める。主なメソッドは次のとおり。

- `static fromRequest(DocumentParser $modx, BrowserConfig $config)`: `anyv('scope')` が `'manage'` なら、管理画面のセッション（`sessionv('mgrValidated')`）と `$modx->hasPermission('file_manager')` を確かめ、どちらかが無ければ `fbError('forbidden', lang('fb_err_manage_forbidden'), 403)`。ルートは `config('filemanager_path') ?: MODX_BASE_PATH`。`scope` が無いか `'picker'` なら今の確認（`use_browser`、`type` が `images` / `media` / `files` のどれか）を行い、ルートは `rb_base_dir`、最初の階層は `type`。`fbError()` は `api.php` の関数なので、`fromRequest()` は失敗の理由（コードと言語キーと状態）を返すか例外にし、`api.php` 側で `fbError()` にする（lib から `api.php` の関数を呼ばない）。
- `isManage()`、`type()`（ピッカーの種類。管理モードは `''`）、`baseDir()`、`guard()`（`PathGuard`。守る場所は `PathGuard::protectedPathsFor($modx)`）。
- `listsFile($name)`: 一覧に出すか。ピッカーは種類の許可リスト、管理モードはすべて。
- `showsHidden()`: ドットで始まる名前を出すか（管理モードだけ真）。
- `makesThumbnails()`: ピッカーだけ真。
- `isAllowedNewName($name)`: 新しい名前のファイルを作ってよい拡張子か。ピッカーは `BrowserConfig::isAllowedFilename($type, $name)`、管理モードは合わせた許可リスト（`BrowserConfig::allowedExtensionsAll()` を足す）。
- `isAllowedRename($oldName, $newName)`: ピッカーは今の `isAllowedFilename($type, $newName)`、管理モードは拡張子が同じなら真、違えば `isAllowedNewName($newName)`（要相談の決着に合わせる）。
- `fileUrl($absPath)` と `pickPath($rel)`: 公開 URL と SetUrl へ渡すパス。管理モードは `pickPath` を返さず（`null`）、`fileUrl` はルートが `MODX_BASE_PATH` の中のときだけ `site_url` からの URL、外なら `null`。
- `uploadEvent()`: `OnFileBrowserUpload` か `OnFileManagerUpload`。

`PathResolver` は `BrowserScope` から作る。`resolveDir($type, $folder)` の最初の階層は `$scope->type()` を渡し、`''` のときは足さない（管理モード）。ベースの中かの確認は、今の `strpos($real, $this->baseDir) !== 0` のまま（`realpath()` で解決した実パスの前方一致。`PathGuard::isWithinBase()` と同じ考え方）にし、守る場所の確認（`PathGuard::isProtected()`）を `resolveDir()` と `resolveFile()` に足す。新しく `resolveNewChild($dirReal, $name)` を足し、作る・名前を変える・移す先のパスが守る場所でないかを確かめて、使えるならフルパス、使えなければ `null` を返す。

`api.php` は、入口の確認（セッション → 範囲 → `PathResolver`）を `BrowserScope::fromRequest()` に置き換え、各 `fbAction*()` は `$type` の代わりに `$scope` を受け取る。各操作で直すことは次のとおり。

- `list`: `listsFile()`・`showsHidden()`・`makesThumbnails()` で分ける。守る場所のフォルダは出さない（`fbDirHasSubdirs()` の数え方も同じ）。応答に `writable`（今のフォルダに `is_writable()`）を足す。管理モードでは `path` を返さない。
- `upload`: 拡張子は `isAllowedNewName()`、保存先は `resolveNewChild()`、イベントは `uploadEvent()`。
- `mkdir`: 保存先を `resolveNewChild()` で確かめる。
- `rename`: 拡張子は `isAllowedRename()`、変更後のパスは `resolveNewChild()`、フォルダなら `guard()->containsProtected()` が真のとき断る。
- `delete`: 名前ごとに守る場所でないかを確かめ、フォルダは `containsProtected()` も確かめる。
- `move`・`copy`: 移す先・複製先を `resolveNewChild()` で確かめる。`copy` の拡張子は、管理モードでは確かめない（元と同じ拡張子のため）。
- `download`: 管理モードでは拡張子とドットで始まる名前を問わない。
- `saveImage`: 拡張子の確認を `isAllowedNewName()` にそろえる（別名の保存は新しい名前のファイルを作るため）。
- エラーの文言を足す（`fb_err_manage_forbidden`、`fb_err_protected`）。英語と日本語の言語ファイルに足す。

画面の HTML を `lib/BrowserView.php` へ移す。`BrowserView::markup(array $jsConfig)` がファイルブラウザの HTML（今の `$markup`）を返し、`BrowserView::jsConfig(DocumentParser $modx, BrowserConfig $config, array $options)` が JS の設定を作る（`$options` は `scope`、`type`、`apiUrl`、`initialFolder`、`initialSelect`、`rootLabel`）。JS で使う言語キーの一覧もここへ移す。モーダルの断片と単独のページの外側（今の `browser.php` の後半）は `BrowserView::modalFragment()` と `BrowserView::standalonePage()` にする。`browser.php` はこれらを使うだけにし、`scope` を設定に入れない。管理モードの画面を作る関数（`BrowserView::managePane()`。断片の `<h1>`・CSS・HTML・`<script>` を返す）もここに置くが、`a=31` から使うのは M4。

JS は、`js/api.js` の `apiUrl()` と `postForm()` が `config.scope` を見て、あれば `scope` を必ず付け、管理モードでは `type` を付けない（呼び出し側に散らさない）。`filebrowser.js` は、管理モードでは選んだファイルの確定（`pickFile()`）とプレビューの「このファイルを選択」を出さず、ダブルクリックはテキストならテキストのダイアログ（M3）、それ以外はプレビューにする。`writable` が偽なら、新しいフォルダ・アップロード・貼り付けのボタンを押せなくし、一覧の上に「このフォルダには書き込めません」を出す（文言は既存の `files_dirwritable` などを使わず、`fb_not_writable` を足す。旧のキーは M4 で消すため）。ツリーの一番上の名前は `config.rootLabel`（ルートのフォルダの名前）にする。

この段階では `a=31` は旧ファイル管理のまま。管理モードの画面は、検証のときだけ一時的な入口（Concrete Steps の M2 の 5）から開く。

### M3: テキストの表示・編集、新しいファイル、zip の展開、変更の記録

API に、管理モードだけで受ける操作を足す（ピッカーで呼ばれたら `fbError('forbidden', ..., 403)`）。

- `readText`（GET。`folder`、`file`）: `resolveFile()` で実体を確かめ、拡張子が `BrowserConfig::TEXT_EXTENSIONS` にあるか（`.htaccess` のような名前は名前全体で照合）、大きさが `TEXT_MAX_BYTES` 以下か、NUL を含まないかを確かめる。応答は `{ name, content, hash, writable, readOnly, encoding }`。UTF-8 でなければ `mb_convert_encoding($buffer, 'UTF-8', 'SJIS-win,SJIS,EUCJP-win,EUC-JP')` で変換し、`readOnly: true`、`encoding` に元の文字コードの名前を入れる。`hash` は元のバイト列の `sha1`。
- `saveText`（POST、CSRF。`folder`、`target`、`content`、`hash`）: 同じ確認に加えて、リンクなら断る（`saveImage` の上書きと同じ）、UTF-8 でない元のファイルは断る、今の中身の `sha1` が `hash` と違えば 409（`modified`）、改行を元のファイルに合わせる、`file_put_contents($path, $content, LOCK_EX)` の戻り値を確かめる（`fbAttempt()` で包む）。パーミッションは変えない（既にあるファイルへの書き込みなので保たれる）。
- `newfile`（POST、CSRF。`folder`、`name`）: `sanitizeFilename($name, false)`、`isAllowedNewName()`、`resolveNewChild()`、既にあれば 409。`fopen($path, 'xb')` で作り（同時の作成で上書きしない）、`@chmod($path, $modx->newFilePermissions())`。
- `unzip`（POST、CSRF。`folder`、`target`）: `ZipService::extract($zipPath, $destDir, PathGuard $guard, callable $isAllowedName, DocumentParser $modx)` を呼ぶ（`$isAllowedName` には `$scope->isAllowedNewName(...)` を渡す）。応答は `{ extracted: 件数, failed: [...], skipped: [...] }`。zip の拡張が無ければ 501（`zip_unavailable`）、中身がルートの外・守る場所へ書くなら 400（`unsafe_zip`）。

`lib/ZipService.php` に `extract()` を足し、旧 `unzip()` と下請けの関数を移す（Decision Log のとおり、動きは旧と同じ）。旧の関数のうち `evo()->sanitizeFilename()` を呼ぶ所は、引数で受けた `DocumentParser` を使う。

変更の記録は、`api.php` に `fbLogChange($scope, $action, array $context)` を 1 つ置き、成功した変更の後に呼ぶ。中身は `evo()->logEvent(0, 1, 'File changed in the file browser', 'File browser', ['scope' => ..., 'action' => ..., 'paths' => ...])`。`paths` には、ルートからの相対パス（移動・名前の変更は `from` と `to`）を最大 20 件入れる。失敗の記録（今の `logEvent(0, 3, ...)`）はそのまま。

画面は `js/textEditor.js` を新しく作る。`createTextEditor(ctx)` が、ファイルブラウザの中のダイアログ（`js/overlay.js`）に、エレメントの編集画面と同じ形のコードの欄（`div.code-editor` > `div.code-editor-header`（ファイル名と「行を折り返す」のチェックボックス）、`textarea.phptextarea`（`dir="ltr"`、`wrap="soft"`））と、保存・閉じるのボタンを出す。HTML は `BrowserView::markup()` に足す（管理モードのときだけ）。開くと `readText` を呼び、`readOnly` なら欄を読み取り専用にして理由を出す。書き換えたら `window.documentDirty = true`（EvoShell が離れるときに確かめる）、保存したり閉じたりしたら `false` に戻す。変更があるまま閉じるときは確かめる。保存が 409 なら「ほかで書き換えられています」を出し、上書きはさせない。

一覧と右クリックメニューに、管理モードのときだけ「表示・編集」（テキストの拡張子のファイル）、「展開」（`.zip`）、何も無い所に「新しいファイル」を足す。新しいファイルを作ったら、そのままテキストのダイアログで開く。展開の前には確かめ（旧の `confirm_unzip_file` に当たる文言を `fb_confirm_unzip` として足す）、結果（展開した件数、飛ばしたもの、失敗したもの）を出して一覧を読み直す。文言は `fb_` のキーで言語ファイルに足し、`BrowserView` の JS 用キーの一覧に入れる。

### M4: `a=31` でファイルブラウザを表示し、旧ファイル管理を消す

`manager/actions/element/file_manager.static.php` を新しく作る。先頭で `if (!isset($modx) || !evo()->isLoggedin()) { exit; }`、`file_manager` の権限が無ければ `alert()->setError(3); alert()->dumpError();`（旧と同じ）。`BrowserView::managePane()` に `scope: 'manage'`、`apiUrl: 'media/browser/evo/api.php'`、`initialFolder: getv('folder', '')`、`initialSelect: getv('select', '')`、見出し `lang('manage_files')` を渡して出す。最後に `EvoFileBrowserInit()` を呼ぶインラインの `<script>` を出す（モーダルと同じ理由）。`manager/index.php` の `case 31` はこのファイルを読む。

メインの領域での見た目を整える。`#evoFileBrowser` に `evo-fb-page` のクラスを付け、`filebrowser.css` で高さをメインの領域の見える高さに合わせ、一覧とツリーの中だけがスクロールするようにする。テーマの CSS 変数を使い、色を直に書かない（RevoStyle は凍結テーマなので、崩れないことだけを確かめる）。

`filebrowser.js` で、`evoshell:unload`（メインの領域から離れるとき）に、開いているダイアログと右クリックメニューを閉じる（`document` に付けた `keydown` などの受け手を残さないため）。

フォルダツリーの開閉のボタンをツールバーに足し（`data-action="toggle-tree"`、`aria-expanded` と `aria-controls` を付ける）、`localStorage` の `evoFbTree` に残す。`CustomEvent` の `evofb:navigate` と `evofb:selectionchange` を `#evoFileBrowser` から出す（Decision Log）。

リンクを置き換える。

- `configcheck_mgr_tpl_msg`（英語と日本語）: `index.php?a=31&folder=[+folder+]` にする。`config_check.inc.php` は、`MODX_BASE_PATH . 'assets/templates/manager'` がファイル管理のルートの中なら、ルートからの相対パスを `rawurlencode()` を要素ごとにかけて `folder` に入れ、外なら `index.php?a=31` だけにする（`[+folder+]` を空にする）。
- `filemanager_path_message`・`rb_message`（日本語）と、英語の同じキー: 「ファイル管理」は管理モードのファイルブラウザで、ピッカーよりも広い範囲を扱う、という今の形の説明に直す。

`manager/includes/csrf_token.php` の `31 => ['mode' => ...]` を消す（`a=31` は状態を変えない画面になり、書き込みは API が自分で CSRF トークンを確かめる）。

`files.dynamic.php` を `git rm` で消す。`PathGuard` を使っていた旧の呼び出しは無くなるので、`PathGuard` の `isBase()` など、使われなくなったメソッドがあれば消す。旧ファイル管理だけが使っていた言語キーを、英語と日本語の両方から消す。計画時の数え方（`files.dynamic.php` で使うキーのうち、言語ファイルとこのファイル以外に `'キー'` か `"キー"` の形で出てこないもの）では、次が該当した。`add_folder`、`confirm_delete_dir`、`confirm_delete_dir_recursive`、`confirm_delete_file`、`confirm_unzip_file`、`deleting_file`、`editing_file`、`file_delete_file`、`file_delete_folder`、`file_deleted`、`file_download_unzip`、`file_folder_chmod_error`、`file_folder_created`、`file_folder_deleted`、`file_folder_not_created`、`file_folder_not_deleted`、`file_folder_not_found`、`file_not_deleted`、`file_not_found`、`file_saved`、`file_unzip`、`file_unzip_entry_failed`、`file_unzip_fail`、`file_unzip_skipped`、`files_create_textfile`、`files_data`、`files_dir_not_viewable`、`files_directories`、`files_dirwritable`、`files_editfile`、`files_file_exists`、`files_file_type`、`files_filename`、`files_filename_invalid`、`files_fileoptions`、`files_files`、`files_filesize`、`files_filetype_notok`、`files_modified`、`files_textfile_created`、`files_upload_copyfailed`、`files_upload_error`、`files_upload_error0`〜`5`、`files_upload_inhibited_msg`、`files_upload_ok`、`files_upload_permissions_error`、`files_uploadfile`、`files_viewfile`、`not_readable_dir`。`file_download_file`・`file_not_saved`・`manage_files` はほかでも使うので残す。消す直前に、Concrete Steps の M4 の 6 のコマンドで数え直し、`$_lang['キー']` やプラグインからの参照が無いことを確かめる。

ドキュメントを直す。`assets/docs/events-and-plugins.md` の「ファイルブラウザのイベント」に、管理モードのアップロードで `OnFileManagerUpload` を発火すること（`filepath` は確かめた実パス）を書く。`assets/docs/theme-spacing.md` 89 行目の「ファイル管理（a=31）のアップロード欄（`#uploader`）の上 10px」を消す。ほかに `files.dynamic.php` を指す記述が `assets/docs/` に無いかを grep で確かめる。最後に `/doc-audit` を流す。

## Concrete Steps

コマンドはすべて、作業ツリーのルートで実行する。検証環境は `adaptive-validation-runner` スキルの手順（`references/environment.md`）で立て、アプリのコンテナを `<app>`（既定は `evo-validate-app`）、Playwright を入れた作業用のディレクトリを `<work>` と書く。管理者は `admin`（ロール 2）、権限の弱い利用者は `editor`（ロール 3、`file_manager` あり、`save_plugin` なし、`isAdmin()` は偽）を使う。

### M1

1. `manager/media/browser/evo/lib/PathGuard.php` を作る（Plan of Work のメソッド）。旧の関数の本体を、名前だけ変えて移す。
2. `manager/actions/element/files.dynamic.php` の呼び出しを置き換え、移した関数の定義を消す。フォルダの削除に `containsProtected()` を足す。
3. 移し漏れが無いかを確かめる。

        grep -nE "\b(proteted_path|is_protected_path|normalize_path|is_absolute_local_path|has_parent_reference|filemanager_base_path|is_filemanager_root|is_within_filemanager_path)\(" manager/actions/element/files.dynamic.php

   期待: 何も出ない。

4. 構文を PHP 7.4 と標準の版で確かめる。

        docker run --rm -v "$PWD":/app -w /app php:7.4-cli sh -c 'for f in manager/media/browser/evo/lib/PathGuard.php manager/actions/element/files.dynamic.php; do php -l "$f"; done'
        docker exec -w /var/www/html <app> sh -c 'for f in manager/media/browser/evo/lib/PathGuard.php manager/actions/element/files.dynamic.php; do php -l "$f"; done'

   期待: どちらも `No syntax errors detected in ...` が 2 行。

5. 静的なメソッドを PHP 7.4 で動かす。

        docker run --rm -v "$PWD":/app -w /app php:7.4-cli php -r 'require "manager/media/browser/evo/lib/PathGuard.php"; var_dump(PathGuard::normalize("/app/assets/../manager"), PathGuard::hasParentReference("/a/../b"), PathGuard::isAbsoluteLocal("file:///etc/passwd"), PathGuard::normalize("/app/no/such/../dir"));'

   期待: `string(12) "/app/manager"`、`bool(true)`、`bool(false)`、`string(11) "/app/no/dir"`。

        docker run --rm -v "$PWD":/app -w /app php:7.4-cli php -r 'require "manager/media/browser/evo/lib/PathGuard.php"; $g = new PathGuard("/app", ["/app/assets/plugins"]); var_dump($g->containsProtected("/app/assets"), $g->containsProtected("/app/ASSETS"), $g->containsProtected("/app/content"), $g->isProtected("/app/assets/plugins/x"), $g->isWithinBase("/etc"));'

   期待: `bool(true)`、`bool(true)`、`bool(false)`、`bool(true)`、`bool(false)`。

6. 画面で確かめる（Validation の M1 の表）。main と作業ブランチで同じスクリプトを流し、結果を比べる。
7. コミットの案: `refactor(filemanager): パスの安全確認をファイルブラウザの lib にまとめる`、`fix(filemanager): 中に守る場所を含むフォルダは削除しない`（2 つに分ける）。

### M2

1. `lib/BrowserScope.php` を作り、`lib/BrowserConfig.php` に `allowedExtensionsAll()` を足す。
2. `lib/PathResolver.php` に守る場所の確認と `resolveNewChild()` を足し、最初の階層が `''` のときに足さないようにする。
3. `api.php` の入口と各操作を `BrowserScope` に通す。`grep -n "\$type" manager/media/browser/evo/api.php` で、範囲を通さずに `type` を使っている所が残っていないかを確かめる（期待: `BrowserScope` の生成と、ピッカーの種類を返す所だけ）。
4. `lib/BrowserView.php` を作り、`browser.php` をこれに置き換える。`js/api.js`・`filebrowser.js`・`js/grid.js`・`js/tree.js` を管理モードに対応させる。言語ファイルに `fb_err_manage_forbidden`・`fb_err_protected`・`fb_not_writable` を足す。
5. 管理モードの画面を確かめるための一時的な入口を、コミットしないファイルとして作る。`manager/media/browser/evo/validate-manage.local.php` に、`browser.php` と同じ起動（`IN_MANAGER_MODE`・`MODX_API_MODE`・`DocumentParser`・`getSettings()`）、`file_manager` の権限の確認、`BrowserView::standalonePage()` で管理モード（`scope` は `manage`、`apiUrl` は `api.php`）の単独のページを出す処理を書く。検証が終わったら消し、`git status --short` に出ないことを確かめる。
6. 構文を確かめる（M1 の 4 と同じコマンドで、`lib/*.php`・`api.php`・`browser.php` を対象にする）。JS は ES モジュールとして構文を確かめる（`.js` のままでは CommonJS として読まれ `import` で止まることがあるので、`.mjs` に写して確かめる）。

        docker run --rm -v "$PWD":/app -w /app node:22 sh -c 'for f in manager/media/browser/evo/filebrowser.js manager/media/browser/evo/js/*.js; do cp "$f" /tmp/check.mjs && node --check /tmp/check.mjs || { echo "NG $f"; exit 1; }; done'

   期待: 何も出ず、終了コード 0。
7. Validation の M2 の表を、main と作業ブランチで確かめる。
8. コミットの案: `refactor(filebrowser): 範囲の違いを BrowserScope にまとめ、画面の HTML を BrowserView へ移す`、`feat(filebrowser): ファイル管理の範囲で一覧・操作できる管理モードを追加`。

### M3

1. `lib/BrowserConfig.php` に `TEXT_EXTENSIONS` と `TEXT_MAX_BYTES` を足す。`lib/ZipService.php` に `extract()` を足す。
2. `api.php` に `readText`・`saveText`・`newfile`・`unzip` と `fbLogChange()` を足し、既存の成功した変更の後に `fbLogChange()` を呼ぶ。ファイルの先頭のコメント（action の一覧）を更新する。
3. `js/textEditor.js` を作り、`filebrowser.js`・`js/contextmenu.js` の項目・`BrowserView::markup()` を足す。言語キーを足す。
4. 構文を確かめる（M2 の 6 と同じ）。
5. 検証の前に、旧ファイル管理のテキストの保存で改行が変わるか（Surprises の要確認）を main で確かめる。

        printf 'a\nb\n' > content/files/lf-check.txt
        （main の a=31 で content/files/lf-check.txt を編集画面で開き、そのまま保存する）
        docker exec -w /var/www/html <app> od -c content/files/lf-check.txt | head -2

   結果（`\r \n` が出るか）を Surprises に書き、ファイルを消す。

6. Validation の M3 の表を確かめる。システムログは次で見る。

        docker exec -w /var/www/html <app> php evo log:search "File changed in the file browser" --limit=20

   期待: 操作ごとに 1 行、`context` に `scope`・`action`・`paths`、`user` に利用者の ID。

7. コミットの案: `feat(filebrowser): 管理モードにテキストの表示・編集と新しいファイルを追加`、`feat(filebrowser): 管理モードに zip の展開を追加`、`feat(filebrowser): 成功したファイルの変更をシステムログに残す`。

### M4

1. `manager/actions/element/file_manager.static.php` を作り、`manager/index.php` の `case 31` を差し替える。`BrowserView::managePane()` を仕上げ、`filebrowser.css` に `evo-fb-page` を足す。
2. ツリーの開閉、`CustomEvent`、`evoshell:unload` での後始末を `filebrowser.js`・`js/tree.js`・`BrowserView` に足す。
3. `config_check.inc.php` と言語ファイルのリンク・説明を直し、`csrf_token.php` の `31` を消す。
4. `git rm manager/actions/element/files.dynamic.php`。
5. 参照が残っていないかを確かめる。

        grep -rn "files.dynamic\|mode=drill" manager assets install --include=*.php --include=*.js --include=*.md

   期待: 何も出ない。

        grep -rn "a=31" manager assets install --include=*.php --include=*.js | grep -v tinymce/

   期待: `menu.php` は `item(..., 31, ...)` なので出ず、`welcome.php`・言語ファイル（`configcheck_mgr_tpl_msg`・`filemanager_path_message`・`rb_message`）だけが出て、どれも `mode=` を含まない。

6. 消す言語キーを数え直す（キーごとに、言語ファイル以外での出現を数える。0 のものを消す）。スクリプトは作業ツリーの外（`<work>/count-keys.sh`）に置く。

        keys="add_folder confirm_delete_dir ..."（Plan of Work の M4 の一覧）
        for k in $keys; do printf '%s ' "$k"; grep -rnE "['\"]${k}['\"]" manager assets install --include=*.php --include=*.js --include=*.tpl --include=*.html | grep -v "manager/includes/lang/" | grep -v tinymce/ | wc -l; done

   期待: すべて 0。消した後、`grep -c` で英語と日本語の言語ファイルからキーが消えたことを確かめる。

7. 構文（M2 の 6）、`/doc-audit`、Validation の M4 の表、PHP 7.4 の全体の確認を行う。
8. コミットの案: `feat(manager): ファイル管理をファイルブラウザで表示する`、`feat(filebrowser): フォルダツリーを畳めるようにし、選択と移動をイベントで知らせる`、`refactor(manager): 旧ファイル管理と使われなくなった言語キーを削除`、`docs: ファイル管理の置き換えに合わせてイベントと余白の説明を直す`。

## Validation and Acceptance

確認は Docker の検証環境で行う（`adaptive-validation-runner` スキル。CLI で判定できるものは curl・`php evo`・DB、画面の動きは Playwright のヘッドレス）。各段階で、同じスクリプトを main と作業ブランチで流し、「条件 × main × 作業ブランチ」の表にして PR の本文に写す。ブランチを切り替えたら数秒待つ（OPcache）。テスト用のフォルダとファイルは、`content/files/fm-test/`（ピッカーの範囲でもある）と、サイトのルートの `fm-test/` に作り、終わったら消す。

### M1（旧ファイル管理の動きが main と同じで、1 点だけ変わる）

`editor` でログインし、旧ファイル管理（`a=31`）で次を行う。状態を変える操作は、画面のリンクとボタンから行う（CSRF トークンが要るため）。

| 条件 | main | 作業ブランチ |
|---|---|---|
| `a=31&mode=drill&path=<base>manager` を開く | 「このディレクトリは参照できません」 | 同じ |
| `path=<base>assets/plugins`（`save_plugin` なし）、`path=<base>ASSETS/plugins`（大文字） | 同上 | 同じ |
| `path=<base>assets/../manager`、`path=assets`（相対）、`path=file:///etc` | 同上 | 同じ |
| `path=/etc`（ルートの外） | 同上 | 同じ |
| ルートの一覧で `manager` が灰色で出る | 出る | 同じ |
| `fm-test/` の作成・テキストファイルの作成・編集・保存・削除、画像のアップロード、`../` を含む zip の展開（何も展開されない）、普通の zip の展開 | 各操作の成功・失敗の文言 | 同じ |
| `editor` で、`assets/plugins` を中に持つ `assets` の行の削除アイコンを押す | 実行しない（下の注を参照。コードでは消える） | 「ディレクトリを削除できません」、`assets` が残る（`git status --short assets` が空） |
| `admin`（`isAdmin()` が真か確かめ、偽なら `access_permissions` と `web_access_permissions` を持つロール 1 の利用者を使う）で `path=<base>manager` を開く | 一覧が出る | 同じ |

注: 守る場所はサイトのルートからの絶対パスで決まるので、中に守る場所を含むフォルダは `assets`・`temp`・サイトのルートしか作れない。検証環境は作業ツリーをそのままマウントしているため、main で消える側を確かめると作業ツリーの `assets` が消える。main では実行せず、`files.dynamic.php` の `mode=deletefolder` が `rrmdir()` の前に `is_protected_path()` しか見ていないことを根拠に書く。作業ブランチでは断るので、実行しても何も消えない（念のため、実行の前に `git stash` ではなく `tar czf <work>/assets.tgz assets` で控えを取る）。`containsProtected()` そのものは、Concrete Steps の M1 の 5 と同じやり方で、`PathGuard` に一覧を渡して PHP 7.4 で確かめる（`(new PathGuard('/app', ['/app/assets/plugins']))->containsProtected('/app/assets')` が `true`、`'/app/content'` が `false`）。

### M2（管理モードの API と画面）

API は、Playwright でログインしたページの `page.request`（クッキーを共有する）から呼び、ステータスと JSON を見る。書き込みには、画面の `data-config` の `csrfToken` を使う。

| 条件 | 期待 |
|---|---|
| `editor` で `api.php?action=list&scope=manage&folder=` | 200。`folders` にサイトのルートのフォルダ（`assets`・`content` など）があり、`manager` は無い。`files` に `index.php`・`.htaccess`（あれば）が入り、`thumb` はすべて `null`、`path` は無い。`writable` がある |
| `admin`（`isAdmin()` が真）で同じ | `folders` に `manager` がある |
| `editor` のロールから `file_manager` を外して同じ（終わったら戻す） | 403、`error.code` は `forbidden` |
| ログインしていない・ウェブユーザーのセッション（`rb_webuser=1`）で同じ | 403 |
| 設定 `use_browser=0` で `editor` の `scope=manage` の `list` | 200（管理モードは `use_browser` を見ない） |
| 同じ設定でピッカー（`scope` なし、`type=images`） | 403（今と同じ） |
| `editor` で `scope=manage` の `list` を `folder=manager`、`folder=assets/plugins`、`folder=../` で呼ぶ | 404 か 403。一覧は返らない |
| `editor` で `mkdir`（`folder=temp`、`name=backup`。`temp/backup` が無い状態で） | 403（`protected`）。作られない |
| `editor` で `assets` の名前の変更（`folder=`、`target=assets`）と削除（`folders[]=assets`） | どちらも断られ、`assets` は残る |
| 管理モードで `fm-test/` に `a.php` をアップロード（設定の許可リストに `php` が無い） | 要相談の決着どおり（案では断る） |
| 管理モードで `fm-test/a.txt` を `b.txt` に、`index.php` のコピーを `c.php` → `d.php` に名前を変える | 成功する（拡張子が同じ） |
| 管理モードのアップロード | `OnFileManagerUpload` が発火し、`OnFileBrowserUpload` は発火しない（イベントの名前と値をシステムログに書く一時的なプラグインで確かめ、終わったら消す） |
| ピッカー（TV の画像の欄の「挿入」から開くモーダル）で、一覧・アップロード・名前の変更・削除・移動・コピー・ダウンロード・画像の編集・選択の確定 | main と同じ（`list` の応答のキーと値が main と同じで、`writable` が増えただけ。サムネイルが出る） |
| ポップアップ（QuickManager）と TinyMCE のダイアログからのピッカー | main と同じく開いて選べる |
| 一時的な入口（Concrete Steps の M2 の 5）から管理モードの画面を開く | ツリーの一番上がルートのフォルダの名前、一覧にサムネイルが無く、ダブルクリックで選択の確定が起きない。書き込めないフォルダ（`chmod 555` にした `fm-test/ro/`）では、新しいフォルダ・アップロードのボタンが押せず、注意の文が出る |
| `temp/thumbs/` のファイルの数を、管理モードで `assets/images`（画像の多いフォルダ）を開く前後で数える（`docker exec <app> sh -c 'ls /var/www/html/temp/thumbs \| wc -l'`） | 増えない |

### M3（テキスト・新しいファイル・zip・記録）

画面は M2 の一時的な入口から開く。

| 条件 | 期待 |
|---|---|
| `fm-test/a.txt`（LF、UTF-8）を「表示・編集」で開き、1 行足して保存 | 保存の後に `od -c` で `\r` が無く、足した行がある。システムログに `saveText` の 1 行 |
| 開いたまま、`docker exec` でファイルを書き換えてから保存 | 「ほかで書き換えられています」。ファイルはコンテナで書いた中身のまま |
| CRLF のファイルを編集して保存 | CRLF のまま |
| Shift_JIS のファイル（`printf '\x82\xa0' > fm-test/sjis.txt`） | 「あ」と表示され、読み取り専用。保存のボタンが押せない |
| 3MB のテキスト、NUL を含む `.txt` | 開かず、理由が出る |
| `.htaccess`（`fm-test/.htaccess`）を開く | 開ける |
| 書き込めないファイル（`chmod 444`） | 読み取り専用で開く |
| 編集中に左のメニューで別の画面へ移る | 「変更が保存されていません」の確認が出る（M4 の `a=31` で確かめる。M3 では一時的な入口なので、ダイアログを閉じるときの確かめだけ） |
| 何も無い所の右クリックの「新しいファイル」で `new.txt` | 作られ、テキストのダイアログで開く。パーミッションが設定 `new_file_permissions` の値（空なら 0644） |
| 同じ名前で「新しいファイル」 | 409。中身は空にならない |
| 普通の zip（日本語の名前・フォルダを含む、Windows で作った Shift_JIS の名前を含む）を「展開」 | 名前が化けずに展開され、件数が出る |
| `../evil.txt` を含む zip、`manager/x.txt` に書く zip（`editor`、ルートで展開） | 何も展開されず、理由が出る |
| 許可リストに無い拡張子（`.php`）を含む zip | そのエントリだけ飛ばされ、飛ばした名前が出る |
| ピッカーの範囲で `readText`・`saveText`・`newfile`・`unzip` を `page.request` で呼ぶ | 403 |
| アップロード・新しいフォルダ・名前の変更・削除・移動・コピー・画像の保存を、管理モードとピッカーで 1 回ずつ | `php evo log:search "File changed in the file browser"` に操作ごとに 1 行。`scope` が合っている |

### M4（`a=31` の表示と、旧ファイル管理が無いこと）

| 条件 | 期待 |
|---|---|
| メニューの「ファイル管理」を押す | メインの領域にファイルブラウザが出る（fetch の応答に `X-Evo-Pane: 1`。`lib.js` の `watchFetch()`）。見出しは「ファイル管理」 |
| `index.php?a=31` を直に開く（全体の読み込み） | 同じ画面が header・footer 付きで出る |
| ダッシュボードの「ファイル管理」のアイコン | 同じ画面 |
| `a=31&folder=assets/images&select=<ある画像>` | そのフォルダが開き、その画像が選ばれて見える位置にある |
| `a=31&mode=drill&path=/var/www/html/manager` | ルートが開く（旧の引数は効かない）。`editor` では `manager` が一覧に無い |
| `file_manager` の権限が無い利用者で `a=31` | 権限が無い旨の画面（旧と同じ） |
| 別の画面へ移って戻る、を 3 回 | 毎回、一覧が出る（`EvoFileBrowserInit()` が呼び直される）。コンソールにエラーが無い |
| テキストのダイアログを開いたまま別の画面へ移る | 変更が無ければそのまま移り、ダイアログの受け手が残らない（移った先で Esc を押しても何も起きない）。変更があれば確認が出る |
| ツリーの開閉のボタン | ツリーが畳まれ、再読み込みしても畳まれたまま。ピッカーのモーダルでも同じボタンが効く |
| `document.addEventListener('evofb:navigate', …)` と `evofb:selectionchange` を Playwright で付け、フォルダを移る・ファイルを選ぶ | `detail` に `scope: 'manage'`・`folder`・`names` が入って届く |
| 設定の確認（`assets/templates/manager/login.html` を置く）の警告のリンク | `a=31&folder=assets%2Ftemplates%2Fmanager` のような URL で、そのフォルダが開く |
| `filemanager_path` をサイトのルートの外（`/tmp/fm-root/`）にする | 一覧が出る。画像のプレビューは出ず、ダウンロードはできる。設定の確認のリンクは `a=31` だけ |
| `git ls-files manager/actions/element/files.dynamic.php` | 何も出ない |
| `csrf_token.php` の一覧から `31` が消え、`a=31` を開いても 403 にならない | そのとおり |
| テーマを RevoClassic・RubberWhite・AuroraFlow・SoftSlate・SakuraFlow・AromaHop・RevoStyle に替えて `a=31` を開く | どれも高さがメインの領域に合い、一覧だけがスクロールする（RevoStyle は崩れないことだけ） |
| 英語の管理画面（利用者の `manager_language` を英語） | 文言が英語。キーの名前がそのまま出ている所が無い |
| スモークテスト（`scripts/smoke.js`） | 全 PASS |

PHP 7.4 での確認: `references/environment.md` の「ほかの PHP の版で検証するとき」に従い、`FROM php:7.4-apache` の Dockerfile（apt の取得先をアーカイブへ向ける行を足す）でアプリのイメージを作り、M4 の表のうち「メニューから開く」「`folder` と `select`」「テキストの編集と保存」「zip の展開」「アップロード」「ピッカーで選ぶ」を流す。期待: 標準の版と同じ結果で、システムログに PHP の警告・非推奨の記録が無い（`php evo log:search "Deprecated" --limit=5` と `--level=error` で何も出ない）。

完了の条件: M1〜M4 の表がすべて期待どおりで、メニューの「ファイル管理」からファイルブラウザでサイトのファイルを一覧・アップロード・削除・名前の変更・移動・コピー・ダウンロード・テキストの編集・zip の展開ができ、`files.dynamic.php` がリポジトリに無いこと。

## Idempotence and Recovery

各段階は 1 つの PR にし、段階の終わりで main は常に動く状態にする（M1〜M3 の間、`a=31` は旧ファイル管理のまま）。途中で止めたときは、Progress の最後の完了した段階から続ける。段階の途中なら、`git status` と `git diff --stat` で手を付けたファイルを確かめ、Plan of Work のその段階の箇条を上から照らし合わせる。

`PathGuard`・`BrowserScope`・`BrowserView` の作成と置き換えは、何度やり直しても同じ結果になる。M2 の一時的な入口（`validate-manage.local.php`）と検証用のプラグイン・テスト用のファイル（`content/files/fm-test/`・`fm-test/`・`/tmp/fm-root/`）は、検証のたびに作り直してよく、終わったら必ず消す。消したことは `git status --short` が空であること（検証環境が作る `.htaccess`・`manager/includes/config.inc.php` は無視の対象）で確かめる。ロールの権限・`use_browser`・`filemanager_path`・テーマ・言語を変えて確かめたときは、元の値に戻して `php evo cache:clear` を流す。

M4 で `files.dynamic.php` を消した後に問題が見つかったときは、M4 のコミットを `git revert` すれば、`a=31` が旧ファイル管理に戻る（M1〜M3 の変更はそのまま使える）。サイトを上書きで更新した利用者の環境では、消した `files.dynamic.php` がサーバーに残ることがあるが、どこからも読まれず、直に開いても先頭の `isset($modx)` の確認で何もせずに終わる。

## Artifacts and Notes

- Issue: https://github.com/modxcms-jp/evolution-jp/issues/1046
- 関連の Issue: #1043、#1041、#1017（いずれも閉じている）
- 関連の ExecPlan: `.agent/plans/2026-07-11-file-browser-modal-rewrite.md`、`.agent/plans/archive/2026-11-01-file-browser-kcfinder-extension.md`、`.agent/plans/2026-10-10-manager-input-and-csrf-hardening.md`
- 新しく作るファイル: `manager/media/browser/evo/lib/PathGuard.php`（M1）、`lib/BrowserScope.php`・`lib/BrowserView.php`（M2）、`js/textEditor.js`（M3）、`manager/actions/element/file_manager.static.php`（M4）
- 消すファイル: `manager/actions/element/files.dynamic.php`（M4）
- PR は段階ごとに分ける。M1〜M3 の PR は `Refs #1046`、M4 の PR で `Closes #1046` にする

## Interfaces and Dependencies

PHP のクラス（すべて `manager/media/browser/evo/lib/`、PHP 7.4 で動く書き方）:

    PathGuard
        __construct($baseDir, array $protectedPaths = [])
        static protectedPathsFor(DocumentParser $modx): array
        static normalize($path)              // string|false
        static isAbsoluteLocal($path): bool
        static hasParentReference($path): bool
        isWithinBase($path): bool
        isBase($path): bool
        isProtected($path): bool
        containsProtected($path): bool

    BrowserScope
        static fromRequest(DocumentParser $modx, BrowserConfig $config)
        isManage(): bool / type(): string / baseDir(): string / guard(): PathGuard
        listsFile($name): bool / showsHidden(): bool / makesThumbnails(): bool
        isAllowedNewName($name): bool / isAllowedRename($oldName, $newName): bool
        fileUrl($absPath) / pickPath($rel) / uploadEvent(): string

    PathResolver（既存に足す）
        resolveNewChild($dirReal, $name)     // string|null

    ZipService（既存に足す）
        extract($zipPath, $destDir, PathGuard $guard, callable $isAllowedName, DocumentParser $modx): array
        // ['ok' => bool, 'reason' => 'unavailable'|'unsafe'|'open_failed'|'', 'extracted' => int, 'failed' => [], 'skipped' => []]

    BrowserView
        static jsConfig(DocumentParser $modx, BrowserConfig $config, array $options): array
        static markup(array $jsConfig): string
        static modalFragment(array $jsConfig, $title): string    // browser.php?modal=1
        static standalonePage(array $jsConfig, $title): string   // browser.php（ポップアップ）と M2・M3 の一時的な入口
        static managePane(array $jsConfig, $title): string       // a=31（M4）

API（`manager/media/browser/evo/api.php`）: すべての要求で `scope`（`picker` か `manage`、省略は `picker`）を受ける。`manage` は管理画面のセッションと `file_manager` の権限が要り、`type` は見ない。足す action は `readText`（GET）、`saveText`・`newfile`・`unzip`（POST、CSRF トークン）で、`manage` だけ。`list` の応答に `writable` を足し、`manage` では `path` を返さず、`thumb` は `null`。エラーは今までどおり `{error:{code,message}}` とステータス。足すコードは `protected`、`modified`、`not_text`、`too_large`（既存）、`unsafe_zip`、`zip_unavailable`（既存）。

JS（`manager/media/browser/evo/`）: `data-config` に `scope` と `rootLabel` を足す。`#evoFileBrowser` から `evofb:navigate`（`detail: { scope, folder }`）と `evofb:selectionchange`（`detail: { scope, folder, names }`）を `bubbles: true` で出す。`window.EvoFileBrowserInit()` と `window.EvoFileBrowserPick()`、`SetUrl()` の規約は変えない。

イベント: 管理モードのアップロードで `OnFileManagerUpload`（`filepath`・`filename`）、ピッカーで `OnFileBrowserUpload`（今までどおり）。新しいイベントは足さない。

設定: `filemanager_path`（管理モードのルート。空ならサイトのルート）、`rb_base_dir`・`rb_base_url`・`use_browser`・`rb_webuser`（ピッカー）、`upload_files`・`upload_images`・`upload_media`（許可リスト）、`upload_maxsize`、`new_file_permissions`・`new_folder_permissions`、`image_limit_width`。新しい設定は足さない。

ログ: 成功した変更は `evo()->logEvent(0, 1, 'File changed in the file browser', 'File browser', [...])`（システムログ、`temp/logs/system/YYYY/MM/`）。管理操作ログ（`manager_log`）には、`a=31` を開いたことだけが `manager/index.php` から残る。

外部のもの: PHP の zip 拡張（展開とまとめてのダウンロード。無ければ 501）、mbstring（文字コードの変換）。新しいライブラリは使わない。
