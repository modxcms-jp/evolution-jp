# TinyMCE 7 プラグイン 技術メモ

このドキュメントはプラグインのメンテナンスやカスタマイズを担当する技術者向けの情報です。README では触れていない設定方法や内部構造について記載します。

## バージョンポリシー
- 現在の安定版は `1.0.0` で、以降のマイナーバージョンでは後方互換性を保ちつつ改善や機能追加を行う想定です。
- 破壊的変更を伴う場合はメジャーバージョンを更新し、CHANGELOG で告知します。
- アップグレード時は設定キーや JSON 構成ファイルの差分を確認してください。

## ファイル構成と配置
- プラグインのエントリーポイント: `plugin.tinymce7.php`
- 設定ファイル: `config/manager.json`, `config/frontend.json`, `config/toolbar-presets.json`
- 管理画面設定用スタイル: `css/tinymce7.settings.css`
- MCPuk 連携スクリプト: `js/mcpuk-picker.js`
- 画像編集モーダル連携スクリプト: `js/tinymce-cropper.js`
- TinyMCE ローカル配置先 (任意): `tinymce/js/tinymce/` に公式 TinyMCE7 パッケージを展開
- オートローダー: `src/bootstrap.php`

Evolution CMS では `assets/plugins/tinymce7/` 配下に設置し、Composer などの追加ライブラリは不要です。

## コアとの連携イベント
プラグインは以下のイベントでコアと連携します。

| イベント名 | 役割 |
| --- | --- |
| `OnRichTextEditorRegister` | プラグインを利用可能なエディターとして登録します。|
| `OnRichTextEditorInit` | TinyMCE の初期化スクリプトを出力します。|
| `OnInterfaceSettingsRender` | システム設定画面に TinyMCE7 用の設定入力を追加します。|

## 設定ファイル
`config/` 配下の JSON ファイルは TinyMCE の設定オブジェクトに対応しています。キーや値は TinyMCE 公式ドキュメントの項目と同じ形式です。

| ファイル | 説明 |
| --- | --- |
| `manager.json` | 管理画面での TinyMCE 設定。高さ、プラグイン、ツールバーなどを定義。|
| `frontend.json` | `OnRichTextEditorInit` で `forfrontend` パラメーターが真の場合に読み込まれます。|
| `toolbar-presets.json` | 管理画面のプリセット選択肢（simple/basic/legacy/full など）を定義します。|

更新後は Evolution CMS のキャッシュをクリアして反映させます。

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

## システム設定キー
プラグインが参照する主なシステム設定キーです。旧 TinyMCE プラグインのキーも互換処理しています。

| キー | 内容 |
| --- | --- |
| `tinymce7_toolbar_preset` | プリセットを `simple` / `basic` / `legacy` / `full` から選択します。|
| `tinymce7_menubar` | メニューバーの表示制御（`1` = 表示、`0` = 非表示、空 = TinyMCE 既定値）。|
| `tinymce7_entermode` | Enter キーで挿入する要素を `p` または `br` から選択します。|
| 互換キー (`tinymce_toolbar_preset`, `tinymce_menubar`, `tinymce4_entermode` など) | 旧プラグインとの互換のために自動的に読み替えます。|

## イベントパラメーター
`OnRichTextEditorInit` に渡されたパラメーターは TinyMCE の設定にマージされます。

| パラメーター | 役割 |
| --- | --- |
| `elements` | 対象要素の ID。配列またはカンマ区切りの文字列が利用できます。|
| `height` / `width` | エディター領域のサイズ。TinyMCE 設定の `height` / `width` を上書きします。|
| `forfrontend` | 真の場合は `frontend.json` を読み込んで初期化します。|
| `tinymce7_file_browser` / `file_browser` | `mcpuk`（既定）または `none` を指定できます。|

## MCPuk ファイルブラウザー連携
`tinymce7_file_browser` が `mcpuk` の場合、`js/mcpuk-picker.js` が `file_picker_callback` を上書きし、選択したファイルの URL を CMS のルートに合わせて正規化します。無効化すると TinyMCE の標準ダイアログが利用されます。

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
このプロジェクトは [MIT License](LICENSE) の下で公開されています。
