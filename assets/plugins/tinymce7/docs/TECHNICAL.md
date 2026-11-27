# TinyMCE 7 プラグイン 技術メモ

このドキュメントはプラグインのメンテナンスやカスタマイズを担当する技術者向けの情報です。README では触れていない設定方法や内部構造について記載します。

## バージョンポリシー
- 単体でも配布しますが、サポートするのは最新の Evolution CMS JP Edition だけです（コアのヘルパー関数 `hsc()` などを使います）。
- 現在の安定版は `1.0.1` で、以降のマイナーバージョンでは後方互換性を保ちつつ改善や機能追加を行う想定です。
- 破壊的変更を伴う場合はメジャーバージョンを更新し、CHANGELOG で告知します。
- アップグレード時は設定キーや JSON 構成ファイルの差分を確認してください。

## ファイル構成と配置
- プラグインのエントリーポイント: `plugin.tinymce7.php`
- 設定ファイル: `config/manager.json`, `config/frontend.json`, `config/toolbar-presets.json`
- 管理画面設定用スタイル: `css/tinymce7.settings.css`
- MCPuk 連携スクリプト（ファイルの選択と画像の編集）: `js/mcpuk-picker.js`
- ひな形挿入プラグイン: `js/modx-template.js`
- リンクのダイアログの文書の一覧（JSON を返す入口）: `link-list.php`
- 本文のタグの補完と色付け: `js/tag-assist.js`（`window.mceModxTagAssist(editor)`。`editor-setup.js` から呼ぶ。管理画面だけ読み込む）と、名前の一覧（JSON を返す入口）: `tag-names.php`
- エディター共通の setup（未保存の印・input の通知・リソースの本文の高さ・リンクのダイアログの選択肢・画像編集と貼り付けのアップロードの呼び出し）: `js/editor-setup.js`（`window.tinymce7EditorSetup(editor)`。ファイルピッカーの画像編集 `window.mceModxImageEdit(editor)` もここから呼ぶ）。`EditorInitializer` は config の JSON を出し、設定ファイルやサイトの `setup` を先に呼んだあとでこれを呼ぶ
- TinyMCE ローカル配置先 (任意): `tinymce/js/tinymce/` に公式 TinyMCE7 パッケージを展開
- オートローダー: `src/bootstrap.php`

Evolution CMS では `assets/plugins/tinymce7/` 配下に設置し、Composer などの追加ライブラリは不要です。

## コアとの連携イベント
プラグインは以下のイベントでコアと連携します。

| イベント名 | 役割 |
| --- | --- |
| `OnRichTextEditorRegister` | プラグインを利用可能なエディターとして登録します。|
| `OnRichTextEditorInit` | TinyMCE の初期化スクリプトを出力します。|
| `OnInterfaceSettingsRender` | システム設定画面とユーザー設定画面に TinyMCE7 用の設定入力を追加します。ユーザー設定画面では、編集中のユーザーの値を表示し、ツールバー構成・メニューバー・Enter キーの動作だけを出します（空欄は「グローバル設定を使用」）。編集欄の body の id・クラス、CSSスタイルセレクタ、リンクの rel、iframe の sandbox、ひな形はシステム設定画面（a=17）にだけ出します。|

## 設定ファイル
`config/` 配下の JSON ファイルは TinyMCE の設定オブジェクトに対応しています。キーや値は TinyMCE 公式ドキュメントの項目と同じ形式です。

| ファイル | 説明 |
| --- | --- |
| `manager.json` | 管理画面での TinyMCE 設定。高さ、プラグイン、ツールバーなどを定義。リソースの編集画面の本文だけは、内容の量に合わせて、この高さから画面に収まる高さまでの間で伸び縮みさせる（`js/editor-setup.js`）。|
| `frontend.json` | `OnRichTextEditorInit` で `forfrontend` パラメーターが真の場合に読み込まれます。|
| `toolbar-presets.json` | 管理画面のプリセット選択肢（simple/formats/legacy/full など）を定義します。キーの並びが設定画面の選択肢の並びになり、表示名は言語ファイルの `tinymce7_toolbar_<キー>` です。|

更新後は Evolution CMS のキャッシュをクリアして反映させます。

文字の大きさの一覧は、`manager.json`・`frontend.json` の `font_size_formats`（px）で決めます。欄には今の文字の計算後の大きさ（px）が出るので、一覧も px にして単位をそろえています。

