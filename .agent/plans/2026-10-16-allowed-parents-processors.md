# ExecPlan: 許可するリソースの範囲を、文書を変える処理でも確かめる

## Purpose / Big Picture

ユーザーごとの「許可するリソース」（設定名 `allowed_parents`）は、その利用者が編集できる文書を、指定した文書とその子孫に限るための設定である。今は管理画面で文書の画面を開くときにだけ確かめていて、文書を変える処理（保存・移動・複製・削除・完全削除・公開・下書きの操作など）では確かめていない。この計画で、文書を変える処理すべてが同じ判定で範囲を確かめるようにし、設定した利用者が範囲の外の文書を変えられないようにする（Issue #733）。あわせて、ドキュメントグループの確認が無かった完全削除（ごみ箱を空にする）にも、ほかの処理と同じドキュメントグループの確認を足す。また、下書きの保存と公開で親が変わるときに、通常の保存と同じ親の確認（ドキュメントグループ・循環と存在・範囲）をする。

## Progress

- [x] (2026-10-16) M1: 共通の判定関数を `ManagerAPI` に足し、文書の保存（新規・更新、a=5）と移動（a=52）で確かめる。下書きの PR を作る（実装と検証は 2026-10-17 に完了。コミット e8e9c2a2e と、自己レビューの指摘への対応 bd0206f5d。PR #748（下書き）を作成）
- [x] (2026-10-17) M2: 複製（a=94）・削除（a=6）・削除の取り消し（a=63）・公開（a=61）・非公開（a=62）・完全削除（a=64）で確かめる。完全削除にはドキュメントグループの確認も足す（コミット 609180bdc・1c60ac516。検証は PASS: 範囲の外の複製・削除・削除の取り消し・公開・非公開・完全削除は止まり、範囲の中は通る。ごみ箱を空にするときは 1 件でも通らなければ全体を止める。`use_udperms=1` の完全削除のドキュメントグループの確認も止まる。設定なしの利用者と管理者は変わらない）
- [x] (2026-10-17) M3: 下書きの保存（a=128）・公開（a=129）・削除（a=130）・公開予約の取り消し（a=134）で確かめる。下書きの保存と公開で親が変わるときは、新しい親について、通常の保存と同じ確認（ドキュメントグループ・循環と存在・範囲）もする。PR を `Closes #733` に改め、下書きを解除する（コミット 185cc3530・ee31a20eb。検証は PASS: 範囲の外の下書きの保存・公開（今すぐ・予約）・削除・公開予約の取り消しは止まり、範囲の中は通る。下書きで親を変えるときは、循環・存在・ドキュメントグループ・範囲を確かめて止まる。設定なしの利用者と管理者は、親を変えない操作では変わらない）

## Surprises & Discoveries

