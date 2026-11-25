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
| グローバル設定 `server_protocol`（画面の「サーバータイプ」。`[(server_protocol)]`・`config('server_protocol')`） | なし（警告なしで 1.3 で廃止） | 1.3 | `[(site_url)]`（常に https にするなら `fixed_site_url` を `https://` で書く。`site-url.md`） |

### Document クラス

`new Document($id)` は本体から使われていない。`Document` クラスはパーサーの起動時に読み込まれるので、`loadExtension('documentapi')` を呼ばなくても使える。警告は `Document::__construct()` と静的メソッド（`documentExist()`・`chPublish()`・`chDelete()`・`erase()`）の先頭で残すため、`Document` を使ったときだけ出る（`loadExtension('documentapi')` を呼ぶだけでは出ない）。動き（`Document` の中身と戻り値）は 1.4 まで変えない。

1 件の文書を作る・更新する・削除するときは、DocAPI の `create()`・`update()`・`delete()`・`saveTV()` を使う（#1085）。

- `create()`・`update()` は最後にキャッシュを消す（`evo()->clearCache()`）が、`delete()` は消さない。`delete()` は文書を削除済み・非公開にして（ごみ箱に入れる）値を返さないので、呼んだあとでサイトの表示に反映するには `evo()->clearCache()` を呼ぶ。
- `saveTV()` もキャッシュを消さない。複数の TV を続けて保存したあとに、まとめて `evo()->clearCache()` を呼ぶ。

### server_protocol

画面の項目・初期値・言語キーを 1.3 で消した。警告は残さない。1.3 の時点で、本体にこの値を読む処理は無く、https かどうかは接続ごとに判定する。

`server_protocol` の行があるのは、グローバル設定を保存したことがあるサイトだけ。その行は消さずに残すので、`[(server_protocol)]` は今の値のまま出る。新しいサイトや、一度も保存していないサイトは行が無く、`[(server_protocol)]` は空になる（`config('server_protocol')` は null）。

`[(server_protocol)]` を読んでいるときは `[(site_url)]` に書き換える。サイトの URL を常に https にしたいときは `fixed_site_url` を `https://` で書く（リバースプロキシが https を伝えない環境など）。

## 1.3 で消した・変えたもの

非推奨の期間を置かずに、1.3 で消した・動きを変えたもの。サイトのスニペット・プラグイン・テンプレート・モジュールが使っていると影響する。「警告」が「なし」のものは、使われても記録を残さない。動きは 1.2 系（1.2.6J）との差で、実装を読んで確かめた。

### 本体の API

| もの | 1.3 での動き | 代わり | PR・Issue |
| --- | --- | --- | --- |
| `$modx->input_get()`・`input_post()`・`input_any()`・`input_cookie()` | 定義が無いので、呼ぶと空文字を返す。警告: システムログに「Call undefined method」（type 3）を 1 要求に 1 件 | `getv()`・`postv()`・`anyv()`・`cookiev()`、型つきの `get_` / `post_` / `request_` ヘルパー（`request-input.md`） | #1111 |
| `loadExtension('MakeTable')`・`$modx->table`（`MakeTable`） | `loadExtension()` が `false` を返し、`$modx->table` は空文字になる（`$modx->table->…()` は PHP のエラー）。警告: `$modx->table` を読んだときのシステムログのみ | 表側は `datagrid`（TV の出力ウィジェット・修飾子）、`db()->getHTMLGrid()`。独自の表は `DataTable`（`data-table.md`） | #1160・#1087 |
| `DataGrid`・`DataSetPager`（`manager/includes/controls/`） | クラスのファイルを消した。直接使うコードは動かない。警告: なし | 同上。表側の出力の変化（行の class・ページ送りの引数名など）は `data-table.md` の「サイト側の表」 | #1216・#1087 |
| `loadExtension('EXPORT_SITE')`（クラス `EXPORT_SITE`、`$modx->export`） | `loadExtension()` が `false` を返す。警告: なし | `EvoSiteExporter`・`EvoSiteExportJob`、`php evo.php export:site` | #910 |
| `top.mainMenu.reloadPane()`・`doRefresh()`（管理画面の JS） | 関数が無い。呼ぶと JS のエラーになる。再読み込みを求める URL の引数も `r=1`・`r=2`・`r=3`・`r=9`・`r=10` から `refresh=tree`・`menu`・`all`・`page` に変わり、`r` は読まない。戻さない（#1824 の決定）。警告: なし | `EvoShell.refresh()`、リダイレクト先の URL に `refresh` を付ける（`manager-shell.md`） | #1296・#1824 |
| ログインの「ユーザー名:roleid=N」（`login.processor.functions.php`） | ユーザー名の後ろに `:roleid=N` を付けると、ロール 1 のユーザーが、そのセッションだけロール N の権限で入る動き。管理画面はリクエストごとにロールを DB から読み直す（`refreshMgrPermissions()`、#46）ので、ログイン直後の次のリクエストから効かなくなっていた。#1821 でコードを消した。警告: なし | ロールを変えて確かめるときは、そのロールのユーザーでログインする | #1821・#46 |
| `$modx->contentTypes` | 宣言は残るが、値が入らなくなる（本体のどこからも使っていなかった、キャッシュからの書き出しをやめた）。読むと空。警告: なし | `$modx->config('custom_contenttype')` などの設定を読む | #1814 |

