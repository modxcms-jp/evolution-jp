# ExecPlan: AI向けドキュメント構造の SSOT 整備

## Purpose / Big Picture

PR #442 のレビューラリー分析で判明した構造的問題を解消し、次回以降のドキュメント変更 PR でのレビュー往復を減らす。具体的には `codex-pr-rules.md` への制約追加・スキル定義の重複整理・`/pr-sync` のワークフロー組み込みを行う。

## Progress

- [ ] (2026-05-07) Step 1: `/pr-sync` をワークフローへ組み込む
- [ ] (2026-05-07) Step 2: `codex-pr-rules.md` に SSOT 整合性チェック制約を追加
- [ ] (2026-05-07) Step 3: `roadmap-manager` / `roadmap-next-task` の重複手順を整理
- [ ] (2026-05-07) Step 4: 完了処理プロトコルの粒度をスキル間で統一

## Surprises & Discoveries

## Decision Log

- 2026-05-07: PR #442 で SKILL.md と release-process.md の二重管理を解消（案 A 採用）。同種問題が roadmap-manager/roadmap-next-task にも存在することを確認。
- 2026-05-07: `/pr-sync` コマンドを doc-maintainer スキルに追加。ワークフローへの組み込みは PR マージ後に対応することにした。

## Outcomes & Retrospective

## Context and Orientation

関連ファイル:

- `CLAUDE.md` — ワークフロー定義（PR作成・レビュー時の手順）
- `.claude/skills/doc-maintainer/SKILL.md` — `/pr-sync` の定義
- `.github/codex-pr-rules.md` — Copilot レビューボットへの制約ファイル
- `.claude/skills/roadmap-manager/SKILL.md` — ロードマップ操作スキル
- `.claude/skills/roadmap-next-task/SKILL.md` — 次タスク着手スキル
- `.claude/skills/exec-plan/SKILL.md` — ExecPlan 作成スキル
- `.agent/PLANS.md` — 完了処理プロトコルの正本

## Plan of Work

4ステップを独立した小変更として進める。各ステップは個別コミット。

**Step 1（最優先・小）**: `/pr-sync` をワークフローへ組み込む  
PR 作成後や追加コミット後に `/pr-sync` を実行するステップを `CLAUDE.md` に追記。`doc-maintainer/SKILL.md` にも使い方ガイダンスを添える。

**Step 2（優先・小）**: `codex-pr-rules.md` に SSOT 整合性制約を追加  
「SKILL.md と参照先 docs の間でコンテンツが重複していないか」「見出し参照が実在するか」をチェック観点に追加。ボットの過剰指摘を減らしつつ、重要な SSOT 違反は指摘してもらう。

**Step 3（中）**: `roadmap-manager` / `roadmap-next-task` の重複整理  
両スキルの完了処理・着手処理の記述を読み比べ、重複している手順を `.agent/PLANS.md` 参照に統一する。機能変更はしない。

**Step 4（中）**: 完了処理プロトコルの粒度統一  
`exec-plan/SKILL.md` は完了処理を詳細に列挙、`roadmap-manager/SKILL.md` は `.agent/PLANS.md` に丸投げ、という粒度差を整理する。どちらも「詳細は `.agent/PLANS.md` を正本とする」形に統一し、各スキルには差分（スキル固有の追加手順）だけを残す。

## Concrete Steps

### Step 1: `/pr-sync` をワークフローへ組み込む

**1-1. `CLAUDE.md` の「レビュー時」ワークフローを編集**

`## ワークフロー` → `### レビュー時` セクションに以下を追加:

```
2. PR に追加コミットをした後は `/pr-sync <PR番号>` で概要との乖離を確認・更新する
```

**1-2. `doc-maintainer/SKILL.md` に使い方ガイダンスを追記**

`/pr-sync` コマンド説明の末尾に推奨タイミングを追記:

```
**推奨タイミング:** PR 作成直後、および追加コミットのたびに実行する。
```

**確認:** `CLAUDE.md` の「レビュー時」手順に `/pr-sync` の記載があること。

---

### Step 2: `codex-pr-rules.md` に SSOT 整合性制約を追加

**2-1. `.github/codex-pr-rules.md` を読んで既存の構造を確認する**

**2-2. 「Focus only on」セクションに以下を追加:**

```markdown
- SKILL.md や agent 定義が「正本は〇〇」と宣言している場合、その正本と記述が矛盾していないか
- SKILL.md 内で別ファイルの見出しを参照しているとき、その見出しが実際に存在するか
- 同一ドキュメント内でバージョン表記形式（例: `vX.X.X` vs `release-1.3.0J`）が混在していないか
```

**確認:** `codex-pr-rules.md` に上記3項目が追加されていること。

---

### Step 3: `roadmap-manager` / `roadmap-next-task` の重複整理

**3-1. 両スキルの SKILL.md を読んで重複箇所を特定する**

主な重複候補:
- 完了処理の手順記述（両スキルが「`.agent/PLANS.md` の完了処理プロトコル」を参照しているが、記述粒度が異なる）
- ロードマップ更新の手順（`Status` 変更・`最終更新` 更新など）

**3-2. 重複している手順を「`.agent/PLANS.md` の完了処理プロトコルに従う」参照1行に置き換える**

**確認:** 両スキルで同一内容の箇条書きが消え、参照形式に統一されていること。

---

### Step 4: 完了処理プロトコルの粒度統一

**4-1. `exec-plan/SKILL.md`・`roadmap-manager/SKILL.md`・`roadmap-next-task/SKILL.md` の完了処理記述を読み比べる**

**4-2. 各スキルの完了処理記述を整理する**

- 共通手順（Status 更新・archive 移動・最終更新など）は「詳細は `.agent/PLANS.md` の「完了処理プロトコル」を正本とする」に統一
- スキル固有の追加手順（例: exec-plan の `skill:complete` 実行）だけを各スキルに残す

**確認:** 3スキルの完了処理セクションが同一の参照形式を持ち、固有手順のみ差分として残っていること。

## Validation and Acceptance

- `CLAUDE.md` の「レビュー時」ワークフローに `/pr-sync` が含まれている
- `codex-pr-rules.md` に SSOT・見出し参照・バージョン表記の3チェック項目が追加されている
- `roadmap-manager/SKILL.md` と `roadmap-next-task/SKILL.md` に重複した手順箇条書きがない
- `exec-plan`・`roadmap-manager`・`roadmap-next-task` の3スキルが完了処理を同一形式で参照している

## Idempotence and Recovery

各ステップは独立したファイル編集のみ。コミット済みステップは再実行不要。未着手ステップから再開できる。

## Artifacts and Notes

- 起点: PR #442（ロードマップアーカイブ化・doc-maintainer スキル追加）
- 参考メモ: `.agent/memos/2026-05-07-skill-release-ssot-issue.md`（解消済み問題の記録）
- ロードマップ該当タスク: `## 1. 基盤整備` → `### AI向けドキュメント構造の SSOT 整備`

## Interfaces and Dependencies

外部依存なし。`.github/codex-pr-rules.md` の変更は次の PR レビューから効果が出る。