- 2026-10-16: 画面の側の判定 `manager()->isAllowed()` が使う一覧 `user_allowed_docs`（`getUserAllowedDocs()`）は、`evo()->getChildIds()` で作っている。`getChildIds()` は削除済みの文書を含めず（`deleted=0`）、既定で 10 階層までしかたどらず、子を「エイリアスのパス」をキーにした配列に入れる。そのため、削除済みの文書・深い階層の文書・パスが重なる文書は、範囲の中にあっても一覧に入らないことがある。処理の側でこの一覧を使うと、削除の取り消しが範囲の中の文書でも通らなくなる。根拠: `manager/includes/document.parser.class.inc.php` の `getChildIds()`（3941 行付近）。
- 2026-10-16: `isAllowed($id)` は、`$id` が空のときリクエストの `pid` を読む。処理の側から「ルート（0）を確かめたい」と渡しても、`pid` の有無で結果が変わる。根拠: `manager/includes/extenders/ex_managerapi.php` 802 行付近。
- 2026-10-16: 下書きには親（`parent`）も保存され、下書きの公開で文書に反映される（`ex_revision.php` の `convertData()` と、`publish_draft_content.processor.php` の `publishDraft()` → `evo()->doc->update()`）。下書きの保存と公開は、親を変える操作にもなりうる。
- 2026-10-16: 保存の処理（`save_resource.processor.php` の `checkDocPermission()`）のドキュメントグループの確認は、更新のとき `use_udperms` が 1 でなければ途中で戻る。許可するリソースは `use_udperms` と関係なく効く設定なので、同じ関数の中には入れない。
- 2026-10-16: `unpublish_draft_content.processor.php` の権限が無いときの止め方が、`$e->setError(3)` を使っている。`manager/index.php` 108 行で `$e = new errorHandler` がグローバルに定義されているため、修正前も同じ文言「この操作を行なう権限がありません。」の権限エラーで止まる。M3 で同じファイルを触るので、ほかの処理と同じ `alert()->setError(3)` にそろえる（動きを直すためではなく、書き方の整合のため）。
- 2026-10-16: 完全削除（a=64、`remove_content.processor.php`）が今確かめているのは、`empty_trash` の権限だけである。ドキュメントグループの確認（`evo()->checkPermissions()`）も、許可するリソースの確認もしていない。権限が無いときは `alert()->setError(3)` と `alert()->dumpError()` で止める（`dumpError()` はエラー画面を出して `exit` する。根拠: `manager/includes/error.class.inc.php` 59 行付近）。
- 2026-10-16: 完全削除の対象の文書の取り方は 2 とおりある。リクエストに `id` があれば、その 1 件（`request_intvar('id')`。整数として読めなければ `alert()->setError(2)` で止める）。ツリーの右クリックメニューの「完全に削除」がこの形で呼ぶ（`manager/frames/tree.php` 493 行付近）。`id` が無ければ、サイト全体の `deleted=1` の文書すべて（だれが削除したか・どこにあるかを問わない）。ツリーの「ごみ箱を空にする」がこの形で呼ぶ（同 202 行付近）。どちらも ID の配列 `$ids` にまとめてから、イベント `OnBeforeEmptyTrash` に参照で渡し（プラグインが書き換えられる）、そのあと `deleted=1` の条件付きで、ドキュメントグループの紐づけ・TV の値・文書そのものを消す。削除済みでない文書の ID が来ても、消す SQL の `deleted=1` の条件で何も消えない。
- 2026-10-16: `evo()->checkPermissions()` は、文書が削除済みかどうかを条件にしていない（`site_content` と `document_groups` を ID で引くだけ）。削除済みの文書にもそのまま使える。`use_udperms` が 1 でないときや、`save_role` の権限があるときは true を返すので、呼ぶ側で `use_udperms` の条件を付けなくても、今までの利用者の動きは変わらない。ルート（0）を渡すと、`save_role` があるか `udperms_allowroot` が 1 なら true、`use_udperms` が 1 でなければ true、それ以外は false になる。
- 2026-10-16: 通常の保存（a=5）で、更新のときに親を変えた場合の新しい親の確認は、2 つの関数に分かれている。(1) `checkDocPermission()` の中で、`use_udperms` が 1 で、DB の今の親と `validated('parent')` が違うときだけ、`evo()->checkPermissions(validated('parent'))` を確かめ、外なら `saveFormValues()` と `webAlertAndQuit(lang('access_permission_parent_denied'), 'index.php?a=27&id=…')`。(2) `checkParent()` で、`manager()->invalidParentMessage([文書の ID], validated('parent'))`（自分自身・子孫・存在しない文書を親にしていないか）を確かめ、外なら入力を戻り先のアクションで保存して `webAlertAndQuit($message, 戻り先)`。この 2 つはドキュメントグループの確認 → 循環と存在の確認の順で、親が変わらないときも (2) は走る（今の親はふつう妥当なので結果は変わらない）。どちらも止め方が保存のフォームに戻る作り（入力の保存と戻り先の URL）で、ほかの処理からは呼べない。移動（a=52）は同じ 2 つの関数を、循環と存在 → 文書のドキュメントグループ → 親のドキュメントグループ（`use_udperms` が 1 で、親が変わるときだけ）の順に、ファイルの中で直接呼んでいる。根拠: `manager/processors/document/save_resource.processor.php` の `checkDocPermission()`（340 行付近）と `checkParent()`（320 行付近）、`move_document.processor.php` 38〜68 行付近。
- 2026-10-16: 通常の保存では、新規でルートに作るときだけ `isAllowroot()`（`use_udperms` と関係なく `udperms_allowroot` を見る）で止め、更新で親をルートに変えるときは (1) の `checkPermissions(0)`（`use_udperms` が 1 のときだけ）で止める。今の動きの不ぞろいで、この計画では変えない。
- 2026-10-16: 下書きの値は `ex_revision.php` の `convertData()` で作られ、`parent` は送られてこなければ 0 になる。下書きの編集の画面は `parent` を必ず送る（`manager/actions/document/mutate_content/field_parent_form.tpl` の hidden の入力）。`getDraft()` は下書きが無ければ空の配列を返す。
- 2026-10-16: 公開予約した下書き（`status='standby'`）は、予約の時刻を過ぎた最初のサイトへの要求で `updateDraft()`（`manager/includes/traits/document.parser.subparser.trait.php` 2772 行付近）が、利用者の確認なしに文書へ反映する（親も含む）。下書きを予約の状態にできるのは、下書きの保存（a=128 の `stay=save_standby`）と下書きの公開（a=129 の `publishoption=reserve`）だけなので、この 2 つで確かめれば、予約の反映で範囲の外へ移ることは防げる。
- 2026-10-16: `alert()->setError($code, $message)` は第 2 引数があればその文言を出す（無ければ番号に対応する定型の文言）。`dumpError()` が出すのは文言だけで、番号は画面に出ない。根拠: `manager/includes/error.class.inc.php` の `setError()`。
- 2026-10-17: 設定値の読み方。`(int)` で読むと、全角の読点・空白の入った値（例: 「5、6」）が先頭の 5 だけの範囲になり、全角の数字や数字以外だけの値は空の一覧になる。空の一覧を「制限なし」と扱うと、設定はあるのに処理の側だけ制限が効かなくなる。画面の側（`isAllowed()`）は設定値そのものが空かで判断するので、基準が食い違っていた。
- 2026-10-17: ごみ箱を空にするとき（a=64）は、対象の件数に比例して確認の問い合わせが増える。今の規模では問題にしない。

## Decision Log

