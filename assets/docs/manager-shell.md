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
- 完全な HTML（`<html>`）を返す画面（外部のモジュールなど。`a=112`）は、`replaceDocument()` が文書ごと差し替える。GET で画面を移るときは `pushState` で応答先の URL を積み、直前の管理画面の項目を残す（リダイレクトしない POST・戻る／進むでは `replaceState`）。差し替えると `document.open()` で window のリスナーが消えるので、`popstate` を受け、URL（ハッシュを除く）が変わったら `location.reload()` する。戻るでその URL の管理画面を読み直し、進むでは読み直した管理画面のシェルが `navigate()` で取り直す。どちらもモジュールは 1 回だけ動く。断片を返すモジュール（同梱の docmanager など）はシェルの中に出る。ただし、モジュールの画面を全体で読み込んだあと（F5、モジュールの中でリンク・フォームで移ってから戻ったとき）の「戻る」では、読み込んだ画面に `popstate` のリスナーが無いので、URL だけが変わって管理画面に戻らない（F5 で戻る）。
- 差し替えた断片の `<script>` を実行し終わるまで、`#mainPane`（シェルのモーダルでは `#evoShellModalBody`）に `evo-pane-pending` を付けて隠す（`opacity: 0`）。`src` のある `<script>` の読み込みを待つ間に、タブの組み立てなど JS で整える前の HTML が一瞬描かれるのを防ぐ。非同期で後から整う部品（TinyMCE の初期化など）は待たない。
- 断片の中の `<script>` は、画面を訪れるたびに実行し直す（ページ専用の初期化のため）。`<script src>` は、同じタグが今の文書に残っていれば読まない。
- 1 回だけ動かしたいスクリプトは、次のどちらかにする。
  - 自前のスクリプト: スクリプト自身が確かめて、2 回目は何もしない（例: `if (!window.EvoDataTable)`。`data-table.js`・`mcpuk-picker.js`）。
  - 手を入れられない外部のライブラリの本体: `data-shell-once="<本体が作るグローバル変数の名前>"` を付ける（例: `<script src="…/tinymce.min.js?…" data-shell-once="tinymce">`。TinyMCE 7 は `ScriptFactory::scriptTag()` の第 2 引数）。読めてその変数が出来たら、変数がそのままのあいだは、シェルの中で画面を移っても読み直さない。読めなかったとき（変数が出来ないとき）や、別のスクリプトが同じ名前の変数を作り直したときは、次に訪れたときにまた読む。
  - `data-shell-once` を付けてよいのは、読んだときに画面の要素に触らず、定義と窓への登録だけをするもの。画面の要素を拾って初期化するスクリプトに付けると、2 回目からの訪問で初期化されない。
