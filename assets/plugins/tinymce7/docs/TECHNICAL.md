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
- MCPuk 連携スクリプト: `js/mcpuk-picker.js`
- 画像編集モーダル連携スクリプト: `js/tinymce-cropper.js`
- ひな形挿入プラグイン: `js/modx-template.js`
- エディター共通の setup（未保存の印・input の通知・リンクのダイアログの選択肢）: `js/editor-setup.js`（`window.tinymce7EditorSetup(editor)`）。`EditorInitializer` は config の JSON を出し、設定ファイルやサイトの `setup` を先に呼んだあとでこれを呼ぶ
- TinyMCE ローカル配置先 (任意): `tinymce/js/tinymce/` に公式 TinyMCE7 パッケージを展開
- オートローダー: `src/bootstrap.php`

Evolution CMS では `assets/plugins/tinymce7/` 配下に設置し、Composer などの追加ライブラリは不要です。

## コアとの連携イベント
プラグインは以下のイベントでコアと連携します。

| イベント名 | 役割 |
| --- | --- |
| `OnRichTextEditorRegister` | プラグインを利用可能なエディターとして登録します。|
| `OnRichTextEditorInit` | TinyMCE の初期化スクリプトを出力します。|
| `OnInterfaceSettingsRender` | システム設定画面とユーザー設定画面に TinyMCE7 用の設定入力を追加します。ユーザー設定画面では、編集中のユーザーの値を表示し、ツールバー構成・メニューバー・Enter キーの動作だけを出します（空欄は「グローバル設定を使用」）。編集欄の body の id・クラス、CSSスタイルセレクタ、ひな形はシステム設定画面（a=17）にだけ出します。|

## 設定ファイル
`config/` 配下の JSON ファイルは TinyMCE の設定オブジェクトに対応しています。キーや値は TinyMCE 公式ドキュメントの項目と同じ形式です。

| ファイル | 説明 |
| --- | --- |
| `manager.json` | 管理画面での TinyMCE 設定。高さ、プラグイン、ツールバーなどを定義。|
| `frontend.json` | `OnRichTextEditorInit` で `forfrontend` パラメーターが真の場合に読み込まれます。|
| `toolbar-presets.json` | 管理画面のプリセット選択肢（simple/basic/formats/legacy/full など）を定義します。キーの並びが設定画面の選択肢の並びになり、表示名は言語ファイルの `tinymce7_toolbar_<キー>` です。|

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

### 画像編集 (`image_cropper` 設定)

`config/manager.json` には `image_cropper` オブジェクトを追加しており、Cropper.js を利用したトリミング／回転／リサイズ機能を制御します。

```json
"image_cropper": {
  "enabled": true,
  "jsUrl": "https://cdnjs.cloudflare.com/ajax/libs/cropperjs/1.5.13/cropper.min.js",
  "cssUrl": "https://cdnjs.cloudflare.com/ajax/libs/cropperjs/1.5.13/cropper.min.css",
  "enableDoubleClick": true,
  "outputMimeType": "image/png",
  "outputQuality": 0.92,
  "labels": {
    "editTooltip": "画像を編集"
  },
  "cropperOptions": {
    "viewMode": 1,
    "autoCropArea": 1
  }
}
```

主なオプションは次の通りです。

- `enabled`: `true` にすると画像選択時に「画像を編集」ボタンが表示されます。
- `jsUrl` / `cssUrl`: Cropper.js を読み込む CDN やローカルファイルの URL。空にすると既定値が利用されます。
- `enableDoubleClick`: `true` の場合は画像ダブルクリックでもモーダルが開きます。
- `outputMimeType` / `outputQuality`: `canvas.toDataURL()` の出力形式と品質。JPEG 出力も指定可能です。
- `labels`: モーダル内の文言を上書きできます。
- `cropperOptions`: Cropper.js の初期化オプションをそのまま渡します。

フロントエンド用設定でも `image_cropper` を定義すれば同様の機能を利用できます。不要な場合は `enabled` を `false` に設定するか、オブジェクトごと削除してください。

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
- 画像のダブルクリックで開く画像編集（`image_cropper`）は、`figure` の中の画像でも開きます。