- 2026-10-16（planner）: 判定は `isAllowed()` を変えずに、新しい関数 `manager()->isInAllowedParents($id)` を足して行う。判断の基準は「画面の側の動きを変えずに済むか」と「処理の側の要件（ID を明示して渡す・リクエストを読まない・削除済みの文書も判定できる）を満たせるか」。`isAllowed()` を変えると、画面の側（文書の画面・子の一覧・ツリー）の動きも変わり、作業中の #717（`mutate_content/functions.php`）とも重なる。代替案: `isAllowed()` に引数を足して `pid` を読まない形を選べるようにする案は、1 つの関数に 2 つの判定のしかたが混ざるので採らない。
- 2026-10-16（planner）: 判定は「その文書自身か、祖先のどれかが、許可するリソースに書いた ID か」で行う。祖先は既存の `manager()->getAncestorIds()`（`site_content.parent` をたどる。削除済みも含む。循環しても止まる）で求める。`user_allowed_docs` は使わない（Surprises の 1 つめ）。代替案: `user_allowed_docs` を使えば画面の側と完全に同じ判定になるが、削除の取り消しが範囲の中でも通らない。2026-10-16 確定（メインの判断）: 祖先をたどる形で進める。
- 2026-10-16（planner）: 関数は 1 つの ID を受け取り、真偽値を返す形にする（`evo()->checkPermissions($id)` と同じ形）。止め方は処理ごとに、すぐ隣にあるドキュメントグループの確認（#637）にそろえる（文言は下の 2026-10-16 確定（メインの判断）の決まりによる）。文書と親で文言が違う（`access_permission_denied` と `access_permission_parent_denied`）ので、呼ぶ側が文書と親を別々に呼ぶ。代替案: 「対象の文書の ID の配列」と「親の ID」を 1 回で受け取る形は、どちらで止まったかを返す必要が出て、呼ぶ側が文言を選べなくなるので採らない。
- 2026-10-16（planner）: ルート（0）は範囲の外として扱う。許可するリソースを設定した利用者は、ルートにも、範囲の外のどの文書の下にも、文書を作ったり移したりできない。`udperms_allowroot`（ルートに作ってよいか）は、ドキュメントグループの側の設定なので、この判定には使わない。範囲の頂点の文書（許可するリソースに書いた ID の文書そのもの）は、更新・削除・公開などはできるが、複製は複製の置き場所（その文書の親）が範囲の外なので止まる。2026-10-16 確定（本人の答え）: 範囲の外の親（ルートを含む）への作成・移動・複製は、すべて止める。
- 2026-10-16（planner）: 更新で親が変わらないときは、親を確かめない（範囲の頂点の文書の親は範囲の外なので、確かめると頂点の文書を保存できなくなる）。ドキュメントグループの確認（`checkDocPermission()` の「親が同じなら戻る」）と同じ考え方。
- 2026-10-16（planner）: 管理者でも、許可するリソースを設定していれば止める。`isAllowed()` と同じで、役割（ロール）による例外は作らない。設定が空なら、だれでも今までどおり。
- 2026-10-16（planner）: 許可するリソースの設定値を数の配列に直す処理は、`getUserAllowedDocs()` から private の関数 `getAllowedParentIds()` に切り出し、新しい関数と `getUserAllowedDocs()` の両方で使う（読み方を 1 か所にする）。切り出しで `getUserAllowedDocs()` の結果は変えない（空の要素を落とすことと、値の型以外は同じ。値の型の違いは `in_array()` のゆるい比較なので結果に影響しない）。
- 2026-10-16（本人の答え）: 完全削除（ごみ箱を空にする、a=64、`remove_content.processor.php`）も、この計画に含めて M2 で直す。対象の文書それぞれについて、ドキュメントグループの確認（`evo()->checkPermissions()`）と `isInAllowedParents()` の確認を足す。
- 2026-10-16（planner）: 完全削除の確認は、`$ids` を作り終えたあと、イベント `OnBeforeEmptyTrash` の前に置く（ほかの処理と同じく、イベントの前に確かめる）。止め方は、同じファイルの冒頭の権限の確認と同じく `alert()->setError(3, …)` と `alert()->dumpError()` にし、文言は `lang('access_permission_denied')` を渡す（ほかの処理も、ファイルの中の止め方にそろえている。文言は下の 2026-10-16 確定（メインの判断）による）。`checkPermissions()` は `use_udperms` が 1 でなければ true を返すので、`use_udperms` の条件で囲まない。
- 2026-10-16 確定（本人の答え「全体を止める」）: 「ごみ箱を空にする」（`id` なし）で、対象の中に 1 件でも確認を通らない文書があれば、何も消さずに全体を止める。複数の文書をまとめて移す移動（a=52）が、1 件でも通らなければ全体を止めるのと同じ考え方。代替案: 通らない文書を飛ばして残りだけ消す案は、「ごみ箱を空にした」のに文書が残り、なぜ残ったかが利用者に分からないので採らない。この結果、範囲の外や見られないドキュメントグループの削除済みの文書がごみ箱にあるあいだは、制限のある利用者は「ごみ箱を空にする」を使えず、1 件ずつの完全削除で範囲の中の文書を消すことになる。
- 2026-10-16 確定（メインの判断）: 画面の側の `isAllowed()` を新しい関数に寄せる（判定を 1 つにする）作業は、この計画では行わない。#717 のマージ後に別の Issue で扱う。
- 2026-10-16 確定（メインの判断）: 新しく足す確認で止めるときの文言は、ほかの文書の処理にそろえて、文書のときは `lang('access_permission_denied')`、親（作成先・移動先・下書きの新しい親）のときは `lang('access_permission_parent_denied')` にする。今 `alert()->setError(3)` で止めている処理（保存の新規、完全削除、下書きの 4 つ）では、`alert()->setError(3, lang(…))` のように第 2 引数で渡す（番号だけだと「権限が足りない」という定型の文言になり、文書そのものと置き場所のどちらで止まったかが分からない）。完全削除も `alert()->setError(3, lang('access_permission_denied'))`。同じファイルにある今の確認（下書きの処理の文書のドキュメントグループの確認など）の文言は変えない。
- 2026-10-16 確定（本人の答え「(A) #733 の M3 に含める」）: 下書きの保存（a=128）と下書きの公開（a=129）で、下書きの親が DB の今の親と違うとき、新しい親について、通常の保存と同じ 3 つの確認（ドキュメントグループの確認 `evo()->checkPermissions()`、循環と存在の確認 `invalidParentMessage()`、範囲の確認 `isInAllowedParents()`）をする。下書きの公開は親を変える操作になりうるのに（Surprises）、どれも確かめていなかったため。
- 2026-10-16（planner）: 下書きの 2 つの処理の親の確認は、`ManagerAPI` に関数 `parentChangeDeniedMessage(array $docIds, $parentId)` を足して 1 か所にまとめる（`invalidParentMessage()` の後ろに置く）。中身は、`invalidParentMessage()` が空でなければその文言、`evo()->checkPermissions($parentId)` が false なら `lang('access_permission_parent_denied')`、M1 の `isInAllowedParents($parentId)` が false なら同じ文言、どれも通れば空文字を返す。範囲の判定は M1 の関数をそのまま呼び、規則を二重に持たない。順番は、存在しない親で「権限が無い」と出ないよう、循環と存在の確認を先にする（移動と同じ）。判断の基準は「2 つのファイルに同じ 3 つの確認を書かずに済むか」と「通常の保存・移動の今の止め方を変えずに済むか」。代替案 1: 下書きの 2 つのファイルにそれぞれ 3 つの確認を直接書く案は、同じ数行が 2 か所に重なるので採らない。代替案 2: 通常の保存（a=5）と移動（a=52）もこの関数に寄せる案は、保存の確認がフォームに戻る作り（入力の保存と戻り先の URL）で 2 つの関数に分かれていること、移動は親が変わらないときに専用の文言（`move_resource_new_parent`）を出すこと（Surprises）から、止め方・文言・確認の順が変わる。この計画では採らず、保存と移動は M1 のとおり、今の確認に `isInAllowedParents()` を足して直接呼ぶ。寄せるかどうかは未決で、メインの判断を待つ（決まるまでは M1 のとおり）。
- 2026-10-16（planner）: 公開予約した下書きが予約の時刻に反映されるとき（`updateDraft()`）は確かめない。予約の状態にできるのは a=128 と a=129 だけで、そこで確かめる（Surprises）。
- 2026-10-17（M1 の自己レビューへの対応）: 設定値は、区切った値のうち書式 `^[1-9][0-9]*$` に当たるものだけを採る（0 や「5abc」・全角の数字は採らない）。`isInAllowedParents()` の「制限があるか」は、画面の側と同じ `evo()->conf_var('allowed_parents')` が空かで判断し、設定があるのに正しい ID が 1 つも無いときは false（範囲の外）にする。判断の基準は「画面と処理で制限の有無の基準が同じか」と「読めない設定のとき制限が外れる方向に倒れないか」。代替案: 読めない値は読み飛ばして残りだけで判断する案は、全部読めないとき空になって制限が外れるので、空のときの扱いを上記にそろえる形で採った。画面の側 `getUserAllowedDocs()` も同じ読み方になり、修正前（設定値を分割して `getChildIds()` にそのまま渡していた）と変わるのは、0 や数字以外を含む値。たとえば「0,5」は、修正前は `getChildIds(0)` が全文書を返して実質制限なしになっていたが、修正後は 5 とその子孫だけが範囲になる。「5、6」「5abc」のような値は、修正前は正しく読めていなかった（結果は不定）ので、修正後は範囲に入れない。この変更は PR 本文の「変わること」に含め、リリース記事の候補とする。
- 2026-10-17（M1 の自己レビューへの対応）: 保存の処理の `checkAllowedParents()` は `checkParent()` の後ろで呼ぶ。存在しない親を指定したとき「親が見つからない」の文言が先に出る（M3 の Decision Log の、循環と存在の確認を先にする考え方と同じ）。
- 2026-10-17（M1 の自己レビューへの対応）: `isInAllowedParents()` の docblock の「存在しない ID は範囲の外」を、「設定に書いた ID そのものでなければ範囲の外。存在の確認は呼ぶ側で行う」に直した。関数は DB で存在を確かめず、祖先をたどれない ID は設定に書かれた ID と一致するかだけで判定するため。

