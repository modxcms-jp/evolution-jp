# ExecPlan: 管理画面frameset廃止とAJAXシェル化

## Purpose / Big Picture

管理画面のframeset（HTML4のフレーム分割機構。1画面を複数の独立したHTMLページに分割する古い仕組み）を廃止し、単一HTMLページ上でリソースツリーとグローバルナビゲーションを保持したままコンテンツ領域だけをAJAX（ページ全体を再読込せずサーバーからHTMLを取得して差し替える手法）で切り替える構成へ一括移行する。これによりモダンブラウザとの互換性・保守性・拡張性が向上し、ロードマップの「frame要素廃止（最高優先）」を達成する。

## Progress

- [x] (2026-07-07) M1: 断片応答モード実装（$isPaneRequest、X-Evo-Pane/X-Evo-Action/X-Evo-Loginヘッダー）
- [x] (2026-07-07) M2: シェルレイアウト構築完了（frames/1.php削除、menu/tree部品化、shell.css）
- [x] (2026-07-07) M3: shell.js実装完了（EvoShellオブジェクト、form.submit()プロトタイプパッチ、添付ダウンロード対応込み）
- [x] (2026-07-07) M4: フレーム間参照の書き換え完了（残存参照は互換API経由で動作、grep確認済み）
- [x] (2026-07-07) M5: 特殊アクション対応完了（wait/bkmanager/モジュールdata-no-ajax/ログアウト）
- [ ] (2026-07-07) M6: 自走表示確認 — curl検証は完了（54アクション200・断片応答・リダイレクト・未ログイン検出・a=84/93疎通）。ユーザーのブラウザ確認で発見された5件の不具合を修正済み。ブラウザでの最終確認（保存フロー・履歴・未保存警告・RTL）が残り

## Surprises & Discoveries

- **部品includeによる変数スコープ汚染（重大）**: menu.php/tree.phpをheader.inc.phpから素朴にincludeすると、部品のローカル変数（`$tpl`, `$ph` 等）が同一スコープで実行される後続アクションファイルへ漏れ、welcome.static.php が tree.php のコンテキストメニューテンプレートを誤描画して「ダッシュボードが空白」になった。クロージャ（`static function` + `extract($GLOBALS, EXTR_SKIP)`）でのinclude隔離により解決。アクションファイルの多くは `$tpl`/`$ph` を未初期化で使うため、シェルに部品を追加する際は必ずスコープ隔離すること。
- **transform包含ブロック内のposition:fixedはスクロールに追従する**: `#mainPane` に `transform` を与えて fixed の基準を移す手法は、スクロールコンテナ自身が transform されているため `#actions` がコンテンツと共にスクロールしてしまった。最終的に「ウィンドウ基準の fixed + メニュー高さのオフセット」でレイヤー表示にした。
- **TinyMCEはAJAX遷移で再初期化されない**: `tinymce.EditorManager` に旧インスタンスが残ると同一セレクタへの `tinymce.init()` が無視される。差し替え前の `tinymce.remove()` で解決（shell.jsのteardownPane）。SPA共通の注意点。
- **a=29（error_dialog.static.php）は本ブランチ以前から壊れている**: includeファイル自体がリポジトリに存在せず500になる（mainでも同様）。本タスクの範囲外。core-issues.md へ記録する。
- **shell.js/shell.cssのキャッシュ**: 開発中の修正が反映されない事故が起きたため、filemtimeベースのバージョンクエリを付与した。
- **curlでの検証時の注意**: 未ログイン判定は `Accept-Language` ヘッダー必須（無いと404）。セッションが切れやすいためスクラッチパッドの `mgr.sh`（セッション自動再確立）を使用した。管理者は admin/password（ローカルdocker環境）。
- **断片のトップレベル `let`/`const` は再訪時にSyntaxErrorになる**: AJAX遷移で同じ画面を再訪するとインラインscriptが同一グローバルスコープで再実行され、`let`/`const` の再宣言が `SyntaxError` となりブロック全体が無効化する（resources_listのコンテキストメニュー不動作として発現）。断片のトップレベル宣言は `var` を使うか、IIFE/関数内に収めること。
- **ライブラリがbody直下に追加する要素はシェルのグリッドを崩す**: air-datepickerのカレンダー等がbody直下に入るとグリッドのセルを占有する。shell.cssの保険ルール（3ペイン以外は `grid-area: main`）と、断片内`<link>`のhead巻き上げ（shell.jsの`hoistStylesheets`）で対処した。

