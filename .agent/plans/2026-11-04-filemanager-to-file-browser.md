# ExecPlan: 「ファイル管理」をファイルブラウザに置き換える

## Purpose / Big Picture

管理画面のメニューの「ファイル管理」（`index.php?a=31`）を開くと、画像を選ぶときと同じファイルブラウザが管理画面のメインの領域に表示され、サイトのファイルをドラッグ＆ドロップ・範囲選択・zip でのダウンロードなどで扱えるようにする。削除やアップロードの安全確認を 1 か所にまとめ、旧ファイル管理（`manager/actions/element/files.dynamic.php`）を削除して、同じ操作が 2 か所に別々にある状態をなくす。

Issue: #1046

## Progress

- [x] (2026-11-04) ExecPlan を作成（方針・マイルストーンの分け方・対象外はユーザーと合意済み）
- [x] (2026-11-04) M1: 安全確認の共通化（`lib/PathGuard.php` を作り、旧ファイル管理がそれを使う）。Validation の M1 の表を main と作業ブランチで比べ、違いは `editor` の `assets` の削除を断ることだけだった
- [x] (2026-11-04) 要相談の 3 点を決定（モードの決め方・拡張子は案のとおり、記録は管理操作ログにも残す）。補足の指示（互換の維持、a=13 の記録の中身、権限の確認の分離、zip のエントリごとの確認、共通化の範囲）を反映
- [x] (2026-11-04) 共通化を処理ごとに照らし合わせ直した（一覧とアップロードは範囲ごとの関数に分け、`thumb` はピッカーだけにする）。zip の展開後の上限（256MB）は根拠を確かめて取り下げ、旧と同じにした
- [x] (2026-11-04) `upload_maxsize` が空のときの上限を範囲ごとに今の動きで保つ形に直した（旧は 32MB ではなく本体の上限なしと PHP 7.4 で確認）。zip の展開の安全確認を点検し、ぶら下がったリンク・書く直前の確かめ・0777 のパーミッション・zip そのもののリンク・PHP 7.4 の `basename()` の対策を足した
- [x] (2026-11-04) 管理モードの起動（`a=31`）と API の認可（要求ごとの `file_manager`）を分けて書き、権限を持つ人が API へ直に `scope=manage` を付けて使えることを明記した。管理操作ログの 51 件目より後を `trace_id` でシステムログから追える形にし、ロードマップの到達条件に管理操作ログへの記録を加えた
- [x] (2026-11-05) M2: 管理モード（`PickerScope`・`ManageScope`・`BrowserView`、範囲を見て分けるのは入口だけ）。M1 の PR #1048 のマージ待ちのため、M1 のブランチの上に積んで進めた。ピッカーは main と比べて応答のキーが増えただけで、TV の欄・QuickManager・TinyMCE から選べることを確かめた
- [x] (2026-11-05) M3: テキストの表示・編集、新しいファイル、zip の展開、変更の記録。M2 のブランチの上に積んで進めた。Validation の M3 の表を main と作業ブランチで比べ、期待に外れた行は無かった
- [x] (2026-11-05) M4 の前の関門: Context and Orientation の対応表の各行を M2・M3 の検証結果と照らし、旧でできて新でできない操作が残っていないことを確かめた（ドットで始まる名前の新規作成は旧もできなかった）
- [x] (2026-11-05) M4: `a=31` でファイルブラウザを表示、リンクの置き換え、ツリーの開閉とイベントの入口、`files.dynamic.php` の削除と言語キーの整理（54 キー）。M3 のブランチの上に積んで進めた。PHP 7.4・標準の版・main で Validation の M4 の表を確かめ、回帰は無かった

## Surprises & Discoveries

2026-11-04（計画時）: 旧ファイル管理のフォルダ削除（`mode=deletefolder`）は、指定したフォルダ自体が守る場所か・その配下かだけを確かめている。そのため、守る場所を中に含むフォルダ（`assets/plugins` を中に持つ `assets` など）は、`save_plugin` の権限が無い利用者でも中身ごと消せる。M1 で、中に守る場所を含むフォルダの削除を断るようにする。新しい API でもフォルダの名前の変更で同じことが起きうるので、M2 で同じ確認を通す。

2026-11-04（計画時）: 旧ファイル管理の `logFileChange()` は `logHandler::initAndWriteLog($string, '', '', '', $type, $filename)` を呼ぶ。第 4 引数の `$type`（`'upload'` など）は `writeToLog()` で `(int)` され、管理操作ログ（`manager_log`）の `action` は 0 になる。つまり今の記録は、どの画面の操作かが分からない形で残っている。

2026-11-04（計画時）: 旧ファイル管理の拡張子の一覧（`is_editable_file()` と一覧の表示用）は `'...htaccess' . config('alias_suffix')` と、区切りの `,` を挟まずに設定をつないでいる。設定 `alias_suffix` は本体のどこにも無いので、常に空の文字列がつながるだけで、実害は無い。新しい一覧には持ち込まない。

2026-11-04（計画時）: 旧ファイル管理のテキストの保存は、`<form>` の送信で `textarea` の中身を送る。HTML の仕様では、フォームの送信で改行が CRLF にそろえられるため、LF のファイルを保存すると CRLF に変わるはず（M3 で main の旧ファイル管理を使って確かめた: `a\nb\n` のファイルを開いてそのまま保存すると `a\r\nb\r\n` になる。新しい `saveText` は元のファイルの改行に合わせるので、LF は LF のまま、CRLF は CRLF のまま）。ファイルブラウザの `js/api.js` の `postForm()` は `URLSearchParams` で送るので、改行はそろえられない。

2026-11-04（計画時）: アップロードで同じ名前のファイルがあるとき、旧ファイル管理は上書きし（`$modx->move_uploaded_file()` に同じパスを渡す）、ファイルブラウザは `fbFindFreePath()` で `名前(1).拡張子` のように別の名前にする。同じ「アップロード」でも振る舞いが違うので、共通にせず範囲ごとの関数にする（Decision Log）。

2026-11-04（計画時、PHP 7.4 で確認済み）: 設定 `upload_maxsize` が空のとき、旧ファイル管理には本体の上限が無い。旧のフォームは `MAX_FILE_SIZE` に `evo()->config('upload_maxsize', 32 * 1024 * 1024)` を入れているが、`default.config.php` に `'upload_maxsize' => ''` があり、`array_get()` は `isset()` で確かめるので、空の文字列が返って `(int)` で 0 になる（32MB の既定は使われない）。PHP は `MAX_FILE_SIZE` が 0 のとき大きさを確かめない。`php:7.4-cli` の組み込みサーバーに 40MB を送って確かめた（`upload_max_filesize=64M`）: `MAX_FILE_SIZE=0` では `error: 0` で受け付け、`MAX_FILE_SIZE=1000000` では `error: 2`（`UPLOAD_ERR_FORM_SIZE`）。つまり旧で効いていたのは、PHP の `upload_max_filesize` と `post_max_size` だけ。一方、ファイルブラウザ（ピッカー）は `BrowserConfig::maxUploadSize()` で、空のとき 5,000,000 バイトを当てている。2 つは今の時点で振る舞いが違い、どちらも今の利用者が前提にしているので、範囲ごとにそれぞれの今の振る舞いを保つ（Decision Log）。設定の画面（`tab6_filemanager_settings.inc.php`）は空のときに PHP の上限（`manager()->getUploadMaxsize()`）を初期値として出し、保存すると値が入る。値が入っているサイトでは、旧も新も同じ値で断る。

2026-11-04（計画時）: ファイルブラウザの API（`manager/media/browser/evo/api.php`）は、管理画面のセッション（またはウェブユーザーのセッションと設定 `rb_webuser`）と設定 `use_browser` だけを確かめ、ロールの権限（`hasPermission()`）は見ていない。守る場所の確認も無い。ピッカーのルート（`rb_base_dir`、既定は `content/`）が守る場所と重ならない前提で成り立っている。

2026-11-04（M1）: `PathGuard::normalize()` の最後の要素は、`strrpos()` の前に `rtrim($path, '/')` をかけて取る。`basename()` は末尾の `/` を除いてから最後の要素を返すので、それに合わせないと `/app/no/such/` のような値で結果が変わる。`containsProtected()` は、ルートを `normalize()` で解決するため、守る場所を指すフォルダへのリンクを `folderpath` に渡したときも断る（旧は `rrmdir()` がリンク先をたどって中身を消せた）。リンクの場合は作業ブランチで確かめていない（コードの読み）。

2026-11-05（M2）: 自己レビューで次が分かり、M2 の中で直した。(1) ピッカーと管理モードの入口が `window.EvoFileBrowserInit` を取り合い、EvoShell の中で往復すると、先に評価された入口の振る舞いで動く（ES モジュールは同じ URL を 2 度評価しない）。入口ごとの登録表 `window.EvoFileBrowserEntries` に改めた。(2) プレビューが `url` をどのファイルでも `<img>` で読むので、管理モードでは `.php` を GET で実行してしまう。表示できる画像かをサーバー（`ThumbnailService::isDisplayableImageExtension()`、jpg・jpeg・png・gif・webp・avif・bmp・ico・svg）で決めて `image: true` を付け、プレビューはそれだけを見る。ピッカーでも .tif などで壊れた画像が出なくなる。(3) API は `accesscontrol.inc.php` を通らないので、セッションに残った古い権限で判定していた。`ManageScope::authorize()` で `refreshMgrPermissions()` を呼んでから判定する。ピッカーは要求の数が多く、開いた管理画面が直前に読み直しているので読み直さない。(4) 上書きの確認をブラウザの名前で比べると、サーバーが無害化した名前（NFC、記号の置き換え、`"` や `\`）と違うときに確認が漏れる。サーバーが `code: exists` と `targetName` を返す形にし、結果の対応も名前ではなく受けた順の `index` にした。代わりに、同じ名前のファイルは確認の前に 1 度送られる（大きいファイルでは通信が 2 回になる）。

2026-11-05（M2）: 計画に無かった細部を次のとおり決めた。`BrowserScope` に `listsFolder()`（ピッカーはドットで始まるフォルダを出さない）、`downloadName()`（zip の名前の先頭）、`uploadEvent()`（ピッカーは `OnFileBrowserUpload`、管理モードは `OnFileManagerUpload`。画像の別名の保存も同じ）を足した。`logLabel()` は使う所ができる M3 で足す。`PickerScope::authorize()` は `browser.php` が直した `type` を第 3 引数で受ける。言語キー `fb_confirm_overwrite` を足した。管理モードの上書きはふつうのファイルだけ（フォルダとリンクは断る）。Enter で `openFile` を呼ぶのは、一覧か選んだファイルの名前のボタンにフォーカスがあるときだけ（ピッカーでは、名前のボタンで 2 度目の Enter が名前の変更ではなく選択の確定になる）。書き込めないフォルダへのドロップも止める。POST でも `apiParams` を URL に付ける（`post_max_size` を超えて本文が捨てられたとき、`scope` が落ちてピッカーの認可に回らないように）。

2026-11-05（M2）: ピッカーのルートが `rb_base_dir/<type>` になったので、種類のフォルダの中から別の種類のフォルダを指すリンクは開けなくなり、種類のフォルダそのものがリンクのときは開けるようになった。`OnFileManagerUpload` の `filepath` は末尾に `/` が付く（旧は `postv('path')` で付かない）。管理モードで `.htaccess` を複製すると `sanitizeFilename()` が先頭のドットを落として `htaccess` になる（M3 までに扱いを決める）。アップロードの成功を「エラーに無いこと」で決めているので、PHP の `max_file_uploads` を超えて捨てられたファイルも成功と出る（main と同じ。`uploaded[].index` を使えば直せる）。`OnFileManagerUpload` の `filepath` の形と `uploadEvent()` は、M4 で `events-and-plugins.md` に書く。

2026-11-05（M3）: 次のとおり決めた。(1) 旧ファイル管理も `.htaccess` のようなドットで始まる名前を新しく作れなかった（`sanitizeFilename()` が先頭のドットを落とし、拡張子の無い名前が許可リストに通らない）ので、`newfile` も同じにした。既にある `.htaccess` の表示・編集・保存・削除・移動・ダウンロードはできる。(2) 管理モードでドットで始まるファイルの複製は断る（`isAllowedCopy($sourceName, $newName)`、文言 `fb_err_copy_not_allowed`。ピッカーで断るときの文言もこれに変わる。断る範囲は同じ）。(3) ウェブユーザーのセッションのピッカーの変更は、管理操作ログには書かずシステムログだけに書く（`manager_log.internalKey` は管理ユーザーの ID で、`logHandler` は ID が取れないと HTML のエラーを出して JSON を壊すため）。(4) 管理操作ログの 50 行の上限は 1 回の要求全体で数える（同じ要求で `record()` を 2 回呼んで両方が超えると「ほか N 件」の行が 2 つになることがある）。複製も `from → to` で書き、フォルダの削除はフォルダ 1 行で書く。(5) システムログは計画どおり `logEvent(0, 1, …)`。`send_errormail` を 1（情報以上）にしたサイトでは変更を伴う要求ごとにメールが 1 通届く（本文に操作やパスは入らない）。本体のほかの情報の記録（システムログの削除など）と同じ動きで、既定の 3 では届かない。(6) `readText` は読み取り専用の理由を `readOnlyReason`（`link`・`permission`・`encoding`）で返し、`saveText`・`newfile` は書き込めなければ 403 `not_writable`。(7) `js/grid.js` で、ダブルクリックの 1 回目のクリックが予約したプレビューが残り、開いた約 250ms 後に重なって開く既存の不具合を直した（main のピッカーでも選択の確定の上にプレビューが開いていた）。(8) コミットは、`saveText`・`newfile` が `ChangeLog` を使うので、記録 → テキストと新しいファイル → zip の順にした。

2026-11-05（M3、残したこと）: PHP 7.4 の `mb_detect_encoding()` は、EUC-JP のファイルを候補の並び（`SJIS-win,SJIS,EUCJP-win,EUC-JP`）の先頭の SJIS-win と判別し、化けて表示する（8.4 では正しく判別する）。読み取り専用で開くのでファイルは壊れない。旧ファイル管理も同じ並びで、並びを変えると半角カナだけの Shift_JIS が EUC と誤るので、変えていない。テキストのダイアログは、`evoshell:modalclose` などで外から閉じられたときに後始末（`release()`）が動かず、開き直せなくなりうる（M4 で `evoshell:unload` の後始末と一緒に、`openOverlay()` の `onClose` で直す）。管理操作ログの DB への書き込みに失敗すると、`logHandler` が HTML のエラーを出すので、ファイルは変わったのに応答が JSON にならない（`logHandler` の今の作りによる）。モーダルの断片（`modalFragment()`）には `manageExtras()` を足していないので、M4 の `managePane()` で必ず足す（足さないと `manage.js` の `editorFor()` が止まる）。

2026-11-05（M4）: 次のとおり決めた。`a=31` の画面は API と同じ `ManageScope::authorize()` で権限を確かめる。`evoshell:unload` と `evoshell:modalclose` の後始末は、受け手を付ける `js/overlay.js`・`js/contextmenu.js` が自分で外す。設定の確認のリンクの `folder` は相対パス全体を 1 回 `rawurlencode()` し、ルートの外なら空にする。ファイル管理のルートは `ManageScope::rootPath()` の 1 か所で決め、`ConfigCheck::fileManagerFolderFor()` もそれと `PathGuard::isWithinBase()` を使う。`readText` の応答から使われない `writable` を外し、`PathGuard` の `isBase()`・`hasParentReference()`（旧だけが使っていた）を消した。ピッカーのモーダルと `a=31` が同時に出ると `#evoFileBrowser` が 2 つになり、`getElementById` では a=31 の側しか取れずにピッカーが初期化されなかったので、入口ごとに自分の画面を初期化する形にした。7 テーマの `.file-disabled` と RevoStyle の `#uploader`（旧ファイル管理だけが使っていた規則）を消した（当てはまる要素が無いので見た目は変わらない）。