## リンクの操作の帯 (`link_context_toolbar`)
`manager.json`・`frontend.json` で `link_context_toolbar: true` を指定しており（TinyMCE 7 の既定はオフ）、リンクの中にカーソルを置くと、リンクの上に小さなツールバー（link プラグインのコンテキストフォーム）が出ます。URL の入力欄と、「Link」（入力欄の URL を反映）・「Remove link」（リンクの削除）・「Open link」（リンクを開く）のボタンが並びます。

- URL を直して Enter か「Link」で反映できます。ダイアログは開きません。クラス・Rel・開き方を変えるときは、ツールバーのリンクのボタンか Ctrl+K でダイアログを開きます（`link_quicklink` は既定の `false` のままなので、ツールバーのボタンと Ctrl+K はダイアログを開きます）。
- リンクの文字を範囲選択したときも、quickbars の選択時ツールバーとは同時に出ず、リンクの操作の帯だけが出ます。
- 帯で URL だけ直したときの class・target・rel の残り方は、ダイアログで URL だけ直したときと同じです（確かめた結果は下）。`link_rel_list`（システム設定「リンクの rel」）が空のときは、帯でもダイアログでも rel が外れます（`rel="lightbox"` は消え、`target="_blank"` の `noopener` も消える）。一覧があれば rel は残ります（`noopener` は `target="_blank"` のときに TinyMCE が付けます）。class は、どちらも残ります。

## システム設定キー
プラグインが参照する主なシステム設定キーです。旧 TinyMCE プラグインのキーも互換処理しています。