### DB の API

| もの | 1.3 での動き | 代わり | PR・Issue |
| --- | --- | --- | --- |
| `db()->exec()`・`query()` が無視していた DB エラー | エラー番号 1064（構文）・1054（列が無い）・1060（列の重複）・1061（キーの重複）・1091（DROP の失敗）を、成功（`true`）として返していた。すべての DB エラーで、システムログにエラー（type 3）を残し、500 でその要求を止める（`error_reporting` が 0 のときだけ止めずに `false` を返す）。失敗を許す呼び出しは、第 2 引数 `$watchError` を `false` にして `false` を受け取る。警告: システムログのエラー | 失敗を許す呼び出しは `db()->query($sql, false)` | #602・#585 |
| `db()->update()` の文字列「null」・`escape(null)`・`insert()` の `null` | `update()` は文字列「null」「NULL」を SQL の NULL にしない（PHP の `null` だけ NULL）。`escape(null)` は文字列 `'NULL'` でなく `null` を返す。`insert()` 系は `null` を SQL の NULL にする（空文字や文字列「NULL」でなく）。NOT NULL の列に入れる値は空文字にする。警告: なし | `null` を入れたいときは PHP の `null` を渡す | #554・#540 |

### ファイルの読み込み

| もの | 1.3 での動き | 代わり | PR・Issue |
| --- | --- | --- | --- |
| ファイル系の修飾子（`is_file`・`is_dir`・`file_exists`・`is_readable`・`is_writable`・`is_image`・`imageinfo`（別名 `getimageinfo`）・`filesize`・`file_get_contents`（別名 `readfile`）） | サイトのルートの下のパスだけを見る。`content/` をサイトの外へのシンボリックリンクにした構成では、実体がサイトの外になるので読めない（#1833 で扱う）。サイトの外、管理画面の下、`.` で始まる区切りを含む場所、バックアップ・スナップショットの置き場所は、存在しない扱い（`file_get_contents` は空、値がファイルでなければ値のまま）。1.2.6J では `is_file` 系・`is_image`・`imageinfo` は任意のパスを見ていた。ファイルブラウザが返す URL エンコードした名前も探せる。条件の修飾子（`is_file` 系）に管理画面の下の絶対パスを渡すと、`exit('Can not read core path')` でページを止める（1.2.6J から）。警告: なし | サイトの中に置く | #959・#1833 |
| `@FILE`・`@INCLUDE`・`@DIRECTORY`（`@DIR`）（バインド）、Ditto・Wayfinder・eForm の `@FILE` | `@FILE`・`@INCLUDE` は 1.2.6J で場所の制限が入っている（それより古い版から上げるときの変更）。実パスがサイトのルートの下にあり、管理画面の下・`.` で始まる区切り・バックアップ・スナップショットの置き場所のどれでもないときだけ読む。1.3 では `@DIRECTORY`・`@DIR` も同じ判定を通し、フォルダーが無い・読まない場所のときは空を返す（#1823）。`content/` がサイトの外へのシンボリックリンクの構成では、実体がサイトの外なので読めない（#1833 で扱う）。`@FILE` で `.php` は読まない。警告: なし | サイトの中の `assets/` などに置く | #1823 |

### 管理画面の機能