## Decision Log

- 2026-07-07 (yamamoto/Claude): 実装アプローチは「既存PHPスタック維持 + AJAXシェル化」を採用。SPAフルリライト（React/Vue等）は工数・リスク・プラグイン互換の観点で却下。ユーザー確認済み。
- 2026-07-07 (yamamoto/Claude): URL管理は History API（`pushState`）を採用。各画面遷移で `index.php?a=...&id=...` をアドレスバーに反映し、リロード・ブックマーク・戻る/進むを従来同様に機能させる。ハッシュ方式・URL固定方式は却下。ユーザー確認済み。
- 2026-07-07 (yamamoto/Claude): 一括移行（全アクションを一度に新レイアウトへ対応させてからリリース）とする。段階移行は旧フレームと新レイアウトの混在確認コストが高いため却下。ユーザー確認済み。
- 2026-07-07 (yamamoto/Claude): 本番環境はさくらインターネット等の共有レンタルサーバを想定するため、Node.js実行環境に依存しない。新規JSはビルド不要の素のJavaScript（ES6+）で `manager/media/script/` に直接配置する。AGENTS.md の「jQuery禁止 → Vanilla JS」ルールに従い、新規コードはjQueryを使わない（既存コードのjQueryは本タスクでは温存し、jQuery廃止タスクで別途対応）。
- 2026-07-07 (yamamoto/Claude): AJAX判定はリクエストヘッダー `X-Requested-With: XMLHttpRequest` とクエリ `ajax=pane` の併用とする。ヘッダーはfetchで自前付与し、リダイレクト追跡後も維持されるためprocessor経由の遷移でも断片応答を受け取れる。
- 2026-07-07 (yamamoto/Claude): モジュール実行（a=112）は独自の完全HTMLを出力するためシェル内に埋め込めない（iframeはAGENTS.mdで禁止）。フルウィンドウ表示（通常のページ遷移）とし、UX改善は将来課題として `assets/docs/core-issues.md` に記録する。
- 2026-07-07 (yamamoto/Claude): サードパーティプラグイン互換のため、`OnManagerPreFrameLoader` / `OnManagerFrameLoader` / `OnManagerMainFrameHeaderHTMLBlock` イベントはシェル描画時に従来どおり発火させる。また `window.main` / `window.tree` / `window.mainMenu` の互換シム（旧APIの形を保った代替オブジェクト）を提供する。
- 2026-07-07 (yamamoto/Claude): ロードマップのタスク定義には「段階移行計画」「依存関係: API Router基盤」とあるが、ユーザー判断により一括移行・API Router非依存（既存 `index.php?a=` ルーティングのまま）で先行実施する。
- 2026-07-07 (yamamoto/Claude): アクションボタン（#actions）はユーザー要望により「コンテンツに重なるレイヤー」とし、ウィンドウ基準の `position: fixed` + メニュー高さオフセットで実装。sticky案はフロー内に高さを取りコンテンツを押し下げるため却下。transform包含ブロック案はスクロール追従してしまうため却下。
- 2026-07-07 (yamamoto/Claude): シェル部品（menu/tree）のincludeはクロージャで変数スコープを隔離する（Surprises参照）。部品には `extract($GLOBALS, EXTR_SKIP)` でグローバル変数の読み取りアクセスを与える。

## Outcomes & Retrospective

（実装後に記載）

## Context and Orientation

対象はすべて `manager/` 配下。事前調査（2026-07-07実施）で判明した現状構造:

**framesetの構造**: `manager/index.php:141-144` で `a` パラメータ（アクション番号。管理画面の各機能を数値で識別する仕組み）が無い場合に `manager/frames/1.php` を出力して終了する。`1.php` は `mainMenu`（上部58px、`frames/menu.php`）・`tree`（左260px、`frames/tree.php`）・`main`（コンテンツ、`index.php?a=N` 自身）の3フレームを定義する。`$_SESSION['mainframe']` に保存されたURLで `main` の初期表示を復元する機能（`1.php:13-24`）がある。

