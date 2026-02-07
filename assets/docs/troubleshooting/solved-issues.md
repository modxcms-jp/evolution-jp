# Solved Issues

トラブルシューティングで解決した問題のナレッジベースです。

---

## 2026-02-04: outputfilter の未定義配列キー警告 (PHP 8.0+)

**Reference**: https://forum.modx.jp/viewtopic.php?p=10705#p10705

### エラーメッセージ

```
PHP Warning: Undefined array key 'imgclass' in .../docvars/outputfilter/image.inc.php on line 13
```

### 原因

outputfilter ファイル内で `$params` 配列のキーに直接アクセスしており、ウィジェットパラメータが設定されていない場合にキーが存在しない。PHP 7.x では Notice だったが、PHP 8.0 から Warning に昇格。

### 解決策

null 合体演算子 `??` を使用してデフォルト値を設定:

```php
// Before
'class' => $params['imgclass'],

// After
'class' => $params['imgclass'] ?? '',
```

### 修正ファイル

- `manager/includes/docvars/outputfilter/image.inc.php`
- `manager/includes/docvars/outputfilter/hyperlink.inc.php`
- `manager/includes/docvars/outputfilter/htmltag.inc.php`
- `manager/includes/docvars/outputfilter/datagrid.inc.php`
- `manager/includes/docvars/outputfilter/date.inc.php`
- `manager/includes/docvars/outputfilter/delim.inc.php`
- `manager/includes/docvars/outputfilter/string.inc.php`
- `manager/includes/docvars/outputfilter/richtext.inc.php`

### 関連情報

- PHP 7.0+ で `??` 演算子が使用可能
- PHP 8.0 で未定義配列キーアクセスが Notice から Warning に変更