- メニューとツリーの読み直しは `EvoShell.reloadMenu()` / `EvoShell.reloadTree()`。`index.php?a=1&f=menu|tree` を断片として取り直し、部品のルート要素ごと差し替える。部品の中の `<script>` だけが実行し直される。
- 保存後の読み直しの受け口は `EvoShell.refresh(target)` 1 つ。processor が戻り先の URL に付ける `refresh` を、画面の移動のとき `shell.js` が読んで呼ぶ。`target` の値と意味は `manager/includes/form_flow.class.inc.php` の docblock を正とする（`page` は管理画面のトップ（`index.php`）を読み直す）。画面の script から `EvoShell.refresh()` を直接呼ぶ使い方もある（`refresh_site.dynamic.php`、`wait.static.php`）。全体の読み込み（再読み込み・URL の直打ち）では `refresh` を読まない。シェルの画面でなければ（`chromeless`・QuickManager）何もしない。
- `a=1&f=tree` / `f=menu` を全体の読み込みで開くと（URL の直打ち・古いブックマーク）、管理画面のトップへ移る。部品は単独のページとしては出さない。
- `a=1&f=nodes` はツリーのノードの断片で、`tree.js` が `fetch` で取りに行く（開閉状態の保存の `savestateonly=1` を含む）。
- 本文で開いている文書をツリーで選ぶ。`#mainPane` の中の要素に `data-tree-document="<文書の ID>"` と `data-tree-parents="<根から順の親の ID をカンマで>"` を付けると、`tree.js` の `revealPaneDocument()` が、閉じている親のフォルダを根の側から順に開き（開閉状態も保存する）、その文書のノードを選んで、ツリーの中で見えるところまでスクロールする。リソース一覧から開いたときや URL を直接開いたときのため。呼ぶのは、シェルでの画面の移動（`evoshell:load`）・全体の読み込み・ツリーの読み直し（`restoreTree()`。保存のあとも通る）のとき。並べ替え・削除済みの表示の切り替え・すべて展開では、親を開かずに見えていれば選ぶだけ。すべて折りたたむときは選び直さない。今付けているのは文書の編集画面（`manager/actions/document/mutate_content/_template.tpl` の form。値は `functions.php` の `tree_document_attr()`）。文書が直接の親の中の「残り N 件を表示」で省かれているときは、続きを自動では読み込まず、その行を選んだ見た目にして、この中にあることだけを示す（数千件のフォルダでツリーを重くしないため。続きを読むかは利用者が決める）。利用者がその行を押して続きを読み込んだら、文書が出てくれば文書を、まだ先なら新しい「残り」の行を選び直す。続きの行は、フォルダを開くときと同じ動き（`tree.php` の `slideTreeBox()`。動きを減らす設定では動かさない）で広げ、動き終わってからフォルダの箱の直下へ並べ直して選び直す。親がツリーに無いとき（権限で見えない）は、そこで止める。
- 開いているリソースのフォルダだけを開く切り替え（`#treeAutoCollapse`。アイコンは `style('auto_collapse_tree')`、`common/images/icons/tree_focus.svg`）。既定はオンで、ブラウザーの保存領域（`evo.tree.autoCollapse`。オンは `1`、オフは `0`）にブラウザーごとに覚える（値が無ければオン。読み書きは `try/catch`。使えなくてもツリーを読み直すまでは効く）。状態は `aria-pressed` を正とし、`syncTreeAutoCollapse()` がツリーを描いたときに保存領域から戻す。オンのときは、`revealPaneDocument()` が親を開き終えたあと（画面の移動・全体の読み込み・`restoreTree()` のあと）に `collapseTreeToDocument()` が、開いている文書の親（根まで）と文書自身（フォルダのとき）を残して、ほかのフォルダを閉じる。見えているいちばん外側のフォルダだけを `slideTreeBox()` で閉じ、中は動かさず閉じた状態にして、開閉の状態を 1 回保存し（`saveFolderState()`）、閉じ終えたら今選ばれている行を見せ直す（選び直しはしない）。閉じるのは、親がすべて開いていて、親が 1 つ以上ある（根直下の文書は、その行がある）ときだけ。文書の行が「残り N 件を表示」で省かれていても、親が開けていれば閉じ、省略の行を見せる。リソースの画面でないとき、親がツリーに無いとき、根直下の文書の行が無いときは何もしない（手で開いたフォルダは、次にリソースの画面へ移るまで閉じない）。オンにした瞬間は `revealPaneDocument()` を呼んで今のリソースに合わせ、オフにしても開閉は変えない。
- 「リソースツリーを展開」（`#treeExpand`）と「リソースツリーを縮小」（`#treeCollapse`）は 1 つ分の場所で出し分ける（それぞれを包む `<td>` の `hidden`。`<a>` の `display` をテーマが決めていても両方出ない）。見えているフォルダが 1 つでも開いていれば後者、全部閉じていれば前者だけを見せる。`syncTreeToggleAll()` が開閉のたび（`toggleNode()`・`rpcLoadData()`・自動の折りたたみ）に表示を合わせる。どちらの id と `expandTree()`・`collapseTree()` も、外部の拡張やテーマの CSS が参照しうるので残す。ツリーの上のボタンは 10 個（権限で減る）で既定の幅に収まる。
- ツリー上部のボタンの並びは `.tree-toolbar` が包む（`#treeMenu` と `#floater`）。`#treePane`（スクロールする箱）の上端に `position: sticky; top: 0` で貼り付き、並べ替えの小窓 `#floater` の位置の基準になる（`top: 100%` で並びの直下に出す）。背景はテーマの `.treeframebody` から引き継ぐ（`background: inherit`）。`.treeframebody` が透明・半透明のテーマは、下のツリーが透けて読めなくならないよう、テーマ側で `.tree-toolbar` の背景を上書きする（例: SakuraFlow・AromaHop は、ツリーの地に近い半透明の色を重ね、`backdrop-filter` でぼかす）。下の境界線は、既定では `--color-border-default` の 1px の線。テーマが帯の `box-shadow` を上書きして別の作りにしてよい（例: SakuraFlow は `#topMenu` と同じく、白い光の筋とやわらかい影）。規則は `manager/media/style/common/shell.css`。
- 本文のタブの行（`tabpane.js` の `.tab-row`。入れ子のタブは外側だけ）は、`#mainPane` の上端に `position: sticky` で貼り付く。上の余白（`--shell-main-padding`）の分だけ上に寄せる。背景は `shell.js` の `copyPageBackground()` が body の背景の計算値を `--shell-page-bg-*` に写したものを `background-attachment: fixed` で塗り、地の模様とつなげる。行の下端には本体の上の枠（`--tab-page-border-top`）と同じ線を `::after` で引き、本体と一緒に流れないようにする。貼り付いているあいだは `shell.js` が `is-stuck` を付け、右上に固定した `#actions` の幅（`--shell-actions-width`。ResizeObserver で測る）の分だけ行の右を空けてタブを折り返す。タブの行と `#mainPane` の間の要素に背景色や `overflow` を付けると、地と色が合わない・貼り付かなくなる。規則は `manager/media/style/common/shell.css`。

