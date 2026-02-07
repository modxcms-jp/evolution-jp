---
name: exec-plan
description: ExecPlan（実行計画）の作成・検証・更新を支援するスキル。複雑なタスク（新機能開発、リファクタリング、バグ修正）の設計フェーズで使用します。`/create-plan`でプラン作成を開始。
---

# Exec Plan

`.agent/PLANS.md` の仕様に準拠した ExecPlan を作成・管理するワークフロー。
コーディング規約・ドキュメントマップは `AGENTS.md` を参照。

## コマンド

### /create-plan <タスク概要>
1. タスク概要を受け取り、`AGENTS.md` のドキュメントマップから関連ドキュメント・コードを探索
2. 探索結果をもとにプラン案の骨子（Purpose / Context / Plan of Work）を提示
3. エンジニアと設計方針を確認（影響範囲、技術選定、代替案）
4. 合意内容を反映し `.agent/PLANS.md` のテンプレートに従い `.agent/plans/YYYY-MM-DD-task-name.md` を作成
5. 作成後 `/validate-plan` を自動実行

探索フェーズでは以下を重点的に調査する:
- `assets/docs/architecture.md` — 処理フロー全体像
- `assets/docs/events-and-plugins.md` — フック箇所
- 対象ファイルの既存パターン・依存関係

移行タスク（PDO移行、jQuery廃止、frame廃止等）では追加で:
- **影響ファイルの棚卸し**: Grep で旧APIの使用箇所を網羅的に洗い出す
- **置換パターンの特定**: 旧→新の対応表を作成（例: `mysql_query()` → `db()->query()`）
- **段階的移行の分割単位**: モジュール・機能単位で独立して移行可能な範囲を特定

### 補助ツール（利用可能な場合）
CLI が導入されている場合は、調査フェーズで以下を活用して前提整理を高速化する。
- `php evo config:show` / `php evo config:show <key>` — 設定の把握
- `php evo db:tables [--pattern=...]` — テーブル一覧の把握
- `php evo db:describe <table>` — テーブル構造の把握
- `php evo db:count <table> [--where=...]` — 影響範囲の概算

### /validate-plan [path]
`references/quality-checklist.md` に基づき品質チェックを実施:
1. 必須セクションの存在と内容の充実度
2. 非交渉要件（自己完結、初心者実行可能、動作する成果物、用語定義）
3. 不足項目を指摘し修正を提案

### /update-plan [path]
1. 指定プランの Progress をタイムスタンプ付きで更新
2. 新たな知見を Surprises & Discoveries に追記
3. 設計変更があれば Decision Log に日付と理由を記録

## 意思決定の閾値

**自律判断可能**: コードベース探索、関連ドキュメント読み込み、プランのフォーマット整形

**要相談**: 設計方針の選定、影響範囲の判断、実装の優先順位、代替案のトレードオフ
