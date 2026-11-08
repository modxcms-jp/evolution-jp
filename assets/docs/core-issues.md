# コア改善課題

改修作業を通じて発見されたコア側の課題を記録する。各項目は発見日・発見元・関連するロードマップ項目を明記する。

## 記録ルール

- 新しい課題は末尾に追記
- 解決済みの課題は項目に `[解決済]` と日付を付記（削除しない）
- 優先度は記載しない（ロードマップ側で管理する）

---

## ConfigCheck クラスの UI 結合

- **発見日**: 2026-02-11
- **発見元**: CLI self-bootstrap プラン (`health:check` 実装時)
- **ファイル**: `manager/includes/config_check.inc.php`
- **課題**: チェックロジックと HTML レンダリングが密結合している
  - `IN_MANAGER_MODE` ガードで CLI から `require` できない
  - `run()` 内で HTML `<fieldset>` を直接組み立てている
  - `$_SESSION` / `sessionv()` に依存（admin 判定、TemplateSwitcher チェック等）
  - ファイル末尾でクラスを即座にインスタンス化（定義と実行が混在）
- **改善案**: `generateWarnings()` が既に構造化配列を返しているので、UI 依存を `run()` 側に閉じ込め、チェックロジックを公開メソッドとして CLI・管理画面・API から共有可能にする。`sessionv()` 依存の除去も必要。
- **関連ロードマップ**: なし（新規候補）

## Mysqldumper クラスの設計上の制約

- **発見日**: 2026-02-11
- **発見元**: CLI self-bootstrap プラン (`db:export` mysqldump 改修時)
- **ファイル**: `manager/includes/mysql_dumper.class.inc.php`
- **課題**:
  - `addslashes` ベースのエスケープ（MySQL ネイティブエスケープではない）
  - トランザクション整合性なし（`LOCK TABLES` / `--single-transaction` 相当がない）
  - ~~全体を `file_get_contents` でメモリに読み込む設計~~ → 管理画面のスナップショット・ダウンロードは解消（2026-10-24、`dumpToFile()` が一時ファイルへ書き、書き終えてから rename する）。`createDump()`（`db:export --driver=php` と `db:backup --driver=php` が使う）と復元（SQL 全体を文字列で読み込んでから文に分ける）は、大きな DB ではメモリが要るまま
  - ファイル先頭の `$modx` グローバル変数チェックがクラス定義として不適切
- **改善案**: CLI では `mysqldump` ラッパーをデフォルトにすることで回避済み。管理画面のバックアップ機能でも同様に `mysqldump` を優先候補にすることを検討。クラス自体の改修は PDO 移行と合わせて行うのが効率的。
- **関連ロードマップ**: Phase 2 PDO 移行

## logEvent の HTML 混入（解決済み）

- **発見日**: 2026-02-11
- **解決日**: 2026-04-29
- **発見元**: CLI self-bootstrap プラン（旧DBログ表示の方針検討時）
- **ファイル**: `manager/includes/traits/document.parser.subparser.trait.php` (logEvent メソッド)
- **課題**: `event_log` テーブルの `description` カラムに HTML タグが混入していた。呼び出し元が管理画面表示を前提として HTML を含むメッセージを渡していたため、CLI やAPI で消費する際に `strip_tags` 等の後処理が必要だった。
- **対応**: システムログをJSONLines形式のファイル保存へ移行し、旧DBログ用の表示・削除・エクスポート入口を削除した。CLI は `log:tail` / `log:search` で構造化ログを扱う。
- **関連ロードマップ**: Phase 1 ログ機構の改修（構造化データ保存・HTML 保存の廃止）

## a=29 (error_dialog.static.php) の欠損

- **発見日**: 2026-07-07
- **発見元**: 管理画面frameset廃止プラン（全アクション疎通チェック時）
- **ファイル**: `manager/index.php` (case 29 の include)
- **課題**: `manager/actions/error_dialog.static.php` がリポジトリに存在せず、`index.php?a=29` へアクセスすると include 失敗で500になる。git履歴上もファイルが存在した形跡がなく、frameset廃止以前からの既存欠陥。
- **改善案**: case 29 の分岐を削除するか、汎用エラーダイアログを新設する。参照元（a=29へ誘導するコード）の有無を確認のうえ対応する。
- **関連ロードマップ**: なし（新規候補）

## EvoShellモーダルを閉じると背後ページのTinyMCEまで消える（解決済み）

