# ExecPlan: outputfilter の未定義配列キー警告修正

## 1. 概要 (Overview)

- **Goal**: PHP 8.0+ で outputfilter 使用時に発生する `Undefined array key` 警告を解消する
- **Target Version**: v1.2.1J (バグ修正)
- **Reference**: https://forum.modx.jp/viewtopic.php?p=10705#p10705
- **Context**:
    - 関連: `AGENTS.md`, `.agent/PLANS.md`
    - 対象: `manager/includes/docvars/outputfilter/*.inc.php`

## 2. 原因 (Cause)

outputfilter ファイル内で `$params` 配列のキーに直接アクセスしている箇所があり、キーが存在しない場合に PHP 8.0+ で Warning が発生する。

```php
// 問題のあるコード例 (image.inc.php:14)
'class' => $params['imgclass'],  // キーが未定義だと Warning
```

PHP 7.x では未定義キーへのアクセスは Notice だったが、PHP 8.0 から Warning に昇格した。

## 3. 設計方針 (Design Strategy)

- **既存への影響**: なし（動作は変わらず、警告のみ解消）
- **後方互換性**: 完全維持
- **技術選定**:
    - null 合体演算子 (`??`) を使用してデフォルト値を設定
    - 既存の配列構造・ロジックは変更しない

## 4. 修正対象ファイル (Files to Fix)

| ファイル | 修正が必要なキー |
|----------|------------------|
| `image.inc.php` | `imgclass`, `imgstyle`, `alttext`, `id`, `imgattrib`, `imgoutput` |
| `hyperlink.inc.php` | `title`, `linkclass`, `linkstyle`, `target`, `linkattrib`, `text` + `$o` 初期化 |
| `htmltag.inc.php` | `tagid`, `tagname`, `tagclass`, `tagstyle`, `tagattrib`, `tagoutput` + `$o` 初期化 |
| `datagrid.inc.php` | 全28プロパティ (`egmsg`, `chdrc`, `tblc`, etc.) |
| `date.inc.php` | `default`, `dateformat` |
| `delim.inc.php` | `delim` |
| `string.inc.php` | `stringformat` |
| `richtext.inc.php` | `w`, `h`, `edt` |

## 5. 実装ステップ (Implementation Steps)

- [x] **Step 1: image.inc.php の修正**
    - [x] 全ての `$params['key']` アクセスに `?? ''` を追加
    - [x] `$params['align']` は `isset()` チェック済みなので変更不要

- [x] **Step 2: hyperlink.inc.php の修正**
    - [x] 全ての `$params['key']` アクセスに `?? ''` を追加
    - [x] `$o` 変数の初期化漏れを修正

- [x] **Step 3: htmltag.inc.php の修正**
    - [x] 全ての `$params['key']` アクセスに `?? ''` を追加
    - [x] `$o` 変数の初期化漏れを修正

- [x] **Step 4: 他の outputfilter ファイルの確認と修正**
    - [x] `datagrid.inc.php` - 全28プロパティを修正
    - [x] `date.inc.php` - `default`, `dateformat` を修正
    - [x] `delim.inc.php` - `delim` を修正
    - [x] `string.inc.php` - `stringformat` を修正
    - [x] `richtext.inc.php` - `w`, `h`, `edt` を修正
    - [x] 修正不要: `dateonly.inc.php` (date.inc.php をinclude), `unixtime.inc.php`, `htmlentities.inc.php`, `custom_widget.inc.php`

## 6. 検証方法 (Validation)

1. PHP 8.0+ 環境で Image プロセッサーを持つ TV を作成
2. TV をフロントエンドで表示し、Warning が発生しないことを確認
3. 画像が正常に表示されることを確認

## 7. 副作用の可能性 (Impact)

- **リスク**: 低
- 動作ロジックの変更なし
- デフォルト値として空文字列を設定するため、従来の挙動と同一

## 8. 進捗ログ (Progress Log)

- [2026-02-04]: 計画作成。フォーラム報告 #10705 に基づく。
- [2026-02-04]: 実装完了。8ファイルを修正。
