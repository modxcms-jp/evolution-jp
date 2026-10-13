# Solved Issues

トラブルシューティングで解決した問題のナレッジベースです。

---

## 2026-09-23: 管理画面の POST が CSRF 検証で 403 になる（token: none）

**Reference**: https://github.com/modxcms-jp/evolution-jp/issues/470

### エラーメッセージ

```
CSRF token validation failed | action: 5 | method: POST | token: none | valid_tokens_count: 1 | uri: /manager/index.php
```

`token: none` はフィールドもヘッダも送られていないことを示す。`valid_tokens_count` が 1 以上ならセッション切れではなく、クライアント側の送信経路の問題。

### 原因

- リソース編集のプレビュー（`jscripts.tpl`）がフォームを `target="prevWin"` で送信した後、`target="main"` を設定して戻していた。シェル化で `main` フレームはなくなったため、以後の保存は `shell.js` を経由しないネイティブ送信になる
- AJAX 遷移（`shell.js`）で差し込まれたフォームには hidden の `csrf_token` がなく、`form.submit()` は submit イベントを発火しないため、`header.inc.php` の自動付与も働かなかった
- 同様に `target` 付きフォーム（バックアップ等）や、`beforeSend` を独自指定した jQuery AJAX（`ajaxSetup` の `beforeSend` が上書きされる）でもトークンが欠落しうる

### 解決策

画面ごとの個別対応をやめ、`manager/media/script/csrf.js` で送信経路によらず同一オリジンへの POST にトークンを付与する。

- submit イベント（キャプチャ段階）と `HTMLFormElement.prototype.submit` の両方で、送信直前に hidden の `csrf_token` を補う
- `XMLHttpRequest`（jQuery 含む）と `fetch` で `X-CSRF-Token` ヘッダを付与する（既に付いていれば重複させない）
- `shell.js` が `form.submit` を退避するため、`shell.js` より先に読み込む

あわせてプレビュー処理を、送信前の `action` / `target` に戻す形へ修正した。

### 修正ファイル

- `manager/media/script/csrf.js`（新規）
- `manager/actions/header.inc.php`
- `manager/media/style/common/jscripts.tpl`

### 関連情報

- 過去の個別修正: #267 / #357 / #448
- `header.inc.php` を経由せず完全な HTML を出力するモジュールは対象外。フォームに `csrfTokenField()` を出力すること

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

- サポート対象の PHP 7.4+ では `??` 演算子が使用可能
- PHP 8.0 で未定義配列キーアクセスが Notice から Warning に変更