2026-11-05（M4、確認の後）: 本人の確認を受けて、次を足した。(1) 画面を開いたときの既定の状態（一覧の表示とツリーの開閉）を、入口ごとに `BrowserView::ENTRIES` の 1 か所で決め、`jsConfig()` が `defaults` として渡す（ピッカーはグリッド・ツリーを出す、ファイル管理は一覧・ツリーを畳む）。JS は入口を見て分けず、この値を使う。利用者が切り替えた状態は `js/prefs.js` が入口ごとに `localStorage` の `evoFb.<entry>.view`・`evoFb.<entry>.tree` に残し、次に開いたときはそちらが優先する（前の版の `evoFbView`・`evoFbTree` は読まない。ピッカーで切り替えた表示がファイル管理の既定を上書きしないため）。(2) 一覧表示のとき、エクスプローラーにならって見出しの行（名前・更新日時・種類・サイズ）を出し、押すとその列で並べ替える（同じ列をもう一度押すと逆順）。並べ替えの状態を変えるのはツールバーの選択と見出しの両方から呼ぶ `setSort()` の 1 か所で、選択肢に「種類順」を足した。フォルダーにも更新日時を出すため、一覧の応答のフォルダーの項目に `mtime` を足した（ピッカーの応答もキーが増える）。

2026-11-05（M4、残したこと）: `evoshell:unload`・`evoshell:modalclose` は、どの領域が閉じたかを区別しない。今は `a=31` の上で開けるモーダルが本体に無いので起きないが、そうしたモーダルを足すと、`a=31` のテキストのダイアログが確認なしに閉じて未保存の編集が消える（直すなら `teardownPane()` のイベントに `detail: { pane }` を付け、その領域の中のダイアログだけを閉じる）。同じく、ピッカーのモーダルと `a=31` が同時に出ると、ダイアログの ID とラジオの `name` が重なる（本体には同時に出る経路が無い）。

2026-11-04（M1、検証時）: 旧ファイル管理の一覧を出す応答に `Deprecated: substr(): Passing null to parameter #2 ($offset)` が出る。`ls()` の `$len` が未定義のまま使われているためで、main にもある。M4 で `files.dynamic.php` ごと消えるので直さない。

2026-11-04（計画時）: 2026-07-11 のファイルブラウザ刷新の ExecPlan（`.agent/plans/2026-07-11-file-browser-modal-rewrite.md`）は全段階が完了しているが、`archive/` へ移されていない。この計画では動かさない。

## Decision Log

2026-11-04 (yamamoto): 方針として次を合意した。メニューの「ファイル管理」（`a=31`）は、ファイルブラウザをメインの領域に表示する。モーダル（`browser.php?modal=1`）は、投稿画面などでファイルを選ぶ用途（ピッカー）に残す。段階は 4 つ（安全確認の共通化 → 管理モード → テキストの表示・編集と zip の展開と変更の記録 → `a=31` の差し替え）。左のリソースツリーとの連携は対象外とし、フォルダツリーを畳めることと、選択・移動を JS から受け取れるイベントだけを用意する。古い作法の互換は残さず、`files.dynamic.php` は移し終えたら削除する。

2026-11-04 (yamamoto) 決定: 共通化する処理と分離する処理。共通にするのは、管理モードとピッカーで処理の責務と振る舞いが一致するものだけに限る。ファイル操作の手順・記録・画面の部品も、まとめて共通にはせず、1 つずつ照らし合わせて決める。共通にするとモード（範囲）で分ける条件分岐が増えるもの、範囲ごとに答えの違う問いを 3 つ以上抱えるものは、範囲ごとの別の処理にする。範囲を見て分けてよいのは、`api.php` の入口で範囲のクラスを選ぶ 1 か所と、画面の入口（`browser.php` が `js/picker.js`、`a=31` が `js/manage.js`）だけ。共通の処理の中には `scope` や `mode` を見る `if` を書かない。範囲ごとに違うのが「許してよいか」の yes / no の 1 問だけのときは、その問いを範囲のクラスのメソッドにして、共通の処理から尋ねる（分岐ではなく、答えを受け取るだけになるため）。

  照らし合わせた結果は次のとおり。

  | 処理 | 責務 | 振る舞い | 決定 | 理由 |
  |---|---|---|---|---|
  | 権限の確認 | 同じ（使ってよいか） | 違う（ピッカーはセッション・`rb_webuser`・`use_browser`、管理モードは管理画面のセッションと `file_manager`） | 分ける（`PickerScope::authorize()`・`ManageScope::authorize()`） | 条件がまったく違い、一方を他方に流用すると穴になる |
  | パスの解決と安全確認（`PathGuard`・`PathResolver`） | 同じ | 同じ（ルートと守る場所を渡せば判定は同じ） | 共通 | 違いはルートの値だけで、手順は変わらない |
  | 一覧の読み取り（フォルダを読み、守る場所を除き、フォルダとファイルに分けて並べる） | 同じ | 同じ（何を出すかは `listsFile()` の 1 問） | 共通（`fbReadDir()`） | 違いは yes / no の 1 問だけ |
  | 一覧の項目の形 | 違う（ピッカーは選ぶための情報、管理モードは管理のための情報） | 違う（ピッカーは SetUrl のパス・サムネイル・画像の寸法、管理モードは書き込みの可否・隠しファイル） | 分ける（`fbPickerList()`・`fbManageList()`） | 共通にすると、項目ごとに範囲で分ける `if` が増える |
  | サムネイル（`thumb`） | ピッカーだけ | — | ピッカーだけの操作 | 管理モードでは作らない |
  | アップロード | 同じ（受けたファイルを置く） | 違う（同じ名前のとき、ピッカーは別の名前、管理モードは旧と同じく上書き。拡張子のリストと発火するイベントも違う） | 分ける（`fbPickerUpload()`・`fbManageUpload()`）。大きさの確認と `move_uploaded_file()` の呼び出しだけを共通の小さな関数（`fbStoreUpload()`）にする | 範囲ごとに違う問いが 3 つあり、共通にすると分岐が増える |
  | 新しいフォルダ（`mkdir`） | 同じ | 同じ | 共通 | 範囲で変わるものが無い |
  | 名前の変更（`rename`） | 同じ | 拡張子の確かめ方だけ違う（`isAllowedRename()` の 1 問） | 共通 | 違いは yes / no の 1 問だけ |
  | 削除・移動（`delete`・`move`） | 同じ | 同じ | 共通 | 範囲で変わるものが無い |
  | コピー（`copy`） | 同じ | 拡張子の確かめ方だけ違う（`isAllowedCopy()` の 1 問） | 共通 | 違いは yes / no の 1 問だけ |
  | ダウンロード（`download`） | 同じ | 対象にしてよいかだけ違う（`listsFile()` の 1 問） | 共通 | 違いは yes / no の 1 問だけ |
  | 画像の保存（`saveImage`） | 同じ | 拡張子の確かめ方だけ違う（`isAllowedNewName()` の 1 問） | 共通 | 違いは yes / no の 1 問だけ |
  | テキストの表示・保存、新しいファイル、zip の展開 | 管理モードだけ | — | 管理モードだけの操作（`lib/ManageActions.php`） | ピッカーには要らない |
  | 変更の記録（`ChangeLog`） | 同じ（成功した変更を残す） | 同じ（範囲の印は `logLabel()` の値を書くだけ） | 共通 | 書く先と書き方が同じ |
  | 画面の HTML の本体（一覧・ツリー・ダイアログの枠） | 同じ | 同じ | 共通（`BrowserView::markup()`） | 違うのは足すボタンやダイアログで、それは外側で足す |
  | 画面の外側 | 違う | 違う（モーダルの断片・単独のページ・メインの領域） | 分ける（`modalFragment()`・`standalonePage()`・`managePane()`） | 置かれる場所と見出しが違う |
  | JS の一覧・ツリー・ドラッグ・ダイアログ・フォーカス・右クリックメニューの描画 | 同じ | 同じ（何を並べるかは受け取る） | 共通 | 範囲で変わるものが無い |
  | JS の、ファイルを開いたときの動き・プレビューのボタン・右クリックメニューの項目・アップロードの前の確かめ | 違う | 違う（ピッカーは選んで閉じる、管理モードは表示・編集。管理モードは上書きの前に確かめる） | 分ける（`js/picker.js`・`js/manage.js` が `behaviors` として渡す） | 用途そのものの違い |
  | JS のテキストのダイアログ | 管理モードだけ | — | `js/textEditor.js`（`js/manage.js` だけが読む） | ピッカーには要らない |
  | 範囲が出す CustomEvent（M4） | 同じ | 同じ | 共通 | 出す値に範囲の名前を入れるだけ |

  代わりの案として、範囲の違いを 1 つのクラスに集め、`isManage()` などで分ける案（最初の計画）と、ファイル操作をすべて共通にして範囲のクラスに細かく尋ねる案（2 つ目の計画）を考えた。前者は操作のあちこちに範囲の `if` が散らばり、権限の確認も 1 つのメソッドに混ざる。後者は一覧とアップロードで、範囲のクラスへの問いが増えて実質の分岐になる。どちらも採らない。管理モード用に API のファイルを丸ごと別に作る案は、責務と振る舞いが同じ操作まで 2 か所に分かれるので採らない。

