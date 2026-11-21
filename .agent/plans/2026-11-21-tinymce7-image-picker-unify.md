# ExecPlan: TinyMCE 7 の画像の貼り付けと編集をファイルピッカーにそろえ、本文に data: 画像を入れない

## Purpose / Big Picture

TinyMCE 7 の本文に、画像が `data:image/png;base64,...`（画像の中身を文字にして HTML に直に書いたもの。以下「data: 画像」）で入ることを無くす。画像を貼り付け・ドロップしたときはファイルピッカーと同じアップロードの仕組みでサイトのファイルとして保存し、画像をダブルクリックしたときはファイルピッカーの画像編集を開いて、保存したファイルの URL に差し替える。

利用者から見える成果は、本文が画像の分だけ何 MB にも膨らまないこと（#1666 では何もしない「適用」だけで 102 文字の本文が 165 万文字になった）、貼り付けた画像がファイルピッカーの一覧に出て使い回せること、画像の編集画面が 1 つになること。開発者から見える成果は、画像の保存先・権限・形式の確かめがファイルピッカーの API（`manager/media/browser/evo/api.php`）の 1 か所になり、TinyMCE 7 プラグインが独自に持つ画像編集（`tinymce-cropper.js` と CDN の Cropper.js）が無くなること。

## Progress

- [x] (2026-11-21) 現状を調べ、ExecPlan を作った
- [x] (2026-11-21) 計画者が挙げた 9 つの論点を本人が決め、Decision Log に移した（計画の手順と検証も合わせて直した）
- [ ] M1: ファイルピッカーを、指定したファイルの画像編集を開いた状態で起動できるようにする（PR 1、Refs #1666）
- [ ] M2: TinyMCE で画像をダブルクリックすると、ファイルピッカーの画像編集が開き、保存後に src を差し替える（PR 2、M3 と一緒）
- [ ] M3: `tinymce-cropper.js`・`image_cropper` の設定・Cropper.js の読み込みを消す（PR 2、Closes #1666）
- [ ] M4: 貼り付け・ドロップした画像を、ファイルピッカーの API でアップロードして URL を入れる（PR 3、Closes #1652）

## Surprises & Discoveries

- 2026-11-21: TinyMCE 7（CDN の `tinymce@7`、調べた時点で 7.9.3）は、アップロードの受け手（`images_upload_handler` か `images_upload_url`）が無いと、貼り付け・ドロップした画像を data: 画像のまま本文に残す。`paste_data_images` の既定は true、`automatic_uploads` の既定は true、`images_file_types` の既定は `jpeg,jpg,jpe,jfi,jif,jfif,png,gif,bmp,webp`。`paste_data_images: false` にすると、貼り付け・ドロップした画像のファイルを受け付けず、貼り付けた HTML の中の data: 画像も落とす（`tinymce.js` の `pasteImageData` と `img` のノードフィルター）。
- 2026-11-21: 受け手を設定すると、TinyMCE は `SetContent` のたび（開いたときの読み込みを含む）に本文の中の data: 画像と未アップロードの blob: 画像を探してアップロードする（`EditorUpload` の `editor.on('SetContent', uploadImagesAuto)`）。つまり受け手を足すだけで、data: 画像の入った既存の文書を開くたびに、その画像がアップロードされる。保存せずに閉じれば、次に開いたときにもう一度アップロードされて同じ画像のファイルが増える。対象から外すには `editor.editorUpload.addFilter(img => bool)` を使う（TinyMCE 5 の `images_dataimg_filter` は 7 で廃止された設定の一覧に入っている）。
- 2026-11-21: アップロードの失敗を `images_upload_handler` が `{message, remove: true}` で返すと、TinyMCE はその画像を本文から消して、通知に message を出す。`remove` が無いと blob: のまま残り、保存時の `GetContent` で data: 画像に戻る（`replaceBlobUris`）。
- 2026-11-21: アップロードが終わる前に保存すると、その画像は `GetContent` で data: 画像として送られる（同上）。管理画面の保存は、TinyMCE がフォームの `submit` で `editor.save()` し、そのあと document の `submit` の受け手（`manager/media/script/shell.js` の 1289 行目付近）が `EvoShell.submit()` で送る。`e.defaultPrevented` なら shell は送らない。
- 2026-11-21: ファイルピッカーのアップロード（`fbPickerUpload()`）は、ロールの権限を見ない。通すのは `PickerScope::authorize()` の条件（管理画面にログインしている、かフロントのウェブユーザーで設定 `rb_webuser` が有効。かつ設定 `use_browser` が有効）だけで、「ファイルのアップロード」に当たるロールの権限は本体に無い（`role_file_manager` は管理モードの a=31 用）。拡張子は `upload_images` などの許可の一覧、大きさは `upload_maxsize`（`BrowserConfig::maxUploadSize()`、0 なら 5,000,000 バイト）で確かめ、同名は `名前(1).拡張子` に変え（`fbFindFreePath()`）、`image_limit_width` で縮め（`$modx->move_uploaded_file()`）、`OnFileBrowserUpload` を発火する。
- 2026-11-21: アップロード先のフォルダは既にあることが前提（`resolveDir()` が null なら 404 `not_found`）。日付のフォルダなどへ入れるには、先にフォルダを作る必要がある（`mkdir` は 1 階層ずつで、既にあれば 409）。
- 2026-11-21: `api.php` の頭の説明では upload の応答を `{uploaded:[{index,name,size,url}]}` としているが、実装（`fbPickerUpload()`）は `url` を返していない。本文に入れる URL を返すには、一覧（`fbPickerList()`）の `path`・`url` の作り方を共通の関数にして使う。
- 2026-11-21: CSRF のトークンの確かめに失敗すると、`checkCsrfToken()` は JSON ではなく `text/plain` の 403 を返す。アップロードの受け手は JSON でない応答も失敗として扱う。
- 2026-11-21: 保存時に data: 画像をファイルにする仕組みが別にある。設定 `convert_datauri_to_file`（既定 1）で、`save_resource.processor.php` の `getInputValues()` が要約（`introtext`）だけを `convertDataUriToFiles()`（`manager/actions/document/mutate_content/functions.php`）に通し、`content/images/datauri/{文書ID}/日時_乱数.拡張子` に書く。本文（`content`）は 2025-11-30 のコミット ebfcf837b で対象から外された（理由はコミットに無い）。保存先は `rb_base_dir` ではなく決め打ちで、ファイルピッカーの拡張子・大きさ・同名の決まりもイベントも通らない。`assets/docs/datauri-conversion.md` は今も「対象: content、introtext」と書いていて、実装と食い違う（このドキュメントは `AGENTS.md` のドキュメントマップにも無い）。
- 2026-11-21: 今の画像編集（`assets/plugins/tinymce7/js/tinymce-cropper.js`）は、ダブルクリックのほかに、画像を選んだときの小さな帯（TinyMCE の context toolbar）に「画像を編集」のボタンを出している。適用すると src を data: 画像（既定は PNG）にし、width・height 属性を書き出した画像の画素数にする（#1666 で幅 2400 などになったのはこのため）。
- 2026-11-21: ファイルピッカーの画像編集（`manager/media/browser/evo/js/imageEditor.js`）は、開くたびに保存のしかたを「別名で保存」（`mode=copy`）に戻す（`open()` の中）。編集できるのは jpg・jpeg・png で、同じオリジンの画像だけ（`canEdit()`）。保存は `api.php` の `saveImage`（CSRF・POST だけ）で、別名なら `名前(1).拡張子` で新しいファイルにして `OnFileBrowserUpload` を発火し、上書きならリンクを断り、元のパーミッションを引き継いで置き換える。
- 2026-11-21: ファイルピッカーの開き方は 3 通りある。(1) 管理画面のシェルのモーダル（`window.evoOpenFilePicker()`（`manager/media/browser/evo-file-picker.js`）→ `EvoShell.openFilePicker()`、選んだら `window.EvoFileBrowserPick(path)`）、(2) シェルの無い画面のポップアップ（`window.opener.SetUrl(path)`）、(3) TinyMCE のダイアログの中の iframe（`assets/plugins/tinymce7/js/mcpuk-picker.js` の `editor.windowManager.openUrl()`、選んだら `postMessage({mceAction: 'evoFbPick', url})`）。返し方の分かれ目は `manager/media/browser/evo/js/picker.js` の `pickFile()` の 1 か所。(3) は、シェルのモーダルを TinyMCE のダイアログの上に開くとエディタごと壊れたため選んだ形（`mcpuk-picker.js` のコメント）。
- 2026-11-21: 配布 zip（`git archive --format=zip HEAD`）は、この時点の main（be35be6db）で 1,621,293 バイト。`tinymce-cropper.js` は zip の中で 4,393 バイト（元 15,746）。Cropper.js は CDN から読むので zip に入っていない。