- ツリーの幅は `--shell-tree-width`（既定は header.inc.php が設定 `manager_tree_width`、無ければ 260px で出す）。ツリーの欄（サイドバー）と本文の境目のつまみ（`#evoTreeResizer`。header.inc.php が body の直下に出し、shell.js の `initTreeResizer()` が動かす）をドラッグすると、ツリーの上のボタンの並びが切れない幅（テーマで違う。160px より狭くはしない）から画面の幅の半分までで変わり、幅をブラウザの保存領域（`evo.shell.treeWidth`）に覚える。下限よりさらに左へ、下限の半分より左まで動かすと、ツリーを薄くして知らせ、離すか、そのまま待つとツリーを隠す（待つ間は少しずつ薄くなり、1.1 秒で畳む。時間は shell.css の `tree-collapse-wait` の長さで決め、shell.js は `animationend` で畳む）（`mainMenu.hideTreeFrame(true)`。表示し直すと、ドラッグを始める前の幅で出る）。ドラッグは押した位置からの差で幅を変え、4px 動くまでは始めない。ダブルクリックで既定の幅に戻す
- 利用者の操作でツリーを隠す・表示するとき（`hideTreeFrame(true)`・`defaultTreeFrame(true)`）は、`body` に `tree-animating` を付け、列の幅を 0.2 秒で動かす（急に消えたり広がったりして不具合に見えないように）。ページを開いたときの呼び出し（引数なし）には付けない。隠した状態は `display: none` ではなく、列の幅 0 と `visibility: hidden`（列の形は `0 minmax(0, 1fr)`。表示のときと形をそろえないと、幅が途中の値を取れない）。動きを減らす設定（`prefers-reduced-motion`）では動かさない
- ツリーを閉じているとき（`body.tree-hidden`）は、左端に幅 14px の帯（同じ `#evoTreeResizer`）を残し、ツリーの列の幅をその帯の幅にする（本文が帯の下に潜らないように）。帯はボタンとして読ませ（`role="button"`、名前は `lang('show_tree')`）、押すか Enter・Space で前の幅で開き、右へドラッグするとその幅で開く。上のメニューに出していた「ツリーを表示」のボタン（`#tocText`）はやめた
- ツリーの列は、設定で細くできる（本文を広く使うため）。
  - 設定: グローバル設定の「ツリーペインを細くする」（`manager_tree_narrow`。`0` しない・`2` 半分・`3` 3 分の 1、既定は `0`。ユーザー編集画面・プロフィールでも選べる）。header.inc.php が body に `tree-narrow` と、元の幅を割る数 `--shell-tree-narrow` を出す（`0` なら出さず、今までの幅のまま）。ホバーの無い端末（`@media (hover: hover)` に当たらないタッチ端末）では細くしない。
  - 今までの幅の画面: `#mainPane` の中に `data-shell-tree="full"` の要素を置く。ダッシュボードは `welcome.static.php` が、差し替えられるテンプレートとは別に空の要素を出す。ツリーで移動先を選ぶリソースの移動の画面は `move_document.dynamic.php` の form に付ける。投稿画面の親・リンク先の選択や DocManager の移動のタブなど、画面の一部でツリーから選ぶものは細いままで、ホバーで広げて選ぶ。
  - ホバー: ツリーに乗る・キーボードでフォーカスすると、元の幅に戻して本文を右へ押し出す（本文を隠さない。広げるときは 0.3 秒待ち、通り過ぎただけでは動かさない）。ノードを押して画面を移っても、マウスがツリーに乗っているあいだは元の幅のままで、離れると縮む。
  - 境目のつまみ: 乗っただけでは広げない。細く見せているあいだは、当たりをツリーの側（左）へ 24px 広げる（`shell.css` の `--shell-tree-resizer-reach`。見える線は 8px のまま）。そのため細い幅が狭いときは、ツリーの右側（境目から 28px まで）がつまみになり、そこにかかるノードの文字はホバーで広げてから押す。広げているあいだは 8px に戻し、ツリーの右端のスクロールバーやボタンを覆わない。広げている途中でつまみへ移ったときは、つまみが逃げないよう、離れるまで広げたままにする（`shell.js` が body に `tree-resizer-hold` を付ける。広げていたかは、`#mainPane` の `ResizeObserver` が直前の描画で測ったツリーの幅で見る）。
  - 細い幅: 細い状態でつまみを動かすと細い幅を変え、ブラウザーの保存領域（`evo.shell.treeNarrowWidth`）に覚える。下限は 64px で、32px より左まで動かすとツリーを閉じる。元の幅より広くはしない。覚えた細い幅が無ければ、元の幅を設定の数で割った幅。元の幅を細い幅以下まで縮めたら（ドラッグでも、ダブルクリックで既定に戻したときでも）、細い幅を忘れる。ドラッグ中は body に `is-resizing-narrow` を付け、ツリーを広げない。
  - 自動の拡縮を止める: 細い状態で元の幅まで広げて離すと、そのブラウザーでは止める。ブラウザーの保存領域（`evo.shell.treeNarrowOff`）に止めたときの設定の数を覚え、body に `tree-narrow-off` を付ける（細くする規則はこのクラスのあいだ効かない）。読み込んだときに設定の数が違えば（設定を変えた・「しない」にした）、止めた状態を解く。止めているあいだは、境目にツールチップ（`lang('tree_narrow_restore_hint')`）で戻し方を出し、境目のダブルクリックで自動に戻す。止める・戻すときは、覚えた細い幅を忘れる。
  - ピン: ツリーの上のボタンの並びの右（「ツリーを隠す」の左）に、自動の拡縮を止める・戻すボタン `#treePin`（アイコンは `style('pin_tree')`、`common/images/icons/pin.svg`）を出す。細くする設定のときだけ `manager/frames/tree.php` が出し（判定は `manager_tree_narrow()`。header.inc.php と同じ）、ホバーの無い端末では隠す。押すと止め、もう一度押すと戻す（`EvoShell.toggleTreePin()`）。境目の操作と同じ状態を使い、押された見た目（`.treeButton.is-active`・`aria-pressed`）は `EvoShell.syncTreePin()` が `tree-narrow-off` に合わせる（ツリーを読み直したときは tree.php の `<script>` が呼ぶ）。
  - ダブルクリック: 止めているときは自動に戻す。それ以外は、細い状態なら細い幅を、広げているときは元の幅を既定に戻す。
  - 受け持ち: 細くするかどうかと広げるかどうかは `shell.css` の `:has()` で決める。JS（`shell.js`）は、幅の記憶と、body のクラス `tree-resizer-hold`・`tree-narrow-off`・`is-resizing-narrow` の付け外しを受け持つ。