- **発見日**: 2026-07-11
- **解決日**: 2026-07-11（同一ExecPlan内で対応）
- **発見元**: ファイルブラウザモーダル化ExecPlan(2026-07-11-file-browser-modal-rewrite)の実機検証。リソース編集で本文(TinyMCE)を開いたまま、TVの画像入力からファイルブラウザモーダルを開いて選択・確定すると、本文欄のTinyMCEエディタが消えて素のtextareaに戻る事象を確認。
- **ファイル**: `manager/media/script/shell.js` (`teardownPane`関数)
- **課題**: `teardownPane(pane)`は#mainPane差し替え時とモーダルclose時の両方から呼ばれる共通クリーンアップだが、TinyMCE破棄処理が`window.tinymce.remove()`(引数なし=全インスタンス一括削除)になっていた。#mainPane差し替え時は妥当(旧ページのエディタを一掃してから新ページを差し込む)だが、モーダルclose時に同じ関数を呼ぶと、モーダル内容と無関係な背後ページのエディタまで巻き添えで削除されていた。モーダル内にエディタを含まない操作(ファイルブラウザ等)でも発生する一般的な副作用で、ファイルブラウザ固有のバグではない。
- **対応**: `tinymce.editors`(現存インスタンス一覧)を`pane.contains(editor.getElement())`で絞り込み、閉じるpaneの内側にあるエディタだけを`remove()`するよう変更。#mainPane差し替え時は従来通り全エディタが対象になり(通常すべて#mainPane内にあるため)、モーダルclose時はモーダル内にエディタがなければ何も削除されない。
- **関連ロードマップ**: frame要素廃止(モーダル機構はこのExecPlanの一部として実装)

## モジュール実行 (a=112) のシェル外表示（解決済み）

- **発見日**: 2026-07-07
- **解決日**: 2026-07-07（同一ExecPlan内で対応）
- **ファイル**: `manager/processors/module/execute_module.processor.php`
- **課題**: モジュールは独自の完全HTMLを出力するため、シェル（メニュー/ツリー保持）内にAJAXで埋め込めなかった。当初はフルウィンドウ表示（data-no-ajax）として先送りしていた。
- **対応**: モジュール出力を `<html` タグの有無で判定し、完全HTMLならフルページ応答（shell.jsが自動でフルリロードへフォールバック）、断片ならシェル内（メニュー/ツリー保持）に表示するよう変更した。
- **関連ロードマップ**: frame要素廃止（完了）

## アップデーターの要素の入れ直しで、利用者の紐付けが戻る

- **発見日**: 2026-10-26
- **発見元**: マイグレーション機構の ExecPlan（`.agent/plans/archive/2026-10-30-migration-mechanism-modernization.md`）の着手前の調査
- **ファイル**: `install/processors/prc_insPlugins.inc.php`（106 行）、`install/processors/prc_insTVs.inc.php`（61 行）
- **課題**: アップデーターは、同梱プラグインのイベントの紐付け（`site_plugin_events`）と、同梱 TV のテンプレートの割り当て（`site_tmplvar_templates`）を、その要素の分をすべて消してから入れ直す。利用者が外したイベントや付け替えたテンプレートは、アップデートのたびに配布の状態へ戻る（推測。実際の動きは未確認）。
- **改善案**: 新しく入れた要素だけ紐付けを作り、既にある要素は配布で増えたイベントだけを足す。外したかどうかを見分ける記録が要るかを先に調べる。
- **関連ロードマップ**: なし（新規候補）

## 古いサイトのテーブルのエンジンが MyISAM のまま残る

- **発見日**: 2026-10-26
- **発見元**: マイグレーション機構の ExecPlan の着手前の調査
- **ファイル**: `install/convert2utf8mb4.php`、`install/sql/create_tables.sql`
- **課題**: 1.0.x の新規インストールは `TYPE=MyISAM` で表を作っていた。今の新規インストールは DB の既定（InnoDB）で作るが、アップデーターの utf8mb4 への変換は文字セットだけを変え、エンジンは変えない。古いサイトだけ MyISAM のまま残り、索引の長さの上限（1000 バイト）やトランザクションの有無が新規インストールと違う。
- **改善案**: エンジンをそろえる処理を、利用者が選べる変換としてアップデーターに足すか、マイグレーションで行う。大きな表では時間がかかるので、事前に件数と所要時間の目安を示す。
- **関連ロードマップ**: マイグレーション機構（非対象として切り出した）

## API でも PHP の警告が HTML のエラーページになり、JSON のエラーに届かない