| キー | 内容 |
| --- | --- |
| `tinymce7_toolbar_preset` | プリセットを `toolbar-presets.json` のキー（`simple` / `basic` / `formats` / `legacy` / `full`）から選択します。旧プラグインの `classic`・`advanced` は `legacy`・`full` と読み替えます。|
| `tinymce7_toolbar` | ツールバーの表示制御（`1` = 表示、`0` = 非表示）。未設定なら表示します。非表示のときは TinyMCE の `toolbar` に `false` を渡し、ツールバー構成の並びより優先します。|
| `tinymce7_menubar` | メニューバーの表示制御（`1` = 表示、`0` = 非表示）。未設定なら表示しません。表示のときはツールバー構成の `menubar` の並びを使い、無ければ TinyMCE 既定のメニューを出します。|
| `tinymce7_entermode` | Enter キーで挿入する要素を `p` または `br` から選択します。|
| `tinymce7_body_id` | 編集欄の body に付ける id。TinyMCE の `body_id` に渡し、設定ファイル（`config/*.json`）の `body_id` より優先します。サイト共通の設定で、入力欄はシステム設定画面（a=17）にだけ出します。旧 TinyMCE の `customparams` の `body_id` は、マイグレーション（`manager/includes/migrations/20261107_120000_carry_over_tinymce_body_attributes.php`）が引き継ぎます（どちらかの設定が保存済みなら何もしません）。空の値、サイトのタグ（`[*…*]`・`[(…)]`・`[~…~]` など）や引用符・`\`・`:`・改行を含む値・文字列でない値と、ほかのキーは引き継がず、キー名だけをシステムログに種別 2（警告）で残します。|
| `tinymce7_body_class` | 編集欄の body に付けるクラス（半角スペース区切り）。TinyMCE の `body_class` に渡します。扱いは `tinymce7_body_id` と同じです。|
| `tinymce7_css_selectors` | 「表示名=クラス名;表示名2=クラス名2」の形式で、ツールバーの「書式」に出す CSS クラスを指定します。要素名の無い項目は `selector: '*'` と `inline: 'span'` を併せ持つ `style_formats` に変換され（要素全体を選ぶと要素に class、一部を選ぶと span で包む。段落の中身がリンクだけのときなど、選択が段落の端から端までなら TinyMCE が範囲を段落まで広げるため、`<p>` に class が付く）、「表示名=a.クラス名」のように値の前に要素名を付けた項目は `selector: 'a'` だけの書式（`inline` なし）に変換されます。この書式は TinyMCE が選択の中に `<a>` が無いと無効にするので、段落などには付きません。要素名として読むのは `PreferenceResolver::CLASS_TARGETS` にある名前（今は `a` だけ）で、表にその要素のダイアログの選択肢の設定キー（`a` は `link_class_list`）と、要素名の無い項目も同じ選択肢に入れるか（`withUnprefixed`、`a` は入れる）を持ちます。要素名を分けた後のクラス名は、`.` と空白を 1 つの空白にして複数のクラスにします（`a.btn.primary` は `btn primary`）。表に無い名前は今までどおり値の一部として扱います。要素名つきの項目は、書式と、その要素のダイアログの選択肢にだけ使います。TinyMCE の既定の書式（見出し・インライン・ブロック・配置）の後ろに並びます。指定があるときは importcss プラグインを外し、「書式」にはこのクラスだけを並べます（TinyMCE 3 と同じ。「CSSファイルへのパス」の CSS のクラスをすべて取り込むと一覧が長くなり、同じクラスが重なるため）。設定ファイルで importcss を入れていても外します。指定が空なら、importcss が「CSSファイルへのパス」の CSS からクラスを取り込み、`importcss_append` を有効にして既定の書式の後ろに並べます（取り込めるクラスが無くても、既定の書式が出て押せるように）。ツールバーに `styles` が無ければ、ボタンが少ない行の末尾に足します（選択欄は幅に応じて普通のボタン 2〜3 個ぶんで数え、同じ数なら後ろの行。グループの配列なら末尾にグループを足す）。レガシーの構成は「書式」を段落の種類の欄として 1 行目に持つので、足しません。同じ一覧から、リンクのダイアログの「クラス」の選択肢（`link_class_list`、先頭は「なし」）も作ります。入れるのは要素名の無い項目と `a.` の項目で、名前は、表示名とクラス名が違えば「表示名 (クラス名)」です（`a.` は選択肢の値に含めない）。設定ファイルで `link_class_list` を指定していれば、そちらを使います。サイト共通の設定で、入力欄はシステム設定画面（a=17）にだけ出します。一度保存すると空でも旧キーには戻りません。|
| `tinymce7_link_rel` | 「表示名=値;表示名2=値2」の形式で、リンクのダイアログの「Rel」に出す値を指定します（旧 TinyMCE の「ページからターゲットの関係」。例: `画像を拡大=lightbox`）。空なら欄を出しません。サイト共通の設定で、入力欄はシステム設定画面（a=17）にだけ出します。|
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

## リンクのダイアログの「クラス」と「Rel」
- 選択肢は `link_class_list`・`link_rel_list` です。設定ファイルで指定していればそれを、無ければ `tinymce7_css_selectors`・`tinymce7_link_rel` から作った一覧を使います。どちらも先頭は「なし」で、表示名と値が違えば「表示名 (値)」と出します。一覧が無ければ欄を出しません。
- TinyMCE のリンクのダイアログは、一覧に無い値を初期値にできず、そのまま保存すると class・rel を消します。そこで `js/editor-setup.js` の setup で、ダイアログを開く前（`mceLink` の `BeforeExecCommand`）に、本文のリンクの値のうち一覧に無いものを末尾に足します。class と rel は同じ処理を定義の表（`linkChoiceLists`）で回し、表に種類ごとの値のそろえ方（`normalize`）を持たせます。TinyMCE は rel の選択肢を並べ替え、新しいウィンドウのリンクには `noopener` を足してから、リンクの値と完全一致で比べます。そこで、`link_rel_list` があるときだけ、`PreInit` で `editor.parser.addAttributeFilter("rel", ...)` を登録し、本文を読み込む・貼り付ける・挿入する（ソースコードの画面・ひな形の挿入を含む）ときに、トークンの順が違う rel（`nofollow lightbox` など）を並べ替えます。最初の履歴より前にそろうので、未保存の印は立ちません。一覧が無いサイトの本文は書き換えません。選択肢どうしは `noopener` を除いて比べます。
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