## 画面の差し替えと入力の保護

`manager/media/script/shell.js` の `request()` が、画面を差し替える通信（GET・POST・モーダル）の入口。書きかけの入力を失わないよう、次のように決めている。

- 未保存の印（`window.documentDirty`）は、応答で画面を実際に差し替えるとき（`applyFragment()`・`replaceDocument()`）にだけ消す。送信を始めた時点では消さない。モーダルのフォームは背後の印に触らない。
- 「画面を変えてよいか」の確認は `confirmIfDirty()` の 1 か所。本体の画面を GET で移る入口（リンク・`navigate()`・GET フォーム・戻る／進む）は、これを通る。
- モーダルを閉じたときの背後の取り直しは、背後が未保存なら行わない（書きかけが消えるため）。モーダルで保存した内容は、背後を保存し直すか開き直すと反映される。
- POST の通信失敗・セッション切れ（`X-Evo-Login`）・CSRF トークンの不一致（`text/plain` の 403）では、画面を変えず入力を残し、EvoDialog で知らせ、未保存の印を立てる。文は言語キー `shell_network_error`・`shell_session_expired`・`shell_save_rejected` で、`header.inc.php` が `EvoShell` に渡す。CSRF のトークンはセッションごとなので、別のタブでログインし直すと、元の画面は保存できなくなる（入力をコピーして開き直す）。5xx と HTML のエラー応答は、これまでどおり応答で画面を置き換える（PHP の致命的なエラー画面とセーフモードの案内のボタンを見せるため。この場合、入力は残らない）。
- `request()` は、本体（`#mainPane`）とモーダルを別に数えた番号を付け、最後に始めた要求の応答だけで画面を差し替える。古い応答は捨てる。POST は中断しないが、その後に別の画面を始めていれば、結果の画面で上書きしない（サーバーの処理は終わっている。結果の画面と、その中の知らせは出ない。ツリー・メニューの `refresh` だけ行う）。モーダルを閉じたときも番号を進め、遅れて届いた応答でモーダルを開き直さない。モーダルの保存の応答が閉じたあとに届いたときは、背後が未保存でなく、その間に本体で別の画面を始めていなければ背後を取り直し、別のモーダルが開いていれば、それを閉じるときの取り直しに任せる。
- 番号の対象外: ダウンロード（`Content-Disposition: attachment` の応答は番号によらず保存するが、番号は進めるので、その前に始めた遷移は捨てられる）・アップロード・ツリーとメニューの部分更新（`reloadPartial()`）。
- 「処理中」の表示は、始めた `request()` の数が 0 に戻るまで出す。