## Decision Log

- 2026-11-21 本人の決定: 本文に data: 画像を入れない。画像の入口はファイルピッカー（`manager/media/browser/evo/`）に一本化する。
- 2026-11-21 本人の決定: 貼り付け・ドロップした画像は、ファイルピッカーと同じアップロードの API（`api.php`・`upload.js` の仕組み。権限・CSRF・`upload_images`・`upload_maxsize` の確かめ）で `{rb_base_dir}` に保存し、本文にはその URL を入れる（TinyMCE 7 の `images_upload_handler`）。
- 2026-11-21 本人の決定: 画像をダブルクリックしたときは、TinyMCE 7 プラグインの独自の画像編集（`tinymce-cropper.js`、CDN の Cropper.js、`config/manager.json` の `image_cropper`）を廃止し、その画像を開いた状態でファイルピッカーの画像編集（`imageEditor.js`、API の `saveImage`）を起動する。保存後に本文の src を差し替える。サイトの外の画像では何もしない。
- 2026-11-21 本人の決定: ファイルピッカーは、開くときの指定で特定のファイルの画像編集を開いた状態で起動できるようにする。
- 2026-11-21 本人の決定（置き場所）: 貼り付け・ドロップした画像は `images/年/月/`（例 `images/2026/11/`）に置く。月はサイトの時刻（`server_offset_time` を足した今の時刻）で決める。
  - 理由: 文書の ID が要らないので、新規の文書・TV のリッチテキスト・フロントでも同じに動き、ファイルピッカーで月ごとに探せる。
  - 却下: `images/` の直下（根に溜まって一覧が長くなる）。文書ごと（新規の文書は保存するまで ID が無く、ID の取り直しでファイルを移す処理が要る。TV・複製の扱いも要る）。
- 2026-11-21 本人の決定（ファイル名）: ドロップしたファイルは元の名前（サーバーの `sanitizeFilename()` を通す）。名前の無い貼り付け（スクリーンショットなど。ブラウザは `image.png` のような名前を付ける）は `pasted-年月日-時分秒.拡張子`（例 `pasted-20261121-143022.png`）。同名はファイルピッカーと同じく `名前(1).拡張子`。
  - 却下: いつも日時と乱数（名前から中身が分からない）。いつも元の名前（スクリーンショットが `image(1).png`・`image(2).png`… と並ぶ）。
- 2026-11-21 本人の決定（アップロードできないとき）: 受け付けない。ファイルピッカーが使えない利用者（`use_browser` が無効、フロントのウェブユーザーで `rb_webuser` が無効など）には、貼り付け・ドロップの画像を最初から受け付けない（`paste_data_images: false`）。送って断られたもの（書き込めない・拡張子が `upload_images` に無い・`upload_maxsize` を超える・CSRF の失敗）は、本文から消して理由を通知に出す。data: 画像のまま残すことはしない。
  - 本体に「ファイルのアップロード」のロールの権限が無い件は、この計画では扱わず別の Issue にする（Artifacts and Notes）。