## Outcomes & Retrospective

## Context and Orientation

用語:

- 許可するリソース（`allowed_parents`）: 管理画面のユーザー編集の「許可するリソース」。文書の ID をカンマ区切りで書く。書いた文書とその子孫だけを編集できる、という意味の設定。ユーザーごとの設定（`modx_user_settings` の `setting_name='allowed_parents'`）で、ログイン中の利用者の分が要求ごとに `evo()->config` に読み込まれる。
- 範囲: 許可するリソースに書いた文書と、その子孫の全体。
- 処理（processor）: 管理画面のフォームやリンクから呼ばれ、DB を変える PHP。`manager/index.php` が action 番号（`a=`）で振り分ける。
- ドキュメントグループの確認: `evo()->checkPermissions($id)`（`manager/includes/traits/document.parser.subparser.trait.php` 1095 行付近）。`use_udperms` が 1 のときに、文書が利用者のドキュメントグループで見られるかを確かめる。#637（PR #698、マージ済み）で、保存のときにも確かめるようにした。

関係するコード:

- `manager/includes/extenders/ex_managerapi.php`: `getAncestorIds()`（751 行付近）、`invalidParentMessage()`（779 行付近。処理の側で親を確かめる関数の前例）、`isAllowed()`（802 行付近）、`isContainAllowed()`、`getUserAllowedDocs()`（848 行付近）。
- 文書を変える処理（`manager/processors/document/`）と、今あるドキュメントグループの確認・止め方:

  | a | ファイル | 確かめる ID | 今の止め方（ドキュメントグループ） |
  |---|---|---|---|
  | 5 | `save_resource.processor.php` | 更新: 文書、親を変えるなら新しい親。新規: 作成先の親 | 更新の文書: `saveFormValues()` と `webAlertAndQuit(lang('access_permission_denied'), 'index.php?a=2')`。更新の親: `webAlertAndQuit(lang('access_permission_parent_denied'), 'index.php?a=27&id=…')`。新規の親: `alert()->setError(3)` |
  | 52 | `move_document.processor.php` | 移す文書それぞれと移動先の親 | `accessDenied('access_permission_denied')` / `accessDenied('access_permission_parent_denied')`（ファイル内の関数。ヘッダー付きのエラーページ） |
  | 94 | `duplicate_content.processor.php` | 複製元と、複製の置き場所（複製元の親） | ヘッダー付きのエラーページ（ファイル内に直接書いている） |
  | 6 | `delete_content.processor.php` | 文書（子孫も消えるが、範囲の中の文書の子孫は必ず範囲の中） | `$warning` を立てて `webAlertAndQuit($warning, 'javascript:history.back();')` |
  | 63 | `undelete_content.processor.php` | 文書 | `disp_access_permission_denied()`（ファイル内の関数） |
  | 61 | `publish_content.processor.php` | 文書 | ヘッダー付きのエラーページ（直接書いている） |
  | 62 | `unpublish_content.processor.php` | 文書 | 同上 |
  | 64 | `remove_content.processor.php` | 消す文書それぞれ（`id` があればその 1 件、無ければごみ箱の全件） | なし（`empty_trash` の権限だけ。権限が無いときは `alert()->setError(3)`） |
  | 128 | `save_draft_content.processor.php` | 文書、下書きの親が今の親と違うならその親（親はドキュメントグループ・循環と存在・範囲） | 文書: `alert()->setError(3)`。新しい親: なし |
  | 129 | `publish_draft_content.processor.php` | 文書、下書きの親が今の親と違うならその親（親はドキュメントグループ・循環と存在・範囲） | 文書: `alert()->setError(3)`。新しい親: なし |
  | 130 | `delete_draft_content.processor.php` | 文書 | `alert()->setError(3)` |
  | 134 | `unpublish_draft_content.processor.php` | 文書 | `alert()->setError(3)` |

