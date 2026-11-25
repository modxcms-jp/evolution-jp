# 非推奨の機能

非推奨にした機能と、消す予定の版、代わりの手段。サイトのスニペット・プラグイン・モジュールを書く人と、本体の開発者が読む。

## 警告の残し方

非推奨の機能が使われたら、`logDeprecated($name, $message)`（`manager/includes/helpers.php`）でシステムログに警告（type 2）を残す。

- 同じ `$name` は 1 回の要求につき 1 回だけ記録する
- コンテキストに `deprecated`（`$name`）・`file`・`line`（呼び出し元）・`snippet` または `plugin`（その中から呼ばれたとき）・`uri` を入れる
- 確認は `php evo.php log:search "deprecated" --level=warning`

## 一覧

| 機能 | 非推奨にした版 | 消す版 | 代わり |
| --- | --- | --- | --- |
| `loadExtension('documentapi')` と `Document` クラス（`manager/includes/extenders/ex_documentapi.php`） | 1.3 | 1.4 | `evo()->doc`（`loadExtension('DocAPI')` の DocAPI） |

### Document クラス

`new Document($id)` は本体から使われていない。`loadExtension('documentapi')` を呼ぶと警告が残る。動き（`Document` の中身と戻り値）は 1.4 まで変えない。

1 件の文書を作る・更新する・削除するときは、DocAPI の `create()`・`update()`・`delete()`・`saveTV()` を使う（#1085）。