- 2026-11-21 本人の決定（保存のしかたの既定）: TinyMCE から開いた画像編集は、ファイルピッカーと同じく「別名で保存」が既定。`imageEditor.js` の今の既定（開くたびに `copy` に戻す）をそのまま使うので、既定を変える引数は足さない。利用者が編集画面で「上書き」を選んだときは、src が同じ URL のままなので、エディタの中の画像を `fetch(url, {cache: 'reload'})` で読み直してから src を付け直す（要確認: これでエディタの iframe の中の表示が新しくなること）。
- 2026-11-21 本人の決定（width・height 属性）: 編集した画像を本文に戻すとき、width の属性はそのまま、height は保存した画像の縦横比で計算し直す（`Math.round(width × 新しい高さ ÷ 新しい幅)`）。width が無く height だけあるときは、height をそのままにして width を計算し直す。どちらも無ければ付けない。
  - 却下: 保存した画像の画素数にする（今の cropper の動き。大きな写真ではページの中で大きく出る）。両方消す。
- 2026-11-21 本人の決定（既存の data: 画像）: 既に本文に入っている data: 画像はそのまま残す。開いた時点の本文にある data: 画像は、`editor.editorUpload.addFilter()` でアップロードの対象から外す（開くだけでファイルが書かれ、開くたびに増えることを防ぐ）。文書をまとめて移す CLI は別の Issue にする（Artifacts and Notes）。
- 2026-11-21 本人の決定（保存時の変換 `convert_datauri_to_file`）: 対象を本文（`content`）の `<img>` の `src` の data: 画像だけにし、要約（`introtext`）は外す（#1700 で対応。`assets/docs/datauri-conversion.md` も合わせて直す）。
  - 理由: data: 画像は TinyMCE 側（M4）で入らなくするのが本筋だが、それで防げない経路と既存の文書のためのフォールバックとして残す。利用者は非エンジニアが多く、「リソースを開いてそのまま保存してください」と伝えるだけでレコードを直せる。
  - 既存の data: 画像を開いたときにアップロードしない決定（「既存の data: 画像」）はそのまま。開くだけでは変わらず、保存したときにこの変換でファイルになる。
  - 却下: 今どおり要約だけ（本文に入った data: 画像が直る手段が無い）。
- 2026-11-21 本人の決定（画像のダイアログの「アップロード」タブ）: 出さない（`image_uploadtab: false`）。アップロードは同じダイアログの参照ボタンから、ファイルピッカーで置き場所を選んで行う。
- 2026-11-21 本人の決定（進め方）: PR #1670（#1661、TinyMCE の setup を `js/editor-setup.js` へ）と PR #1671（#1668、ファイルピッカーの選択の帯）のマージを待ち、それから `origin/main` から始める。M1 は #1671 と同じ `filebrowser.js` を、M2・M4 は #1670 と同じ setup を触るため。
- 2026-11-21（計画者）ファイルピッカーへの指定は、今ある `folder`・`select`（開いたときに選ぶファイル）に `edit=1` を足す形にする。`select` のファイルが一覧にあり、`imageEditor.canEdit()` が通れば画像編集を開く。通らなければ（無い・gif・大きすぎる）一覧のまま理由を出す。
  - 理由: 開いたときに選ぶ処理（見つからなければ根のフォルダへ戻る、`navigateTo('', initialSelect)`）と URL の組み立て（`mcpuk-picker.js` の `buildUrl()`、`evo-file-picker.js` の `withInitialSelection()`）をそのまま使える。
  - 却下: Issue のコメントの案 `edit=<rb_base_dir からのパス>`（パスを分ける処理がもう 1 つ増える）。
- 2026-11-21（計画者）画像編集から開いたピッカーは、保存できたら保存したファイルを「選んだ」として返す。返し方は今の `pickFile()` をそのまま使う（シェルのモーダル・ポップアップ・TinyMCE の iframe の 3 通り）。呼び出し側から見ると、ファイルを選んだときと同じ形で URL が届く。編集を取り消したら、ピッカーごと閉じる（TinyMCE の iframe には新しい `postMessage({mceAction: 'evoFbClose'})`、シェルのモーダルは `EvoShell.closeModal()`、ポップアップは `window.close()`）。
  - 理由: 編集のためだけに開いたので、取り消しの後に一覧が残ると、もう一度閉じる手間が増える。返し方を増やさない。
  - 却下: 取り消し後も一覧を残す（別の画像を選べるが、TinyMCE のダブルクリックでは選んでも何も起きない）。
- 2026-11-21（計画者）TinyMCE からは、ファイルの選択（`file_picker_callback`）と同じく `editor.windowManager.openUrl()` の iframe で開く。開く・閉じる・結果を受ける処理は `mcpuk-picker.js` の `openInEditorDialog()` に寄せ、ダブルクリックの処理もこのファイルに置く（ファイルピッカーとの橋渡しを 1 ファイルにする）。`file_browser` が `none` のときはこのファイルを読まないので、ダブルクリックも貼り付けのアップロードも無い（貼り付けの画像は受け付けない）。
- 2026-11-21（計画者）画像の src からファイルを割り出すのは JS（`mcpuk-picker.js`）で行う。PHP は `BrowserConfig::baseUrl()`（`rb_base_url` の絶対の URL）を、今の `mcpukBootstrapScript()` の値と一緒に渡す。src は `editor.documentBaseURI.toAbsolute()` で絶対の URL にし、`baseUrl` で始まり、その次の要素が `images`・`media`・`files` のどれかなら、その種類・フォルダ・ファイル名（要素ごとに `decodeURIComponent`）で開く。始まらない（別のオリジン・`rb_base_url` の外）、`data:`・`blob:`、拡張子が jpg・jpeg・png でないものは、何もしない。
  - 却下: 今の `splitCurrentPath()`（パスの中の `images` という要素を探す）を使う。`rb_base_url` の外の `/images/` を誤って拾う。
