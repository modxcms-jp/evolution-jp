---
name: roadmap-next-task
description: ロードマップ（`.agent/roadmap.md`）の依存順を基準に次の未完了タスクへ着手するスキル。完了時のチェック反映、ExecPlan未作成タスクの計画作成先行、実装開始までを一貫して行う。
---

# Roadmap Next Task

`.agent/roadmap.md` をSSOTとして、次に着手すべきタスクを選定し、必要なら ExecPlan を作成してから実装に入る。
実装規約は `AGENTS.md` を最優先とする。
ロードマップ全体の追加・変更・削除や記述フォーマットの維持は `roadmap-manager` を使い、このスキルは「次タスク着手」に限定する。

## 実行ルール

1. 正本は必ず `.agent/roadmap.md` を使う（`assets/docs/roadmap.md` は案内用途）。
2. タスク選定は「実行順ロードマップ（依存順）」の上から順に行う。
3. 既に完了している作業を見つけた場合は、実装前にロードマップのチェック状態を同期する。
4. 着手対象タスクに ExecPlan が無い場合は、実装前に `.agent/PLANS.md` 準拠で新規作成する。
5. ExecPlan がある場合でも、着手前に目的・進捗・検証条件を更新する。
6. 実装完了時は、対応タスクの `Status` を `DONE` に更新し、`完了日` を当日に更新する。
7. チェック更新時は `最終更新` 日付も当日に更新する。

## コマンド

### /next-task
1. `.agent/roadmap.md` の未完了項目を依存順で確認し、最上位の次タスクを1件特定する。
2. 対象タスクに `ExecPlan:` 行があるか確認する。
3. `ExecPlan:` 行が無い場合、`.agent/plans/YYYY-MM-DD-task-name.md` を作成し、該当タスク配下へ `ExecPlan:` 行を追記する。
4. 作成済み/新規の ExecPlan を更新し、最初の実装ステップを `Progress` に追加する。
5. 実装に着手する。

### /sync-roadmap
1. 直近の実装結果・テスト結果から完了条件を満たした項目を抽出する。
2. `.agent/roadmap.md` の該当タスクの `Status` を `DONE` に更新し、`完了日` を当日に更新する。
3. 互換表示としてチェックボックスが残っている項目がある場合のみ整合させる。
4. `最終更新` を当日に更新する。

### /finish-task
1. 対象 ExecPlan の `Progress` と `Validation and Acceptance` を完了状態に更新する。
2. `/sync-roadmap` を実行してロードマップへ完了反映する。
3. 未完了の次タスク候補を1件提示して終了する。

## 参照順（最小）

1. `AGENTS.md`
2. `.agent/roadmap.md`
3. `.agent/PLANS.md`
4. `assets/docs/architecture.md`（実装影響が大きいとき）

## 意思決定の閾値

**自律判断可能**:
- 次タスクの機械的選定（依存順で先頭の未完了）
- ExecPlan の有無判定と新規作成
- ロードマップのチェック同期

**要相談**:
- 優先順位の入れ替え
- 複数タスク同時着手
- ロードマップ項目そのものの追加・削除