## body 直下の要素

シェルの body（`body.evo-shell`）はグリッドで、メニュー・ツリー・本文の 3 つを並べる。ほかの body 直下の要素は、ライブラリが足すオーバーレイなどでレイアウトが崩れないよう、本文の領域に重ねた幅 0・高さ 0 の要素になる（`manager/media/style/common/shell.css` の `body.evo-shell > :not(...)`）。

- 画面の部品は `#mainPane` の中に置く。body 直下に要素を足すと、幅 0 になって見えない（`position: fixed` でも、幅を決めていなければ潰れる）。
- 例外は、この規則の `:not(...)` に並べたものだけ（`#preLoader`・`#evoShellModalOverlay`・ダイアログの `.evo-dialog`・ツールチップの `#evoTooltip`・ツリーの幅を変えるつまみの `#evoTreeResizer`・権限の無い人へのエラーの案内 `.evo-error-guide`・知らせ（トースト）の `#evoNotice`）。`.evo-error-guide` は、#mainPane より前の失敗では body 直下に出るので、本文の領域に置く。`#evoNotice` は、#mainPane を差し替えても残すため body 直下に置く（`manager-notice.md`）。ダイアログは自前で作らず `EvoDialog` を使う（`manager-dialog.md`）。body 直下に置くしかない部品を本体に足すときは、この規則に足して理由をコメントに書く。

## JS・CSS の読み込みと版

管理画面の静的な JS・CSS は、読み込みの URL に必ず版（ファイルの更新日時）を付ける。付けないと、アップグレード後にブラウザが古い JS・CSS をキャッシュから使い続け、画面が壊れる（#1358）。付け方は `manager/includes/helpers.php` のヘルパーにそろえる。