ツールバーのボタン名は TinyMCE 7 の名前で書きます。TinyMCE 6 で `formatselect` → `blocks`、`fontsizeselect` → `fontsize`、`styleselect` → `styles`、`fontselect` → `fontfamily` などに変わり、古い名前は警告なしで無視されます（[公式の移行ガイド](https://www.tiny.cloud/docs/tinymce/latest/migration-from-5x/)）。

### TinyMCE 本体の読み込み元

`tinymce_script_url` を指定していればその URL を使用し、未指定の場合は既定の CDN (`https://cdn.jsdelivr.net/npm/tinymce@7/tinymce.min.js`) から読み込みます。ローカルで使いたい場合は公式パッケージの `tinymce/` を `assets/plugins/tinymce7/tinymce/` に配置し、設定ファイルに `"tinymce_use_local": true` を追加してください。CDN・ローカル問わず独自 URL を使う場合は `tinymce_script_url` で上書きできます。

```json
{
  "tinymce_script_url": "https://cdn.jsdelivr.net/npm/tinymce@7/tinymce.min.js"
}
```

このキーを省略した場合は既定の CDN (`https://cdn.jsdelivr.net/npm/tinymce@7/tinymce.min.js`) を利用します。ローカル利用を優先したい場合は `"tinymce_use_local": true` を指定したうえで、`assets/plugins/tinymce7/tinymce/js/tinymce/tinymce.min.js` が存在することを確認してください。

```json
{
  "tinymce_use_local": true
}
```

プラグイン同梱の JS（`js/*.js`）、自動で決まる言語ファイル（`language_url`）、ローカルの `tinymce.min.js` を使うときのその URL には、ファイルの更新時刻を `?v=` で付けます（CDN の TinyMCE を使うときも、同梱の JS と言語ファイルには付けます）。ローカルの TinyMCE が後から読むプラグイン・スキン・言語ファイルには、`cache_suffix`（`v=` と `tinymce.min.js` の更新時刻）を設定します。`cache_suffix` は、設定ファイルにあればそれを優先し、CDN や `tinymce_script_url` を使うときは付けません。ローカルの TinyMCE はパッケージを丸ごと入れ替えてください（`tinymce.min.js` を変えずにプラグインやスキンだけ差し替えると、版が変わりません）。

### 言語ファイルの解決順序

TinyMCE の UI 言語は `language` オプションで決まります（未指定時は EVO の manager_language を判定）。言語ファイルは次の優先度で解決します。

1. `assets/plugins/tinymce7/tinymce/js/tinymce/langs/<lang>.js`（公式パッケージをそのまま配置した場合）
2. `assets/plugins/tinymce7/langs/<lang>.js`（必要最小限の言語ファイルだけを置きたい場合）

Tiny Cloud 公式の言語パック配布はダウンロード前提で CDN はありません。`language_url` が見つからない場合は TinyMCE 既定の英語 UI で表示されます。必要な言語だけを上記のいずれかの場所へコピーしてください。

### 画像編集（ファイルピッカーの画像編集）

`tinymce7_file_browser` が `mcpuk`（既定）のとき、ファイルピッカーの画像編集（トリミング・回転・サイズ変更）で本文の画像を編集できます。保存すると、保存したファイルの URL で本文の画像の `src` を差し替えます（`data:` 画像にはなりません）。処理は `js/mcpuk-picker.js` の `window.mceModxImageEdit(editor)` で、`js/editor-setup.js` から呼びます。入口は次の 2 つです。

- 本文の画像をダブルクリックすると、TinyMCE の「画像の挿入/編集」ダイアログが開きます。ソース欄の参照ボタンの隣の「画像を編集」を押すと、その画像を開いた状態でファイルピッカーの画像編集が開きます。保存すると、ダイアログのソースと幅・高さの欄が書き換わり、幅・高さ・キャプション・クラスなどをそのダイアログで直してから「保存」で本文に入れます。
  - TinyMCE の画像のダイアログにはボタンを足す API が無いので、ダイアログが開いたとき（`OpenWindow`）にその DOM へボタンを差し込みます。TinyMCE の更新でダイアログの作りが変わって差し込めないときは、コンソールにエラー（`TinyMCE7: the image dialog layout has changed ...`）を出します。TinyMCE の版を上げたときは、ダイアログにボタンが入ることを確かめてください。`image_advtab` などでダイアログにタブを付けたときは、タブを切り替えると TinyMCE が中身を描き直すので、ボタンは消えます（帯のボタンを使ってください）。
  - ボタンは、ソースがサイトの画像のときだけ押せます。参照ボタンでソースを変えたときも、ダイアログが開いている間は追いかけます。
  - ダイアログを閉じた後に、編集した画像の `srcset` を外します。上書きしたときは、本文の同じファイルの画像（書き方が違っても）の表示を読み直します。
  - ダブルクリックでダイアログを開くのは、サイトの外の画像や編集できない形式の画像でも同じです（そのときは「画像を編集」を押せません）。
- 画像を選んだときに出る帯の「画像を編集」ボタン（TinyMCE の公式の部品）を押すと、ダイアログを通らずにファイルピッカーの画像編集が開き、保存すると本文の画像を直接差し替えます。ダイアログへの差し込みが働かないときも、この入口は使えます。

- 画像の編集の対象は、`src` がファイルピッカーの種類のフォルダ（`rb_base_url` の下の `images`・`media`・`files`）にある jpg・jpeg・png の画像だけです。`data:`・`blob:`、別のサイトの画像、`rb_base_url` の外、ほかの形式の画像、`rb_base_url` が管理画面と別のオリジンのときの画像では、ダイアログの「画像を編集」と帯のボタンは押せません。
- 保存のしかたは「別のファイルとして保存」が既定です。「元のファイルを上書き」を選んだときは、同じファイルかを URL の origin とパス（`decodeURIComponent` した要素）で比べ、エディターの画像の表示用の `src` にだけ `_evofb=<時刻>` のクエリを付けて表示を新しくします。保存される値（`data-mce-src`。本文の `src` の元）にはクエリは付かず、元の `src` にあったクエリも保存後は付きません。
- 本文の `width` はそのまま、`height` は新しい画像の縦横比で計算し直します（`height` だけがあるときは `width` を計算し、どちらも無ければ付けません）。ただし、新しい画像の大きさを超えるときは、新しい画像の大きさまで下げます（小さくリサイズした画像を引き伸ばして粗く出さないため。例: `width="1200"` の画像を 600×400 にリサイズすると `width="600" height="400"`）。`srcset` は消します。
- 編集を取り消すと、ダイアログが閉じて本文は変わりません。
- `tinymce7_file_browser` を `none` にすると `js/mcpuk-picker.js` を読まないので、この機能は働きません。
- 大きさを読めなかったときは、`width`・`height` は元のままにします。
- ピッカーへの設定は `window.MODX_FILE_PICKER`（`FileBrowserResolver::mcpukBootstrapScript()` が出力）で渡します。キーは `browserUrl`（ピッカーの URL）、`rbBaseUrl`（`rb_base_url` の絶対 URL）、`baseUrl`（サイトの設置パス `MODX_BASE_URL`）、`siteUrl`（`MODX_SITE_URL`）、`stripImagePaths`（グローバル設定「相対パスを渡す」）、`types`（`BrowserConfig::TYPES`）、`editableExtensions`（`BrowserConfig::EDITABLE_EXTENSIONS`）、`imageEdit`（画像編集を付けるか。ピッカーを使えない利用者では `false`）、`upload`（貼り付けのアップロードを使うときだけ。`{url, csrfToken}`）です。
- ボタンの文言は言語ファイルの `tinymce7_image_edit` で、エディターのオプション `modx_image_edit_label` として渡します。

### 貼り付け・ドロップした画像のアップロード

画像の入口はファイルピッカーに一本化しています。エディターに貼り付けた・ドロップした画像は、TinyMCE の `images_upload_handler`（`js/mcpuk-picker.js` の `window.mceModxImageUpload`）でファイルピッカーの API（`manager/media/browser/evo/api.php` の `action=uploadPasted`）に送り、返った URL を本文の `src` に入れます。置き場所は `{rb_base_dir}/images/年/月/`、名前の無い画像の名前は `pasted-doc-{リソースID}-{連番}.拡張子`（チャンクの編集では `pasted-chunk-{チャンクID}-{連番}.拡張子`）です（`assets/docs/file-storage.md`）。画像のダイアログの「アップロード」タブは、どの設定でも出しません（`image_uploadtab: false`）。

使うかどうかは `EditorInitializer::canUploadPasted()`（1 か所）が決めます。次のすべてを満たすときだけ使えます。

- `tinymce7_file_browser` が `mcpuk`
- 管理画面のエディター（`forfrontend` でない）。フロントのエディターでは使いません。フロントのページはキャッシュに入り、セッションごとの CSRF トークンを埋め込めないためです
- ID のある画面（`EditorInitializer::managerScreen()`。リソースの編集 `a=27`・下書きの編集 `a=131`/`132` と、チャンクの編集 `a=78`。`id` が 1 以上）。新しいリソース（`a=4`・`72`）では、保存するまで ID が無いのでアップロードせず、`paste_data_images` は `true` のまま `data:` 画像をエディターに置きます（保存時の変換がファイルにします）。新しいチャンク（`a=77`）と、ほかの画面では貼り付けた画像を受けません（`paste_data_images: false`。チャンクは保存時の変換の対象外のため）
- このセッションがファイルピッカーの `images` を使える（`PickerScope::authorize()`。`api.php` が要求のたびに行う確かめと同じ。`use_browser` が有効なとき）
- ロールの権限 `file_upload` がある（`PickerScope::allows()`。`api.php` の `uploadPasted` が表の `perm` で確かめるのと同じ）。権限が無いとき（ピッカーは使えるのに `file_upload` だけ無いとき）は、どの画面でも、新しいリソースでも `paste_data_images: false` にし、画像を含む貼り付け・ドロップで警告の通知（`modx_upload_forbidden_message`、`js/editor-setup.js`）を出します。権限以外の理由で受けないときは通知しません
- CSRF トークンを出せる（`getCurrentCsrfToken()` がある）
- `upload_images` に、TinyMCE の画像の拡張子（`jpeg,jpg,jpe,jfi,jif,jfif,png,gif,bmp,webp`）と重なるものがある

使えるときは、`images_upload_handler` を付け、`images_file_types` を TinyMCE の既定と `upload_images` の重なりにし、`window.MODX_FILE_PICKER.upload`（`{url, csrfToken}`）を渡します。使えないときは `paste_data_images: false` にして、貼り付けた画像を受けません（本文に `data:` 画像が入らない）。フロントのエディターでは貼り付けた画像を受けません（`paste_data_images: false`）。画像編集（`mceModxImageEdit` の登録と帯のボタン）は、管理画面ではピッカーを使えないとき付けません（`MODX_FILE_PICKER.imageEdit`）。フロントでは `file_browser` が `mcpuk` なら付け、使えない人が開いたときはピッカーが断ります（参照ボタンと同じ）。

- 名前は、サーバーが決めます。JS は、名前を持つ File ならその名前、無ければ `image.拡張子` を送るだけです。送るときは、画面の種類 `kind`（`doc`・`chunk`）と ID `id` も付けます（`MODX_FILE_PICKER.upload` の `kind`・`id`）。サーバー（`FileStore::pastedPlaceholderExtension()`・`savePastedFile()`）が、名前が `image.拡張子` のものを `pasted-doc-12-1.png` の形に置き換え（連番は、そのフォルダーの同じ種類・ID の連番の最大 + 1。途中の番号を消しても使い回さないが、いちばん大きい番号を消すとその番号はまた使われる）、ほかの名前は元のまま保存します（同名があれば `名前(1).拡張子`）。拡張子は `blob.type` から決めます（`image/jpeg` は `jpg`）。
- 失敗（サーバーが返したエラー・JSON でない応答・HTTP のエラー）は `{message, remove: true}` で reject し、TinyMCE がその画像を本文から外して通知を出します。月のフォルダーを作れないときも HTTP エラー（`mkdir_failed` は 500）で返り、同じ扱いです。
- 開いたときから本文にある `data:` 画像は、アップロードの対象にしません（`editorUpload.addFilter()`）。TinyMCE は読み込み時に base64 の `data:` 画像を `blob:` の URL に置き換え、保存時に `data:` に戻すため、初期化までの `SetContent` で本文の `blob:`・`data:` の画像の `src` を覚えて外します。開くたびに、既存の画像がファイルに保存されることはありません（保存すると、コアの保存時の変換が行います。`assets/docs/datauri-conversion.md`）。
- 保存の前に、アップロード中の画像を待ちます。エディターの元の欄（textarea）のフォームに、`submit` の受け手を capture で付け、未アップロードの画像があれば `preventDefault()` して `editor.uploadImages()` を待ち、終わったら `form.requestSubmit(submitter)` で送り直します。アップロードが 1 つでも失敗したとき、`blob:` が残ったとき、送り直しに失敗したときは、送らずにエディターの通知（`tinymce7_upload_not_saved`）を出し、`documentDirty` を `true` に戻します（`manager/media/script/shell.js` の `submit` の受け手は `defaultPrevented` なら送らず、TinyMCE の受け手よりもこの受け手が先に呼ばれます。`document.mutate.save.click()` の合成クリックでも同じに動きます）。`form.submit()` を直接呼ぶ画面は `submit` イベントが出ないため、待ちません。
- 呼び出しは `js/editor-setup.js` の `window.mceModxPastedUpload(editor)` で、`MODX_FILE_PICKER.upload` があるときだけ働きます。

## 画像のキャプション (`image_caption`)
`config/manager.json`・`config/frontend.json` で `image_caption: true` を指定しており、どのツールバー構成でも画像のダイアログに「キャプションの表示」が出ます（構成の定義 `toolbar-presets.json` は同じキーを持たないので、設定ファイルの値がそのまま効きます）。オンにすると、画像が次の形で保存されます。

```html
<figure class="image">
  <img src="assets/images/sample.jpg" alt="...">
  <figcaption>キャプション</figcaption>
</figure>
```

- 公開側の CSS では、`figure.image` を画像とキャプションを包む箱として扱います。書き方の例です。

  ```css
  figure.image {
      display: table;      /* 画像の幅に箱を合わせる */
      margin: 1em auto;
  }
  figure.image img {
      display: block;
      max-width: 100%;
      height: auto;
  }
  figure.image > figcaption {
      display: table-caption;
      caption-side: bottom;
      font-size: 0.875em;
      text-align: center;
  }
  ```

  編集欄に同じ見た目を出すには、「CSSファイルへのパス」の CSS に同じ規則を書きます。
- キャプションをオンにすると、仮の文字「Caption」（英語）が `<figcaption>` に入ります。本文で書き換えてください。書き換えずに保存すると、公開側にもそのまま `Caption` と出ます。
- キャプションを外すと `figure` ごと `<img>` だけに戻ります。
- 画像にリンクを付けたときは `figure.image > a > img` の形です。リンクのダイアログの「クラス」「Rel」の補正（「リンクのダイアログの「クラス」と「Rel」」）は、ダイアログが編集するリンクではなく本文のすべてのリンクを見るので、この形でも働きます。
- ファイルピッカーの画像編集（「画像編集（ファイルピッカーの画像編集）」）は、`figure` の中の画像でも開きます。

## リンクの操作の帯 (`link_context_toolbar`)
`manager.json`・`frontend.json` で `link_context_toolbar: true` を指定しており（TinyMCE 7 の既定はオフ）、リンクの中にカーソルを置くと、リンクの上に小さなツールバー（link プラグインのコンテキストフォーム）が出ます。URL の入力欄と、「Link」（入力欄の URL を反映）・「Remove link」（リンクの削除）・「Open link」（リンクを開く）のボタンが並びます。

- URL を直して Enter か「Link」で反映できます。ダイアログは開きません。クラス・Rel・開き方を変えるときは、ツールバーのリンクのボタンか Ctrl+K でダイアログを開きます（`link_quicklink` は既定の `false` のままなので、ツールバーのボタンと Ctrl+K はダイアログを開きます）。
- リンクの文字を範囲選択したときも、quickbars の選択時ツールバーとは同時に出ず、リンクの操作の帯だけが出ます。
- 帯で URL だけ直したときの class・target・rel の残り方は、ダイアログで URL だけ直したときと同じです（確かめた結果は下）。`link_rel_list`（システム設定「リンクの rel」）が空でも、本文のリンクに rel があれば、その値から選択肢を作るので、帯でもダイアログでも rel は残ります（`noopener` は `target="_blank"` のときに TinyMCE が付けます）。class も残ります。「リンクの rel」が空で、本文に noopener 以外の rel が無い文書では、新しいウィンドウのリンクを直すと `noopener` が外れます（`target="_blank"` は残ります。今のブラウザーは `_blank` を `noopener` として扱います）。
- 「リンクを開く」は、href が `[~ID~]`（数字だけ）のリンクのとき、その文書の公開側の URL を開きます（`js/link-open.js`。管理画面だけ）。TinyMCE はそのままの `[~ID~]` をサイトの URL に付けて開き 404 になるため、link プラグインより先に、帯とメニューのボタン（`aria-label` が `Open link` の翻訳）の押下・Ctrl（Mac は Cmd）+ クリック・Alt+Enter を横取りします。URL は `link-list.php?id=` から取ります（`LinkListRepository::url()`。一覧に出る文書と同じ範囲で、無い・削除済み・権限なしは 404。ログインしていなければ 403）。押した時点で空の窓を開けておき（ポップアップを止められないため）、URL が分かったらその窓を移します。取れなかったときは窓を閉じ、エディターの通知（`tinymce7_link_open_missing`）で知らせます。ほかの href は TinyMCE に任せます。設定ファイルに `modx_link_open`（`{url, missing}`）を書いていれば、そちらを使います。

## リンクのダイアログの文書の一覧 (`link_list`)
管理画面のエディターには、リンクの挿入/編集のダイアログに「リンクの一覧」が出ます。サイト内の文書を木の形で選ぶと、URL の欄に `[~id~]` が入ります（TinyMCE 3 の `tinymce.linklist.php` にあたります）。設定やシステム設定の項目は無く、いつも有効です。公開側のエディター（`frontend.json`・`forfrontend`）には出しません。設定ファイルに `link_list` を書いていれば、そちらを使います。

- **入口**: `link-list.php` が JSON を返します。`EditorInitializer` が `link_list` に URL を渡すので、TinyMCE がダイアログを開くたびに、管理画面の利用者のセッションで取りに行きます。ファイルピッカー（`manager/media/browser/evo/api.php`）の一覧と同じく、読み取りだけで状態を変えないため、セッション（`mgrValidated`）の確認だけで CSRF トークンは要りません。ログインしていなければ 403 と `[]` を返します。
- **作り**: 文書の取り出しと木にする処理は `TinyMCE7\Editor\LinkListRepository`。入口は取り出しを呼んで JSON にするだけです。
- **値と表示**: 値は文書を移してもリンクが切れない `[~id~]` で、`convert_urls: false`・`relative_urls: false` のため TinyMCE は書き換えず、本文には `href="[~id~]"` のまま保存され、公開側で URL になります。表示は「メニュータイトル（無ければページタイトル）(ID)」で、未公開の文書は末尾に「（未公開）」を添えます（言語ファイルの `tinymce7_link_list_unpublished`）。削除済みの文書は出しません。メニューに表示しない文書も出します。
- **並び**: メニューオーダー順。子を持つ文書は入れ子のメニューになり、メニューの見出し自身は選べないので、先頭に同じ名前の項目を置いて、その文書を選べるようにします。親が見えない文書（削除済み・権限なし）は最上位に置きます。
- **権限**: `use_udperms` が有効で、利用者が管理者（`access_permissions` と `web_access_permissions` を両方持つ）でないときは、`privatemgr=0`、または利用者の文書グループ（セッションの `mgrDocgroups`）に入る文書だけを返します。式の形はツリー（`manager/frames/nodes.php` の `tree_visibility_condition()`）と同じです。ただし `use_udperms` が無効なら絞りません（ツリーは `use_udperms` を見ずに `privatemgr` で絞ります。この一覧は保存の確かめ `ManagerAPI::canOpenWithDocumentGroups()` と同じ扱い）。`tree_show_protected` は見ません。一覧に出すのはリンクを張れる文書だけです。
- **件数の上限**: 出す文書は 1000 件（`LinkListRepository::MAX_ITEMS`）までです。ダイアログを開くたびに、削除済みでない全件（軽い列だけ）を読んでから木にして打ち切ります。超えるときは、1000 件に収まる深さまでで打ち切ります（最上位だけで超えるときは、先頭の 1000 件）。深さで打ち切ったときは、末尾に選べない説明の項目（`tinymce7_link_list_truncated`。値は空で、選ぶと URL が空になるだけ）を足します。深いところの文書は一覧に出ないので、URL の欄に `[~id~]` を直接書いてください。遅延読み込みや検索はしません。

## 本文のタグの補完と色付け
管理画面のエディターでは、本文に書くタグ（チャンク `{{name}}`・スニペット `[[name]]`・リソース変数とテンプレート変数 `[*name*]`・設定 `[(name)]`）を書くのを助けます。設定の項目は無く、いつも有効です。公開側のエディター（`frontend.json`・`forfrontend`）には出しません。

- **補完**: 書き始め（`{{`・`[[`・`[*`・`[(`）を打つと名前の候補を出し、選ぶと閉じ記号まで入れます（`{{bre` → `{{bread_crumb}}`）。文の途中でも開きます。名前に使わない文字（空白・括弧・閉じ記号など）を打つと閉じます。既にあるタグの名前の途中で選んだときは、入力位置のあとの名前の残りまでを置き換えます（あとが閉じ記号なら、空白を含む名前の残りも閉じ記号ごと置き換え、`?`・`:`・`&` なら引数とフィルターを残します）。候補には、チャンク・スニペットの説明、テンプレート変数のキャプションを添え、無効のチャンクには「（無効）」（`tinymce7_tag_disabled`）を添えます。
  - TinyMCE は、登録した順に書き始めを左へ探し（空白まで）、最初に見つかった書き始めで補完を決め、ほかの書き始めは試しません。そのため `{{a}}文[(` では `{{` に決まります。`tag-assist.js` は、どの書き始めで決まっても、入力位置にいちばん近い書き始めで読み直し、選んだときはその書き始めからを置き換えます。
- **色付け**: 本文の中のタグに、種類ごとの地の色を付けます。名前の一覧に無い名前は赤の波線、無効のチャンクは灰色の取り消し線にします。名前は本体と同じ区切りで取り出します（`_getSplitPosition` を写した `splitPosition`。名前は空白を含んでよく、`?`・改行・`&` で引数、`:` でフィルター。`[*#name*]` の `#` は外す）。照らさないもの: `{{@FILE:…}}` などの `@`、プラグインが足す `{{#name}}`・`[[#name]]`、`[[$_GET(x)]]` のようなスーパーグローバル、`[*a|b*]`・`[*name@context*]`、本体が置く `[*phx*]`・`[*dummy*]`。`{{` のあと・`}}` の前が半角スペースのチャンクと、先頭が空白のスニペットは本体がタグとして扱わない（実行しない）ので、色を付けません。
  - CSS Custom Highlight API で範囲に色を付けるだけなので、本文の HTML は変わりません（保存する内容に要素が増えません）。使えないブラウザーでは色を付けません。
  - 表示のときにプラグインが足す設定・`addChunk` などで `#` を付けずに足したものは、名前の一覧に無いため「無い名前」に見えます。テンプレートに割り当てていないテンプレート変数は、名前があれば「ある」とします。
- **名前の一覧**: `tag-names.php` が `{names: {chunk, snippet, field, setting}, labels}` の JSON を返します。各項目は `{name, note, disabled}` で、取り出しは `TinyMCE7\Editor\TagNameRepository`。`field` は `site_content` の列とテンプレート変数、`setting` は読み込んだ設定の名前だけで、値は返しません。説明の HTML は除きます。エディターの初期化のときに取ります。同じ画面のエディター（リッチテキストのテンプレート変数など）はまとめて初期化されるので、作ってから 5 秒の間は 1 回の取得を分け合います。管理画面は画面を移ってもスクリプトを読み直さないため、それより後に開いたエディターは取り直します（作ったばかりの要素を無い名前にしないため）。取れなかったとき（ログインが切れた・エラー）は照らさず（種類の色だけ）、5 秒おいてから補完を開いたときに取り直し、取れたら同じ一覧を使うエディターの色付けを直します。`link-list.php` と同じく、管理画面のセッション（`mgrValidated`）の確認だけで、ログインしていなければ 403 と `{}` を返します。設定ファイルに `modx_tag_names_url` を書いていれば、そちらを使います。

## システム設定キー
プラグインが参照する主なシステム設定キーです。旧 TinyMCE プラグインのキーも互換処理しています。

| キー | 内容 |
| --- | --- |
| `tinymce7_toolbar_preset` | プリセットを `toolbar-presets.json` のキー（`simple` / `formats` / `legacy` / `full`）から選択します。旧プラグインの `classic`・`advanced` と、旧構成の `basic` は、`legacy`・`full`・`formats` と読み替えます。|
| `tinymce7_toolbar` | ツールバーの表示制御（`1` = 表示、`0` = 非表示）。未設定なら表示します。非表示のときは TinyMCE の `toolbar` に `false` を渡し、ツールバー構成の並びより優先します。|
| `tinymce7_menubar` | メニューバーの表示制御（`1` = 表示、`0` = 非表示）。未設定なら表示しません。表示のときはツールバー構成の `menubar` の並びを使い、無ければ TinyMCE 既定のメニューを出します。|
| `tinymce7_entermode` | Enter キーで挿入する要素を `p` または `br` から選択します。|
| `tinymce7_body_id` | 編集欄の body に付ける id。TinyMCE の `body_id` に渡し、設定ファイル（`config/*.json`）の `body_id` より優先します。サイト共通の設定で、入力欄はシステム設定画面（a=17）にだけ出します。旧 TinyMCE の `customparams` の `body_id` は、マイグレーション（`manager/includes/migrations/20261107_120000_carry_over_tinymce_body_attributes.php`）が引き継ぎます（どちらかの設定が保存済みなら何もしません）。空の値、サイトのタグ（`[*…*]`・`[(…)]`・`[~…~]` など）や引用符・`\`・`:`・改行を含む値・文字列でない値と、ほかのキーは引き継がず、キー名だけをシステムログに種別 2（警告）で残します。|
| `tinymce7_body_class` | 編集欄の body に付けるクラス（半角スペース区切り）。TinyMCE の `body_class` に渡します。扱いは `tinymce7_body_id` と同じです。|
| `tinymce7_css_selectors` | 「表示名=クラス名;表示名2=クラス名2」の形式で、ツールバーの「書式」に出す CSS クラスを指定します。要素名の無い項目は `selector: '*'` と `inline: 'span'` を併せ持つ `style_formats` に変換され（要素全体を選ぶと要素に class、一部を選ぶと span で包む。段落の中身がリンクだけのときなど、選択が段落の端から端までなら TinyMCE が範囲を段落まで広げるため、`<p>` に class が付く）、「表示名=a.クラス名」のように値の前に要素名を付けた項目は `selector: 'a'`（`img`・`table` も同様）だけの書式（`inline` なし）に変換されます。この書式は TinyMCE が選択の中にその要素が無いと無効にするので、段落などには付きません。要素名として読むのは `PreferenceResolver::CLASS_TARGETS` にある名前（今は `a`・`img`・`table`）で、表にその要素のダイアログの選択肢の設定キー（`a` は `link_class_list`、`img` は `image_class_list`、`table` は `table_class_list`）と、要素名の無い項目も同じ選択肢に入れるか（`withUnprefixed`、`a` は入れ、`img`・`table` は入れない。画像の回り込みのクラスなどは `img.` で書く前提）を持ちます。要素名を分けた後のクラス名は、`.` と空白を 1 つの空白にして複数のクラスにします（`a.btn.primary` は `btn primary`）。表に無い名前は今までどおり値の一部として扱います。要素名つきの項目は、書式と、その要素のダイアログの選択肢にだけ使います。TinyMCE の既定の書式（見出し・インライン・ブロック・配置）の後ろに並びます。指定があるときは importcss プラグインを外し、「書式」にはこのクラスだけを並べます（TinyMCE 3 と同じ。「CSSファイルへのパス」の CSS のクラスをすべて取り込むと一覧が長くなり、同じクラスが重なるため）。設定ファイルで importcss を入れていても外します。指定が空なら、importcss が「CSSファイルへのパス」の CSS からクラスを取り込み、`importcss_append` を有効にして既定の書式の後ろに並べます（取り込めるクラスが無くても、既定の書式が出て押せるように）。ツールバーに `styles` が無ければ、ボタンが少ない行の末尾に足します（選択欄は幅に応じて普通のボタン 2〜3 個ぶんで数え、同じ数なら後ろの行。グループの配列なら末尾にグループを足す）。TinyMCE 3 風（`legacy`）の構成は「書式」を段落の種類の欄として 1 行目に持つので、足しません。同じ一覧から、リンクのダイアログの「クラス」の選択肢（`link_class_list`、先頭は「なし」）も作ります。入れるのは要素名の無い項目と `a.` の項目で、名前は、表示名とクラス名が違えば「表示名 (クラス名)」です（`a.` は選択肢の値に含めない）。`img.` の項目は `image_class_list`、`table.` の項目は `table_class_list` に同じ形で入れます。設定ファイルで `link_class_list`・`image_class_list`・`table_class_list` を指定していれば、そちらを使います。サイト共通の設定で、入力欄はシステム設定画面（a=17）にだけ出します。一度保存すると空でも旧キーには戻りません。|
| `tinymce7_link_rel` | 「表示名=値;表示名2=値2」の形式で、リンクのダイアログの「Rel」に出す値を指定します（旧 TinyMCE の「ページからターゲットの関係」。例: `画像を拡大=lightbox`）。空なら欄を出しません。サイト共通の設定で、入力欄はシステム設定画面（a=17）にだけ出します。|
| `tinymce7_sandbox_iframes` | 本文の iframe に `sandbox` を付けるか（`1` = 付ける、`0` = 付けない）。未設定なら付けます（TinyMCE 7 の既定）。付けるときは、手で書いた `sandbox` の値も空で上書きされます。付けないときだけ TinyMCE の `sandbox_iframes` に `false` を渡しますが、`false` のときは既存の属性に触れないため、すでに保存された `sandbox` は残ります（ソースコードの編集で消します）。設定ファイル（`config/*.json`）で `sandbox_iframes` を指定していればそちらを優先します。管理画面とフロントのエディターの両方に効きます。`sandbox_iframes_exclusions`（youtube.com・vimeo.com などには付けない）の既定は変えません。サイト共通の設定で、入力欄はシステム設定画面（a=17）にだけ出します。|
| `tinymce7_keep_scripts` | 本文の `<script>`・`<style>` を残すか（`1` = 残す、`0` = 消す）。未設定なら消します。管理画面のエディター（Quick Manager+ の編集を含む）だけに効き、フロント（`forfrontend`）では設定にかかわらず消します。「残す」のときだけ、`js/editor-setup.js` の `window.tinymce7KeepScriptsProtect`（正規表現）を TinyMCE の `protect` に足します（正規表現は JSON で渡せないため、`EditorInitializer` の初期化 JS で `config.protect` に連結します。設定ファイルが `protect` を持っていれば消さずに足します）。`protect` した部分は `escape()` で退避されるので、MODX タグ・日本語・絵文字も 1 文字も変わらず、無害化（`xss_sanitization`）は切りません（`valid_elements: '*[*]'` や `extended_valid_elements` は使いません）。`<!-- … -->` の中に script・style があるときは、コメントごと保護します。`<script-x>` のような別の要素名は対象にしません。貼り付け・ドロップした HTML（エディターの中での切り取り・貼り付けを含む）は、保護した部分のコメント（`<!--mce:protected …-->`）が取り出しで生の script に戻らないよう、「残す」のときだけ `PastePostProcess` で消します（`e.internal` では分けません）。ひな形の挿入とソースコードの編集は、利用者が意図して書く経路なので対象外です。制限: 属性の値や `<textarea>` の中に生の `<script>`・`<style>` を書いた形は保護しません（属性は属性ごと消え、textarea は中身が壊れます。`&lt;` で書きます）。後ろに `</script>` が無い script は保護されず消えます。Firefox では、本文が script だけのとき先頭に空の段落が入ります（「消す」でも同じです）。サイト共通の設定で、入力欄はシステム設定画面（a=17）にだけ出します。|
| `tinymce7_template_docs` | ひな形にするリソースの ID（カンマ区切り）。未保存なら旧 TinyMCE 3 の `mce_template_docs` を読みます。|
| `tinymce7_template_chunks` | ひな形にするチャンクの名前（カンマ区切り）。未保存なら旧 TinyMCE 3 の `mce_template_chunks` を読みます。|
| `tinymce7_template_category` | このカテゴリに属するチャンクをすべてひな形にします（カテゴリ名で指定）。ひな形の 3 項目はサイト共通の設定で、入力欄はシステム設定画面（a=17）にだけ出します。|
| 互換キー (`tinymce_toolbar_preset`, `tinymce_menubar`, `tinymce4_entermode`, `tinymce_css_selectors` など) | 旧プラグインとの互換のために自動的に読み替えます。|

## イベントパラメーター
`OnRichTextEditorInit` に渡されたパラメーターは TinyMCE の設定にマージされます。

| パラメーター | 役割 |
| --- | --- |
| `elements` | 対象要素の ID。配列またはカンマ区切りの文字列が利用できます。|
| `height` / `width` | エディター領域のサイズ。TinyMCE 設定の `height` / `width` を上書きします。|
| `forfrontend` | 真の場合は `frontend.json` を読み込んで初期化します。|
| `tinymce7_file_browser` / `file_browser` | `mcpuk`（既定）または `none` を指定できます。|

## MCPuk ファイルブラウザー連携
`tinymce7_file_browser` が `mcpuk` の場合、`js/mcpuk-picker.js` が `file_picker_callback` を上書きし、選択したファイルの URL を CMS のルートに合わせて正規化します。グローバル設定「相対パスを渡す」（`strip_image_paths`）が「はい」なら相対パス（例: `assets/images/a.jpg`）のまま、「いいえ」ならサイトの URL を付けた絶対 URL で渡します（ホスト付きの値は、どちらでもそのまま渡します）。無効化すると TinyMCE の標準ダイアログが利用されます。

## ひな形の挿入
TinyMCE 7 ではオープンソース版の `template` プラグインが廃止され、有償の Templates（advtemplate）に置き換わりました。本プラグインは MODX のリソースとチャンクをひな形として挿入する独自プラグイン `modxtemplate`（`js/modx-template.js`）を同梱しています。

- 管理画面のエディターで、システム設定 `tinymce7_template_*` から 1 件以上のひな形が見つかったときだけ、`external_plugins` に登録して読み込みます。フロントエンド用（`forfrontend`）では読み込みません。
- 一覧と本文は `TinyMCE7\Editor\TemplateRepository` が集め、エディター設定の `modx_templates` として渡します（別のエンドポイントは使いません）。リソースは公開中かつ管理画面で閲覧できるもの、チャンクは公開中のものに限ります。
- ツールバーのボタン名は旧プラグインと同じ `template`、メニュー項目名は TinyMCE 7 の「挿入」メニュー既定値に含まれる `inserttemplate` です。ひな形が無いときは、ツールバー設定から `template` を取り除きます。
- 本文は MODX タグを含めて保存されているまま挿入します。ダイアログのプレビューは、エディターのスキーマで無害化した HTML を、`sandbox="allow-same-origin"` 付きの iframe（`srcdoc`）に表示します（スクリプトは動きません）。iframe には、`contentCSS`（設定の `content_css`（［CSS ファイルへのパス］を含む）とスキンの `content.min.css`）、`content_style`、`<base href>` を入れ、body の id・class・dir は編集欄の body から写すので、編集欄に近い見た目になります。

## 保存すると失われるものの通知
管理画面のエディター（リソースの本文・リッチテキストの TV・チャンク・Quick Manager+）だけが対象で、フロントのエディターには出しません（`EditorInitializer::lossNoticeLabels()` が `modx_loss_notice` を渡したときだけ有効）。
- 開いた時点で 1 回だけ、元の欄（textarea）の値と、初期化後の `getContent()`（保存するときの値）を、実行も読み込みもされない文書（`DOMParser`）に読み込んで比べます（`js/editor-setup.js` の `bindLossNotice()`）。元の欄の値は、TinyMCE が取り込む前の `setup` の時点で控えます。差し戻し・エディターやテンプレートの切り替えは、送信のときに欄の値が TinyMCE の値に置き換わるので、そのあとの画面では差が無く、出ません。
- 数えるのは、要素（script・style・iframe・object・embed・svg・form・noscript・link・meta・template・math）、`on` で始まる属性、`javascript:` で始まる属性の値、iframe の `sandbox` の値です。独自要素、スキーマに無い属性、`data:` の値などは数えません。減った要素と属性は「失われるもの」、保存後に元の iframe に無い `sandbox` の値が現れたときは「sandbox が付く」として、警告の帯にまとめます。差が無ければ出しません。設定ごとの条件は持たないので、`protect`（`tinymce7_keep_scripts`）や `sandbox_iframes`（`tinymce7_sandbox_iframes`）で残している分は差になりません。
- 警告は、エディターのすぐ前に置くページの流れの中の帯（`.tinymce7-loss-notice`、`css/loss-notice.css`）です。TinyMCE の通知は位置を計算して重ねるため、本文の枠のスクロールやタブの切り替えに追従しないので使いません。帯は閉じるボタンで消え、エディターの破棄でも消えます。操作は止めず、未保存の印も立てません。文は `textContent` で入れ、本文の断片は混ぜず、件数と言語ファイル（`tinymce7_loss_*`）の言葉だけを出します。
- 比べるのは `DOMParser` で 2 回の解析と要素の走査で、2MB ほどの本文でも 0.4 秒ほどです（TinyMCE の初期化は数秒）。

## リンクのダイアログの「クラス」と「Rel」
- 選択肢は `link_class_list`・`link_rel_list` です。設定ファイルで指定していればそれを、無ければ `tinymce7_css_selectors`・`tinymce7_link_rel` から作った一覧を使います。どちらも先頭は「なし」で、表示名と値が違えば「表示名 (値)」と出します。一覧が無ければ欄を出しません（ただし本文のリンクに rel があれば、下の補正で「なし」とその値の一覧ができ、欄が出ます）。
- TinyMCE のリンク・画像のダイアログは、一覧に無い値を初期値にできず、そのまま保存すると class・rel を消します（表のプロパティは class を保つので対象外）。ダイアログは開くときに一覧を読み、画像のダイアログはツールバー・メニュー・右クリックから `execCommand` を通さずに開かれます。そこで `js/editor-setup.js` の setup で、本文が変わるたび（`init SetContent change undo redo`。リンクは `mceLink` の `BeforeExecCommand` でも）に、本文の該当する要素の値のうち一覧に無いものを元の一覧の末尾に足して作り直します。リンクの class・rel と画像の class は同じ処理を定義の表（`choiceLists`）で回し、表に値を集める要素（`selector`）、元の一覧が空でも本文に値があれば「None + その値」の一覧を作るか（`seedNone`。リンクの rel だけ。本文に値が無ければ空のままで欄を出さない）、選択肢の設定キー（`option`）、属性（`attribute`）、種類ごとの値のそろえ方（`normalize`）を持たせます。TinyMCE は rel の選択肢を並べ替え、新しいウィンドウのリンクには `noopener` を足してから、リンクの値と完全一致で比べます。そこで、`PreInit` で `editor.parser.addAttributeFilter("rel", ...)` を登録し、本文を読み込む・貼り付ける・挿入する（ソースコードの画面・ひな形の挿入を含む）ときに、トークンの順が違う rel（`nofollow lightbox` など）を並べ替えます（「リンクの rel」が空のサイトも対象。変わるのは、複数の値を持ち順が昇順でない rel だけで、意味は同じ）。最初の履歴より前にそろうので、未保存の印は立ちません。選択肢どうしは `noopener` を除いて比べます。
- ダイアログが編集するリンクは選び方（キャレット・範囲選択・キャプション付き画像）で決まり方が変わるため、選んでいるリンクではなく本文のすべてのリンクを見ます。そのため、ほかのリンクの値も選択肢に出ます。
- 「新しいウィンドウ」で開くリンクには、TinyMCE が `rel` に `noopener` を足します（例: `rel="lightbox noopener"`）。ダイアログでは選んだ値が選ばれたまま出ます。

## コード構成
- `TinyMCE7\Plugin` クラスがエントリーポイントで、イベントごとの処理を担当します。
- `TinyMCE7\Services\Config` が JSON 設定ファイルの読み込みとマージを行います。
- `TinyMCE7\Services\ToolbarPreset` がプリセットの解決を担当します。
- グローバル関数 `evo()` は `src/bootstrap.php` で定義され、Evolution CMS のサービスロケーターを取得します。

## 開発時のヒント
- TinyMCE のバージョンを更新する場合は `tinymce/` ディレクトリを新しいビルドで置き換えてください。
- 設定のデバッグにはブラウザーのコンソールで `tinyMCE.activeEditor.settings` を確認すると便利です。
- 既存テーマやプラグインとの互換性確認のため、キャッシュをクリアした状態でリッチテキストエディターを複数リソースで試験することを推奨します。

## ライセンス
このプロジェクトは MIT License の下で公開されています。
