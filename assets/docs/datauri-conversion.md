# Data URI 自動ファイル化機能

## 概要

リソース保存時に本文の Data URI スキーム (`data:image/png;base64,...`) の画像を検出し、ファイルに変換する機能。エディターで画像を貼り付けると Data URI として埋め込まれ、HTML が肥大化する。

貼り付けた画像は、本来はエディター側でアップロードして URL を入れる（TinyMCE 7 は `.agent/plans/2026-11-21-tinymce7-image-picker-unify.md` の M4）。この変換は、それで防げなかったもののフォールバックとして残す。エディター側で防げない経路（ほかのエディター、HTML ごとの貼り付け）と、すでに本文に入っている Data URI の画像を扱う。既存のリソースは、利用者に「リソースを開いて、そのまま保存してください」と伝えれば直る。

## 対象と保存先

- 対象フィールド: `content`（本文）だけ。`introtext`（要約）は対象にしない。ウェブリンク（`type` が `reference`）の本文も対象にしない
- 対象の位置: `<img>` 要素の `src` 属性の値になっている Data URI だけ（`isImgSrcValue()`）。属性を 1 つずつ読むので、`data-src` やほかの属性の値の中の `src=` は対象にならない。本文の文章、`<script>`・`<style>`・`<textarea>` の中、HTML コメントの中の `data:` は書き換えない。本文を頭からタグ単位でたどり、引用符で囲んだ属性の値は読み飛ばすので、属性の値の中に `<textarea>` などの文字があっても要素とは見なさない（`openTagStartAtEnd()`）
- 新規のリソースの ID を保存の途中で取り直したときは、書き出したファイルを新しい ID の場所へ移し、本文のパスも直す（`moveDataUriFilesToNewId()`）
- 保存先: `content/images/datauri/{リソースID}/` （例: `20250103143022_a1b2c3d4.png`）
- 最大ファイルサイズ: 50MB
- 対応 MIME: `image/png`, `jpeg`, `gif`, `webp`, `svg+xml`, `bmp`, `application/pdf`, `text/plain`, `text/html`, `text/css`, `application/json`, `application/xml`

## 画像リサイズ

`<img>` タグの `width` / `height` 属性が元画像より小さい場合のみ縮小（拡大しない）。アスペクト比維持、透明度サポート（PNG/GIF/WebP）。JPEG は EXIF の Orientation を画素に反映して書き出す（書き出すと EXIF は残らないため）。大きさの判定も、向きを反映した幅・高さで行う（`jpeg_exif_orientation_from_string()` / `gd_apply_orientation()`、`manager/includes/helpers.php`）。

## 設定

- システム設定キー: `convert_datauri_to_file`（`1`: 有効（デフォルト）、`0`: 無効）
- DB に行が無い既存インストールでも、読み込み時に `default.config.php` の値で補われる（グローバル設定を保存しなくてよい）

## 実装ファイル

- `manager/actions/document/mutate_content/functions.php` — 変換処理の実装
- `manager/processors/document/save_resource.processor.php` — リソース保存時の呼び出し
- `manager/includes/default.config.php` — デフォルト設定
- `manager/includes/lang/japanese-utf8.inc.php` / `english.inc.php` — 言語ファイル

## 動作フロー

リソース保存 → `getInputValues()` → 設定チェック → `convertDataUriToFiles()` で `<img>` の `src` の Data URI を検出 → `<img>` タグから寸法抽出 → 必要に応じリサイズ → ファイル保存 → パスに置換 → 変換済みコンテンツで保存

## トラブルシューティング

変換されない場合: (1) `convert_datauri_to_file` が `1` か確認 (2) システムログ確認 (3) `content/images/datauri/` の書き込み権限確認。50MB 超や非対応 MIME の Data URI は変換されずシステムログに記録される。