- **発見日**: 2026-10-31
- **発見元**: ファイルブラウザの KCFinder 級拡張（#678）と、削除の失敗が表示されない不具合（#969）
- **ファイル**: `manager/includes/document.parser.class.inc.php`（`set_error_handler()` と `phpError()`）、`manager/media/browser/evo/api.php`
- **課題**: `MODX_API_MODE` で動く API でも、PHP の警告（`unlink()`・`filesize()` の Permission denied など）を DocumentParser のエラーハンドラーが拾い、HTML のエラーページを出して HTTP 500 で止まる。`display_errors=Off` でも起きる。そのため、API が JSON の `errors` を返すはずの分岐に届かず、画面はエラーを読めない
  - ファイルブラウザでは、削除（#969、#988 で修正）、壊れたシンボリックリンクがあるフォルダの一覧、コピーで繰り返し問題になり、そのたびに `@` と戻り値の確認で個別に避けた
- **改善案**: API の入口で警告を例外か JSON のエラーに変える、またはエラーハンドラーが API（`MODX_API_MODE`、または JSON を返すリクエスト）では HTML を出さずに記録だけにする。それまでは、API のファイル操作は戻り値を確かめ、警告で止まらないように書く
- **関連ロードマップ**: なし（新規候補）

## グローバル設定の保存が、送られたキーをすべて保存する

- **発見日**: 2026-11-05
- **発見元**: グローバル設定の画面を定義から組む ExecPlan（`.agent/plans/2026-11-05-settings-screen-definition.md`）の着手前の調査
- **ファイル**: `manager/processors/save_settings.processor.php`（`save_settiongs()`・`nonSettingKeys()`）
- **課題**: 保存は `$_POST + default.config.php` のキーを許可リスト無しで `system_settings` に `REPLACE INTO` する。設定ではない欄（言語から文面を読み込む select、初期文面の hidden、テンプレートの一括変更など）は、`nonSettingKeys()` の一覧に手で足して除いている。画面に設定でない欄を足すたびに一覧を直す必要があり、漏れると不要な行が `system_settings` に残る。プラグインの欄（`On…SettingsRender`）も同じ仕組みで保存されるため、単純に許可リストへ替えるとプラグインの設定が保存されなくなる
- **改善案**: #1067 で設定の定義（`manager/includes/settings_form.definition.inc.php`）ができたら、保存するキーを「定義の設定キー + `default.config.php` のキー + プラグインが宣言したキー」に絞る。プラグインがキーを宣言する口（イベントの戻り値や定義への追加）を先に決める。#1067 では保存の処理を変えないと決めたので、別の課題として扱う
- **関連ロードマップ**: #1067（設定の定義。非対象として切り出した）

## 表とページ送りの組み方が DataTable の外に残る（解決済み）

- **[解決済] 2026-11-08（#1204）**: 管理ログ・検索・スケジュール・ManagerManager の表を `DataTable`・`DataTablePager` に移し、`paginate.inc.php` と `tablesort.js` を消した。以下は当時の記録
- **発見日**: 2026-11-07
- **発見元**: 表の部品（DataGrid・MakeTable）を作り直す ExecPlan（`.agent/plans/archive/2026-11-07-data-table-component.md`、#1087）の利用箇所の洗い出し
- **ファイル**: `manager/includes/paginate.inc.php`（`Paging`）、`manager/actions/report/logging.static.php`、`manager/actions/main/search.static.php`、`manager/actions/report/site_schedule.static.php`、`manager/media/script/tablesort.js`、`assets/plugins/managermanager/libs/OnPluginFormRender.inc.php`
- **課題**: #1087 で表の部品を `DataTable`（`manager/includes/controls/data_table.class.php`）にまとめるが、その対象（DataGrid・MakeTable）の外に、同じ役割の作りが残る
  - ページ送りの 3 つ目の実装 `Paging`（引数 `int_cur_position`）を、管理ログが使っている（メッセージの画面も使っていたが、メッセージ機能は本体から外された）。リンクの HTML を配列で返し、画面ごとに組み立て直している
  - 管理ログ・検索・スケジュールの表は手書きで、`border`・`cellpadding`・`bgcolor`・`width` の属性を出し、並べ替えはブラウザ側の `tablesort.js`（IE 向けの分岐を含む古いライブラリ）で行う
  - ManagerManager の設定の補助の表も、`gridItem`・`gridAltItem` を手で付けて組んでいる
- **改善案**: これらの画面を `DataTable`・`DataTablePager` に移し、`paginate.inc.php` と `tablesort.js` を消す。並べ替えは `DataTableLinks` のサーバー側の並べ替えにそろえる。それまで、新しく書くページ送りと表は `DataTable` の部品だけを使い、`Paging` と `tablesort.js` を使うコードを増やさない（決まりは `assets/docs/data-table.md` に書いた）
- **関連ロードマップ**: 自前で組み立てている表とページ送りを DataTable に移す（#1204）