- 2026-11-21（計画者）画像を選んだときの帯の「画像を編集」ボタンは残し、ダブルクリックと同じ処理にする（キーボードで操作する人のため。今の cropper にもある）。アイコンは TinyMCE の `edit-image`。
- 2026-11-21（計画者）貼り付けのアップロードは、`api.php` のピッカーの範囲に新しい操作（仮の名前 `uploadPasted`、CSRF あり・POST だけ）を足す。置き場所の決まり（`images/年/月/`）をサーバーで持ち、無いフォルダを作り（`mkdir` と同じ確かめ。守る場所・パーミッション・管理操作ログ）、1 ファイルずつの保存は `fbPickerUpload()` の繰り返しの中身を共通の関数に出して使う（拡張子・大きさ・同名・縮小・イベントが同じになる）。応答は `{uploaded:[{index,name,path,url}], errors:[...]}`。`path` は一覧と同じ作り方（共通の関数）にする。
  - 理由: 置き場所の決まりを JS に持たせると、フォルダを作る往復（1 階層ずつの `mkdir`）が増え、決まりがサーバーと画面に分かれる。
  - 却下: 今の `upload` に「フォルダが無ければ作る」を足す（ピッカーの画面のアップロードの動きまで変わる）。
- 2026-11-21（計画者）アップロードできるかは、エディタを出すときに PHP で `PickerScope::authorize(evo(), new BrowserConfig(evo()), 'images')` を呼んで決める（ピッカーの入口と同じ判定を使う）。通れば `images_upload_handler` を設定し、トークン（`getCurrentCsrfToken()`）と送り先の URL を渡す。通らなければ受け手を設定せず `paste_data_images: false` にする。`images_file_types` は TinyMCE の既定と `upload_images` の重なりにする（許可していない形式は最初から受け付けない）。
- 2026-11-21（計画者）アップロードの途中で保存したときは、送る前にアップロードを待つ。エディタの元の欄のフォームに、捕捉の段階（capture）の `submit` の受け手を付け、未アップロードの画像があれば `preventDefault()` して `editor.uploadImages()` を待ち、終わったら `form.requestSubmit(e.submitter)` で送り直す（要確認: shell の document の受け手は `defaultPrevented` を見て送らないこと、TinyMCE の `submit` の受け手より先に呼ばれること、`document.mutate.save.click()` の合成クリックでも同じに動くこと）。
- 2026-11-21（計画者）既存のサイトの設定ファイルに `image_cropper` の項目が残っていても、読む処理を消すだけにする（TinyMCE は知らない設定を無視する）。互換のための読み替えは置かず、リリースノートに書く。

## Outcomes & Retrospective

## Context and Orientation

用語:

- ファイルピッカー: 管理画面でファイルを選ぶ・アップロードする画面。`manager/media/browser/evo/`。入口は `browser.php`（ピッカー）と、メニューの「ファイル管理」（a=31、管理モード）。この計画で触るのはピッカーだけ。
- 種類のフォルダ: `rb_base_dir`（既定 `content/`）の下の `images`・`media`・`files`。ピッカーは開くときの `type` でどれか 1 つを根にする。
- data: 画像・blob: 画像: 画像の中身を URL に直に書いたもの（data:）と、ブラウザの中だけで有効な仮の URL（blob:）。TinyMCE は貼り付けた画像を blob: で表示し、本文を取り出すときに data: に戻す。
- `images_upload_handler`: TinyMCE が blob:・data: の画像を見つけたときに呼ぶ関数。画像を受け取り、保存した URL を返す Promise を返す。

主なファイル（origin/main be35be6db 時点）:

- `assets/plugins/tinymce7/src/TinyMCE7/Editor/EditorInitializer.php`: エディタの設定を作り、`tinymce.init()` の JS を出す。`image_cropper` を読み、`window.tinymce7CropperConfig` と `js/tinymce-cropper.js` を出す（71〜76 行目・132〜141 行目）。`file_browser` が `mcpuk`（既定）なら `mcpukBootstrapScript()` と `js/mcpuk-picker.js` を出し、`config.file_picker_callback = window.mceModxFilePicker` にする。setup は PHP の文字列で組み立てている（#1670 で `js/editor-setup.js` に移る）。
- `assets/plugins/tinymce7/src/TinyMCE7/Editor/FileBrowserResolver.php`: ピッカーの URL と、`window.MODX_FILE_BROWSER_URL`・`MODX_BASE_URL`・`MODX_SITE_URL`・`MODX_STRIP_IMAGE_PATHS` を出す。
- `assets/plugins/tinymce7/js/mcpuk-picker.js`: `window.mceModxFilePicker`。`openInEditorDialog()`（`openUrl` の iframe、`evoFbPick` を受ける）、`normalizeUrl()`（ピッカーの `path` を本文に入れる URL にする。`strip_image_paths` を見る）。
- `assets/plugins/tinymce7/js/tinymce-cropper.js`、`config/manager.json` の `image_cropper`、`README.md` の 17 行目、`docs/TECHNICAL.md` の「画像編集 (`image_cropper` 設定)」の節。`config/frontend.json` には `image_cropper` は無い。
- `manager/media/browser/evo/browser.php`: `type`・`folder`・`select` を読み、`BrowserView::jsConfig()` に渡す。
- `manager/media/browser/evo/lib/BrowserView.php`: `jsConfig()`（`initialFolder`・`initialSelect` など）。
- `manager/media/browser/evo/filebrowser.js`: 画面の初期化。`createImageEditor()` の `onSaved` は一覧を読み直して保存した名前を選ぶ。最初の一覧は `load(initialSelect)`。
- `manager/media/browser/evo/js/picker.js`: ピッカーの振る舞い（`pickFile()`）。
- `manager/media/browser/evo/js/imageEditor.js`: 画像編集。`open(file)`（`file` は一覧の項目。`name`・`url`・`mtime`・`width`・`height`）、`canEdit(file)`、保存のしかたのラジオ（`data-role="edit-mode"`）。
- `manager/media/browser/evo/api.php`: `fbPickerUpload()`・`fbFindFreePath()`・`fbActionMkdir()`・`fbActionSaveImage()`・`fbPickerList()`。範囲ごとの操作の表は `PickerScope::actions()`。
- `manager/media/browser/evo/lib/PickerScope.php`・`BrowserConfig.php`: 認可・拡張子・大きさ・`baseUrl()`。
- `manager/includes/csrf_token.php`: `getCurrentCsrfToken()`・`checkCsrfToken()`（POST の `csrf_token`、`X-CSRF-Token` ヘッダー）。
- `assets/docs/file-storage.md`（置き場所の決まり）、`assets/docs/datauri-conversion.md`（保存時の変換）。