2026-11-04 (yamamoto) 決定: 管理操作ログの行数の抑え方。管理操作ログには 1 回の要求で 50 行までを書き、それより後のファイルは、システムログの同じ要求の行から追えるようにする。2 つのログを結ぶ手がかりには、システムログが要求ごとにすでに付けている `trace_id`（`EvoLogger` が `X-Request-ID` か乱数から作り、同じ要求の行すべてに入る）を使い、新しい ID は作らない。

  確かめたこと: システムログ（`temp/logs/system/YYYY/MM/`）は、`logEvent()` の情報（type 1）も必ず書き、`context` の配列を件数や長さで切り詰めない（`EvoLogger::sanitizeValue()` は値を伏せるだけ）。1 つのファイルが `system_log_max_bytes`（既定 100MB）を超えると、`.1`〜`.10` の世代に回して残す。管理操作ログは `manager_log_limit`（既定 2000 行）を超えると、`manager_log_trim`（既定 100 行）ずつ古い行から消える。つまり、50 行で打ち切った分を、今の書き方の管理操作ログからたどる手がかりは無く、システムログの行にパスが全件あっても、どの行かを見つけにくい。

  設計: `EvoLogger` に、今の要求の `trace_id` を返す公開の静的メソッド `EvoLogger::traceId()` を足す（中身は今の `getTraceId()` と同じで、`collectCommonContext()` もこれを使う。ID の作り方の正本を 1 つに保つ）。`ChangeLog::record()` は、50 行を超えたとき最後の行を `ほか N 件（trace_id=<trace_id>）` にする。50 行に収まるときも、システムログの同じ要求の行に `trace_id` が入るので、管理操作ログの日時・利用者から、システムログの行を引ける。全件は `php evo log:search <trace_id>` で、システムログの 1 行（`context.paths` に全件、`context.operation`・`context.scope`、`context.user`、`context.request.ip`）として取り出せる。

  50 行にした理由: 1 回の zip の展開や一括の削除で数千件の行を書くと、`manager_log_limit` の 2000 行を 1 回で使い切り、ほかの利用者や別の操作の記録まで押し出す。そうなると、全体の追いやすさがかえって下がる。50 行は既定の上限の 2.5% で、手で選んで行う操作（数件〜数十件）なら全件が管理操作ログに載る。

  代わりの案: (a) 上限を設けず、管理操作ログに全件を書く。1 回の操作でほかの記録を押し出すので採らない。(b) 1 回の要求で 1 行の要約だけを書く。管理操作ログで「どのファイルに」が分からなくなり、補足の指示（a=13 で誰が・いつ・どのファイルに・何をしたか）に合わないので採らない。(c) 管理操作ログの列を足す、または別の表を作る。表の形を変えるマイグレーションが要り、この計画の範囲を超えるので採らない。

  限界: 51 件目より後を追えるのは、システムログのその月のファイルが残っている間だけ。システムログの世代や `temp/logs/` を消すと追えない。この点は PR の本文に書く。

2026-11-04 (yamamoto) 決定: 管理モードとピッカーで今の振る舞いが違うものは、無理にそろえず、範囲ごとにそれぞれの今の振る舞いを保つ（目的は旧ファイル管理の置き換えで、ピッカーの利用者の動きも変えない）。当てはまるのは、アップロードで同じ名前があるときの扱い（管理モードは上書き、ピッカーは別の名前）と、`upload_maxsize` が空のときの上限（管理モードは本体では制限せず PHP の上限だけ、ピッカーは 5,000,000 バイト）。上限は `BrowserScope::maxUploadSize()` で範囲ごとに返す。`ManageScope` は `upload_maxsize` が正の数ならその値、空なら `null`（本体では制限しない）。`PickerScope` は `BrowserConfig::maxUploadSize()`。旧は、値があるときにフォームの `MAX_FILE_SIZE` で PHP に断らせていた。新は fetch で送るので `MAX_FILE_SIZE` を使わず、サーバーで `$_FILES` の大きさと比べて断る（同じ値で断るので、利用者から見た動きは同じ）。既定値をそろえる（たとえば両方を PHP の上限に合わせる）案は、どちらかの今の動きを変えるので、この計画では採らない。

2026-11-04 (yamamoto) 決定: 管理モードを決める仕組み。画面の起動と API の認可は、別のものとして扱う。起動: `a=31` の画面だけが、サーバーで作る JS の設定（`data-config`）の `apiParams` に `scope: 'manage'` を入れ、JS（`js/api.js`）はすべての要求にこれを付ける。ピッカーの画面（`browser.php`）は、URL に何を付けても `scope` を設定に入れない。これは画面をどの範囲で出すかを決めるだけで、権限の確認ではない。認可: API は、要求が `a=31` の画面から来たかを確かめない（確かめる手段も持たない）。`scope=manage` の要求を受けるたびに、`ManageScope::authorize()` で管理画面のセッションと `file_manager` の権限を確かめ、通れば管理モードで処理し、通らなければ 403 で断る（ピッカーへは戻さない）。そのため、`file_manager` の権限を持つ人は、`a=31` を開かずに API へ直に `scope=manage` を付けて呼んでも、管理モードの操作ができる。これは設計どおりとする。その人は `a=31` の画面で同じ操作ができるので、直に呼べても守るべきものは増えない（管理モードに入れるかの境目は権限で、入口の画面ではない）。権限の無い人（ピッカーだけを使う人、ウェブユーザーのセッション）は、`scope=manage` を付けても 403 になる。代わりの案は 2 つ。(a) `a=31` を開いたときにセッションへ使い捨ての合言葉を置き、API はそれと照合する。権限のある人が自分で付ける場合と比べて守れるものが増えず、タブを複数開いたときの扱いが増える。(b) 管理モード用の入口を別に作る。共通の操作が 2 か所に分かれる。どちらも採らない。

2026-11-04 (yamamoto) 決定: 管理モードとピッカーの権限の確認は分けて持つ。`api.php` の入口が `PickerScope::authorize()` か `ManageScope::authorize()` のどちらかを呼び、それぞれのクラスが自分の条件だけを確かめる（Decision Log の「共通化する処理と分離する処理」）。ピッカーは今の条件（管理画面のセッション、またはウェブユーザーのセッションと `rb_webuser`。加えて `use_browser`）を変えない。管理モードは管理画面のセッションと `file_manager` だけを見て、ウェブユーザーのセッションは認めず、`use_browser` も見ない。管理モードだけの操作（`readText`・`saveText`・`newfile`・`unzip`）は、ピッカーの範囲では 403 にする。一方の条件をもう一方に流用しない（`file_manager` を持つ人がピッカーで管理モードの範囲を見られる、`use_browser` を切ると `a=31` が使えなくなる、のどちらも起こさない）。

2026-11-04 (yamamoto) 決定: 旧ファイル管理でできた操作は、意図せず使えなくしない。Context and Orientation の対応表の各行を M4 の前に照らし合わせ、旧でできて新でできないものが残っていれば M4 に入らない。旧と見た目や手順が変わるもの（守る場所を灰色で出さずに隠す、テキストの保存がダイアログになる、`a=31&mode=drill&path=` が効かなくなる）は、PR の本文に書いて知らせる。

2026-11-04 (yamamoto) 決定: 管理モードでの拡張子の許可リストは、旧ファイル管理と同じ水準を保つ（既存の設定を守る）。新しい名前のファイルを作る操作（アップロード、新しいファイル、zip の展開の各エントリ）は、`upload_files`・`upload_images`・`upload_media` を合わせた許可リスト（旧 `uploadablefiles()`）で確かめる。名前の変更は、拡張子が変わらないなら許し、変わるときは同じ許可リストで確かめる（旧ファイル管理には名前の変更が無く、`.php` のファイルの名前を変えられないと困るため）。一覧・表示・編集・削除・移動・コピー・ダウンロードは拡張子を問わない（コピーは元と同じ拡張子になる）。zip の展開では、エントリを 1 件ずつ、名前の無害化（`sanitizeFilename()`）・許可リスト・ルートの中か・守る場所でないかで確かめ、通らないものは飛ばして名前を返す。代わりの案は、管理モードでは許可リストを一切当てない（`file_manager` の権限を持つ人は `.php` を編集できるので、拡張子で止める意味は薄い）が、既存の設定を守るため採らない。

2026-11-04 (yamamoto) 決定: 変更の記録は、管理操作ログ（`a=13`、`manager_log`）に個々のファイル操作を残し、あわせてシステムログ（`evo()->logEvent()`）にも書く。管理操作ログの列で「誰が・いつ・どのファイルに・何をしたか」が分かるようにする。誰が: `internalKey` と `username`（`logHandler` がログイン中の利用者を入れる）。いつ: `timestamp`。どのファイルに: `itemname` にルートからの相対パス（移動と名前の変更は `from → to`）。UTF-8 でない名前は旧と同じく `mb_scrub()` し、255 文字を超えるものは先頭を省いて末尾を残す。何をしたか: `message` に操作の名前（`Uploaded File`・`Created Folder`・`Created File`・`Renamed`・`Deleted File`・`Deleted Folder`・`Moved`・`Copied`・`Saved Image`・`Modified File`・`Unzipped`）。`action` は今の旧の不具合（文字列を渡して 0 になる）を直して `31` を入れ、`itemid` は `x`。何件もまとめた操作（複数の削除・移動・コピー、zip の展開）は 1 件ごとに 1 行とし、1 回の要求で 50 行を超える分は書かずに、最後に「ほか N 件（trace_id=…）」の 1 行を足す。システムログには、範囲（`scope`）・操作の名前・全件のパスを入れた 1 行を書く。51 件目より後の追い方と、50 行にした理由は、次の「管理操作ログの行数の抑え方」に書く。記録するのは成功した変更だけで、表示（旧の「Viewing File」）は記録しない（一覧やプレビューのたびに増え、変更の追跡には要らないため）。ピッカーの範囲でも同じ関数で記録し、`message` の先頭に範囲が分かる印（`[picker]`）を付ける。代わりの案は、システムログだけに書く（`a=13` に個々の操作が載らない）だったが、管理操作ログで追えることを優先して採らない。

2026-11-04 (yamamoto/Claude): 管理モードのアップロードでは、旧ファイル管理と同じ `OnFileManagerUpload` を発火し、ピッカーでは今までどおり `OnFileBrowserUpload` を発火する。旧ファイル管理に付けたプラグインが動き続けるようにするため（AGENTS.md「既存フック位置を無視して新イベントを追加する」を避ける）。渡す値は `filepath`（保存先のフォルダの確かめた実パス）と `filename`。旧ファイル管理ではリクエストで受けたパスだったので、`assets/docs/events-and-plugins.md` にその違いを書く。

2026-11-04 (yamamoto/Claude): 管理モードは設定 `use_browser` に左右されない（`file_manager` の権限だけで決める）。旧ファイル管理は `use_browser` を見ていなかったため。ウェブユーザーのセッションは管理モードを使えない（`hasPermission()` は管理画面のセッションの権限しか見ない）。

2026-11-04 (yamamoto/Claude): 守る場所の判定（旧 `proteted_path()` と `is_protected_path()`）は、管理モードだけでなくピッカーにも当てる。判定を 1 か所に置いて両方が通る形にするため（SSOT）。ピッカーのルートは既定で守る場所と重ならないので、普通のサイトでは動きは変わらない。`rb_base_dir` をサイトのルートにしているサイトでは、権限によって `manager/` などがピッカーから見えなくなる。

2026-11-04 (yamamoto/Claude): 守る場所は一覧とツリーに出さない（旧ファイル管理は灰色で名前だけ出していた）。出すと、一覧・ツリー・移動のダイアログ・右クリックメニューのそれぞれで「開けない・操作できない項目」の扱いが要り、どれか 1 つの漏れが操作の入口になるため。API は一覧に出さないだけでなく、名前を指定された操作でも断る。

2026-11-04 (yamamoto/Claude): 管理モードの一覧は、サムネイルを作らない（`thumb` を返さず、一覧はファイルの種類のアイコンを出す。SVG も同じ）。`vendor` のように数の多いフォルダを開いたとき、`temp/thumbs/` へ大量に書き出さないため。画像の寸法（`getimagesize()`、ファイルの先頭だけを読む）は今までどおり返し、プレビューと画像の編集は使えるようにする。ドットで始まる名前（`.htaccess` など）も出す（旧ファイル管理と同じ）。

2026-11-04 (yamamoto/Claude): 管理モードのルートがサイトのルート（`MODX_BASE_PATH`）の外にあるときは、ファイルの公開 URL が無いので `url` を `null` にする。その場合、画像のプレビューは出さず、取り出しはダウンロード（API の `download`）で行う。