**アクションルーティング**: `manager/index.php:435-789` の巨大switchで `a` の値に応じて `manager/actions/**/*.php`（画面表示）または `manager/processors/**/*.php`（保存・削除などの処理）をincludeする。`index.php:374-433` のリストにある約58アクションだけが `manager/actions/header.inc.php`（`<!DOCTYPE html>`〜`<body>` とCSRFメタタグ・共通JSを出力）と `manager/actions/footer.inc.php`（`</body></html>`）で挟まれ、アクションファイル自体は本文断片のみを出力する。footerの挟み込みリストは `index.php:791` にheaderとは別に存在する（内容がわずかに異なる: 117, 100 はfooterのみ）。

**アクションの4分類**:
1. ページ型（headerリストの約58個）: 断片HTMLを出力 → AJAX差し替え対象
2. processor型（保存・削除等30個以上）: HTML出力なし、`header('Location: index.php?a=...&r=N')` でリダイレクト（PRGパターン: POST後にリダイレクトして二重送信を防ぐ手法）
3. 自己完結型: a=84（リソースセレクタ、`window.open` ポップアップ）、a=93（バックアップマネージャ）、a=112（モジュール実行、任意HTML出力）、a=999（プラグイン出力）
4. 既存AJAX型: a=1&f=nodes（ツリーノード）、a=118（設定ajax）、a=114&ajax=entries（システムログ。`index.php:168` の `$isRawSystemLogRequest` でheader/footerをスキップする先行実装）、`manager/ajax.php`（ajaxaルーター）

**フレーム間参照の棚卸し**（`grep -rEno "(top|parent)\.(main|tree|mainMenu)\b"` の結果、12ファイル・約70箇所）:
- `frames/tree.php`（26箇所）: `top.main.document.location.href = "index.php?a=..."` によるmain遷移、および `parent.main.setMoveValue(id,name)` / `setParent(id,name)` / `setLink(id)` のクロスフレーム関数呼び出し（204-230行）
- `frames/nodes.php`（12箇所）、`actions/header.inc.php`（10箇所: `top.mainMenu.work()/stopWork()`、`parent.tree.ca = "open"`）、`frames/menu.php`（8箇所: `reloadPane()`、`removeLocks()` 等）
- 残り: `processors/document/move_document.processor.php`(4)、`frames/1.php`(4)、`actions/tool/import_site.static.php`(3)、`actions/document/move_document.dynamic.php`(3)、`media/script/tree.js`(1)、`actions/wait.static.php`(1)、`actions/permission/messages.static.php`(1)、`actions/document/resources_list.static.php`(1)

**`target="main"` リンク**: `frames/menu.php:443` の `item()` 関数が生成。ログアウトのみ `target="_top"`（menu.php:258）。`actions/document/publish_draft.dynamic.php:86` のformにも `target="main"` あり。

**ツリー再読込の合図**: processorのリダイレクトURLに付く `r=N` パラメータを `actions/header.inc.php:98-100` が読み `doRefresh(r)` → `top.mainMenu.reloadPane(r)` を呼ぶ。`reloadPane` は `frames/menu.php:147` に定義。a=7（`actions/wait.static.php`）は「ツリー再読込のための中継ページ」。

**CSRF**: `manager/includes/csrf_token.php` がトークンを管理（セッション内最大10個ローテーション）。`header.inc.php:130-206` に「metaタグからトークンを読み全POSTフォームへhidden注入 + jQueryの `$.ajaxSetup` で `X-CSRF-Token` ヘッダー付与」の仕組みが実装済み。

**インラインscript**: `manager/actions/` 配下45ファイルが `<script>` を含む。`innerHTML` 差し替えではscriptが実行されないため対策必須。

**未ログイン時**: `manager/includes/accesscontrol.inc.php` がログインフォームHTMLを出力する（AJAXレスポンスとして返ると断片差し替えが壊れる）。

**ビルドツール**: 存在しない（package.json等なし）。素のJS/CSSを直接配置する運用。

## Plan of Work

方針は「`header.inc.php` / `footer.inc.php` をシェル（メニュー・ツリー・コンテンツ枠を含む共通外殻）へ拡張し、AJAXリクエスト時だけ両者をスキップして断片を返す」こと。この設計を選ぶ理由は、(1) アクションファイルが元々「断片出力」設計であり変更が不要、(2) 直接URLアクセス（リロード・ブックマーク）時は従来どおりPHPが完全ページを描画するためpushStateとの整合が自然に取れる、(3) `$isRawSystemLogRequest`（a=114）という同型の先行実装が既にあり手法が実証済み、のためである。