- 移動は a=52 が唯一の入口（移動の画面 a=51 から呼ばれる）。
- 画面の側: `manager/actions/document/mutate_content/functions.php` の `checkPermissions()`（#717 で作業中。この計画では触らない）、`document_data.static.php`、`resources_list.static.php`、`manager/frames/nodes.php`。
- テストのフレームワークは無い。確認は検証環境（`.claude/skills/adaptive-validation-runner/`）で行う。

DocumentParser への影響: 管理画面の処理だけを変えるので、`executeParser()`・`prepareResponse()`・`parseDocumentSource()`・`postProcess()` のどれにも影響しない。

キャッシュへの影響: 確認は DB を変える前に行い、止めたときは何も書かない。キャッシュの消し方は変えない。

## Plan of Work

共通の関数（M1 で足す）。`manager/includes/extenders/ex_managerapi.php` の `isContainAllowed()` の後ろに置く。

    /**
     * 文書が、ログイン中の利用者の「許可するリソース」（allowed_parents）の範囲の中かを返す。
     * 文書を変える処理から、確かめる文書や作成先・移動先の親の ID を明示して呼ぶ。リクエストの値は読まない。
     * 設定が空なら常に true（画面の側の isAllowed() と同じ基準）。設定があるのに正しい ID が 1 つも無いときは false。
     * ルート（0）は、設定があれば範囲の外（false）。存在しない ID は、設定に書いた ID そのものでなければ範囲の外。
     * 存在の確認は呼ぶ側で行う。
     * 削除済みの文書も、親をたどって判定する。
     *
     * @param int $id 文書の ID（作成先・移動先の親を確かめるときは親の ID）
     * @return bool
     */
    public function isInAllowedParents($id)

中身の流れ: `getAllowedParentIds()` が空なら true → `$id = (int)$id`、0 以下なら false → `$id` が一覧にあれば true → `getAncestorIds($id)` と一覧に共通の ID があれば true → それ以外は false。`getAllowedParentIds()`（private）は、`getUserAllowedDocs()` と同じ規則（空白と `|` を区切りとみなす）で設定値を分け、正の整数だけを返す。`getUserAllowedDocs()` もこれを使うように直す。

親を変えるときの確認をまとめる関数（M3 で足す）。同じファイルの `invalidParentMessage()` の後ろに置く。

    /**
     * $docIds の親を $parentId に変えられないとき、その理由を表示用の文言で返す。変えられるなら空文字。
     * 循環と存在（invalidParentMessage()）、ドキュメントグループ（evo()->checkPermissions()）、
     * 許可するリソースの範囲（isInAllowedParents()）の順に確かめる。
     * 親が今の親から変わるときだけ呼ぶ（範囲の頂点の文書の今の親は範囲の外のため）。
     *
     * @param int[] $docIds 親を変える文書
     * @param int $parentId 新しい親の ID
     * @return string
     */
    public function parentChangeDeniedMessage(array $docIds, $parentId)

中身の流れ: `$parentId = (int)$parentId` → `invalidParentMessage($docIds, $parentId)` が空でなければそれを返す → `evo()->checkPermissions($parentId)` が false なら `lang('access_permission_parent_denied')` → `isInAllowedParents($parentId)` が false なら同じ文言 → 空文字。ルート（0）は、`invalidParentMessage()` では通り、`checkPermissions(0)` と `isInAllowedParents(0)` の結果で決まる（通常の保存の更新でルートへ移すときと同じ）。この関数は読み取りだけで、止め方は呼ぶ側が決める。

処理の側の置き方。どの処理も、すぐ隣のドキュメントグループの確認の直後に置き、止め方はそれにそろえる。文言は、文書なら `lang('access_permission_denied')`、親なら `lang('access_permission_parent_denied')`（Decision Log）。ドキュメントグループの確認は `use_udperms` の条件の中にあることが多いが、この確認は条件の外に置く。

- 保存（a=5）: `save_resource.processor.php` に関数 `checkAllowedParents()` を足し、`checkParent()` の直後で呼ぶ（存在しない親を指定したときに「親が見つからない」の文言が先に出るよう、循環と存在の確認の後ろに置く）。新規なら `validated('parent')` を確かめ、外なら `alert()->setError(3, lang('access_permission_parent_denied'))` と `alert()->dumpError()`。更新なら `validated('id')` を確かめ、外なら `saveFormValues()` と `webAlertAndQuit(lang('access_permission_denied'), 'index.php?a=2')`。更新で DB の今の親と `validated('parent')` が違えば、新しい親を確かめ、外なら `saveFormValues()` と `webAlertAndQuit(lang('access_permission_parent_denied'), 'index.php?a=27&id=…')`。
- 移動（a=52）: ドキュメントグループの確認の `if` ブロックの後ろで、`$doc_ids` それぞれを確かめて外なら `accessDenied('access_permission_denied')`、続けて `$new_parent` を確かめて外なら `accessDenied('access_permission_parent_denied')`。
- 複製（a=94）: ドキュメントグループの確認の後ろで、`$id` と、その親（`site_content.parent` を `(int)` で読む）を確かめ、どちらかが外なら同じヘッダー付きのエラーページで止める。
- 削除（a=6）: `elseif` の連なりで、ドキュメントグループの確認の次に `elseif (!manager()->isInAllowedParents($id))` を足し、`$warning = lang('access_permission_denied')`。
- 削除の取り消し（a=63）: ドキュメントグループの確認の後ろで、外なら `disp_access_permission_denied()`。
- 公開・非公開（a=61・62）: ドキュメントグループの確認の後ろで、外なら同じヘッダー付きのエラーページで止める（ファイルに書かれた 7 行のブロックをもう 1 つ書くのではなく、2 つの条件を `||` でまとめて 1 つの `if` にする）。複製も同じようにまとめる。
- 完全削除（a=64）: `$ids` を作り終えたあと（`id` ありの 1 件・ごみ箱の全件のどちらでも）、イベント `OnBeforeEmptyTrash` の前で、`$ids` それぞれについて `evo()->checkPermissions($docid)` と `manager()->isInAllowedParents($docid)` を確かめる。どちらかが通らない文書が 1 件でもあれば、何も消さずに `alert()->setError(3, lang('access_permission_denied'))` と `alert()->dumpError()` で止める。`$ids` が空のとき（ごみ箱が空）は、確かめる文書が無いので今までどおりに進む。
- 下書きの保存（a=128）: 文書のドキュメントグループの確認の直後で文書を確かめ、外なら `alert()->setError(3, lang('access_permission_denied'))` と `dumpError()` と `return`。保存する値 `$fields` を作ったあと、`revision->save()` の前で、`$parent = (int)array_get($fields, 'parent', 0)`（`convertData()` と同じく、無ければ 0）が DB の今の親（`site_content.parent` を `where('id', '=', $id)` で読み、`(int)` にする）と違えば、`$message = manager()->parentChangeDeniedMessage([$id], $parent)` を求め、空でなければ `alert()->setError(3, $message)` と `dumpError()` と `return`。予約の保存（`stay=save_standby`）も同じ経路なので、予約の前に止まる。
- 下書きの公開（a=129）: 文書のドキュメントグループの確認の直後で文書を確かめ、外なら `alert()->setError(3, lang('access_permission_denied'))` と `dumpError()`。`REVISION` を読み込んだあと、今すぐの公開と予約（`publishoption=reserve`）に分かれる前で、`$draft = evo()->revision->getDraft($docid)` が空でなく、`(int)$draft['parent']` が DB の今の親と違えば、`manager()->parentChangeDeniedMessage([$docid], $draft['parent'])` を求め、空でなければ同じく止める。下書きが無いとき（`getDraft()` が空）は親を確かめない（反映される親が無い）。
- 下書きの削除・公開予約の取り消し（a=130・134）: 文書を確かめ、外なら `alert()->setError(3, lang('access_permission_denied'))`。親は変わらないので確かめない。a=134 は、冒頭の `$e->setError(3)` / `$e->dumpError()` を `alert()->setError(3)` / `alert()->dumpError()` に直す。