- 版を計算するのは `asset_version($absPath)`（更新日時。読めないときは本体の版）だけ。渡すパスは `MODX_MANAGER_PATH . '…'` か `MODX_BASE_PATH . '…'` で書く（`__DIR__` 起点にしない）。
- URL を作るヘルパーは 2 つ。管理画面の画面から `manager/` からの相対で読むときは `manager_asset('media/script/tree.js')`（`?版` を付けて返す）。URL をすでに持っている所（ManagerManager の `includeJs()` / `includeCss()`、QuickManager、TinyMCE 7 の設定 CSS など）は `site_asset($url)`（サイト内の URL にだけ版を付け、外部の URL・`//` で始まる URL・`?` や `#` を含む URL はそのまま返す）。
- `.tpl`（PHP を使えない）: パスはそのまま見える形で書き、版だけを `?[+<ファイル名>_<拡張子>_ver+]` のプレースホルダーにする（ハイフンは `_`。例: `login.js?[+login_js_ver+]`）。値は、その tpl を置き換える PHP 側で `asset_version()` で作って渡す（例: `manager/includes/accesscontrol-not-mgr.inc.php`、`assets/modules/docmanager/classes/docmanager.class.php`）。置き換える所は、プレースホルダー名で grep して見つける。例外は 2 つ。テーマの `style.css` はテーマ名もプレースホルダーなので `theme_css_ver`、QuickManager の編集画面は `style.css` が 2 つ並ぶので自分の分を `qm_css_ver`。
- 対象外: DocumentParser の `regClientScript()` / `regClientCSS()`（サイト側の汎用の入口）、外部の URL、`assets/plugins/*/tinymce/`、`install/`、TinyMCE 7 の `ScriptFactory`（TinyMCE 本体の `cache_suffix` の `v=` 形式に合わせた作りで、互換を守る対象）。
- 付け忘れは CI の `.github/scripts/asset-version-lint.php`（`php .github/scripts/asset-version-lint.php`）が検出する（`token_get_all()` で PHP の文字列と HTML を読み、`<script src>`・`<link href>` の値が `.js`・`.css` で終わり `?` が無いものを拾う）。`'src="' . $x . '"'` のように連結で URL を組み立てる形は検査できないので、ヘルパーを通すこと。除外はそのスクリプトの先頭の一覧に、理由つきで書く。

## frameset 時代の名前の互換

frameset 時代のコードは、`top.main`・`parent.tree`・`top.mainMenu` のように、ほかのフレームの window を名前で指していた。シェルは 1 つのページなので、同じ名前を自分の window に用意して、そのまま動くようにしている。

| 名前 | 中身 | 用意している場所 |
| --- | --- | --- |
| `window.main` | 自分の window（`window.main = window`） | `manager/actions/header.inc.php` |
| `window.tree` | ツリーの状態（`ca`）と操作（`saveFolderState`・`restoreTree`・`expandTree`・`collapseTree`・`updateTree`・`reload`・`resizeTree`） | `manager/frames/tree.php` |
| `window.mainMenu` | メニューの操作（`reloadtree`・`reloadmenu`・`hideTreeFrame`・`defaultTreeFrame`・`removeLocks`・`showWin`・`keepMeAlive`・`work`・`stopWork` など） | `manager/frames/menu.php` |

- `target="main"` のリンク・フォームは、`target` の無いものと同じに扱う（`shell.js` の `hasOwnTarget()`）。完全な HTML に差し替わった画面にはシェルが無いので、`header.inc.php` が最上位の窓（iframe を除く）の `window.name` を `main` にしておく。差し替えても同じ窓なので名前は残り、モジュールの `target="main"` はそのタブへ届く（管理画面を経ずに URL を直接開いたタブでは届かず、新しいタブが開く）。
- シェルでは `top`・`parent` も自分の window を指すので、`top.tree` や `parent.mainMenu` と書いたコードも同じものに届く。
- シェルのモーダル（`#evoShellModal`。シェルの中で開くファイルブラウザを含む）は iframe ではなく、断片を同じ window に差し込む。互換の名前はそのまま届く。
- シェルの中に置いた iframe（TinyMCE のダイアログなど）では、iframe 自身の window には `tree`・`mainMenu` が無いが、`parent`・`top` からはシェルのものに届く。
- QuickManager が公開側のページに埋め込んだ編集画面では、`parent`・`top` が公開側のページを指すので、届かない（自分の window にも無い）。
- `window.tree` は、ツリーの状態 `ca` を持つ。ツリーのノードを押したときの動き（開く・移動先にする・親にする・リンク先にする）は `ca` で決まり、移動・親の選択・リンク先の選択の画面が書き換える。画面を移るたびに `shell.js` が `open` に戻す。
- `tree.resizeTree()` は何もしない。ツリーの大きさはシェルの CSS が決める。外部のコードが呼んでも壊れないよう、名前だけ残している。