2026-11-04 (yamamoto/Claude): テキストの表示・編集は、ファイルブラウザの中のダイアログ（`js/overlay.js` を使う、画像の編集と同じ作り）で行う。中身は、エレメントの編集画面と同じコードの欄（`div.code-editor` の見出しと「行を折り返す」の切り替え、`textarea.phptextarea`）で出し、テーマの CSS をそのまま使う。対象の拡張子は旧 `is_editable_file()` と表示用の一覧を合わせたもの（`txt`、`php`、`tpl`、`shtml`、`html`、`htm`、`xml`、`js`、`css`、`pagecache`、`htaccess`、`sample`）にし、`BrowserConfig::TEXT_EXTENSIONS` の 1 か所に置く。`.htaccess` のような拡張子だけの名前は、名前全体を拡張子として照合する（旧と同じ）。`json`・`md` などは足さない（必要になったら、この定数に足すだけで済む）。

2026-11-04 (yamamoto/Claude): テキストとして開くのは 2MB（`BrowserConfig::TEXT_MAX_BYTES = 2097152`）までで、NUL を含むものは開かない（バイナリとみなす）。UTF-8 でない中身（Shift_JIS・EUC-JP など）は、旧ファイル管理と同じく変換して表示するが、保存はさせない（表示のみ）。変換した UTF-8 で書き戻すと、ファイルの文字コードが黙って変わるため。保存のときは、開いたときの中身のハッシュ（`sha1`）を送り、今のファイルと違えば 409 で断る（FTP などで同時に書き換えられたものを上書きしないため）。改行は、保存する前のファイルが CRLF なら CRLF に、そうでなければ LF にそろえて書く。

2026-11-04 (yamamoto/Claude): zip の展開は管理モードだけにし、旧 `unzip()` とその下請け（`unzip_entry_name()`・`unzip_entry_path()`・`unzip_unique_path()`・`unzip_free_name()`・`unzip_copy_entry()`）を `lib/ZipService.php` の `extract()` へ移す。動きは旧と同じ（全エントリを先に確かめ、ルートの外・守る場所へ書くものが 1 つでもあれば何も展開しない。許可リストに無い拡張子は飛ばす。展開先に前からあるファイルは上書きする。Shift_JIS の名前を UTF-8 にする。展開後の大きさに上限を設けない）。新しいファイル（空のテキストファイルを作る）も管理モードだけにする。

2026-11-04 (yamamoto) 決定: zip の展開の安全確認。旧 `unzip()` の確認を点検し、足りない所を足す。

  旧で確かめていること（そのまま `ZipService::extract()` へ移す）: (1) 展開の前に全エントリを照らし合わせ、`\` を `/` に置き換えた生の名前に `..` の要素があるもの、展開先がルートの外になるもの（`normalize_path()` が、存在する部分を `realpath()` で解決するので、展開先の中の、リンク先のあるシンボリックリンクがルートの外を指す場合も含む）、守る場所に当たるものが 1 件でもあれば、何も展開しない。(2) 名前を `/` で要素に分け、空と `.` の要素を捨て、要素ごとに `sanitizeFilename()` で無害化する（前後のドットと空白を落とすので、無害化した後に `..` は残らない。先頭の `/` やドライブの名前はルートの中の相対パスになる）。(3) ファイルの拡張子を、無害化した後の名前で、合わせた許可リスト（`checkExtension()`）と照らし合わせ、通らないものは飛ばす。(4) zip の中のシンボリックリンクは、リンクとしては作らず、中身（リンク先の文字列）を普通のファイルとして書く（`getStream()` と `fopen()` で写すため）。(5) 同じ zip の中で無害化した名前が重なったら `(1)` などを付け、展開先に前からあるファイルは上書きする。(6) ストリームで写すので、大きなエントリでもメモリに載せない。

  足りない所と対策:
  - ぶら下がったシンボリックリンク: 展開先に、リンク先の無いシンボリックリンクがあると、`realpath()` が解決できず、(1) では「まだ無いパス」として通る。その後の `fopen($target, 'wb')` はリンクをたどり、ルートの外にファイルを作れる。対策として、事前の照らし合わせで、展開先のパスの各要素のうち存在するもの（`file_exists() || is_link()`）がシンボリックリンクなら、何も展開しない。
  - 照らし合わせから書き込みまでの間: 事前の照らし合わせの後に名前を付け直す（(5)）ので、書くパスは照らし合わせたパスと同じとは限らない。また、照らし合わせと書き込みの間にリンクを置かれる余地がある。対策として、エントリを書く直前にも、付け直した後のパスを `PathResolver::resolveNewChild()`（ルートの中か・守る場所でないか）で確かめ、書く先とその親がシンボリックリンクでないことを確かめる。通らないものは書かず、`failed` に入れる（事前の照らし合わせは通っているので、全体は止めない）。上書きのときは、置き換えるものが普通のファイルであること（`is_file() && !is_link()`）を確かめる。
  - パーミッション: 旧は展開の間 `umask(0)` にし、`mkdir($dir, 0777, true)` と `fopen()` で作るので、展開したフォルダは 0777、ファイルは 0666（誰でも書き込める）になる。対策として `umask()` を変えず、作ったフォルダに `newFolderPermissions()`、新しく作ったファイルに `newFilePermissions()` を当てる（既定は 0755・0644。旧ファイル管理の新しいフォルダ・新しいファイル・アップロードと同じ値）。上書きしたファイルのパーミッションは変えない。互換性への影響: 展開したものが、ほかの利用者（FTP の利用者など）から書き込めなくなることがある。旧の新しいフォルダ・アップロードと同じ扱いにそろうだけなので採り、PR の本文に書く。
  - zip そのもの: 展開する zip は `resolveFile()` で、ルートの中にあり、守る場所でなく、シンボリックリンクでないものに限る（旧はパスの照らし合わせだけで、リンクかは見ていない）。展開先は zip と同じフォルダで、`resolveDir()` を通す。
  - PHP 7.4 の `basename()`: 旧 `normalize_path()` は `basename()` を使う。`api.php` の注にあるとおり、PHP 7.4 ではロケールによってマルチバイトの名前を正しく扱えない（計画時に `php:7.4-cli` の `LC_ALL=C` で `basename('/app/content/日本語')` が `content` を返すことを確かめた）ので、まだ無い日本語の名前を含むパスを正しく畳めない。親の側は `dirname()` と `realpath()` で解決するので、ずれるのは最後の名前だけで、守る場所の判定をすり抜ける形にはならないが、照らし合わせを正しくするため、M1 で `PathGuard::normalize()` に移すとき `strrpos()` と `substr()` で最後の要素を取る形に変える（M1 で変える 2 点目）。

  採らないもの: 展開後の大きさとエントリの数の上限（上の「zip の展開後の大きさ」）。zip の中のエントリのパーミッション（外部属性）を当てること（旧も当てていない。実行権の付いたファイルを作らないため）。

2026-11-04 (yamamoto) 決定: zip の展開後の大きさ。上限は設けず、旧と同じにする。一度は `BrowserConfig::ZIP_MAX_BYTES`（256MB）を当てる案を書いたが、根拠を確かめて取り下げた。

  根拠の確認: 256MB は、2026-11-01 のファイルブラウザ拡張（`.agent/plans/archive/2026-11-01-file-browser-kcfinder-extension.md` の Decision Log）で、zip を「作る」まとめてのダウンロードのために決めた値である。200MB を zip にしたときのメモリと圧縮の時間を測って決めたもので、展開（書き出す量・ディスクの空き）とは関係が無い。展開に当てる根拠にはならない。

  既存のアップロードの上限との関係: アップロードの上限（`upload_maxsize`）は、送られてくる 1 つのファイルの大きさを抑えるもので、zip 自体はこれを受ける。展開後の大きさは、旧ファイル管理でもファイルブラウザでも抑えていない（FTP で置いた zip を展開することもできる）。展開後に上限を当てると、アップロードの上限とは別の、利用者が設定で変えられない新しい上限が 1 つ増える。

  必要性: 展開できるのは `file_manager` の権限を持つ人だけで、その人は `.php` を作って書けるので、ディスクを埋めることも、それ以上のこともすでにできる。zip 爆弾を防ぐ意味は薄い。zip の見出しに書かれた大きさは作った人が偽れるので、確かめても確実には止められない。

  互換性への影響: 上限を当てると、旧で展開できた大きな zip（サイトのバックアップ、画像の一式など）が展開できなくなる。これは「旧でできた操作を意図せず使えなくしない」に反する。

  以上から上限は設けない。ディスクがいっぱいになって途中で失敗したときは、旧と同じく、展開できたものと失敗したものを返す。代わりの案として、展開の前にディスクの空き（`disk_free_space()`）と比べる案も考えたが、見出しの大きさが偽れるうえ旧に無い動きなので、この計画では採らない。

2026-11-04 (yamamoto/Claude): `a=31` の画面は新しい `manager/actions/element/file_manager.static.php` に置き、`manager/index.php` の `case 31` からこれを読む。画面の HTML は `lib/BrowserView.php` にまとめ、`browser.php`（ピッカーのモーダルと単独のページ）と `a=31` の両方がこれを使う（今は `browser.php` に直に書かれている）。

2026-11-04 (yamamoto/Claude): フォルダツリーの開閉は、ツールバーのボタンで切り替え、状態を `localStorage` の `evoFbTree` に残す（表示の切り替え `evoFbView` と同じ作り）。ピッカーでも使える。外の JS 向けには、ファイルブラウザの要素（`#evoFileBrowser`）から、泡立つ（`bubbles: true`）`CustomEvent` を 2 つ出す。`evofb:navigate`（一覧を読み終えたとき。`detail` は `{ scope, folder }`）と `evofb:selectionchange`（選択が変わったとき。`detail` は `{ scope, folder, names }`）。外からファイルブラウザを操作する入口（フォルダを開かせるなど）は、リソースツリーとの連携を作るときに足す（今は使う所が無い）。

2026-11-04 (yamamoto/Claude): `a=31` の URL は `index.php?a=31&folder=<ルートからの相対パス>&select=<ファイル名>` にする（ピッカーの `initialFolder` / `initialSelect` と同じ）。旧の `mode=drill&path=<絶対パス>` は受け付けない（互換を残さない。付いていてもルートが開く）。

2026-11-04 (yamamoto/Claude): M1 は、旧ファイル管理の動きを変えない置き換え（関数を `PathGuard` へ移すだけ）を基本にし、変えるのは「中に守る場所を含むフォルダの削除を断る」と「`normalize()` で最後の要素を `basename()` で取らない（PHP 7.4 でマルチバイトの名前を落とさない）」の 2 点だけにする。ピッカーの API が `PathGuard` を使い始めるのは M2 で、範囲の導入と同時にする（M1 でピッカーの動きを変えない）。

## Outcomes & Retrospective

2026-11-05: M1〜M4 の実装と検証を終えた。メニューの「ファイル管理」（`a=31`）はファイルブラウザの管理モードになり、旧ファイル管理（`files.dynamic.php`）と、旧だけが使っていた言語キー 54 個・テーマの CSS の規則を消した。安全確認は `PathGuard` に、範囲の違いは `PickerScope`・`ManageScope` の yes / no の問いにまとめ、共通の処理に範囲を見る `if` は無い。旧より良くなった点: 中に守る場所を含むフォルダの削除を断る、zip の展開で外を指すリンクをたどらずパーミッションを設定の値にする、テキストの保存で改行を変えず競合を 409 で断る、Shift_JIS のファイルを化けたまま保存しない、権限を要求ごとに読み直す、成功した変更を管理操作ログに 1 件ごとに残す、同じ名前のアップロードの前に確かめる。ピッカーの応答はキー（`writable`・`image`・`index`）が増えただけで、TV の欄・QuickManager・TinyMCE から main と同じく選べる。

振り返り: 自己レビューで見つかった中の指摘（入口のグローバルの取り合い、プレビューが `.php` を `<img>` で読む、古い権限、上書きの確認の名前のずれ）は、どれも共通の部品を 2 つの範囲で使い分けて初めて起きる問題だった。計画の段階で「同じ部品を 2 つの入口から交互に使う」場合を Validation に入れておけば、もっと早く見つかった。M1 の PR のマージを待たずに M2〜M4 をブランチを積んで進めたので、マージの後は順に main へ載せ直して PR にする。

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
| アップロード | 1 件ずつ。合わせた許可リスト。`$modx->move_uploaded_file()`、`OnFileManagerUpload`、`logFileChange('upload')` | `upload`（複数）。種類の許可リスト。`OnFileBrowserUpload` | M2: 管理モードは合わせた許可リストと `OnFileManagerUpload` |
| 新しいフォルダ | `mode=newfolder`。`sanitizeFilename()`、`newFolderPermissions()` | `mkdir` | M2: 守る場所の確認を通す |
| 新しいファイル | `mode=newfile`。合わせた許可リスト、空のファイル、`newFilePermissions()` | なし | M3: `newfile`（管理モードだけ） |
| zip の展開 | `mode=unzip`（`unzip()`） | なし | M3: `unzip`（管理モードだけ） |
| ファイルの削除 | `mode=delete`（1 件）。`logFileChange('delete')` | `delete`（`files[]`） | M2: 守る場所の確認を通す |
| フォルダの削除 | `mode=deletefolder`（`rrmdir()`。ルートは消さない） | `delete`（`folders[]`。`fbDeleteTree()`、リンクはたどらない） | M1: 旧で中に守る場所を含むものを断る。M2: 新でも同じ |
| 名前の変更・移動・コピー | なし | `rename`・`move`・`copy` | M2: 管理モードでも使える。拡張子が変わる名前の変更だけ許可リストで確かめる |
| 画像の編集 | なし | `saveImage` | 管理モードでも使える |
| パーミッション | 新しいフォルダ・ファイルに設定の値を当てるだけ（変える画面は無い） | `mkdir`・`upload`・`copy` で同じ | 変えない |
| 変更の記録 | `logFileChange()`（表示・編集・アップロード・削除を `manager_log` へ。`action` は 0 になる） | 失敗だけ `evo()->logEvent()` | M3: 成功した変更を管理操作ログ（`action=31`、1 件ごとに 1 行）とシステムログの両方へ |
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