マイルストーンは6つ。M1（サーバー側断片応答）→ M2（シェル構築）→ M3（SPAナビJS）は積み上げ式で、M3完了時点で「ツリー・メニューを保持したままコンテンツだけ切り替わる」動作をブラウザで確認できる（PoC到達点）。M4で残る旧フレーム参照を一掃し、M5で特殊ケースを塞ぎ、M6で全アクションの表示確認を行う。

シェル構築（M2）の要点: `frames/menu.php` と `frames/tree.php` は現在それぞれ独立した完全HTMLページとして動く。これを「部品モード」（`<div>` 断片と初期化JSのみを出力）で動作できるよう改修し、`header.inc.php` からincludeする。両ファイルのJSはグローバル関数が衝突しないよう、それぞれ `window.mainMenu = {...}` / `window.tree = {...}` のオブジェクトに集約する。これが同時に互換シムとなる（旧コードの `top.mainMenu.reloadtree()` は、フレームが無くなると `top === window` なので `window.mainMenu.reloadtree()` として解決される）。`top.main.document.location.href = url` 系は `window.main = window` を定義すれば動作する（フルリロードになる）が、リポジトリ内の参照はすべてM4で `EvoShell.navigate(url)`（AJAX遷移）へ書き換え、シムはサードパーティプラグイン救済用と位置付ける。

SPAナビJS（M3）の要点: 新規ファイル `manager/media/script/shell.js`（Vanilla JS）に `EvoShell` オブジェクトを実装する。責務は (a) `navigate(url)`: fetchで `X-Requested-With` ヘッダー付きGET→ `#mainPane` のinnerHTML差し替え → `<script>` 要素を `document.createElement('script')` で再生成して順次実行 → `history.pushState` → `response.url` の `r=N` を見てツリー部分再読込、(b) `popstate` リスナーで戻る/進む対応、(c) コンテンツ内リンクのクリック委譲（`data-no-ajax` 属性・`target` 属性付き・外部URLは除外）、(d) `#mainPane` 内のPOSTフォームのsubmit委譲（`FormData` + fetch。`enctype="multipart/form-data"` のファイルアップロードもFormDataで対応）、(e) `documentDirty`（未保存変更フラグ。既存グローバル変数）が真ならAJAX遷移前に確認ダイアログ、(f) レスポンスがログインフォーム（後述のマーカーで判定）ならフルリロードへフォールバック。

## Concrete Steps

### M1: サーバー側の断片応答モード

編集対象: `manager/index.php`、`manager/includes/accesscontrol.inc.php`

`manager/index.php` の `$isRawSystemLogRequest` 定義（168行付近）の直後に、断片応答判定を追加する:

    $isPaneRequest = (
        serverv('HTTP_X_REQUESTED_WITH') === 'XMLHttpRequest'
        || getv('ajax') === 'pane'
    ) && !$isRawSystemLogRequest;

374行と791行の `in_array(...) && !$isRawSystemLogRequest` の条件に `&& !$isPaneRequest` を追加し、AJAX時はheader/footerを出力しない。断片応答時はレスポンスヘッダー `X-Evo-Pane: 1` と、画面タイトル用の `X-Evo-Action: <アクション番号>` を送出する（シェルJSが正当な断片かを判定するため）。

`accesscontrol.inc.php` のログインフォーム出力直前に、`X-Requested-With` がある場合はヘッダー `X-Evo-Login: required` を付ける（シェルJSのフルリロード判定用）。

確認コマンド（開発環境のURLは適宜読み替え）:

    curl -s -D - -o /dev/null -H "X-Requested-With: XMLHttpRequest" -b "<ログイン済みCookie>" "http://localhost/manager/index.php?a=2" | grep -i x-evo-pane
    # 期待: X-Evo-Pane: 1 が出力され、本文に <!DOCTYPE html> が含まれない（-o を外して確認）

### M2: シェルレイアウト構築

編集対象: `manager/actions/header.inc.php`、`manager/actions/footer.inc.php`、`manager/frames/menu.php`、`manager/frames/tree.php`、`manager/index.php`、`manager/frames/1.php`（削除）、新規 `manager/media/style/common/shell.css`（テーマ非依存の配置用CSS。既存テーマ構成に合わせて配置先は実装時に調整可）

