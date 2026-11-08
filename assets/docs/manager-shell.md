# 管理画面のシェル

管理画面は、メニュー・ツリー・本文を 1 つのページに描く「シェル」でできている。frameset は使わない（AGENTS.md の移行ルール）。
この文書は、シェルの組み立てと、frameset 時代のコードのために残している互換の名前の決まりを書く。画面の JS から別の画面へ移る書き方は `jquery-migration.md` の「画面の JS から別の画面へ移る」を正本とする。

## 組み立て

| 部品 | 描く場所 | ルート要素 |
| --- | --- | --- |
| メニュー | `manager/frames/menu.php` | `<header id="topMenu">` |
| ツリー | `manager/frames/tree.php`（ノードは `manager/frames/nodes.php`） | `<aside id="treePane">` |
| 本文 | 各アクション | `<main id="mainPane">`（`header.inc.php` が開き、`footer.inc.php` が閉じる） |

- `manager/actions/header.inc.php` が、メニューとツリーを部品として読み込み、本文の前に描く。`?chromeless=1` の画面と QuickManager の埋め込みでは、メニューとツリーを描かない（body に `evo-shell` が付かない）。
- 画面の移動は `manager/media/script/shell.js`（`EvoShell`）が受け持つ。`X-Evo-Shell: 1` を付けて要求し、`#mainPane` だけを差し替える（応答は `X-Evo-Pane: 1` の断片）。
- メニューとツリーの読み直しは `EvoShell.reloadMenu()` / `EvoShell.reloadTree()`。`index.php?a=1&f=menu|tree` を断片として取り直し、部品のルート要素ごと差し替える。部品の中の `<script>` だけが実行し直される。
- `a=1&f=tree` / `f=menu` を全体の読み込みで開くと（URL の直打ち・古いブックマーク）、管理画面のトップへ移る。部品は単独のページとしては出さない。
- `a=1&f=nodes` はツリーのノードの断片で、`tree.js` が `fetch` で取りに行く（開閉状態の保存の `savestateonly=1` を含む）。

## frameset 時代の名前の互換

frameset 時代のコードは、`top.main`・`parent.tree`・`top.mainMenu` のように、ほかのフレームの window を名前で指していた。シェルは 1 つのページなので、同じ名前を自分の window に用意して、そのまま動くようにしている。

| 名前 | 中身 | 用意している場所 |
| --- | --- | --- |
| `window.main` | 自分の window（`window.main = window`） | `manager/actions/header.inc.php` |
| `window.tree` | ツリーの状態（`ca`）と操作（`saveFolderState`・`restoreTree`・`expandTree`・`collapseTree`・`updateTree`・`reload`・`resizeTree`） | `manager/frames/tree.php` |
| `window.mainMenu` | メニューの操作（`reloadtree`・`reloadmenu`・`hideTreeFrame`・`defaultTreeFrame`・`reloadPane`・`removeLocks`・`showWin`・`keepMeAlive`・`work`・`stopWork` など） | `manager/frames/menu.php` |

- シェルでは `top`・`parent` も自分の window を指すので、`top.tree` や `parent.mainMenu` と書いたコードも同じものに届く。
- シェルのモーダル（`#evoShellModal`。シェルの中で開くファイルブラウザを含む）は iframe ではなく、断片を同じ window に差し込む。互換の名前はそのまま届く。
- シェルの中に置いた iframe（TinyMCE のダイアログなど）では、iframe 自身の window には `tree`・`mainMenu` が無いが、`parent`・`top` からはシェルのものに届く。
- QuickManager が公開側のページに埋め込んだ編集画面では、`parent`・`top` が公開側のページを指すので、届かない（自分の window にも無い）。
- `window.tree` は、ツリーの状態 `ca` を持つ。ツリーのノードを押したときの動き（開く・移動先にする・親にする・リンク先にする）は `ca` で決まり、移動・親の選択・リンク先の選択の画面が書き換える。画面を移るたびに `shell.js` が `open` に戻す。
- `tree.resizeTree()` は何もしない。ツリーの大きさはシェルの CSS が決める。外部のコードが呼んでも壊れないよう、名前だけ残している。

### 決まり

新しく書く・書き換えるコードに当てはめる。

- 互換の名前を使わない。画面の移動・メニューやツリーの読み直しは `EvoShell` の API（`go`・`navigate`・`reloadTree`・`reloadMenu`・`closeModal`）を使う。
- 例外は `window.tree` だけ。ツリーの状態 `ca` を変える API は `EvoShell` に無いので、`window.tree` を使う。`top.`・`parent.` を付けずに書き、無いとき（`chromeless`・QuickManager の埋め込み）は何もしない（例: `manager/media/style/common/jscripts.tpl` の `enableParentSelection()`）。

互換の名前は、外部のプラグイン・モジュール・テーマのために残す。本体の中には、まだ `top.`・`parent.` の書き方で使っている箇所がある（`manager/frames/nodes.php` のノードの `onclick`、`move_document.dynamic.php`・`move_document.processor.php`、`import_site.static.php`、`wait.static.php`、同梱の docmanager など）。これらを書き換えても、互換の名前は消さない。消すときは、外部への影響を Issue で調べてから決める。

## 残している名残

frameset の名前やしくみが残っているが、理由があって残しているもの。

| 名残 | 残す理由 |
| --- | --- |
| セーフモードの切り替えは `a=1&f=menu` へ POST する（`manager/includes/helpers.php` の `manager_safe_mode_toggle_form()` と `manager_apply_safe_mode_request()`） | 送り先と受け手の判定がそろっていて動いている。送り先を変えるなら両方を一緒に変える |
| 画面の `body` の `id` は `f` から取り、既定は `mainpane`（`manager/actions/header.inc.php`） | テーマの CSS が `body#mainpane` を参照している |
| ログイン画面とロックアウトの画面は、最上位の画面にフレーム（iframe）があれば、最上位の画面で開き直す（`manager/media/script/login.js`、`manager/media/style/common/manager.lockout.tpl` の `top.frames.length` の判定） | iframe の中（TinyMCE のダイアログ、QuickManager の埋め込みなど）でセッションが切れたとき、ログイン画面を最上位に出すため |
| イベント名 `OnManagerMainFrameHeaderHTMLBlock`・`OnManagerPreFrameLoader`・`OnManagerFrameLoader` | プラグインの API なので改名しない |
| ディレクトリ名 `manager/frames/`、テーマの CSS の `div.treeframebody` | テーマや拡張が参照しうる |
| ロールの権限 `frames` | 本体では確かめていないが、DB の列の既定値が 0 で、外部の拡張が `hasPermission('frames')` を見ている可能性がある（#1265） |