実装時の注意（互換性）:

- 旧でできた操作を落とさない。対応表の各行は、M4 に入る前に新の画面で 1 つずつ確かめる（Validation の M4 の表）。旧にだけあった次の点は、新でも同じにするか、変わることを PR の本文に書く: ドットで始まる名前（`.htaccess` など）が一覧に出ること、`.htaccess` などのテキストを編集できること、ルートの外のフォルダを `filemanager_path` に指定できること、書き込めないフォルダで状態が分かること、zip の展開で前からあるファイルを上書きすること。
- 管理モードのアップロードは旧と同じく同じ名前のファイルを上書きする。上書きの前に画面で確かめる（旧には確かめが無かった）。上限は旧と同じ（`upload_maxsize` が空なら本体では制限せず、PHP の上限だけが効く）。ピッカーの上限（空なら 5,000,000 バイト）は変えない。上書きの前の確かめが増えることを PR の本文に書く。
- `OnFileManagerUpload` に付けたプラグインは動き続ける必要がある。渡す `filepath` が「リクエストで受けた値」から「確かめた実パス」に変わるので、`assets/docs/events-and-plugins.md` に書く。
- 旧の URL（`a=31&mode=drill&path=...`、`mode=view`・`mode=save`）は効かなくなる。本体の中のリンクは M4 で置き換えるが、利用者がブックマークや自作のモジュールで使っている場合に備え、PR の本文に書く。互換のための読み替えは残さない（`path` の絶対パスを受けると、ルートの外を指す値を受け取る口が残るため）。
- 管理操作ログの行が増える。1 回の要求で 50 行までにし、`manager_log_limit`・`manager_log_trim` によるログの回転で古い記録が押し出されることと、51 件目より後はシステムログ（`trace_id` で引く）にだけ残ることを PR の本文に書く。
- ピッカーの応答と振る舞いは変えない（`list` の応答は `writable` と、表示できる画像の項目の `image` が増えるだけ。upload の応答は `index` が増えるだけ）。TV の欄・TinyMCE・QuickManager から開くピッカーを、M2 と M4 で main と比べる。
- PHP 7.4 で動く書き方にする（共用型、`str_starts_with()`、`match` を使わない）。

実装時の注意（セキュリティ）:

- 範囲はサーバーで決め、権限はリクエストのたびに確かめる。`scope=manage` を手で付けても、`file_manager` が無ければ 403。管理モードの確認ではウェブユーザーのセッションを認めない。
- 管理モードのルートはサイト全体になりうる。すべてのパスを `realpath()` で解決してルートの中かを確かめ、守る場所（`PathGuard::isProtected()`・`containsProtected()`）を、読む操作（一覧・表示・ダウンロード）にも書く操作にも当てる。シンボリックリンクはたどらない（削除・コピー・テキストの保存・zip の展開）。
- zip の展開は、エントリを 1 件ずつ確かめる。名前の無害化（`sanitizeFilename()`）、`..` や絶対パスを含まないか、展開先がルートの中か、守る場所でないか、拡張子が許可リストにあるか、を見る。ルートの外・守る場所へ書くものが 1 件でもあれば何も展開しない（旧と同じ）。許可リストに無いものは飛ばして名前を返す。シンボリックリンクのエントリは作らない。展開先にあるぶら下がったシンボリックリンクで止め、書く直前にもパスを確かめ直し、パーミッションは設定の値にする（Decision Log の「zip の展開の安全確認」）。展開後の大きさには上限を設けない（旧と同じ。理由は Decision Log の「zip の展開後の大きさ」）。
- 書き込みの操作はすべて POST と CSRF トークン（`checkCsrfToken()`）で受ける。`readText` も、応答が JSON で `Content-Type: application/json` と `X-Content-Type-Options: nosniff` を付ける（旧の `mode=view` のように HTML に混ぜない）。
- テキストの表示では、中身を画面に入れるとき `textContent`（または `textarea` の `value`）を使い、`innerHTML` を使わない。ファイル名も同じ。
- ルートが公開の範囲の中にあるとき、管理モードで作ったファイル（`.php` など）はそのままサイトで動く。拡張子の許可リストを既存の設定のまま当てるのはこのため（Decision Log）。
- 記録の `itemname` と `message` は `logHandler` が `db()->escape()` してから書く。`ChangeLog` の側で前もってエスケープしない（二重になる）。

DocumentParser の処理の流れ（`executeParser()` / `prepareResponse()` / `parseDocumentSource()` / `postProcess()`）には、どの段階も関わらない。管理画面とファイルブラウザの API だけを変える。キャッシュは、サイトのキャッシュ（`temp/cache/`）に関わらない。サムネイルのキャッシュ（`temp/thumbs/`）は、管理モードでは作らない。新しく足す `js/textEditor.js` には、ほかの `js/*.js` と同じくキャッシュバスターが付かないが、`manager/media/browser/evo/.htaccess` の `no-cache` で古いものが使われない。

## Plan of Work

### M1: 安全確認の共通化

`manager/media/browser/evo/lib/PathGuard.php` を新しく作り、旧ファイル管理の安全確認の関数をメソッドとして移す。中身（判定の仕方）は変えない。ただし `normalize()` では、最後の要素を `basename()` ではなく `strrpos($path, '/')` と `substr()` で取る（Decision Log の「zip の展開の安全確認」の PHP 7.4 の `basename()`）。

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

共通にするか分けるかは、Decision Log の「共通化する処理と分離する処理」の表に従う。範囲を見て分けるのは、`api.php` の入口で範囲のクラスを選ぶ 1 か所と、画面の入口の JS（`js/picker.js` か `js/manage.js`）だけ。共通の処理の中には、`scope` や `isManage()` で分ける `if` を書かない。

`lib/BrowserScope.php` は、範囲ごとに答えが変わる yes / no の問いと、範囲ごとの値だけを並べた interface にし、`lib/PickerScope.php` と `lib/ManageScope.php` がそれぞれの答えを持つ。範囲が何かを尋ねるメソッド（`isManage()` など）は置かない。

- `static authorize(DocumentParser $modx, BrowserConfig $config)`（各クラス）: その範囲を使ってよいかを確かめ、よければインスタンス、だめなら失敗の理由（コード・言語キー・状態）を返す。`PickerScope` は今の条件（管理画面のセッション、またはウェブユーザーのセッションと `rb_webuser`。`use_browser`。`type` が `images` / `media` / `files` のどれか）をそのまま移す。`ManageScope` は管理画面のセッション（`sessionv('mgrValidated')`）と `$modx->hasPermission('file_manager')` だけを見る。2 つの条件は互いに流用しない。lib から `api.php` の `fbError()` は呼ばず、`api.php` 側で `fbError()` にする。
- `baseDir()`: ルート。`PickerScope` は `rb_base_dir` の下の `type`、`ManageScope` は `config('filemanager_path') ?: MODX_BASE_PATH`。`PathResolver` はこの値だけを受け取り、`type` を知らなくてよくなる。
- `guard()`: `PathGuard`（共通）。ルートは `baseDir()`、守る場所は `PathGuard::protectedPathsFor($modx)`。
- `listsFile($name)`: 一覧に出し、ダウンロードの対象にしてよいか。`PickerScope` は種類の許可リストにあり、ドットで始まらないもの。`ManageScope` はすべて。
- `isAllowedNewName($name)`: 新しい名前のファイルを作ってよい拡張子か。`PickerScope` は `BrowserConfig::isAllowedFilename($type, $name)`、`ManageScope` は合わせた許可リスト（`BrowserConfig::allowedExtensionsAll()` を足す）。
- `isAllowedRename($oldName, $newName)`: `PickerScope` は今の `isAllowedFilename($type, $newName)`、`ManageScope` は拡張子が同じなら真、違えば `isAllowedNewName($newName)`。
- `isAllowedCopy($sourceName, $newName)`: `PickerScope` は今の確認、`ManageScope` は元の名前がドットで始まらなければ真（元と同じ拡張子のため。ドットで始まる名前は `sanitizeFilename()` が先頭のドットを落とすので、黙って別の名前にせず断る。M3 で決めた）。
- `maxUploadSize()`: アップロードの上限（バイト）か `null`（本体では制限しない）。`PickerScope` は `BrowserConfig::maxUploadSize()`（空なら 5,000,000）、`ManageScope` は `upload_maxsize` が正の数ならその値、空なら `null`（旧と同じ。Decision Log）。
- `logLabel()`: 記録の `message` の先頭に付ける印（`PickerScope` は `[picker] `、`ManageScope` は `''`）。
- `actions()`: その範囲にだけある操作の表（action の名前 → 関数）。`PickerScope` は `list`（`fbPickerList`）・`thumb`・`upload`（`fbPickerUpload`）。`ManageScope` は `list`（`fbManageList`）・`upload`（`fbManageUpload`）と、M3 の `readText`・`saveText`・`newfile`・`unzip`。

`api.php` の入口は、`anyv('scope') === 'manage'` で `ManageScope::authorize()` か `PickerScope::authorize()` のどちらかを呼ぶ 1 行だけで範囲を決める。`scope` を受けて分けるのはここだけ。action の表は「共通の操作の表 + `$scope->actions()`」で作り、表に無い action は 400。範囲だけの操作がほかの範囲で動かないのは、表に載らないから（操作の中で範囲を確かめない）。

共通の操作（`mkdir`・`rename`・`delete`・`move`・`copy`・`download`・`saveImage`）は、今の `fbAction*()` を 1 つずつ残し、`$type` の代わりに `BrowserScope $scope` を受け取る。範囲で違うのは yes / no の問いだけで、それを `$scope` に尋ねる。

- `mkdir`: 保存先を `resolveNewChild()` で確かめる。
- `rename`: 拡張子は `isAllowedRename()`、変更後のパスは `resolveNewChild()`、フォルダなら `guard()->containsProtected()` が真のとき断る。
- `delete`: 名前ごとに守る場所でないかを確かめ、フォルダは `containsProtected()` も確かめる。
- `move`・`copy`: 移す先・複製先を `resolveNewChild()` で確かめる。`copy` の拡張子は `isAllowedCopy()`。
- `download`: 対象は `listsFile()` が真のものだけ。
- `saveImage`: 拡張子の確認を `isAllowedNewName()` にそろえる（別名の保存は新しい名前のファイルを作るため）。

範囲ごとの操作は次のとおり。

- 一覧: フォルダを読む共通の関数 `fbReadDir(PathResolver $resolver, BrowserScope $scope, $folder, $foldersOnly)` が、`scandir()`・守る場所の除外（`fbDirHasSubdirs()` の数え方も同じ）・`listsFile()` での絞り込み・フォルダとファイルの仕分けと並べ替えを行い、`{ folders, files: [{ name, fullPath, relPath, size, mtime }] }` を返す。`fbPickerList()` は今の `fbActionList()` の項目の形（`url`・`path`・`thumb`・`width`・`height`）をそのまま作る（ピッカーの応答は今と同じで、`writable` が増えるだけ）。`fbManageList()` は `name`・`size`・`mtime`・`url`（ルートが `MODX_BASE_PATH` の中のときだけ `site_url` からの URL、外なら `null`）・`writable`（ファイルごと）を作り、サムネイルと SetUrl のパスは作らない。応答には今のフォルダの `writable` を両方で入れる。
- アップロード: `fbPickerUpload()` は今の `fbActionUpload()`（種類の許可リスト、同じ名前なら `fbFindFreePath()` で別の名前、`OnFileBrowserUpload`）。`fbManageUpload()` は旧ファイル管理と同じく、合わせた許可リスト・同じ名前なら上書き・`OnFileManagerUpload`。上書きの前に、置き換えるものがファイルであること（フォルダやリンクでない）、守る場所でないこと、書き込めることを確かめる。大きさは、それぞれの関数が `$scope->maxUploadSize()` と比べて断る（`null` なら比べない。旧と同じく PHP の上限だけが効く）。2 つが共に使うのは、`$modx->move_uploaded_file()` の呼び出しを `fbAttempt()` で包む `fbStoreUpload($tmpPath, $targetPath)` だけ（大きさの確認は範囲で振る舞いが違うので、共通の関数に入れない）。
- エラーの文言を足す（`fb_err_manage_forbidden`、`fb_err_protected`）。英語と日本語の言語ファイルに足す。

