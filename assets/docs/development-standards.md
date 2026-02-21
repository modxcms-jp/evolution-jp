# Development Standards

このドキュメントは実装時の具体規約を定義する。判断の優先順位は `AGENTS.md` に従う。

## ヘルパー利用

グローバルオブジェクトには `manager/includes/helpers.php` のヘルパー経由でアクセスする。

* `evo()`
* `db()`
* `manager()`

## 入力値取得

スーパーグローバルは直接参照しない。

* `$_GET` -> `getv($key, $default)`
* `$_POST` -> `postv($key, $default)`
* `$_REQUEST` -> `anyv($key, $default)`
* `$_SERVER` -> `serverv($key, $default)`
* `$_COOKIE` -> `cookiev($key, $default)`
* `$_SESSION` 読み取り -> `sessionv($key, $default)`
* `$_SESSION` 書き込み -> `sessionv('*key', $value)`
* リクエストメソッド判定 -> `is_post()` / `is_get()`

## DBセキュリティ

エスケープはDB操作の直前で実行する。

* `db()->insert(db()->escape($data), $table)`
* `db()->update(db()->escape($data), $table, $where)`
* NG: 変数宣言時の個別エスケープ
* NG: `compact()` の使用

## コーディングスタイル

* 配列は `[]` を使用する（`array()` 非推奨）
* デフォルト値は `?:` を優先する
* 早期リターンを優先する
* 文字列補間は `"{$var}"` を使用する
* インデントはスペース4つ
* PHP終端タグ `?>` は不要

## ログ

ログは `evo()->logEvent()` を使用する。

## レビュー言語

レビューは日本語で実施する。