1. `menu.php` / `tree.php` を部品化する。それぞれ先頭で完全ページモード（従来の `a=1&f=menu` 直アクセス。後方互換のため当面残す）と部品モード（定数 `EVO_SHELL_PARTIAL` が定義されている場合）を分岐し、部品モードでは `<html>` や `<head>` を出力せず、ルート要素 `<header id="mainMenu">` / `<aside id="treePane">` とスクリプトのみ出力する。両ファイルのグローバルJS関数は `window.mainMenu` / `window.tree` オブジェクトへ集約する（例: `function reloadtree(){...}` → `window.mainMenu = { reloadtree: function(){...}, ... }`。既存呼び出し箇所は `top.mainMenu.reloadtree()` のままで動く）。
2. `header.inc.php` を拡張し、`<body>` 直後に部品モードで `menu.php` と `tree.php` をincludeし、続けて `<main id="mainPane">` を開く。ツリー幅は `manager_tree_width` 設定値をCSS変数（`--tree-width`）として出力し、CSS Gridで「上: メニュー / 左: ツリー / 右: メイン」を組む（`shell.css`）。RTL言語（右から左へ読む言語）設定時はgridの列順を反転する。互換のためここで `window.main = window;` を定義し、`OnManagerPreFrameLoader` / `OnManagerFrameLoader` を発火する。`shell.js`（M3）の `<script>` タグもここで読み込む。
3. `footer.inc.php` で `</main>` を閉じる。
4. `index.php:141-144` の frameset分岐を「`$_SESSION['mainframe']` があればそのURLへ、なければ `index.php?a=2`（またはパスワード忘れ時 `a=28`）へリダイレクト」に置き換える。`frames/1.php` は削除する。
5. `header.inc.php` 内の `top.mainMenu.hideTreeFrame()` 等フレーム前提コードを新API（`mainMenu.hideTree()` = CSSクラス切替）へ更新する。

期待観測結果: ブラウザで `index.php?a=2` を直接開くと、フレームなしの単一ページにメニュー・ツリー・ようこそ画面が表示される。ページソースに `<frameset>` が存在しない。

### M3: SPAナビゲーションJS

編集対象: 新規 `manager/media/script/shell.js`

Plan of Work 記載の (a)〜(f) を実装する。要点:

- script再実行: 差し替え後 `container.querySelectorAll('script')` を走査し、`src` 付きは同一URLのロード済み記録があればスキップ、インラインは新要素を生成して実行する。実行前に `window.documentDirty = false` にリセットする。
- pushState のstateには `{url: <遷移先>}` を保存し、`popstate` では `EvoShell.navigate(state.url, {push: false})` を呼ぶ。
- フォーム送信: fetchはリダイレクトを透過追跡するため、processorの `Location: index.php?a=...&r=N` の最終レスポンス（断片）がそのまま返る。`response.url` を `pushState` に使い、URLの `r` パラメータがあれば `window.tree.reload()`（部分再読込）を呼ぶ。従来 `r` を処理していた `header.inc.php` の `doRefresh` はフルページ描画時用に残す。
- フォールバック: `X-Evo-Pane` ヘッダーが無い、`X-Evo-Login: required` がある、fetch例外、のいずれかで `location.href = url` のフルリロードに切り替える（機能を壊さないための保険）。

期待観測結果: ツリーでリソースをクリックすると、ツリーとメニューが再描画されずにコンテンツだけが切り替わり、アドレスバーが `index.php?a=27&id=N` に変わる。ブラウザの戻るボタンで前画面に戻る。F5リロードで同じ画面が完全描画される。

### M4: フレーム間参照の書き換え

編集対象: Context and Orientation 記載の12ファイル（`frames/1.php` はM2で削除済み）

置換パターン表:

| 旧コード | 新コード |
|---|---|
| `top.main.document.location.href = url` / `parent.main.location.href = url` | `EvoShell.navigate(url)` |
| `parent.main.setMoveValue(id, name)` 等のクロスフレーム関数呼び出し | `window.main` シム経由で解決されるため `main.setMoveValue(id, name)` に統一（関数はコンテンツ側scriptがグローバルに定義） |
| `target="main"` 属性（menu.php の `item()`、publish_draft.dynamic.php） | 属性を除去し、リンクはクリック委譲でAJAX遷移 |
| `target="_top"`（ログアウト） | `data-no-ajax` 属性付き通常リンク |
| `top.mainMenu.X()` / `parent.tree.X` | そのまま動作（`window.mainMenu` / `window.tree` に集約済みのため）。ただし `location.reload()` している `reloadmenu` 等は部分再描画APIへ実装変更 |
| `top.tree.location.href = 'index.php?a=1&f=tree'`（reloadPane内） | `window.tree.reload()` |