DocumentParser（`executeParser()`・`prepareResponse()`・`parseDocumentSource()`・`postProcess()`）への影響は無い（管理画面のエディタとファイルピッカーだけ）。キャッシュへの影響も無い（文書の保存は今どおりキャッシュを消す。画像のファイルはページのキャッシュに入らない）。イベントは、貼り付けのアップロードと別名の保存で、今のピッカーと同じ `OnFileBrowserUpload` が発火する。新しいイベントは足さない。

## Plan of Work

M1 でファイルピッカーに「このファイルの画像編集を開いて起動し、保存したらそのファイルを選んだとして返す」入口を作る。これは TinyMCE に依らず、シェルのモーダル・ポップアップ・iframe のどれから開いても同じに動く。M2 で TinyMCE のダブルクリックをこの入口につなぎ、M3 で独自の画像編集を消す（M2 と M3 は同じダブルクリックの奪い合いになるので 1 つの PR にする）。M4 で貼り付け・ドロップをファイルピッカーの API につなぐ。M4 は M1〜M3 に依らないので、順番を入れ替えてもよい。

TinyMCE 側の新しい処理は、ファイルピッカーとの橋渡しとして `mcpuk-picker.js` に集め、setup（#1670 の後は `js/editor-setup.js`）からはそれを呼ぶだけにする。サーバー側の決まり（置き場所・権限・拡張子・大きさ・同名）は `api.php` とピッカーの範囲のクラスだけに置き、TinyMCE のプラグインには持たせない。

## Concrete Steps

始める前に `gh pr view 1670 -R modxcms-jp/evolution-jp --json state` と `gh pr view 1671 ...` が両方 `MERGED` であることを確かめる（まだなら待つ）。ブランチは `origin/main` から `git worktree add -b <ブランチ名>` で作る。Issue には紐づけない（M1 の PR が #1666 を閉じないように）。PHP を変えたら、`docker run --rm -v "$PWD":/app -w /app php:7.4-cli php -l <ファイル>` と検証用のイメージの `php -l` を通す。

### M1: 指定したファイルの画像編集を開いて起動する（ブランチ `feat/1666-picker-open-editor`）

1. `manager/media/browser/evo/browser.php`: `get_boolvar('edit')` を読み、`BrowserView::jsConfig()` に `initialEdit` として渡す。`lib/BrowserView.php` の `jsConfig()` に `'initialEdit' => !empty($options['initialEdit'])` を足す。
2. `manager/media/browser/evo/filebrowser.js`: 最初の一覧を読み終えたとき（`load(initialSelect)` の後、選んだファイルが見つかったとき）、`config.initialEdit` なら `imageEditor.canEdit(file)` を確かめて `imageEditor.open(file)` する。通らなければ、一覧の上にメッセージ（言語の項目を足す。例 `fb_edit_unavailable`「この画像は編集できません」）を出す。最初の一覧の 1 回だけで、フォルダを移ったあとには開かない。
3. 編集から開いたときの終わり方を、振る舞い（`behaviors`）に問う形で足す。`imageEditor.js` の `createImageEditor()` に `onCancel` を足し（取り消し・Esc・閉じるで呼ぶ。保存のあとは呼ばない）、`filebrowser.js` は `config.initialEdit` で開いた編集に限り、保存したら一覧を読み直して保存したファイルの項目を `behaviors.editDone(entry)` に、取り消したら `behaviors.editCancel()` に渡す。`js/picker.js` の振る舞いに `editDone`（`pickFile(entry.path)`）と `editCancel`（シェルのモーダルは `EvoShell.closeModal()`、ポップアップは `window.close()`、iframe は `window.parent.postMessage({mceAction: 'evoFbClose'}, window.location.origin)`）を足す。管理モード（`js/manage.js`）は足さない（`initialEdit` を渡さない）。
4. 保存のしかたの既定は今どおり「別名で保存」のまま（`imageEditor.js` は変えない）。
5. `api.php` の頭の説明に、`browser.php` の `edit` は書かない（API ではない）。`browser.php` の頭のコメントに `edit=1` の意味を足す。

期待される観測: `browser.php?type=images&folder=<f>&select=<jpg>&edit=1` を単独で開くと、画像編集が開いた状態で出る。保存するとピッカーが閉じ（ポップアップ）、呼んだ側の `SetUrl` に保存したファイルの `path` が渡る。`edit` を付けないときは main と同じ。

### M2・M3: ダブルクリックをファイルピッカーの編集にし、cropper を消す（ブランチ `feat/1666-tinymce7-edit-in-picker`）