この計画で行わないこと（非対象）:

- 画面の側（`isAllowed()`、`mutate_content/functions.php`、子の一覧、ツリー）の判定の変更。
- 通常の保存（a=5）と移動（a=52）の親の確認を、`parentChangeDeniedMessage()` に寄せること（Decision Log。未決）。
- 公開予約した下書きが予約の時刻に反映されるとき（`updateDraft()`）の確認（予約するときに確かめる。Decision Log）。
- 通常の保存で、新規のルートへの作成と更新でのルートへの移動の、ルートの扱いの不ぞろい（Surprises）。
- プラグインのイベント（`OnBeforeDocFormSave`・`OnBeforeEmptyTrash` など）で値が書き換えられた場合の確認（確認はイベントの前に行う。今のドキュメントグループの確認と同じ）。
- 止めたときのログの記録（今のドキュメントグループの確認も記録していない）。

## Concrete Steps

作業ディレクトリ: `/home/yamamoto/ghq/github.com/modxcms-jp/evolution-jp/.claude/worktrees/issue-733`（以下、コマンドは `-C` や `cd` でここを明示する）。

### M1: 共通の関数と、保存・移動

編集するファイル:

- `manager/includes/extenders/ex_managerapi.php`（`isInAllowedParents()` と `getAllowedParentIds()` を足し、`getUserAllowedDocs()` を `getAllowedParentIds()` を使うように直す）
- `manager/processors/document/save_resource.processor.php`
- `manager/processors/document/move_document.processor.php`

静的な確認:

    php -l manager/includes/extenders/ex_managerapi.php
    php -l manager/processors/document/save_resource.processor.php
    php -l manager/processors/document/move_document.processor.php

期待: どれも `No syntax errors detected`。

推奨コミット: `fix(document): 文書の保存と移動で、許可するリソースの範囲を確かめる`

M1 のあと、メインが PR を下書きで作る（Artifacts and Notes を参照）。

### M2: 複製・削除・削除の取り消し・公開・非公開・完全削除

編集するファイル:

- `manager/processors/document/duplicate_content.processor.php`
- `manager/processors/document/delete_content.processor.php`
- `manager/processors/document/undelete_content.processor.php`
- `manager/processors/document/publish_content.processor.php`
- `manager/processors/document/unpublish_content.processor.php`
- `manager/processors/document/remove_content.processor.php`

静的な確認: 上の 6 つのファイルに `php -l`。期待は M1 と同じ。

推奨コミット（2 つに分ける）:

- `fix(document): 文書の複製・削除・公開などで、許可するリソースの範囲を確かめる`（a=94・6・63・61・62 の 5 ファイル）
- `fix(document): 完全削除で、ドキュメントグループと許可するリソースの範囲を確かめる`（`remove_content.processor.php`）

### M3: 下書きの 4 つの処理

編集するファイル:

- `manager/includes/extenders/ex_managerapi.php`（`parentChangeDeniedMessage()` を足す）
- `manager/processors/document/save_draft_content.processor.php`
- `manager/processors/document/publish_draft_content.processor.php`
- `manager/processors/document/delete_draft_content.processor.php`
- `manager/processors/document/unpublish_draft_content.processor.php`

静的な確認: 上の 5 つのファイルに `php -l`。期待は M1 と同じ。

推奨コミット（2 つに分ける）:

- `fix(document): 下書きの保存・公開・削除で、許可するリソースの範囲を確かめる`（4 つの処理の、文書の範囲の確認と a=134 の止め方の直し）
- `fix(document): 下書きの保存と公開で親が変わるとき、新しい親を確かめる`（`ex_managerapi.php` の `parentChangeDeniedMessage()` と、a=128・129 の親の確認）

M3 のあと、メインが PR 本文の `Refs #733` を `Closes #733` に改め、CI が通ったら `gh pr ready` で下書きを解除する。

## Validation and Acceptance

### 検証環境の準備（各マイルストーンで共通）

`.claude/skills/adaptive-validation-runner/` の手順で、ほかと重ならない名前とアドレスで立てる。修正前は main の detached ワークツリー（`git worktree add --detach <一時パス> origin/main`）で、別の名前の環境を立てて比べる。

    EVO_VALIDATE_NAME=evo733 EVO_BIND_IP=<空いているアドレス> docker compose -f compose.yml -f .claude/skills/adaptive-validation-runner/scripts/compose.validation.yml up -d --build
    EVO_VALIDATE_NAME=evo733 EVO_BIND_IP=<同じアドレス> NODE_PATH=<work>/pw/node_modules node .claude/skills/adaptive-validation-runner/scripts/setup-site.js --sample --editor

