# Data URI 自動ファイル化機能

## 概要

リソース保存時に Data URI スキーム (`data:image/png;base64,...`) を自動検出し、ファイルに変換する機能。TinyMCE などで画像を貼り付けると Data URI として埋め込まれ HTML が肥大化する問題を解決する。

## 対象と保存先

- 対象フィールド: `content`、`introtext`
- 保存先: `content/images/datauri/{リソースID}/` （例: `20250103143022_a1b2c3d4.png`）
- 最大ファイルサイズ: 50MB
- 対応 MIME: `image/png`, `jpeg`, `gif`, `webp`, `svg+xml`, `bmp`, `application/pdf`, `text/plain`, `text/html`, `text/css`, `application/json`, `application/xml`

## 画像リサイズ

`<img>` タグの `width` / `height` 属性が元画像より小さい場合のみ縮小（拡大しない）。アスペクト比維持、透明度サポート（PNG/GIF/WebP）。

## 設定

- システム設定キー: `convert_datauri_to_file`（`1`: 有効（デフォルト）、`0`: 無効）
- 既存インストールではグローバル設定を一度保存すると `default.config.php` の値が反映される

## 実装ファイル

- `manager/actions/document/mutate_content/functions.php` — 変換処理の実装
- `manager/processors/document/save_resource.processor.php` — リソース保存時の呼び出し
- `manager/includes/default.config.php` — デフォルト設定
- `manager/includes/lang/japanese-utf8.inc.php` / `english.inc.php` — 言語ファイル

## 動作フロー

リソース保存 → `getInputValues()` → 設定チェック → `convertDataUriToFiles()` で Data URI を検出 → `<img>` タグから寸法抽出 → 必要に応じリサイズ → ファイル保存 → パスに置換 → 変換済みコンテンツで保存

## トラブルシューティング

変換されない場合: (1) `convert_datauri_to_file` が `1` か確認 (2) イベントログ確認 (3) `content/images/datauri/` の書き込み権限確認。50MB 超や非対応 MIME の Data URI は変換されずイベントログに記録される。
