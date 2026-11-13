# ExecPlan: 7 テーマで本体まで同じ規則を共通の CSS へ移す（#1448 の 1 本目）

Issue: #1448（Refs。最後の PR で閉じる）

## Purpose / Big Picture

同梱の 7 テーマの `style.css` は、同じ規則を 7 回書き写している。そのうち中身まで同じ規則を共通の CSS（`manager/media/style/common/theme-base.css`）へ移し、使われていない規則は消す。見た目は変えない。テーマを直す人は、共通の規則を 7 か所で直さずに済む。

## Progress

- [x] (2026-11-14) マージ後の main（e1a3498f2）で、移せる規則を数え直した（入れ子の外・各テーマに 1 回・中身が同じ: 59 個）
- [x] (2026-11-14) 層に入れる案と入れない案を、静的な洗い出しで比べた（並走セッションと独立に流して一致）。層に入れない案に決めた
- [x] (2026-11-14) 56 個を移し、そのうち当たる要素の無い・効かない・重複する 10 個を消した。theme-base.css は 46 個を役割ごとに並べた
- [ ] 7 テーマ × 34 画面の全要素の計算後のスタイルと、規則ごとの確かめで、main と比べる
- [ ] 下書きの PR を作る

## Surprises & Discoveries

- 参考の数え方（dup2.py）は、`@media` の中の規則もトップレベルとして数える。移す対象を決めるには、入れ子を追う抽出が要る。
- 59 個のほとんどは「テーマの一般的な規則を、詳細度で上書きするための規則」だった。層に入れると、層の外のテーマの規則に詳細度に関係なく負ける（例: `.actionButtons img { margin-top: 1px }` が `img, a img { margin: 0 }` に負ける）。
- 層の外でも、テーマより先に読むと、同じ詳細度でテーマの中で前にあった規則との順番が逆になる。値が変わるのは `h4, h5 { font-weight: bold }` と `h6 { font-weight: bold; font-size: 0.9em }` だけだった（前にある初期化の規則 `h1, …, address { font-weight: normal }` に負ける）。
- TV の textarea は `form_textarea.tpl` が `class="[+tvtype+]"` を付けるので、クラスは `textarea`・`textareamini`。`.tv_textarea`・`.tv_textareamini` はどこからも付かない。
- `.treeNode` は `nodes.php` で `span` に付く。`div.treeNode` は当たる要素が無い。
- `fieldset#access_permissions`・`#document_content`・`.disabledImage`・`.printonly`（印刷用の CSS も無い）は、本体のどこにも無い。

## Decision Log

- 2026-11-14: 層に入れない。59 個は詳細度でテーマの規則に勝つ前提の規則が多く、層に入れると負ける。中身が 7 テーマで同じなので、今テーマが上書きしたい規則でもない。層への振り分けは、部品ごとに変数にして移す PR で、その部品のテーマ側の規則と一緒に行う（#1448 の「守ること」の層の項目は、その PR から当てはめる）。
- 2026-11-14: 見出しの 3 個（`h1, h2, h3, h4, h5, h6, .subTitle`、`h4, h5`、`h6`）はテーマに残す。初期化の規則（RevoStyle だけ中身が違い、移せない）と組で扱う必要がある。
- 2026-11-14: 使われていない・効かない・重複する規則は、移さずに消す（共通の場所に移すと、生きている規則に見えて読み手を迷わせる）。消したもの: `form#mutate textarea.tv_textarea`・`.tv_textareamini`、`fieldset#document_content textarea#ta`、`.disabledImage`・`.disabledImage img`、`div.treeNode`、`form .imeoff`（`ime-mode` は効かない）、`a img { border: 0 }`（7 テーマの `img, a img { border: 0 }` と重なる）、`fieldset#access_permissions dt label`・`dl dd ul li`、`.printonly`。
- 2026-11-14（並走セッションの点検を受けて）: 値がテーマごとに違って移す対象ではなかった `fieldset#access_permissions dd` も、同じ理由で当たらないので、見出しのコメントごと 7 テーマから消した。`.form br`・`.form fieldset.list legend` も、`class="form"` が管理画面に無い（インストーラーは自分の CSS を読む）ので消した。theme-base.css は 44 個。
- 2026-11-14: `.hide`・`.skip`・`.inline` は本体では使われていないが、汎用の名前で外部のモジュールが使っている見込みがあるので、移して残す（PR の本文の「使われていない候補」に入れる）。

## Outcomes & Retrospective

## Context and Orientation

- 新しいファイル: `manager/media/style/common/theme-base.css`（層の外。役割ごとの見出し付き）
- 読み込み: `manager/includes/helpers.php` の `manager_theme_stylesheets()`。forms.css → theme-base.css → テーマの style.css の順
- テーマ: `manager/media/style/{AromaHop,AuroraFlow,RevoClassic,RevoStyle,RubberWhite,SakuraFlow,SoftSlate}/style.css`
- 文書: `assets/docs/theme-variables.md` の「新しいテーマを作るとき」
- DocumentParser・キャッシュへの影響はない（管理画面の CSS だけ）

## Plan of Work

入れ子を追う抽出で、7 テーマで中身まで同じ規則を選ぶ。順番が逆になって値が変わる規則（静的に洗い出す）はテーマに残す。残りはテーマから消し、使われているものだけを theme-base.css に役割ごとに並べる。同じ詳細度で順番が要る組（`.actionButtons a:hover img` → `a.disabled img`）は元の順番を保つ。

## Concrete Steps

1. 抽出と移動: 作業用のスクリプト（identical.py・move_identical.py・drop_headings.py。リポジトリの外）
2. 順番の洗い出し: flipcheck.py（層の外の前提で、同じ詳細度・前にある・同じ要素に当たりうる・同じ性質）
3. 使われているかの確かめ: `grep -rn` でクラス名・id を manager・assets・install から探す（CSS と言語ファイルを除く）
4. 比較: compstyle.js（全要素の計算後のスタイル）と rulecheck.js（規則ごとに、当たる要素のその性質。サンプルに無い要素は差し込み、TinyMCE は読み込みを止める）を、main と修正後で続けて流す

## Validation and Acceptance

- 7 テーマ × 34 画面で、全要素の計算後のスタイルが main と同じ（main 同士の揺れを除く）
- 規則ごとの確かめで、theme-base.css の 46 個と、重複として消した `a img` が、すべて要素に当たり、値が main と同じ
- テーマの style.css に、7 テーマで中身まで同じ規則が見出しの 3 個のほかに残っていない

## Idempotence and Recovery

テーマの style.css を `git checkout origin/main -- <パス>` で戻し、移動のスクリプトを流し直せば同じ結果になる。theme-base.css は手で並べたので、作り直すときはこのブランチのものを使う。

## Artifacts and Notes

- 並走セッションの確かめ: order_risk.py（順番が逆になる規則）、usage.sh（クラス・id の使われ方）

## Interfaces and Dependencies

- `manager_theme_stylesheets()` が返す `<link>` が 2 つから 3 つになる
- 外部のテーマ: 同梱テーマを写したものなら、同じ規則が二重になるだけで見た目は変わらない