1. `FileBrowserResolver::mcpukBootstrapScript()`: `BrowserConfig::baseUrl()` を足して渡す（`require_once MODX_MANAGER_PATH . 'media/browser/evo/lib/BrowserConfig.php'`）。値が増えるので、`window.MODX_*` を足し続けず、1 つのオブジェクト（例 `window.MODX_FILE_PICKER = {browserUrl, baseUrl, rbBaseUrl, siteUrl, stripImagePaths}`）にまとめ、`mcpuk-picker.js` の読み方も合わせる。
2. `assets/plugins/tinymce7/js/mcpuk-picker.js`:
   - `resolveSiteImage(editor, img)`: Decision Log の割り出しを行い、`{type, folder, name}` か null を返す。
   - `openImageEditor(editor, img)`: `browser.php?type=..&folder=..&select=..&edit=1` を `openInEditorDialog()` で開く。`onMessage` に `evoFbClose`（ダイアログを閉じる）を足す。`evoFbPick` で受けた `path` を `normalizeUrl()` に通し、`editor.undoManager.transact()` の中で src を差し替え、`data-mce-src` も同じ値にし、`srcset` を消し、width・height を Decision Log のとおりに直す（新しい画像の縦横比は、受けた URL を `new Image()` で読んで `naturalWidth`・`naturalHeight` から取る。上書きのときは `fetch(url, {cache: 'reload'})` の後に読む）。最後に `editor.nodeChanged()` と `editor.dispatch('change')`。
   - `global.mceModxImageEdit = function (editor) {...}`: `DblClick`（`event.target` が `IMG` で、`data-mce-object`・`data-mce-placeholder` が無く、`resolveSiteImage()` が null でないとき `preventDefault()` して開く。null なら何もしない）と、`edit-image` アイコンのボタンを context toolbar（画像を選んだとき）に登録する。ボタンの文言はプラグインの言語ファイル（`src/TinyMCE7/lang/`）から渡す。
3. setup から `if (window.mceModxImageEdit) window.mceModxImageEdit(editor)` を呼ぶ（#1670 の後は `js/editor-setup.js`、前なら `EditorInitializer.php` の文字列）。
4. M3: `assets/plugins/tinymce7/js/tinymce-cropper.js` を消す。`EditorInitializer.php` の `image_cropper` を読む処理・`window.tinymce7CropperConfig`・cropper の script を消す。`config/manager.json` の `image_cropper` を消す。`README.md` の 17 行目と `docs/TECHNICAL.md` の節を、ファイルピッカーの画像編集の説明に書き換える。
5. 確認のコマンド: `git grep -n "cropper\|Cropper\|image_cropper" -- assets manager` が、ファイルピッカーの `cropBox.js`（名前が似ているだけで別物）以外で 0 件。

期待される観測: 文書の編集（a=27）でファイルピッカーで入れた jpg をダブルクリックすると、TinyMCE のダイアログの中にファイルピッカーの画像編集が開く。回転して保存すると、本文の src が `.../content/images/<名前>(1).jpg` に変わり、保存後の DB の `content` に `data:` が無い。

### M4: 貼り付け・ドロップした画像をアップロードする（ブランチ `feat/1652-tinymce7-paste-upload`）

1. `manager/media/browser/evo/api.php`:
   - `fbPickerUpload()` の 1 ファイルの保存（拡張子・大きさ・空いている名前・`fbStoreUpload()`・`OnFileBrowserUpload`・記録）を `fbPickerStoreFile($resolver, $scope, $modx, $dir, $file)` に出し、`fbPickerUpload()` はそれを繰り返すだけにする（応答は今と同じ）。
   - `fbPickerList()` の `path`・`url` の作り方を `fbPickerFileUrls($scope, $relPath)` に出す。
   - 新しい操作 `uploadPasted`（`PickerScope::actions()` に `'csrf' => true, 'post' => true`）: 置き場所 `images/年/月/`（サイトの時刻）を決め、無ければ 1 階層ずつ作る（`fbActionMkdir()` の確かめと作り方を共通の関数 `fbEnsureFolder()` に出して両方で使う）。ファイルは `files[]` で受け、`fbPickerStoreFile()` で保存し、`{uploaded:[{index,name,path,url}], errors:[{index,name,message}]}` を返す。名前は画面が付けて送り（M4 の 3）、サーバーは今のアップロードと同じく無害化と同名の連番だけを行う。
   - 頭の説明に `uploadPasted` を足し、upload の応答の説明の `url` を実装に合わせて直す。
2. `EditorInitializer.php`（`file_browser` が `mcpuk` のときだけ）: `PickerScope::authorize()` が通れば、`mcpukBootstrapScript()` の値に `upload: {url: api.php の絶対の URL, csrfToken: getCurrentCsrfToken()}` を足し、設定に `images_upload_handler`（JS で `window.mceModxImageUpload` を入れる。`file_picker_callback` と同じ書き方）・`images_file_types`（`upload_images` との重なり）・`image_uploadtab: false` を入れる。通らなければ `paste_data_images: false`。`getCurrentCsrfToken()` が無い（管理画面の外の）ときも通らない扱いにする。
3. `mcpuk-picker.js`:
   - `global.mceModxImageUpload = function (blobInfo, progress) {...}`: FormData に `action=uploadPasted`・`type=images`・`csrf_token`・`files[]` を入れ（名前は、`blobInfo.blob()` が名前を持つ `File` で、その名前が `image.拡張子` でなければ元の名前、そうでなければブラウザの時刻で `pasted-年月日-時分秒.拡張子`。拡張子は `blob.type` から決める（`image/jpeg` は `jpg`））、`XMLHttpRequest` で送る（`upload.onprogress` を `progress()` に渡す）。成功なら `normalizeUrl(uploaded[0].path)` で resolve、`errors` か JSON でない応答（CSRF の 403 など）なら `reject({message, remove: true})`（画像を本文から消して理由を出す）。
   - `init` のときに本文の data: 画像の src を覚え、`editor.editorUpload.addFilter()` でそれを外す（既存の data: 画像を開くたびにアップロードしない）。貼り付けた画像は blob: で入るので対象に残る。
   - 保存の前に待つ処理（Decision Log の capture の `submit`）を `global.mceModxImageEdit` と同じ setup の入口で付ける。