`grep -rEn "(top|parent)\.(main|tree|mainMenu)\b" manager/ --include="*.php" --include="*.js"` が、互換シム定義箇所と `window.mainMenu`/`window.tree` 経由の正当な呼び出し以外でヒットしなくなるまで置換する。

### M5: 特殊アクション対応

- a=84（リソースセレクタ）: `window.open` ポップアップのため変更不要。ポップアップから親を操作するコードがあれば `opener` 参照を確認する。
- a=93（バックアップマネージャ）・a=112（モジュール実行）・a=999: シェル外のフルページとして扱う。メニューが生成するこれらへのリンクに `data-no-ajax` を付与する。a=93 は自前でheader/footerを読むためリスト整合を確認する。
- a=7（wait.static.php、ツリー再読込中継）: 参照元を `window.tree.reload()` 直接呼び出しへ置換し、アクション自体は互換のため残す。
- a=8（ログアウト）: 通常遷移（`data-no-ajax`）。
- `beforeunload` の未保存警告（header.inc.php:113）: フルページ離脱用に残しつつ、AJAX遷移時は `EvoShell.navigate` 内の `documentDirty` チェックで代替する。
- `processors/document/move_document.processor.php` 内の `parent.tree` 参照JSを新APIへ更新する。

### M6: 自走表示確認と回帰チェック

Validation and Acceptance の手順を全て実行し、結果を Progress に記録する。

### 想定コミット分割

1. `feat(manager): AJAXリクエスト時にheader/footerを省略する断片応答モードを追加`（M1）
2. `feat(manager): framesetを廃止しシェルレイアウトへ移行`（M2、`frames/1.php` 削除含む）
3. `feat(manager): fetch+pushStateによるSPAナビゲーション shell.js を追加`（M3）
4. `refactor(manager): フレーム間参照をEvoShell/互換シム経由へ置換`（M4）
5. `fix(manager): 特殊アクションのシェル対応と回帰修正`（M5・M6の修正分）

## Validation and Acceptance

すべてブラウザまたはcurlで観察可能な動作として定義する。開発環境（docker compose のアプリコンテナ、または既存の開発サーバ）で管理画面にログインした状態を前提とする。

1. **frameset不在**: `index.php`（パラメータなし）へアクセスすると `index.php?a=2` へリダイレクトされ、ページソースに `<frameset>` / `<frame` が含まれない。
2. **AJAX遷移**: ツリーのリソースをクリック→コンテンツのみ更新（開発者ツールのNetworkタブでドキュメント再読込が発生しない）、アドレスバーが `?a=27&id=N` に更新される。グローバルナビの「エレメント管理」等も同様。
3. **ブラウザ履歴**: 数画面遷移後、戻るボタンで直前の画面に戻り、進むボタンで再訪できる。任意の画面でF5リロードするとツリー・メニュー込みで同じ画面が表示される。
4. **保存フロー（PRG）**: リソース編集（a=27）でタイトルを変更して保存→保存後画面へAJAXで遷移し、ツリーの該当ノード表示が更新される（`r` パラメータによる部分再読込）。テンプレート・スニペット・チャンク・プラグイン・TVの新規作成/保存/削除も同様に完了する。
5. **未保存警告**: リソース編集中にフィールドを変更し、保存せず別画面へ遷移しようとすると確認ダイアログが出る。
6. **ページ型アクション網羅**: `index.php:374` のリストにある全アクション番号について `index.php?a=<N>` を直接開き、PHPエラー・レイアウト崩れ・JSコンソールエラーがないことを確認する（id必須のアクションは有効なidを付与）。curlでの機械チェック例:

        for a in 2 3 4 7 9 10 11 13 16 17 18 19 22 23 26 27 28 31 35 38 40 51 53 59 70 71 72 74 75 76 77 78 83 86 87 88 91 95 99 101 102 106 107 108 113 114 120 127 131 132 133 200 300 301; do
          code=$(curl -s -o /dev/null -w "%{http_code}" -b "<Cookie>" "http://localhost/manager/index.php?a=$a")
          echo "a=$a -> $code"
        done
        # 期待: 全行 200（リダイレクト系は 302 も可）