`PathResolver` は、`BrowserScope` から `baseDir()` と `guard()` を受け取って作る。`resolveDir($folder)` は最初の階層を足さない（ルートに含まれているため）。ベースの中かの確認は、今の `strpos($real, $this->baseDir) !== 0` のまま（`realpath()` で解決した実パスの前方一致）にし、守る場所の確認（`PathGuard::isProtected()`）を `resolveDir()` と `resolveFile()` に足す。新しく `resolveNewChild($dirReal, $name)` を足し、作る・名前を変える・移す先のパスが守る場所でないかを確かめて、使えるならフルパス、使えなければ `null` を返す。どれも範囲を知らない。

画面の HTML を `lib/BrowserView.php` へ移す。`BrowserView::markup(array $jsConfig)` は共通の本体（今の `$markup`）を返し、`BrowserView::jsConfig(DocumentParser $modx, BrowserConfig $config, array $options)` が JS の設定を作る（`$options` は `apiParams`、`apiUrl`、`initialFolder`、`initialSelect`、`rootLabel`、`entry`）。JS で使う言語キーの一覧もここへ移す。外側は用途ごとに分ける。`modalFragment()` と `standalonePage()` はピッカー用（今の `browser.php` の後半）で、`browser.php` はこれらを使うだけにする。`managePane()`（断片の `<h1>`・CSS・HTML・`<script>`）は管理モード用で、`a=31` から使うのは M4。管理モードにだけあるボタンやダイアログの HTML は `managePane()` の側で足し、`markup()` の中で範囲を見て分けない。

JS は、共通の部品（`filebrowser.js` の `init()`、`js/grid.js`・`js/tree.js`・`js/dnd.js`・`js/upload.js`・`js/dialogs.js`・`js/contextmenu.js`・`js/overlay.js`）と、用途ごとの入口（`js/picker.js` と `js/manage.js`）に分ける。共通の部品は、一覧の項目にある値（`thumb` があればサムネイル、無ければアイコン、など）をそのまま描くだけにし、範囲を見て描き分けない。`init(root, behaviors)` は、用途で変わる振る舞いを `behaviors` として受け取る。

- `openFile(entry)`: ダブルクリックと Enter で動く。ピッカーは選んだファイルの確定（`pickFile()`）、管理モードはテキストならテキストのダイアログ（M3）、それ以外はプレビュー。
- `previewActions(entry)`: プレビューに出すボタン。ピッカーは「このファイルを選択」、管理モードは無し。
- `contextMenuItems(entry)`: 右クリックメニューに足す項目。ピッカーは無し、管理モードは M3 の「表示・編集」「展開」「新しいファイル」。
- `confirmOverwrite(names)`: 管理モードの upload が `overwrite=1` なしで同じ名前のファイルに当たると、サーバーは保存せずに `errors` に `code: exists` と無害化した後の名前（`targetName`）を返す。画面はその名前を並べてこれに尋ね、真なら該当のファイルだけを `overwrite=1` で送り直す（旧は確かめずに上書きしていた。上書きできることは保ち、確かめを足す）。ピッカーのサーバーは `exists` を返さない（別の名前にする）。M2 の実装で、元は `beforeUpload(files, existingNames)` としていたものを改めた（ブラウザの名前とサーバーが無害化した名前が違うと確認が漏れるため）。

`picker.js` と `manage.js` は、それぞれの `behaviors` を作って `init()` を呼ぶだけにする。`browser.php` は `picker.js` を、`a=31` は `manage.js` を読む（`jsConfig` の `entry`）。`js/api.js` は、設定の `apiParams`（ピッカーは `{ type }`、管理モードは `{ scope: 'manage' }`。サーバーの `jsConfig()` が作る）を、すべての要求にそのまま付ける。

`writable` が偽なら、新しいフォルダ・アップロード・貼り付けのボタンを押せなくし、一覧の上に「このフォルダには書き込めません」を出す（応答の値を見るだけなので両方の範囲で同じ）。文言は既存の `files_dirwritable` などを使わず、`fb_not_writable` を足す（旧のキーは M4 で消すため）。ツリーの一番上の名前は `config.rootLabel`（ルートのフォルダの名前）にする。

この段階では `a=31` は旧ファイル管理のまま。管理モードの画面は、検証のときだけ一時的な入口（Concrete Steps の M2 の 5）から開く。

### M3: テキストの表示・編集、新しいファイル、zip の展開、変更の記録

管理モードだけで受ける操作を `lib/ManageActions.php` に置き、`ManageScope::actions()` から登録する。ピッカーの action の表には載らないので、ピッカーで呼ばれたら表に無い action として 400 になる（操作の中で範囲を確かめない）。

- `readText`（GET。`folder`、`file`）: `resolveFile()` で実体を確かめ、拡張子が `BrowserConfig::TEXT_EXTENSIONS` にあるか（`.htaccess` のような名前は名前全体で照合）、大きさが `TEXT_MAX_BYTES` 以下か、NUL を含まないかを確かめる。応答は `{ name, content, hash, readOnly, readOnlyReason, encoding }`（M3 で `readOnlyReason` を足し、M4 で使われない `writable` を外した）。UTF-8 でなければ `mb_convert_encoding($buffer, 'UTF-8', 'SJIS-win,SJIS,EUCJP-win,EUC-JP')` で変換し、`readOnly: true`、`encoding` に元の文字コードの名前を入れる。`hash` は元のバイト列の `sha1`。
- `saveText`（POST、CSRF。`folder`、`target`、`content`、`hash`）: 同じ確認に加えて、リンクなら断る（`saveImage` の上書きと同じ）、UTF-8 でない元のファイルは断る、今の中身の `sha1` が `hash` と違えば 409（`modified`）、改行を元のファイルに合わせる、`file_put_contents($path, $content, LOCK_EX)` の戻り値を確かめる（`fbAttempt()` で包む）。パーミッションは変えない（既にあるファイルへの書き込みなので保たれる）。
- `newfile`（POST、CSRF。`folder`、`name`）: `sanitizeFilename($name, false)`、`isAllowedNewName()`、`resolveNewChild()`、既にあれば 409。`fopen($path, 'xb')` で作り（同時の作成で上書きしない）、`@chmod($path, $modx->newFilePermissions())`。
- `unzip`（POST、CSRF。`folder`、`target`）: `ZipService::extract($zipPath, $destDir, PathGuard $guard, callable $isAllowedName, DocumentParser $modx)` を呼ぶ（`$isAllowedName` には `$scope->isAllowedNewName(...)` を渡す）。応答は `{ extracted: 件数, failed: [...], skipped: [...] }`。zip の拡張が無ければ 501（`zip_unavailable`）、中身がルートの外・守る場所へ書くなら 400（`unsafe_zip`）。

`lib/ZipService.php` に `extract()` を足し、旧 `unzip()` と下請けの関数を移す。旧の確認はそのまま移し、Decision Log の「zip の展開の安全確認」の対策（ぶら下がったリンクで止める、書く直前の確かめ、`umask(0)` と 0777 をやめて設定のパーミッションを当てる、zip そのもののリンクの確認）を足す。書く直前の確かめのため、`extract()` は `PathGuard` に加えて `PathResolver` を受け取る。旧の関数のうち `evo()->sanitizeFilename()` を呼ぶ所は、引数で受けた `DocumentParser` を使う。

変更の記録は、`lib/ChangeLog.php` に `ChangeLog::record(BrowserScope $scope, $operation, array $paths)` を 1 つ置き、`api.php` の成功した変更の後に呼ぶ（Decision Log の「変更の記録」のとおり）。管理操作ログへは `logHandler::initAndWriteLog($message, '', '', 31, 'x', $itemname)` を 1 件ごとに呼ぶ（`$message` は操作の名前、ピッカーでは先頭に `[picker]`。`$itemname` はルートからの相対パスで、移動と名前の変更は `from → to`。`mb_scrub()` し、255 文字を超えるものは末尾を残して先頭を省く）。1 回の要求で 50 行を超える分は書かず、最後に `ほか N 件（trace_id=<trace_id>）` の 1 行を足す（`trace_id` は `EvoLogger::traceId()` から受け取る。`manager/includes/logger.class.php` にこのメソッドを足し、`collectCommonContext()` もこれを使う形にする）。システムログへは `evo()->logEvent(0, 1, 'File changed in the file browser', 'File browser', ['scope' => ..., 'operation' => ..., 'paths' => ...])` を 1 行書く（`paths` は全件）。zip の展開は、展開できたファイルを 1 件ずつ記録する。失敗の記録（今の `logEvent(0, 3, ...)`）はそのまま。M1 では、旧 `logFileChange()` の `action` が 0 になる不具合は直さない（M4 で旧ごと消すため）。

画面は `js/textEditor.js` を新しく作る。`createTextEditor(ctx)` が、ファイルブラウザの中のダイアログ（`js/overlay.js`）に、エレメントの編集画面と同じ形のコードの欄（`div.code-editor` > `div.code-editor-header`（ファイル名と「行を折り返す」のチェックボックス）、`textarea.phptextarea`（`dir="ltr"`、`wrap="soft"`））と、保存・閉じるのボタンを出す。HTML は `BrowserView::manageExtras()` にまとめ、管理モードの外側（M3 の一時的な入口の `standalonePage()`、M4 の `managePane()`）が `markup($jsConfig, $extras)` に渡す（`markup()` の中で範囲を見て分けない）。`js/textEditor.js` は `js/manage.js` だけが読む。開くと `readText` を呼び、`readOnly` なら欄を読み取り専用にして理由を出す。書き換えたら `window.documentDirty = true`（EvoShell が離れるときに確かめる）、保存したり閉じたりしたら `false` に戻す。変更があるまま閉じるときは確かめる。保存が 409 なら「ほかで書き換えられています」を出し、上書きはさせない。

`js/manage.js` の `behaviors.contextMenuItems()` で、右クリックメニューに「表示・編集」（テキストの拡張子のファイル）、「展開」（`.zip`）、何も無い所に「新しいファイル」を足す（共通の `js/contextmenu.js` は、渡された項目を並べるだけ）。新しいファイルを作ったら、そのままテキストのダイアログで開く。展開の前には確かめ（旧の `confirm_unzip_file` に当たる文言を `fb_confirm_unzip` として足す）、結果（展開した件数、飛ばしたもの、失敗したもの）を出して一覧を読み直す。文言は `fb_` のキーで言語ファイルに足し、`BrowserView` の JS 用キーの一覧に入れる。

### M4: `a=31` でファイルブラウザを表示し、旧ファイル管理を消す

`manager/actions/element/file_manager.static.php` を新しく作る。先頭で `if (!isset($modx) || !evo()->isLoggedin()) { exit; }`、`file_manager` の権限が無ければ `alert()->setError(3); alert()->dumpError();`（旧と同じ）。`BrowserView::managePane()` に `scope: 'manage'`、`apiUrl: 'media/browser/evo/api.php'`、`initialFolder: getv('folder', '')`、`initialSelect: getv('select', '')`、見出し `lang('manage_files')` を渡して出す。最後に `window.EvoFileBrowserEntries['manage']` を呼ぶインラインの `<script>` を出す（モーダルと同じ理由。M2 で `EvoFileBrowserInit()` から入口ごとの登録表に改めた）。`manager/index.php` の `case 31` はこのファイルを読む。

メインの領域での見た目を整える。`#evoFileBrowser` に `evo-fb-page` のクラスを付け、`filebrowser.css` で高さをメインの領域の見える高さに合わせ、一覧とツリーの中だけがスクロールするようにする。テーマの CSS 変数を使い、色を直に書かない（RevoStyle は凍結テーマなので、崩れないことだけを確かめる）。

`evoshell:unload`（メインの領域から離れるとき）と `evoshell:modalclose` に、開いているダイアログと右クリックメニューを閉じる（`document` に付けた `keydown` などの受け手を残さないため）。M4 の実装で、受け手を付ける `js/overlay.js`（`openOverlay()` の `onClose` で呼び出し側の後始末も呼ぶ）と `js/contextmenu.js` が、それぞれ自分で外す形にした（付ける所と外す所を同じ場所に置くため）。

フォルダツリーの開閉のボタンをツールバーに足し（`data-action="toggle-tree"`、`aria-expanded` と `aria-controls` を付ける）、`localStorage` の `evoFbTree` に残す。`CustomEvent` の `evofb:navigate` と `evofb:selectionchange` を `#evoFileBrowser` から出す（Decision Log）。

