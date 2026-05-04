# ExecPlan: Codex レビューボットの指摘粒度改善

## Purpose / Big Picture

`.github/codex-pr-rules.md` に Codex レビューの観点制約を追加し、agent/skill 定義ファイルへの変更に対してエッジケース網羅性ではなく設計方針との整合性のみを確認するよう指示する。これにより、手順書 PR でのレビューラリー（PR #433 では 40 コミット超）を防ぐ。

## Progress

- [x] (2026-05-04) `.github/codex-pr-rules.md` に `## Review scope for agent and skill definitions` セクションを追加
- [ ] (2026-05-04) 変更を PR として提出し、Codex によるレビューが観点制約に従っているか確認

## Surprises & Discoveries

PR #433 のレビューラリー分析:
- Codex は手順書ドキュメントを「実装コードの仕様書」として扱い、「すべてのエッジケースが明示されているか」を要求し続けた
- 1つの指摘に対応するたびに差分が生まれ、その差分に対して新たな指摘が発生する構造
- `.github/codex-pr-rules.md` は Codex がレビュー時にも参照するファイルであり、ここに制約を書けば効果を期待できる

## Decision Log

- **2026-05-04**: `.github/codex-pr-rules.md` への追記を選択。Codex 側の設定ファイルを直接変更する方法（`.codex/` 配下）は設定項目が未確認で確実性が低いため、既知の参照ファイルに書く方針を優先した。効果がない場合は Codex 設定側の調査を次手とする。

## Outcomes & Retrospective

## Context and Orientation

**問題の構造**:
Codex レビューボット（`chatgpt-codex-connector`）は PR の差分をレビューする際、`.github/codex-pr-rules.md` を参照する。現状このファイルは PR 作成ルール（タイトル・説明・ラベル）のみを定義しており、レビュー観点の制約がない。

**対象ファイル**:
- `.github/codex-pr-rules.md`（レビュー観点制約を追加する唯一のファイル）

**影響を受けるパス**（レビュー制約の適用対象）:
- `.claude/skills/` — Claude 実行用スキル定義
- `.codex/skills/` — Codex 実行用スキル定義
- `.github/agents/` — GitHub Agent 定義
- `.agent/agents/` — エージェント責務定義
- `.agent/plans/` — ExecPlan（手順書）
- `.agent/roadmap.md` — ロードマップ

## Plan of Work

`## Review scope for agent and skill definitions` セクションを `.github/codex-pr-rules.md` に追加する。セクションの内容:

1. 対象パスを列挙する
2. 確認すべき観点を明示する（設計方針との整合性・パスや API の正確性・他定義との矛盾）
3. 指摘してはいけない観点を明示する（エッジケース網羅性・スタイル・例の追加要求など）

英語で記述する（既存ファイルが英語のため）。

## Concrete Steps

**編集対象**: `.github/codex-pr-rules.md`

ファイル末尾に以下のセクションを追加する:

    ## Review scope for agent and skill definitions

    For changes to the following paths:
    - `.claude/skills/`
    - `.codex/skills/`
    - `.github/agents/`
    - `.agent/agents/`
    - `.agent/plans/`
    - `.agent/roadmap.md`

    Focus only on:
    - Consistency with the design principles defined in `AGENTS.md`
    - Correctness of referenced file paths, API endpoints, and command syntax
    - Whether the change contradicts other agent or skill definitions in the same repository

    Do NOT raise issues about:
    - Completeness of edge case coverage in procedures
    - Whether every possible scenario is explicitly handled
    - Style, wording, or naming preferences
    - Requests to add more examples or alternative approaches
    - Hypothetical failure modes that are not demonstrated by the change itself

**想定コミット**:

    docs(codex): agent/skillファイル変更時のレビュー観点制約を追加

## Validation and Acceptance

1. `.github/codex-pr-rules.md` に `## Review scope for agent and skill definitions` セクションが追加されている
2. 次に agent/skill 定義ファイルを変更する PR を作成したとき、Codex のレビューコメントが「エッジケース指摘」よりも「設計整合性の確認」に絞られていることを確認する（定性的な観察で十分）

## Idempotence and Recovery

変更は `.github/codex-pr-rules.md` への1セクション追記のみ（複数行）。中断しても既存ルールに影響しない。

## Artifacts and Notes

- 参照 PR: #433（40コミット超のレビューラリー発生事例）
- `.github/codex-pr-rules.md`: 現状は PR 作成ルールのみ（タイトル・説明・ラベル）

## Interfaces and Dependencies

- Codex レビューボット（`chatgpt-codex-connector`）が `.github/codex-pr-rules.md` を参照してレビューを実行することを前提とする
- 効果がない場合は Codex 側の設定（`.codex/` 配下の設定ファイル）を追加調査する