サンプルサイトから、子を持つ文書 A（範囲の頂点にする）、A の子 A1、範囲の外の文書 B、B の子 B1 を選ぶ（`docker exec evo733-db mariadb -uuser -ppassword evodb -e "SELECT id,parent,pagetitle FROM modx_site_content"`）。削除の取り消しの確認用に、A の子 A2 と B の子 B2 を、完全削除の確認用に、A の子 A3 と B の子 B3 を削除済みにしておく（管理者で削除するか、`deleted=1, deletedon=UNIX_TIMESTAMP()` にする）。完全削除は文書が DB から消えるので、ケースごとに検証用の DB を戻すか、削除済みの文書を作り直す。

利用者:

- 制限あり: `editor` に許可するリソース A を設定する。
  `REPLACE INTO modx_user_settings (user,setting_name,setting_value) SELECT id,'allowed_parents','<A>' FROM modx_manager_users WHERE username='editor'`
  ロール 3 に足りない権限（`new_document`・`edit_document`・`save_document`・`move_document`・`delete_document`・`publish_document`・`empty_trash`・`view_unpublished` など、各処理の冒頭で確かめているもの）があれば、検証用の DB で `modx_user_roles` の id=3 の列を 1 にする。
- 設定なし: 同じ `editor` から設定の行を消したもの。
- 管理者: `admin`（設定なし）。

ドキュメントグループの確認が先に止めないよう、主な確認は `use_udperms=0` で行う。保存と移動だけは `use_udperms=1`（文書は公開のまま）でも 1 回ずつ確かめ、範囲の中なら通ることを見る。

各処理は、検証用のスクリプト（作業用ディレクトリに置き、`scripts/lib.js` の `login()` で利用者ごとにログインする。トークンの扱いは `refreshCache()` が見本）から、管理画面の通常の要求と同じ形で呼ぶ。判定は画面の表示ではなく DB で行う。

- 文書の変化: `SELECT id,parent,published,deleted,editedon,pagetitle FROM modx_site_content WHERE id IN (…)` を前後で比べる。新規作成と複製は、`SELECT MAX(id) FROM modx_site_content` と件数で見る。
- 下書きの変化: `SELECT elmid,status,version FROM modx_site_revision WHERE elmid IN (…)` を前後で比べる。

結果は「処理 × 利用者 × 対象（範囲の中・外）× main・ブランチ」の表にまとめ、PR 本文の「確認手順」に写す。

### M1 の完了条件

- 制限ありの利用者で、次はブランチでは止まり DB が変わらない（main では変わる）: B1 の更新、B の下への新規作成、ルートへの新規作成、A1 の親を B に変える更新、A1 を B の下へ移動、B1 を A の下へ移動、A1 と B1 をまとめて A の下へ移動。
- 制限ありの利用者で、次は main とブランチの両方で通る: A1 の更新、A そのものの更新（親を変えない）、A の下への新規作成、A1 を A の下の別の文書の下へ移動。
- 新規作成（B の下・ルート）で止まるときは、エラー画面に「ここにリソースを作成する権限がありません。」が出る（日本語の管理画面のとき。以下の文言も同じ）。
- 設定なしの `editor` と `admin` は、上のすべてが main とブランチで同じ結果になる。
- 画面の側は変わらない: 制限ありの利用者で、ツリーと A の子の一覧（a=120&id=<A>）の表示が main と同じ。

### M2 の完了条件

- 制限ありの利用者で、次はブランチでは止まり DB が変わらない（main では変わる）: B1 の複製、A の複製（置き場所が範囲の外のため）、B1 の削除、B2 の削除の取り消し、B1 の公開、B1 の非公開。
- 制限ありの利用者で、次は main とブランチの両方で通る: A1 の複製、A1 の削除、A2 の削除の取り消し、A1 の公開・非公開、A の公開・非公開。
- 設定なしの `editor` と `admin` は、main とブランチで同じ結果になる。
- 完全削除（`use_udperms=0`、制限ありの利用者）:
  - `a=64&id=<B3>` は、ブランチでは止まり B3 が DB に残る（main では消える）。
  - `a=64&id=<A3>` は、main とブランチの両方で A3 が消える。
  - `id` なし（ごみ箱を空にする）で、ごみ箱に A3 と B3 があるとき、ブランチでは止まり A3 も B3 も残る（main では両方消える）。ごみ箱に A3 だけがあるときは、main とブランチの両方で A3 が消える。
  - 止まったときは、エラー画面に「このリソースにアクセスする権限がありません。」が出る。
- 完全削除（ドキュメントグループ）: `use_udperms=1` にし、削除済みの文書 A3 を、`editor` が属さないドキュメントグループに入れる（検証用の DB で `modx_document_groups` に行を足し、A3 の `privatemgr` を 1 にする）。許可するリソースの設定なしの `editor` で `a=64&id=<A3>` を呼ぶと、ブランチでは止まり A3 が残る（main では消える）。同じ条件で `admin` は main とブランチの両方で消える。
- 完全削除: 設定なしの `editor`（`use_udperms=0`）と `admin` は、`id` あり・なしとも main とブランチで同じ結果になる。

### M3 の完了条件

