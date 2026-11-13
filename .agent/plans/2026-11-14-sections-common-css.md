# ExecPlan: セクションの規則を共通の CSS と変数にまとめる（#1448 の 2 本目）

Issue: #1448（Refs）。前提: #1451（7 テーマで同じ規則の移動）。このブランチは #1451 のブランチの上に積む

## Purpose / Big Picture

セクションの見出しの帯（`.sectionHeader`）と本体の箱（`.sectionBody`）の規則は、7 テーマが値だけ変えて書き写している。規則を `manager/media/style/common/sections.css` の 1 か所にし、テーマは `:root` で値だけを決めるようにする。行数の削減より、「部品の規則は 1 か所、テーマは値だけ」という構造にすることが目的。見た目は変えない。

## Progress

- [x] (2026-11-14) 7 テーマのセクションの規則を一覧にし、7 テーマすべてが書いている「セレクタと性質」の組を選んだ
- [x] (2026-11-14) 変数にする条件を絞った（3 テーマ以上で値が違い、テーマの雰囲気を決める性質だけ）。それ以外は値で書き、違うテーマに上書きを残した
- [x] (2026-11-14) 層に入れて勝ち負けが変わる組み合わせを静的に洗い出し、`.sectionBody h3` を対象から外した
- [x] (2026-11-14) PR A のブランチと比べて、7 テーマ × 34 画面の計算後のスタイルを突き合わせた。差が 2 つ見つかり、直したあとは揺れ（ファイル管理のホバー、子リソース一覧のリンク 1 件。測り直すと出ない）だけになった
- [ ] 下書きの PR を作る

## Surprises & Discoveries

- 値だけ違う規則は、1 行の宣言が 1 行の変数に置き換わるだけなので、行数はほとんど減らない（テーマで 141 行減・80 行増、sections.css で約 40 行）。
- AromaHop・SakuraFlow は `--color-section-header-text` を定義していたが使っておらず、見出しには別の色を直接書いていた。この変数はテーマの外でも使われていない（並走セッションが grep で確認）。
- `--border-thin` は AromaHop・AuroraFlow・SakuraFlow に無い。共通の CSS の値に使うと、そのテーマで枠が消える。
- `.sectionBody h3` を層に入れると、テーマの `h3 { … }` と初期化の規則 `h1, …, h6 { font-weight: normal; margin; padding }` に負ける（今までは詳細度で勝っていた）。
- セクションのクラス（`.section`・`.sectionHeader`・`.sectionBody`）は、マークアップではすべて `div` に付いている。
- 突き合わせで見つかった差 1: ロール編集と個人設定の fieldset の余白が 0 になった。PR A で層の外に置いた theme-base.css の `fieldset { padding: 0 }` が、層に入れた `.sectionBody fieldset { padding }` に勝つため。PR A で素の要素の初期化を `@layer evo-base` に入れて直した。テーマだけを相手にした洗い出しでは見つからない（層の外の共通の CSS も相手に入れる。flip_common.py）。
- 突き合わせで見つかった差 2: RubberWhite で見出しの帯の下の枠が消えた。前のほうの `.sectionHeader` の `border-bottom: none` が、後ろの規則の `border` に上書きされて効いていなかったのに、`border` を層に移したことで効くようになった。同じ規則の効いていない宣言（`color`・`font-weight`・`background-color` も）を消した。同じセレクタの規則が 2 つあるテーマでは、前の規則の宣言が効いているかを確かめる。

## Decision Log

- 2026-11-14: sections.css は `@layer evo-components` に入れる（#1448 の決まり）。テーマ側の同じ性質の規則も同じ PR で消すので、テーマに残る上書きは層の外で必ず勝つ。
- 2026-11-14: 変数にするのは 9 個（見出しの帯の枠と角、箱の地・枠・内側の余白、fieldset の余白、legend の余白・影・角）。見出しの帯の文字色は既存の `--color-section-header-text` で書き、新しい変数は作らない。1〜2 テーマしか違わない性質（見出しの帯と legend の `font-weight: bold`、legend の地と枠）は値で書き、違うテーマに上書きを残す。
- 2026-11-14: 共通の CSS では `--border-thin` を使わず `1px` と書く（定義のあるテーマはどれも 1px）。
- 2026-11-14: `.sectionBody h3` は移さない。PR A でテーマに残した見出しの組と合わせて、見出しの PR で扱う。
- 2026-11-14: `.sectionBody .tab-page`（`!important` 付きの余白）と `.tab-page .sectionHeader` はタブの PR で扱う。
- 2026-11-14: RevoStyle（凍結）は規則を残し、変数だけを足す。

## Outcomes & Retrospective

## Context and Orientation

- 新しいファイル: `manager/media/style/common/sections.css`
- 読み込み: `manager/includes/helpers.php` の `manager_theme_stylesheets()`（forms.css → theme-base.css → sections.css → テーマ）
- 文書: `assets/docs/theme-variables.md` の「セクション」と、変数の一覧の `--color-section-header-text` の行
- DocumentParser・キャッシュへの影響はない

## Plan of Work

作業用のスクリプト（varize.py。指定は spec-sections.json）で、指定した「セレクタと性質」をテーマから消して `:root` に変数を足し、sections.css に既定値付きの `var()` で書く。固定値の性質は、テーマの値が固定値と同じときだけ消す。見出しの文字色は header_color.py で契約の変数に寄せる。作り直しは build_sections.sh。

## Concrete Steps

1. `sh build_sections.sh`（作業ツリーのルートで。テーマを戻してから作り直す）
2. 層の洗い出し: `python3 flip2.py spec-sections.json <A-*.css のディレクトリ>`。`:is()` の中のカンマの区切り損ねと、要素の無い `.form legend` だけが残ること
3. 計算後のスタイル: compstyle.js を、このブランチと #1451 のブランチで続けて流し、compdiff.py で比べる

## Validation and Acceptance

- 7 テーマ × 34 画面で、全要素の計算後のスタイルが #1451 のブランチと同じ（データで変わる画面の揺れを除く）

## Idempotence and Recovery

`build_sections.sh` は何度流しても同じ結果になる（テーマの CSS を戻してから作る）。

## Artifacts and Notes

- 後でやること（見出しの PR）: PR A でテーマに残した `h1, h2, h3, h4, h5, h6, .subTitle`・`h4, h5`・`h6` と、この PR で残した `.sectionBody h3`。初期化の規則 `h1, …, address` との順番を含めて扱う
- 後でやること（タブの PR）: `.sectionBody .tab-page` と `.dynamic-tab-pane-control .tab-page` の余白を 1 つの変数・1 つの規則にし、`!important` をやめる（並走セッションの調べ: 外からの上書きは無く、テーマの中の 2 つの規則が張り合っているだけ）

## Interfaces and Dependencies

- テーマの新しい変数: `--section-header-border`・`--section-header-radius`・`--section-body-bg`・`--section-body-border`・`--section-body-padding`・`--section-fieldset-padding`・`--section-legend-padding`・`--section-legend-shadow`・`--section-legend-radius`
- `--color-section-header-text` を共通部が読むようになった