### 決まり

新しく書く・書き換えるコードに当てはめる。

- 互換の名前を使わない。画面の移動・メニューやツリーの読み直しは `EvoShell` の API（`go`・`navigate`・`reloadTree`・`reloadMenu`・`refresh`・`closeModal`）を使う。
- 例外は `window.tree` だけ。ツリーの状態 `ca` を変える API は `EvoShell` に無いので、`window.tree` を使う。`top.`・`parent.` を付けずに書き、無いとき（`chromeless`・QuickManager の埋め込み）は何もしない（例: `manager/media/style/common/jscripts.tpl` の `enableParentSelection()`）。

互換の名前は、外部のプラグイン・モジュール・テーマのために残す。本体の中には、まだ `top.`・`parent.` の書き方で使っている箇所がある（`manager/frames/nodes.php` のノードの `onclick`、`move_document.dynamic.php`・`move_document.processor.php`、同梱の docmanager など）。これらを書き換えても、互換の名前は消さない。消すときは、外部への影響を Issue で調べてから決める。

## タイトル（`<title>`）

書式「画面名 | サイト名」の正本は `manager_title($screen)`（`manager/includes/helpers.php`。エスケープ前の文字列を返し、出力の直前で `hsc()`）。ログイン画面もこれを使う。管理画面の `header.inc.php` は、書式（画面名の位置に `{screen}`）とサイト名だけのタイトルを `<title>` の `data-format` / `data-site-only` に渡し、`shell.js` の `updateDocumentTitle()` が `#mainPane` の最初の `<h1>` の文字を当てはめる（h1 が無ければサイト名だけ）。見出しと違う名前をタブに出したい画面は、`#mainPane` の中の要素に `data-page-title="..."`（tpl を `parseText()` に通す画面では、値は `hsc_for_tpl()`。タグ解析を通るため）を付ける。空でなければ h1 より優先する（例: 文書の編集画面は、既存の文書なら pagetitle）。全体の読み込み時と、`#mainPane` の差し替え後に呼ぶ。画面に画面名を付けるには、その画面に `<h1>` を置く。

## 残している名残

frameset の名前やしくみが残っているが、理由があって残しているもの。

| 名残 | 残す理由 |
| --- | --- |
| セーフモードの切り替えは `a=1&f=menu` へ POST する（`manager/includes/helpers.php` の `manager_safe_mode_toggle_form()` と、受け手の判定 `manager_is_safe_mode_request()`） | 送り先と受け手の判定がそろっていて動いている。送り先を変えるなら両方を一緒に変える |
| 画面の `body` の `id` は `f` から取り、既定は `mainpane`（`manager/actions/header.inc.php`） | テーマの CSS が `body#mainpane` を参照している |
| ログイン画面とロックアウトの画面は、最上位の画面にフレーム（iframe）があれば、最上位の画面で開き直す（`manager/media/script/login.js`、`manager/media/style/common/manager.lockout.tpl` の `top.frames.length` の判定） | iframe の中（TinyMCE のダイアログ、QuickManager の埋め込みなど）でセッションが切れたとき、ログイン画面を最上位に出すため |
| イベント名 `OnManagerMainFrameHeaderHTMLBlock`・`OnManagerPreFrameLoader`・`OnManagerFrameLoader` | プラグインの API なので改名しない |
| ディレクトリ名 `manager/frames/`、テーマの CSS の `div.treeframebody` | テーマや拡張が参照しうる |
| ロールの権限 `frames` | 本体では確かめていないが、DB の列の既定値が 0 で、外部の拡張が `hasPermission('frames')` を見ている可能性がある（#1265） |

## 文書の操作の設定

`document-actions.js` は header で全画面に 1 回だけ読み、確認と警告の文言・トップページ・エラーページ・メンテナンス中ページの ID（`EvoDocumentActions.config`、`ex_docapi.php` の `actionConfig()`）もページ全体を読んだときに決まる。システム設定でこれらを変えたあと、シェルの遷移だけで読み直さずにいると古い ID で判定されるが、サーバー側（`docDeleteCheck()`）が正しく断るので壊れない（断りの警告が確認のあとに出る）。