4. 文書: `assets/docs/file-storage.md` に「エディタに貼り付けた画像の置き場所」の節を足す。`assets/plugins/tinymce7/docs/TECHNICAL.md` に貼り付けのアップロードを書く。`assets/docs/datauri-conversion.md` に、TinyMCE 7 では本文の data: 画像がエディタで入らなくなったこと（貼り付けはファイルピッカーの API でアップロード）を書く（保存時の変換の対象は #1700 で本文に直してある）。

期待される観測: a=27 で PNG を貼り付けると、少しのあいだ通知が出て、`content/images/2026/11/pasted-20261121-143022.png` のようなファイルができ、ファイルピッカーの一覧に出る。保存後の DB の `content` の src はそのファイルの URL で、`data:` も `blob:` も無い。

## Validation and Acceptance

Docker の検証環境（`adaptive-validation-runner`。`docker compose exec app ...`）で、修正前（main）と比べる。画面の確かめは Playwright のヘッドレスで、本文の確かめは DB（`php evo.php db:query "SELECT LENGTH(content), LOCATE('data:', content), LOCATE('blob:', content) FROM modx_site_content WHERE id=<id>"`）で行う。コンソールのエラーが 0 件であることを、各段階で確かめる。

M1:

| 開き方 | 期待 |
| --- | --- |
| `browser.php?type=images&select=<jpg>&edit=1`（単独・ポップアップ） | 画像編集が開いた状態で出る。保存（別名）で `<名前>(1).jpg` ができ、`window.opener.SetUrl` にその `path` が渡り、窓が閉じる。取り消しで窓が閉じ、ファイルは増えない |
| シェルのモーダル（`evoOpenFilePicker(url + '&edit=1')` を画面から呼ぶ） | 同じ。保存で `SetUrl` に渡ってモーダルが閉じる。取り消しでモーダルが閉じる |
| `select` が gif・無いファイル・4096px を超える画像 | 一覧が出て、編集できない理由が出る（無いファイルは今どおり根のフォルダへ戻る） |
| `edit` なし（今の 3 つの開き方、管理モード a=31） | main と同じ（選択・プレビュー・右クリックの「編集」と保存後の一覧） |

M2・M3:

| 操作 | main | 修正後 |
| --- | --- | --- |
| ピッカーで入れた jpg をダブルクリック → 何もせず保存（別名） | cropper が開き、適用で data: の PNG（本文 1,650,099 文字） | ピッカーの編集が開き、src が `(1).jpg` に変わる。本文の長さは 200 文字未満で、`data:` が 0 |
| width・height 属性のある画像を左回転・トリミングして保存 | data: の PNG、width・height は書き出した画素数 | 新しいファイルの URL。width は元のまま、height は新しい縦横比で計算した値 |
| 帯の「画像を編集」ボタン | cropper が開く | ダブルクリックと同じ |
| 開いたときの保存のしかた | ― | 「別名で保存」が選ばれている |
| 上書きを選んで保存 | ― | src は同じ URL のまま、エディタの中の画像が新しい中身で出る |
| 別のオリジンの画像・data: 画像・gif をダブルクリック | cropper が開く | 何も開かない |
| 編集を取り消す | ― | ダイアログが閉じ、本文は変わらない（未保存の印も立たない） |
| ページの読み込み | `cdnjs.cloudflare.com` の cropper を読む（ダブルクリック時）、`tinymce-cropper.js` を読む | どちらも読まない（Playwright の `page.on('request')` で 0 件） |
| ファイル選択（画像のダイアログの参照ボタン） | ピッカーが開いて選べる | 同じ |

M4:

| 操作 | main | 修正後 |
| --- | --- | --- |
| PNG を貼り付け（Playwright で `ClipboardEvent('paste')` に `DataTransfer` の `File` を入れて送る）して保存 | 本文に data: | 置き場所にファイルができ、本文はその URL。システムの管理操作ログに `[picker] Uploaded File` |
| jpg のファイルをドロップ | 本文に data: | 元の名前で保存、同名があれば `(1)` |
| 貼り付けの直後（アップロード中）に保存 | ― | アップロードを待ってから送られ、本文に `data:`・`blob:` が無い |
| `upload_images` から png を外して PNG を貼り付け | 本文に data: | 本文に画像が入らない（`images_file_types` から外れる。要確認: TinyMCE が受け付けなかった画像をブラウザの既定の貼り付けで data: のまま入れないこと。入るなら `paste_preprocess` で落とす） |
| `upload_maxsize` を 1000 にして貼り付け | 本文に data: | 画像が消え、大きすぎる旨が出る。ファイルはできない |
| 保存先に書き込めない（`content/images/` を 555 にして貼り付け） | 本文に data: | 画像が消え、失敗の理由が出る |
| `use_browser` を 0 にして貼り付け・ドロップ | 本文に data: | 受け付けない。本文は変わらない |
| 月の初めの貼り付け（フォルダが無い） | ― | `images/年/月/` が作られ、管理操作ログに `[picker] Created Folder` とアップロードの行が残る |
| スクリーンショット（名前 `image.png`）を同じ秒に 2 回貼り付け | ― | `pasted-年月日-時分秒.png` と `pasted-年月日-時分秒(1).png` |
| CSRF のトークンが古い（セッションのトークンを消してから貼り付け） | ― | 画像が消え、失敗が出る（エディタは壊れない） |
| data: 画像の入った既存の文書を開いて、何もせず閉じる・保存する | 本文に data: | ファイルは増えず、本文もそのまま（data: のまま） |
| 画像のダイアログ | アップロードのタブが無い | 無い（main と同じ） |
| 要約（introtext）に data: 画像を入れて保存 | `content/images/datauri/<ID>/` に変換される | 同じ |