7. **断片応答**: 上記curlに `-H "X-Requested-With: XMLHttpRequest"` を付けると、レスポンスに `X-Evo-Pane: 1` があり本文に `<!DOCTYPE` が含まれない。
8. **特殊アクション**: a=84 がポップアップで開きリソース選択が親画面に反映される。a=93・a=112（任意のモジュール）がフルページで表示される。a=8 でログアウトできる。
9. **セッション切れ**: 別タブでログアウト後、元タブでツリーをクリックするとログイン画面へフルリロードされる（断片がコンテンツ領域に混入しない）。
10. **RTL**: システム設定で言語方向をRTLに切り替え、ツリーが右側に配置される。

受け入れ条件: 上記1〜9がすべて成功（10は目視確認のみで可）。加えて `grep -rn "frameset\|<frame " manager/ --include="*.php"` が実質ヒットゼロ（コメント・互換定数を除く）。

## Idempotence and Recovery

作業は専用ブランチ（例: `feature/manager-deframe`）で行い、`main` には影響させない。各マイルストーンはコミット単位で閉じるため、途中中断時は最後のコミットから再開できる。M1・M3・M4・M5は再実行しても同じ結果になる編集（条件追加・置換）である。M2の `frames/1.php` 削除はgit履歴から復元可能。動作不能に陥った場合は `git restore` で直前コミットへ戻し、Progress の該当項目を未完了に戻して原因を Surprises & Discoveries に記録してから再開する。データベースへの変更は本タスクには存在しない。

## Artifacts and Notes

- 本計画の事前調査ログ: 2026-07-07 のセッションで実施（フレーム間参照の棚卸しgrep結果は Context and Orientation に転記済み）
- 関連ファイル: `manager/index.php`, `manager/frames/1.php`, `manager/frames/menu.php`, `manager/frames/tree.php`, `manager/frames/nodes.php`, `manager/actions/header.inc.php`, `manager/actions/footer.inc.php`, `manager/includes/csrf_token.php`, `manager/includes/accesscontrol.inc.php`, `manager/ajax.php`
- 新規ファイル: `manager/media/script/shell.js`, `manager/media/style/common/shell.css`
- ロードマップ対応タスク: `.agent/roadmap.md` の「frame要素廃止（最高優先）」
- モジュール実行（a=112）のシェル内表示は未対応の既知課題として `assets/docs/core-issues.md` へ実装完了時に追記する

## Interfaces and Dependencies

- **プラグインイベント**: `OnManagerPreFrameLoader` / `OnManagerFrameLoader`（シェル描画時に発火継続）、`OnManagerMainFrameHeaderHTMLBlock`（header.inc.php で発火継続）、`OnManagerPageInit`。イベント名は互換のため変更しない。
- **JS互換シム**: `window.main`（= window）、`window.mainMenu`、`window.tree`。サードパーティプラグイン・モジュールが `top.main.location.href` を代入した場合はフルリロードとして動作する（機能は維持、AJAXの恩恵なし）。
- **CSRF**: 既存の `csrfTokenMeta()` メタタグ + `X-CSRF-Token` ヘッダー方式を踏襲。`shell.js` のフォーム送信fetchでも同ヘッダーを付与する。トークンはセッション内最大10個ローテーションのため、長時間開きっぱなしのシェルでは断片レスポンス受信時にmetaタグ相当の新トークンを `X-Evo-Csrf` ヘッダー等で受け取り更新することを実装時に検討する（Surprises に記録）。
- **jQuery**: 既存アクション断片のインラインscriptが依存するため、シェル（header.inc.php）での読み込みを継続する。新規コードでは使用しない。
- **外部依存**: なし（Node.js・ビルドツール・CDN不使用。共有レンタルサーバのPHPのみで動作）。
- **ロードマップ上の依存**: タスク定義上は「API Router基盤」に依存とされているが、本計画は既存 `index.php?a=` ルーティングのまま実施する（Decision Log 参照）。将来のRouter統合時は `EvoShell.navigate` のURL生成を一点変更すればよい。