- 制限ありの利用者で、次はブランチでは止まり `modx_site_revision` と `modx_site_content` が変わらない（main では変わる）: B1 の下書きの保存、A1 の下書きを親 B で保存、B1 の下書きの公開、B1 の下書きの削除、B1 の公開予約の取り消し。下書きは事前に管理者で作っておく。
- 親が範囲の外の下書き（A1 の下書きの `parent` を検証用の DB で B に書き換えたもの）を、制限ありの利用者で公開すると、ブランチでは止まり A1 の親が変わらない。今すぐの公開と予約（`publishoption=reserve`）の両方で確かめ、予約では `modx_site_revision` の `status` が `standby` にならないことを見る。
- 下書きの新しい親の循環と存在（`use_udperms=0`、設定なしの `editor` と `admin`）: A1 の下書きを、親を A1 の子にして保存する・存在しない ID にして保存する、の 2 つは、ブランチでは止まり `modx_site_revision` が変わらない（main では保存される）。DB で同じ親に書き換えた下書きの公開も、ブランチでは止まり A1 の親が変わらない。
- 下書きの新しい親のドキュメントグループ（`use_udperms=1`、設定なしの `editor`）: `editor` が属さないドキュメントグループに入れた文書 C（`modx_document_groups` に行を足し、C の `privatemgr` を 1 にする）を親にした A1 の下書きの保存と公開は、ブランチでは止まる（main では通る）。同じ条件で `admin` は main とブランチの両方で通る。
- 止めたときの画面の文言: 文書で止まるときは「このリソースにアクセスする権限がありません。」、範囲の外やドキュメントグループの親で止まるときは「ここにリソースを作成する権限がありません。」、循環と存在で止まるときは通常の保存と同じ文言（子孫なら `illegal_parent_child`、存在しなければ `parent_not_found`）が出る。
- 制限ありの利用者で、A1 の下書きの保存・公開・削除・公開予約の取り消しは、main とブランチの両方で通る。
- 設定なしの `editor` と `admin` は、下書きの新しい親の循環と存在・ドキュメントグループのケース（上の 2 つ）を除いて、main とブランチで同じ結果になる。親を変えない下書きの保存と公開は、親を確かめないので変わらない。
- `unpublish_draft_content.processor.php` を、`publish_document` の無い利用者で呼ぶと、ブランチでも main でも、同じ文言の権限のエラー画面で止まる（`$e` は `manager/index.php` でグローバルに定義済み。`alert()` へのそろえは、ほかの処理との書き方の整合のため）。

### Issue が閉じられる状態

M1〜M3 の完了条件（M2 の完全削除を含む）をすべて満たし、PR の CI が通り、PR 本文に「変わること」と確認の表があること。

### 互換性・リリース記事に書くこと

PR には `fix`・`security`・`仕様変更` のラベルを付け、本文の「変更内容」に次を書く。

- 「許可するリソース」を設定した利用者は、範囲の外の文書を、保存・移動・複製・削除・削除の取り消し・完全削除・公開・非公開・下書きの操作で変えられなくなる（これまでは文書の画面を開くときだけ確かめていた）。
- 設定値の読み方が厳しくなる。正の整数（半角）だけを ID として読み、0・数字以外・全角の数字は読まない。読める ID が 1 つも無い設定は、範囲がどこにも無い（すべて範囲の外）扱いになる。たとえば「0,5」は、以前は全文書が範囲になっていたが、5 とその子孫だけになる。
- 同じ利用者は、範囲の外（ルートを含む）に文書を作ったり移したり複製したりできなくなる。範囲の頂点に指定した文書そのものの複製も、複製の置き場所が範囲の外になるので止まる。
- 完全削除（ごみ箱を空にする・1 件ずつの完全削除）でも、ドキュメントグループの確認をするようになる。`use_udperms` が 1 のとき、見られないドキュメントグループの文書は完全削除できなくなる。
- 下書きの保存と公開で親が変わるとき、通常の保存と同じく、新しい親のドキュメントグループ・循環と存在・範囲を確かめるようになる。許可するリソースを設定していない利用者でも、見られないドキュメントグループの文書や、自分の子孫・存在しない文書を親にした下書きは、保存も公開もできなくなる。
- 「ごみ箱を空にする」は、ごみ箱に 1 件でも確認を通らない文書（範囲の外、または見られないドキュメントグループの文書）があれば、何も消さずに止まる。その利用者は、範囲の中の文書を 1 件ずつ完全削除する。
- 管理者を含め、許可するリソースを設定していない利用者の動きは、完全削除のドキュメントグループの確認と、下書きの親の確認を除いて変わらない。設定している管理者は、ほかの利用者と同じく止まる。
- 新規作成の画面は、ルートを指定しても開けるが、保存で止まる（画面の側の確認は別に扱う）。

## Idempotence and Recovery

- マイルストーンごとにコミットするので、途中で止まったら Progress の最初の未完了のマイルストーンから再開する。ブランチと PR は Artifacts and Notes の記録を使う。
- 検証環境は `EVO_VALIDATE_NAME=evo733`（main 側は別の名前）で立てる。やり直すときは同じ値を先頭に付けて `down -v --rmi local` で消し、作業ツリーに残った `manager/includes/config.inc.php` を消してから立て直す。検証用のデータの変更は検証環境の DB の中だけなので、消せば元に戻る。
- 判定関数は読み取りだけで、DB を書かない。何度呼んでも同じ結果になる。

## Artifacts and Notes

- Issue: #733（対象はこの 1 件）
- ブランチ: `fix/733-allowed-parents-processors`（main `765ffe10c` から作成、`gh issue develop` で #733 に紐づけ済み）
- ワークツリー: `.claude/worktrees/issue-733`
- PR: #748（下書き。M1 で作成、`Refs #733`）。M3 で `Closes #733` に改める。
- ラベル: `fix`・`security`・`仕様変更`
- 関連: #717（画面の側の確認。作業中）、#637・PR #698（保存でのドキュメントグループの確認）

## Interfaces and Dependencies

- 新しい公開メソッド: `ManagerAPI::isInAllowedParents($id): bool`（`manager()->isInAllowedParents($id)`、M1）と、`ManagerAPI::parentChangeDeniedMessage(array $docIds, $parentId): string`（M3。中で `invalidParentMessage()`・`evo()->checkPermissions()`・`isInAllowedParents()` を呼ぶ）。既存の `isAllowed()`・`isContainAllowed()`・`getUserAllowedDocs()` の呼び出し方と結果は変えない。
- 使う既存のメソッド: `ManagerAPI::getAncestorIds()`、`evo()->config` の `allowed_parents`、`evo()->checkPermissions()`（M2 の完全削除、M3 の下書きの親）、`ManagerAPI::invalidParentMessage()`（M3）、`evo()->revision->getDraft()`（M3）、`alert()->setError($code, $message)` の第 2 引数（止めたときの文言）。
- #717 とはファイルが重ならない（#717 は `manager/actions/document/` 側）。どちらが先にマージされても衝突しない見込み。
- function_exists のガードは付けない。DBAPI には手を入れない（ID は呼ぶ側で `(int)` にそろえる）。