リンクを置き換える。

- `configcheck_mgr_tpl_msg`（英語と日本語）: `index.php?a=31&folder=[+folder+]` にする。`config_check.inc.php` は、`MODX_BASE_PATH . 'assets/templates/manager'` がファイル管理のルートの中なら、ルートからの相対パス全体に `rawurlencode()` を 1 回かけて `folder` に入れ（`assets%2Ftemplates%2Fmanager`。M4 で Validation の期待の形に合わせた）、外なら `[+folder+]` を空にする（`index.php?a=31&folder=`）。
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

1. `lib/BrowserScope.php`（interface）・`lib/PickerScope.php`・`lib/ManageScope.php` を作り、`lib/BrowserConfig.php` に `allowedExtensionsAll()` を足す。
2. `lib/PathResolver.php` を、ルートと `PathGuard` を受け取る形にし、守る場所の確認と `resolveNewChild()` を足す（`type` を知らない形にする）。
3. `api.php` の入口と各操作を `BrowserScope` に通す。範囲を見て分ける所が入口の 1 か所だけかを確かめる。

        grep -n "\$type\|'manage'\|scope" manager/media/browser/evo/api.php manager/media/browser/evo/lib/*.php
        grep -n "scope" manager/media/browser/evo/filebrowser.js manager/media/browser/evo/js/*.js

   期待: `api.php` では入口の 1 行（`anyv('scope') === 'manage'`）だけ。`lib/` では `PickerScope` の中の `type` だけ。JS では `js/api.js` が `apiParams` を付ける所と `js/manage.js` だけで、共通の部品に `scope` で分ける `if` が無い。
4. `lib/BrowserView.php` を作り、`browser.php` をこれに置き換える。`filebrowser.js` の `init()` が `behaviors` を受け取る形にし、`js/picker.js`（今の振る舞いを移す）と `js/manage.js` を作る。`js/api.js` は `apiParams` を付ける形にする。言語ファイルに `fb_err_manage_forbidden`・`fb_err_protected`・`fb_not_writable` を足す。
5. 管理モードの画面を確かめるための一時的な入口を、コミットしないファイルとして作る。`manager/media/browser/evo/validate-manage.local.php` に、`browser.php` と同じ起動（`IN_MANAGER_MODE`・`MODX_API_MODE`・`DocumentParser`・`getSettings()`）、`file_manager` の権限の確認、`BrowserView::standalonePage()` で管理モード（`scope` は `manage`、`apiUrl` は `api.php`）の単独のページを出す処理を書く。検証が終わったら消し、`git status --short` に出ないことを確かめる。
6. 構文を確かめる（M1 の 4 と同じコマンドで、`lib/*.php`・`api.php`・`browser.php` を対象にする）。JS は ES モジュールとして構文を確かめる（`.js` のままでは CommonJS として読まれ `import` で止まることがあるので、`.mjs` に写して確かめる）。

        docker run --rm -v "$PWD":/app -w /app node:22 sh -c 'for f in manager/media/browser/evo/filebrowser.js manager/media/browser/evo/js/*.js; do cp "$f" /tmp/check.mjs && node --check /tmp/check.mjs || { echo "NG $f"; exit 1; }; done'

   期待: 何も出ず、終了コード 0。
7. Validation の M2 の表を、main と作業ブランチで確かめる。
8. コミットの案: `refactor(filebrowser): 範囲ごとの違いを PickerScope に分け、画面の HTML を BrowserView へ移す`、`feat(filebrowser): ファイル管理の範囲で一覧・操作できる管理モードを追加`。

### M3

1. `lib/BrowserConfig.php` に `TEXT_EXTENSIONS` と `TEXT_MAX_BYTES` を足す。`lib/ZipService.php` に `extract()` を足す。
2. `lib/ChangeLog.php` を作る。`api.php` に `readText`・`saveText`・`newfile`・`unzip` を足し、既存の成功した変更の後に `ChangeLog::record()` を呼ぶ。ファイルの先頭のコメント（action の一覧）を更新する。
3. `js/textEditor.js` を作り、`filebrowser.js`・`js/contextmenu.js` の項目・`BrowserView::markup()` を足す。言語キーを足す。
4. 構文を確かめる（M2 の 6 と同じ）。
5. 検証の前に、旧ファイル管理のテキストの保存で改行が変わるか（Surprises の要確認）を main で確かめる。

        printf 'a\nb\n' > content/files/lf-check.txt
        （main の a=31 で content/files/lf-check.txt を編集画面で開き、そのまま保存する）
        docker exec -w /var/www/html <app> od -c content/files/lf-check.txt | head -2

   結果（`\r \n` が出るか）を Surprises に書き、ファイルを消す。

6. Validation の M3 の表を確かめる。システムログは次で見る。

        docker exec -w /var/www/html <app> php evo log:search "File changed in the file browser" --limit=20

   期待: 操作ごとに 1 行、`context` に `scope`・`operation`・`paths`、`user` に利用者の ID。

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

### M1（旧ファイル管理の動きが main と同じで、2 点だけ変わる）

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

注: 守る場所はサイトのルートからの絶対パスで決まるので、中に守る場所を含むフォルダは `assets`・`temp`・サイトのルートしか作れない。検証環境は作業ツリーをそのままマウントしているため、main で消える側を確かめると作業ツリーの `assets` が消える。main では実行せず、`files.dynamic.php` の `mode=deletefolder` が `rrmdir()` の前に `is_protected_path()` しか見ていないことを根拠に書く。作業ブランチでは断るので、実行しても何も消えない（念のため、実行の前に `git stash` ではなく `tar czf <work>/assets.tgz assets` で控えを取る）。`PathGuard::normalize()` の直しは、`php:7.4-cli` で `LC_ALL=C` にして確かめる（まだ無い `/app/content/日本語/新規` を渡し、同じ文字列が返る。main の `normalize_path()` では最後の要素が別の名前になることを表に書く。計画時に `php:7.4-cli` で `LC_ALL=C` にして `basename('/app/content/日本語')` を確かめると `content` が返り、`C.UTF-8` では `日本語` が返った）。`containsProtected()` そのものは、Concrete Steps の M1 の 5 と同じやり方で、`PathGuard` に一覧を渡して PHP 7.4 で確かめる（`(new PathGuard('/app', ['/app/assets/plugins']))->containsProtected('/app/assets')` が `true`、`'/app/content'` が `false`）。

### M2（管理モードの API と画面）

API は、Playwright でログインしたページの `page.request`（クッキーを共有する）から呼び、ステータスと JSON を見る。書き込みには、画面の `data-config` の `csrfToken` を使う。

| 条件 | 期待 |
|---|---|
| `editor` で `api.php?action=list&scope=manage&folder=` | 200。`folders` にサイトのルートのフォルダ（`assets`・`content` など）があり、`manager` は無い。`files` に `index.php`・`.htaccess`（あれば）が入る。項目に `thumb`・`path` のキーが無く、`writable` がある |
| `admin`（`isAdmin()` が真）で同じ | `folders` に `manager` がある |
| `editor` のロールから `file_manager` を外して同じ（終わったら戻す） | 403、`error.code` は `forbidden` |
| ログインしていない・ウェブユーザーのセッション（`rb_webuser=1`）で同じ | 403 |
| 設定 `use_browser=0` で `editor` の `scope=manage` の `list` | 200（管理モードは `use_browser` を見ない） |
| 同じ設定でピッカー（`scope` なし、`type=images`） | 403（今と同じ） |
| `editor` で `scope=manage` の `list` を `folder=manager`、`folder=assets/plugins`、`folder=../` で呼ぶ | 404 か 403。一覧は返らない |
| `editor` で `mkdir`（`folder=temp`、`name=backup`。`temp/backup` が無い状態で） | 403（`protected`）。作られない |
| `editor` で `assets` の名前の変更（`folder=`、`target=assets`）と削除（`folders[]=assets`） | どちらも断られ、`assets` は残る |
| 管理モードで `fm-test/` に `a.php` をアップロード（設定の許可リストに `php` が無い） | 断られる（`upload_files` などに `php` を足すと通る） |
| 管理モードで `fm-test/a.txt` を `a.php` に名前を変える（許可リストに `php` が無い） | 断られる |
| 管理モードで `fm-test/a.txt` を `b.txt` に、`index.php` のコピーを `c.php` → `d.php` に名前を変える | 成功する（拡張子が同じ） |
| 管理モードで、`fm-test/` にある `a.txt` と同じ名前のファイルをアップロード | 上書きの確認が出る。「はい」で中身が置き換わる（`a(1).txt` はできない）。「いいえ」なら何も変わらない |
| ピッカーで、同じ名前のファイルをアップロード | 今と同じく `名前(1).拡張子` で保存される |
| 管理モードで `thumb` を `page.request` で呼ぶ | 400（表に無い action） |
| `upload_maxsize` を空にし、PHP の `upload_max_filesize`・`post_max_size` を 64M にした検証環境で、40MB のファイルをアップロード | 管理モードでは受け付けられる（main の旧ファイル管理と同じ）。ピッカーでは今と同じく大きさの上限で断られる |
| `upload_maxsize` を 1000000 にして、2MB のファイルをアップロード | 管理モードもピッカーも断られる（main の旧ファイル管理も断る） |
| 管理モードの `list` の応答 | 画像でない項目のキーは `name`・`size`・`mtime`・`url`・`writable` だけ。表示できる画像には `image`、ラスター画像には `width`・`height` も付く（Decision Log の「寸法は今までどおり返す」） |
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
| 普通の zip（日本語の名前・フォルダを含む、Windows で作った Shift_JIS の名前を含む）を「展開」 | 名前が化けずに展開され、件数が出る。管理操作ログ（`a=13`）に、展開できたファイルが 1 件ずつ `Unzipped` で載る |
| `../evil.txt` を含む zip、`manager/x.txt` に書く zip（`editor`、ルートで展開） | 何も展開されず、理由が出る |
| 許可リストに無い拡張子（`.php`）を含む zip | そのエントリだけ飛ばされ、飛ばした名前が出る |
| `fm-test/` に、ルートの外（`/tmp/fm-outside/x.txt`、まだ無い）を指すぶら下がったシンボリックリンク `x.txt` を置き、`x.txt` を含む zip を `fm-test/` で展開 | 何も展開されず、理由が出る。`/tmp/fm-outside/x.txt` ができない（main の旧ファイル管理では作られることを、作業ツリーの外の `/tmp` だけで確かめて表に書く） |
| `fm-test/sub` を、ルートの外のフォルダを指すシンボリックリンクにして、`sub/a.txt` を含む zip を展開 | 何も展開されない（旧と同じ） |
| `a\..\..\x.txt`、`/etc/x.txt`、`C:/x.txt`、`./a/./b.txt` という名前のエントリを含む zip | `..` を含むものがあれば何も展開されない。無ければ、`etc/x.txt` などルートの中の相対パスに展開される（旧と同じ） |
| シンボリックリンクのエントリ（`ln -s /etc/passwd l` を `zip --symlinks` で入れたもの）を含む zip | `l` は普通のファイル（中身は `/etc/passwd` の文字列）として作られ、リンクにはならない |
| フォルダを含む zip を展開し、`stat -c %a` でパーミッションを見る | フォルダは設定 `new_folder_permissions`（既定 755）、ファイルは `new_file_permissions`（既定 644）。main の旧では 777・666 になることを表に書く |
| 展開する zip そのものが、ルートの外の zip を指すシンボリックリンク | 開かれず、理由が出る |
| ピッカーの範囲で `readText`・`saveText`・`newfile`・`unzip` を `page.request` で呼ぶ | 400（表に無い action）。ファイルは変わらない |
| アップロード・新しいフォルダ・名前の変更・削除・移動・コピー・画像の保存・テキストの保存を、管理モードとピッカーで 1 回ずつ | 管理操作ログ（`a=13` の画面と `php evo db:count manager_log --where="action=31"`）に、操作ごとに利用者の名前・日時・相対パス・操作の名前の 1 行が増える。ピッカーの行は `message` が `[picker]` で始まる。`action` が 0 の行は増えない。`php evo log:search "File changed in the file browser"` にも操作ごとに 1 行あり、`scope` が合っている |
| 60 件のファイルをまとめて削除 | 管理操作ログには 50 行と `ほか 10 件（trace_id=…）` の 1 行。その `trace_id` で `php evo log:search <trace_id>` を流すと、`context.paths` に 60 件すべてが入った 1 行が出る（51〜60 件目の名前が入っている） |
| 一覧・プレビュー・テキストの表示だけを行う | 管理操作ログに個々の操作の行は増えない（`a=31` を開いた「Using file manager」だけ） |

### M4（`a=31` の表示と、旧ファイル管理が無いこと）

| 条件 | 期待 |
|---|---|
| メニューの「ファイル管理」を押す | メインの領域にファイルブラウザが出る（fetch の応答に `X-Evo-Pane: 1`。`lib.js` の `watchFetch()`）。見出しは「ファイル管理」 |
| `index.php?a=31` を直に開く（全体の読み込み） | 同じ画面が header・footer 付きで出る |
| ダッシュボードの「ファイル管理」のアイコン | 同じ画面 |
| `a=31&folder=assets/images&select=<ある画像>` | そのフォルダが開き、その画像が選ばれて見える位置にある |
| `a=31&mode=drill&path=/var/www/html/manager` | ルートが開く（旧の引数は効かない）。`editor` では `manager` が一覧に無い |
| `file_manager` の権限が無い利用者で `a=31` | 権限が無い旨の画面（旧と同じ） |
| 別の画面へ移って戻る、を 3 回 | 毎回、一覧が出る（`EvoFileBrowserEntries['manage']` が呼び直される）。コンソールにエラーが無い |
| `a=31` → TV の欄からピッカーのモーダル → `a=31` → ピッカー、と往復する | ピッカーはダブルクリックで選択が確定し、`a=31` はプレビューと上書きの確認が出る（入口の振る舞いが入れ替わらない） |
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

完了の条件: M1〜M4 の表がすべて期待どおりで、成功した変更が管理操作ログ（`a=13`）に 1 件ごとに残り（1 回の操作で 50 行まで、それより後は `trace_id` でシステムログから全件を追える）、メニューの「ファイル管理」からファイルブラウザでサイトのファイルを一覧・アップロード・削除・名前の変更・移動・コピー・ダウンロード・テキストの編集・zip の展開ができ、`files.dynamic.php` がリポジトリに無いこと。

## Idempotence and Recovery

各段階は 1 つの PR にし、段階の終わりで main は常に動く状態にする（M1〜M3 の間、`a=31` は旧ファイル管理のまま）。途中で止めたときは、Progress の最後の完了した段階から続ける。段階の途中なら、`git status` と `git diff --stat` で手を付けたファイルを確かめ、Plan of Work のその段階の箇条を上から照らし合わせる。

`PathGuard`・`PickerScope`・`ManageScope`・`BrowserView` の作成と置き換えは、何度やり直しても同じ結果になる。M2 の一時的な入口（`validate-manage.local.php`）と検証用のプラグイン・テスト用のファイル（`content/files/fm-test/`・`fm-test/`・`/tmp/fm-root/`）は、検証のたびに作り直してよく、終わったら必ず消す。消したことは `git status --short` が空であること（検証環境が作る `.htaccess`・`manager/includes/config.inc.php` は無視の対象）で確かめる。ロールの権限・`use_browser`・`filemanager_path`・テーマ・言語を変えて確かめたときは、元の値に戻して `php evo cache:clear` を流す。

M4 で `files.dynamic.php` を消した後に問題が見つかったときは、M4 のコミットを `git revert` すれば、`a=31` が旧ファイル管理に戻る（M1〜M3 の変更はそのまま使える）。サイトを上書きで更新した利用者の環境では、消した `files.dynamic.php` がサーバーに残ることがあるが、どこからも読まれず、直に開いても先頭の `isset($modx)` の確認で何もせずに終わる。

## Artifacts and Notes

- Issue: https://github.com/modxcms-jp/evolution-jp/issues/1046
- 関連の Issue: #1043、#1041、#1017（いずれも閉じている）
- 関連の ExecPlan: `.agent/plans/2026-07-11-file-browser-modal-rewrite.md`、`.agent/plans/archive/2026-11-01-file-browser-kcfinder-extension.md`、`.agent/plans/2026-10-10-manager-input-and-csrf-hardening.md`
- 新しく作るファイル: `manager/media/browser/evo/lib/PathGuard.php`（M1）、`lib/BrowserScope.php`・`lib/PickerScope.php`・`lib/ManageScope.php`・`lib/BrowserView.php`・`js/picker.js`・`js/manage.js`（M2）、`lib/ManageActions.php`・`lib/ChangeLog.php`・`js/textEditor.js`（M3）、`manager/actions/element/file_manager.static.php`（M4）
- 消すファイル: `manager/actions/element/files.dynamic.php`（M4）
- PR は段階ごとに分ける。M1〜M3 の PR は `Refs #1046`、M4 の PR で `Closes #1046` にする
- M1: ブランチ `feat/1046-m1-path-guard`（`gh issue develop` で #1046 に紐づけ）、PR #1048
- M2: ブランチ `feat/1046-m2-manage-scope`（M1 のブランチの上に積んだ。#1048 のマージ後に main へ載せ直して PR にする）
- M3: ブランチ `feat/1046-m3-text-zip-log`（M2 のブランチの上に積んだ。M2 の PR のマージ後に main へ載せ直して PR にする）
- M4: ブランチ `feat/1046-m4-replace-a31`（M3 のブランチの上に積んだ。M3 の PR のマージ後に main へ載せ直して PR にし、`Closes #1046` にする）

## Interfaces and Dependencies

PHP のクラス（すべて `manager/media/browser/evo/lib/`、PHP 7.4 で動く書き方）:

    PathGuard
        __construct($baseDir, array $protectedPaths = [])
        static protectedPathsFor(DocumentParser $modx): array
        static normalize($path)              // string|false
        static isAbsoluteLocal($path): bool
        isWithinBase($path): bool
        isProtected($path): bool
        containsProtected($path): bool

    BrowserScope（interface）
        baseDir(): string / guard(): PathGuard
        listsFile($name): bool / listsFolder($name): bool       // M2 で listsFolder を足した
        isAllowedNewName($name): bool / isAllowedRename($oldName, $newName): bool / isAllowedCopy($sourceName, $newName): bool
        maxUploadSize(): ?int                // PHP 7.4 で使える ?int。null は本体では制限しない
        downloadName(): string / uploadEvent(): string           // M2
        name(): string / logRoot(): string / logLabel(): string  // M3。name は 'picker' か 'manage'、logRoot は記録のパスの頭（ピッカーは種類のフォルダ）
        actions(): array                     // その範囲にだけある action の名前 => 関数と、CSRF・POST の要否

    api.php の関数（共通）
        fbReadDir(PathResolver $resolver, BrowserScope $scope, $folder, $foldersOnly): array
        fbStoreUpload($tmpPath, $targetPath): bool
    api.php の関数（範囲ごと）
        fbPickerList / fbPickerUpload / fbActionThumb（ピッカー）
        fbManageList / fbManageUpload（管理モード）

    PickerScope implements BrowserScope
        static authorize(DocumentParser $modx, BrowserConfig $config)   // PickerScope か、失敗の理由
    ManageScope implements BrowserScope
        static authorize(DocumentParser $modx, BrowserConfig $config)   // ManageScope か、失敗の理由

    ManageActions                            // 管理モードだけの操作（M3）
        readText / saveText / newfile / unzip

    ChangeLog（M3）
        static record(BrowserScope $scope, $operation, array $paths)   // $paths の要素は文字列か [元, 先]
        static relPath(array $dir, $name)

    EvoLogger（既存。manager/includes/logger.class.php に足す。M3）
        static traceId(): string             // 今の要求の trace_id。collectCommonContext() もこれを使う

    PathResolver（既存に足す）
        resolveNewChild($dirReal, $name)     // string|null

    ZipService（既存に足す）
        static extract($zipPath, $destDir, PathResolver $resolver, PathGuard $guard, callable $isAllowedName, DocumentParser $modx): array
        // ['ok' => bool, 'reason' => 'unavailable'|'unsafe'|'open_failed'|'', 'extracted' => int, 'files' => [], 'failed' => [], 'skipped' => []]
        // files は展開できたファイルの一覧（ChangeLog の 1 件ごとの記録に使う）

    BrowserConfig（既存に足す。M3）
        TEXT_EXTENSIONS / TEXT_MAX_BYTES / static isTextFilename($name): bool

    BrowserView
        static jsConfig(DocumentParser $modx, BrowserConfig $config, array $options): array
        static markup(array $jsConfig, $extras = '', $classes = ''): string   // $extras・$classes は外側が決める用途ごとの HTML とクラス。markup() の中で範囲を見て分けない
        static entryScripts(array $jsConfig): string             // モジュールの <script> と入口の呼び直し。modalFragment と managePane が共有（M4）
        static manageExtras(): string                            // 管理モードだけの HTML（テキストのダイアログ）。M3
        static modalFragment(array $jsConfig, $title): string    // browser.php?modal=1
        static standalonePage(DocumentParser $modx, array $jsConfig, $title, $extras = ''): string   // browser.php（ポップアップ）と M2・M3 の一時的な入口
        static managePane(array $jsConfig, $title): string       // a=31（M4）。markup($jsConfig, self::manageExtras(), 'evo-fb-page') を使う

    ManageScope（M4 で足す）
        static rootPath(DocumentParser $modx): string   // ファイル管理のルート（filemanager_path か MODX_BASE_PATH）。決めるのはここだけ
        rootLabel(): string                  // ルートのフォルダの名前（basename() を使わない）

    ConfigCheck（manager/includes/config_check.inc.php。M4）
        fileManagerFolderFor($path): string  // ファイル管理のルートの中なら rawurlencode した相対パス、外なら空

API（`manager/media/browser/evo/api.php`）: すべての要求で `scope`（`manage` か、省略でピッカー）を受け、入口で `ManageScope::authorize()` か `PickerScope::authorize()` を呼ぶ。`manage` は管理画面のセッションと `file_manager` の権限が要り、`type` は見ない。足す action は `readText`（GET）、`saveText`・`newfile`・`unzip`（POST、CSRF トークン）で、`ManageScope::actions()` だけが登録する。`thumb` は `PickerScope::actions()` だけが登録する。`list` は範囲ごとの関数で、ピッカーの応答は今の形に `writable` と `image`（表示できる画像だけ）を足しただけ、管理モードの応答は `name`・`size`・`mtime`・`url`・`writable` と、表示できる画像の `image`、ラスター画像の `width`・`height`（`path`・`thumb` のキーを持たない）。`upload` の応答の `uploaded` と `errors` は受けた順の `index` を持ち、管理モードの `upload` は `overwrite=1` が無いと同じ名前のファイルを `code: exists`（と `targetName`）で返して保存しない。エラーは今までどおり `{error:{code,message}}` とステータス。足すコードは `protected`、`modified`、`not_text`、`too_large`（既存）、`unsafe_zip`、`zip_unavailable`（既存）。

JS（`manager/media/browser/evo/`）: `data-config` に `apiParams`・`rootLabel`・`entry` を足す。`filebrowser.js` の `registerEntry(entry, behaviors)` が `window.EvoFileBrowserEntries[entry]` に起動の関数を置き、behaviors（`openFile`・`previewActions`・`contextMenuItems`・`confirmOverwrite`）は入口の `js/picker.js` と `js/manage.js` がそれぞれ渡す。画面の `data-config` の `entry` と違う入口では初期化しない。`#evoFileBrowser` から `evofb:navigate`（`detail: { scope, folder }`）と `evofb:selectionchange`（`detail: { scope, folder, names }`）を `bubbles: true` で出す。`window.EvoFileBrowserPick()` と `SetUrl()` の規約は変えない。`window.EvoFileBrowserInit()` は M2 で `window.EvoFileBrowserEntries[entry]` に置き換えた（ピッカーと管理モードの入口が 1 つのグローバルを取り合い、EvoShell の中で往復すると振る舞いが入れ替わるため。呼んでいたのは本体の断片だけ）。

イベント: 管理モードのアップロードで `OnFileManagerUpload`（`filepath`・`filename`）、ピッカーで `OnFileBrowserUpload`（今までどおり）。新しいイベントは足さない。

設定: `filemanager_path`（管理モードのルート。空ならサイトのルート）、`rb_base_dir`・`rb_base_url`・`use_browser`・`rb_webuser`（ピッカー）、`upload_files`・`upload_images`・`upload_media`（許可リスト）、`upload_maxsize`、`new_file_permissions`・`new_folder_permissions`、`image_limit_width`。新しい設定は足さない。

ログ: 成功した変更は `ChangeLog::record()` が、管理操作ログ（`manager_log`。`action=31`、`itemid='x'`、`itemname` に相対パス、`message` に操作の名前）に 1 件ごとに 1 行（1 回の要求で 50 行まで。超えたら最後の行に `trace_id`）と、システムログ（`evo()->logEvent(0, 1, 'File changed in the file browser', 'File browser', [...])`、`temp/logs/system/YYYY/MM/`）に 1 行を書く。

外部のもの: PHP の zip 拡張（展開とまとめてのダウンロード。無ければ 501）、mbstring（文字コードの変換）。新しいライブラリは使わない。
