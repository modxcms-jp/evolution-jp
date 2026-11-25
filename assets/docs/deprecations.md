# 非推奨の機能

非推奨にした機能と、消す予定の版、代わりの手段。サイトのスニペット・プラグイン・モジュールを書く人と、本体の開発者が読む。

## 警告の残し方

非推奨の機能が使われたら、`logDeprecated($name, $message)`（`manager/includes/helpers.php`）でシステムログに警告（type 2）を残す。

- 同じ `$name` は 1 回の要求につき 1 回だけ記録する
- コンテキストに `deprecated`（`$name`）・`file`・`line`（呼び出し元）だけを入れる。スニペット・プラグイン・URL は全ログ共通の `current_snippet`・`active_plugin`・`decoded_request_uri`・`request.url` に入る
- `$modx` の非推奨メソッド（`DeprecatedAPI`）は、これとは別に、`__call()` が別の条件で情報（type 1）のログを残す。`--level=warning` の検索には出ない
- 確認は `php evo.php log:search "deprecated" --level=warning`

## 一覧

| 機能 | 非推奨にした版 | 消す版 | 代わり |
| --- | --- | --- | --- |
| `Document` クラス（`manager/includes/extenders/ex_documentapi.php`） | 1.3 | 1.4 | `evo()->doc`（`loadExtension('DocAPI')` の DocAPI） |
| グローバル設定 `server_protocol`（画面の「サーバータイプ」。`[(server_protocol)]`・`config('server_protocol')`） | 1.3（廃止済み） | 1.3 | `fixed_site_url` を `https://` で書く（`site-url.md`） |

### server_protocol

画面の項目・初期値・言語キーを 1.3 で消した。この値を読んで動きを変える処理は元から無く、https かどうかは接続ごとに判定する。新しいサイトには `server_protocol` の行が無く、`[(server_protocol)]` は何にも置き換わらない。アップデートしたサイトの行は消さずに残すので、`[(server_protocol)]` は今の値のまま出る。

テンプレートやスニペットで `[(server_protocol)]` を読んでいるときは、`[(site_url)]` を使うか、`fixed_site_url` を `https://` で書く。

### Document クラス

`new Document($id)` は本体から使われていない。`Document` クラスはパーサーの起動時に読み込まれるので、`loadExtension('documentapi')` を呼ばなくても使える。警告は `Document::__construct()` と静的メソッド（`documentExist()`・`chPublish()`・`chDelete()`・`erase()`）の先頭で残すため、`Document` を使ったときだけ出る（`loadExtension('documentapi')` を呼ぶだけでは出ない）。動き（`Document` の中身と戻り値）は 1.4 まで変えない。

1 件の文書を作る・更新する・削除するときは、DocAPI の `create()`・`update()`・`delete()`・`saveTV()` を使う（#1085）。