| もの | 1.3 での動き | 代わり | PR・Issue |
| --- | --- | --- | --- |
| メッセージ機能（`a=10`・`a=65`・`a=66`、上部の「メッセージ」、ロールの権限 `messages`、設定 3 項目） | 画面を消した。`a=10` などはほかの存在しない `a` と同じ扱い。`hasPermission('messages')` は常に偽、`[+MessageInfo+]`・`[+MessageIcon+]`・`[+iconMessage+]` は空、`$_style` の `icons_mail` など・消した言語キーが無くなる。保存済みのメッセージがあれば `user_messages` テーブルは残る（画面からは読めない）。警告: なし | なし | #1131・#1069 |
| 「HTMLからサイトをインポート」（`a=95`）、ロールの権限 `import_static` | 画面と権限を消した。列 `user_roles.import_static` はマイグレーションで消し、移り先をシステムログに残す | 別配布のモジュール Import HTML（`https://github.com/modxcms-jp/evo-import-html`） | #1590 |
| 旧ファイルブラウザ `manager/media/browser/mcpuk/`（配布ファイル） | 更新（マイグレーション）で配布ファイルを消す。この URL を直に開くプラグインや設定は 404 になる。警告: なし | 後継の `manager/media/browser/evo/`（ファイルピッカー） | #1827・#1815 |

### TV・テンプレート

| もの | 1.3 での動き | 代わり | PR・Issue |
| --- | --- | --- | --- |
| TV の選択肢のラベル（ドロップダウン・リスト・チェックボックス・ラジオ。`@SELECT` で作るものを含む） | 編集画面でエスケープして出す。ラベルに書いた HTML はタグが文字として出る。保存する値は変わらない。TV の説明は、対の `<b>`・`<strong>`（属性なし）だけ太字になり、ほかのタグは文字になる。警告: なし | ラベルには文字だけを書く | #1018・#895 |

### 同梱のスニペット

| もの | 1.3 での動き | 代わり | PR・Issue |
| --- | --- | --- | --- |
| Ditto の `&debug` | 効かない。警告: システムログに警告（type 2）を 1 呼び出しに 1 件 | 外す | #1582・#1573 |
| Ditto の `&config=@CHUNK …`・`&extenders=@CHUNK …` | チャンクの PHP を実行せず、その指定を飛ばして続ける。警告: 同上 | チャンクの PHP をファイルに移し、`@FILE`（サイトのルートからのパス）で指定する | 同上 |
| Ditto の `&extenders` にチャンク名だけを書く指定 | チャンクの PHP を実行しない。警告（type 2）に加え、「extender が無い」エラー（type 3）をログに残し、その文を返して一覧は出さない（`@CHUNK` と違い、続けない）。警告: 同上 | 同上 | 同上 |
| Ditto の TV の `tv` 接頭辞（`[+tvimage+]` で TV `image` を出す書き方） | TV として読まない。警告: 同上（対象のフィールドをコンテキストに入れる） | `tv` を付けずに TV の名前を書く | 同上 |
| Ditto 1.x 時代のパラメータ名（`&startID`・`&summarize`・`&limit`・`&tplAltRows` など 14 個）と `&config=compatibility` | 今の名前（`&parents`・`&display`・`&queryLimit`・`&tplAlt` など）に値を引き継ぐ。`&config=compatibility` は `summary` extender を足す。警告: 同上（廃止予定。消す版は未定） | 今の名前に書き換える。`[+summary+]` は `&extenders=summary` | 同上 |
| eForm の `&mobile`・`&mobiletext`・`&sendAsHtml`・`&reportAbuse`・`&sendirect` | 効かない。警告: フォームの送信のときだけシステムログに警告を 1 件（処理は止めない） | 携帯向けのメールは通常の宛先（`&to`・`&cc`・`&bcc`）に入れる。控えは `&ccsender`、自動返信は `&automessage` | #1568・#1565 |
| eForm の検証ルール・`#FILTER` の `#EVAL` | `#EVAL` の検証ルールは「知らないルール」として入力エラー、`#FILTER #EVAL` は値を変えない。警告: 同上 | `#FUNCTION` | 同上 |
| Wayfinder の `&debug` | 効かない。警告: システムログに警告（type 2）を呼び出しごとに 1 件 | 外す | #1575・#1570 |
| Wayfinder の `&config=bootstrap-navbar`（`configs/bootstrap-navbar.config.php`。Bootstrap 3 向け） | 設定ファイルを消したので、既定のメニューの出力になる。警告: なし | `&outerTpl`・`&rowTpl`・`&parentRowTpl` などに、自分のテンプレート（`@CODE:`・`@FILE:`・チャンク）を指定する。`startId=0`・`level=1` はその設定が決めていた値なので、必要なら呼び出しに書く | #1575・#1570 |
