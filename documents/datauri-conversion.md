# Data URI 自動ファイル化機能

## 概要

リソース保存時に Data URI スキーム (`data:image/png;base64,...`) を自動検出し、ファイルに変換する機能です。

TinyMCE などのエディタで画像を貼り付けると、編集後の画像が Data URI として埋め込まれ、HTMLのサイズが非常に大きくなる問題を解決します。

## 機能詳細

### 自動変換

- リソースの `content` フィールドと `introtext` フィールドを対象
- Data URI を検出すると自動的にファイルに保存し、パスに置換
- `<img>` タグの `width` / `height` 属性が指定されている場合は、その値に応じて画像を縮小（拡大はしない）

### 保存先

```text
content/images/datauri/{リソースID}/
```

例: `content/images/datauri/123/20250103143022_a1b2c3d4.png`

### サイズ制限

- 最大ファイルサイズ: 50MB
- 対応 MIME タイプ:
  - 画像: `image/png`, `image/jpeg`, `image/gif`, `image/webp`, `image/svg+xml`, `image/bmp`
  - その他: `application/pdf`, `text/plain`, `text/html`, `text/css`, `application/json`, `application/xml`

### 画像リサイズ

`<img>` タグに `width` / `height` 属性がある場合:

- 指定サイズが元の画像より小さい場合のみ縮小
- 拡大は行わない（データサイズ削減が目的のため）
- アスペクト比を維持
- 透明度をサポート（PNG/GIF/WebP）

**例:**

```html
<!-- 元の画像: 2000x1500 -->

<!-- width="800" → 800x600 に縮小 -->
<img src="data:image/jpeg;base64,..." width="800">

<!-- width="2500" → 拡大しないので 2000x1500 のまま -->
<img src="data:image/jpeg;base64,..." width="2500">

<!-- 指定なし → 2000x1500 のまま -->
<img src="data:image/jpeg;base64,...">
```

## 設定方法

### 新規インストール

インストール時に `default.config.php` のデフォルト値により自動的に有効化されます。

### 既存インストール

1. 管理画面にログイン
2. 「ツール」→「グローバル設定」を開く
3. 設定を一度保存する（何も変更しなくてもOK）
4. `default.config.php` のデフォルト値が `system_settings` テーブルに反映されます

### 設定値

- `1`: 有効（デフォルト）
- `0`: 無効

## 実装ファイル

- `manager/actions/document/mutate_content/functions.php`: 変換処理の実装
- `manager/processors/document/save_resource.processor.php`: リソース保存時の呼び出し
- `manager/includes/default.config.php`: デフォルト設定（`convert_datauri_to_file` => '1'）
- `manager/includes/lang/japanese-utf8.inc.php`: 日本語言語ファイル
- `manager/includes/lang/english.inc.php`: 英語言語ファイル

## 動作フロー

1. リソース保存時に `getInputValues()` が呼び出される
2. システム設定 `convert_datauri_to_file` をチェック
3. 有効な場合、`convertDataUriToFiles()` を実行
   - Data URI を検出
   - `<img>` タグから `width` / `height` 属性を抽出
   - 画像をリサイズ（必要な場合）
   - ファイルに保存
   - パスに置換
4. 変換されたコンテンツでリソースを保存

## トラブルシューティング

### 変換されない場合

1. システム設定 `convert_datauri_to_file` が `1` になっているか確認
2. イベントログを確認（変換処理のログが記録されます）
3. `content/images/datauri/` ディレクトリの書き込み権限を確認

### ファイルサイズが大きすぎる場合

50MB を超える Data URI は変換されません。イベントログにエラーが記録されます。

### 対応していない形式の場合

対応していない MIME タイプの Data URI は変換されず、そのまま保存されます。イベントログに警告が記録されます。

## パフォーマンス

- 文字列検索による高速な Data URI 検出
- 正規表現の使用を最小限に抑制
- 巨大な Data URI でもバックトラック問題を回避
- GD ライブラリを使用した高品質な画像リサイズ

## セキュリティ

- MIME タイプの許可リストによる制限
- ファイルサイズの上限設定
- ファイル名にランダム文字列を含めて衝突を回避
- リソースIDごとにディレクトリを分離