PHP 7.4 で、`api.php`・`browser.php`・`BrowserView.php`・`EditorInitializer.php`・`FileBrowserResolver.php` の `php -l` が通る。`uploadPasted` を `php:7.4-cli` ではなく検証用のイメージで動かし、`curl` で CSRF なし（403）・GET（405）・拡張子違い・大きすぎ・正常の 5 通りの応答を確かめる（管理画面の curl には `Accept-Language` を付ける）。

配布 zip の大きさ: 各 PR で `git archive --format=zip HEAD -o /tmp/after.zip` と main を比べ、差を PR に書く。M3 で `tinymce-cropper.js`（zip の中で約 4.4KB）が減り、M1・M2・M4 で増える JS・PHP はそれより小さい見込み。

## Idempotence and Recovery

各マイルストーンは独立した PR なので、途中で止まったら、そのブランチのワークツリーで続ける。前の PR がマージされる前に次へ進むときは、前のブランチから次のブランチを作り、前のブランチを base にした PR にする。検証で作ったファイル（`content/images/` の下の貼り付けた画像・編集で増えたファイル）は、確かめが終わったら消す。設定（`use_browser`・`upload_images`・`upload_maxsize`）を変えた確かめは、終わったら元の値に戻す。

すべてのマイルストーンの実装と検証が済んだら、先にそのことを告げて本人の確認を取り、確認の後で `.agent/PLANS.md` の「完了処理プロトコル」に従う（この 2 つの Issue は `.agent/roadmap.md` に載っていないので、ExecPlan のアーカイブだけを行う。載せるかは本人に確かめる）。

## Artifacts and Notes

- Issue: #1652（貼り付け・ドロップで data: 画像が入る）、#1666（ダブルクリックの画像編集で data: 画像になる）。関連: #1668 / PR #1671（ファイルピッカーの選択の帯に「編集」）、#1661 / PR #1670（TinyMCE の setup の JS をファイルへ）
- PR の分け方: PR 1 = M1（`Refs #1666`）、PR 2 = M2+M3（`Closes #1666`）、PR 3 = M4（`Closes #1652`）
- 想定のコミット:
  - M1: `feat(filebrowser): 指定したファイルの画像編集を開いた状態でピッカーを起動できるようにする`
  - M2: `feat(tinymce7): 画像のダブルクリックでファイルピッカーの画像編集を開く`
  - M3: `refactor(tinymce7): 独自の画像編集（Cropper.js）を廃止する`
  - M4（api）: `refactor(filebrowser): ピッカーのアップロードの 1 ファイルの保存を共通の関数にする`
  - M4（api）: `feat(filebrowser): エディタに貼り付けた画像を決まった置き場所へアップロードする操作を足す`
  - M4（tinymce7）: `feat(tinymce7): 貼り付け・ドロップした画像をファイルピッカーの API でアップロードする`
  - 文書: `docs: 貼り付けた画像の置き場所と保存時の変換の対象を実装に合わせる`
- ExecPlan のブランチ: `plan/1652-1666-image-upload`
- 別に起票する（起票はまとめ役が行う。この計画では扱わない）:
  - ファイルのアップロードのロールの権限: 本体に「ファイルのアップロード」に当たるロールの権限が無く、ファイルピッカー（`PickerScope`）は管理画面にログインしている人なら誰でもアップロードできる。貼り付けのアップロードも同じ判定を使うので、権限を足すとそのまま効く
  - 既に本文に入っている data: 画像をまとめてファイルへ移す CLI: #1652・#1666 の不具合で入った data: 画像を、文書をまたいで `images/年/月/` などへ移し、本文の src を書き換える（この計画では開いたときにアップロードの対象から外すだけ）
  - 配布 zip の大きさ: `git archive --format=zip HEAD` で、main（be35be6db）がすでに 1,621,293 バイトで、目安の 1.5MB を超えている

## Interfaces and Dependencies

- TinyMCE 7（CDN の `tinymce@7`、または同梱の `tinymce_use_local`）: `images_upload_handler(blobInfo, progress) => Promise<string>`、`editor.editorUpload.addFilter()`、`editor.uploadImages()`、`editor.windowManager.openUrl()` の `onMessage`、`editor.ui.registry.addButton()`・`addContextToolbar()`。
- ファイルピッカーの外から見える変化: `browser.php` が `edit=1` を受ける。`api.php` のピッカーの範囲に `uploadPasted` が増える。`postMessage` に `evoFbClose` が増える（`evoFbPick` は今どおり）。
- TinyMCE 7 プラグインの外から見える変化: `config/manager.json` の `image_cropper` と `window.tinymce7CropperConfig` が無くなる（自分の設定ファイルで cropper を調整していたサイトは、項目が読まれなくなる。リリースノートに書く）。`window.MODX_FILE_BROWSER_URL`・`MODX_BASE_URL`・`MODX_SITE_URL`・`MODX_STRIP_IMAGE_PATHS` は 1 つのオブジェクトにまとまる（本体で読むのは `mcpuk-picker.js` だけ。2026-11-21 に `git grep` で確かめた）。
- 本体の設定: `use_browser`・`rb_base_dir`・`rb_base_url`・`rb_webuser`・`strip_image_paths`・`upload_images`・`upload_maxsize`・`image_limit_width`・`new_folder_permissions`・`new_file_permissions`。新しい設定は足さない。
