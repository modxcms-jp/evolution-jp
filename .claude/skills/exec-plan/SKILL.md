---
name: exec-plan
description: ExecPlan（実行計画）の作成・検証・更新を支援するスキル。複雑なタスク（新機能開発、リファクタリング、バグ修正）の設計フェーズで使用します。`/create-plan`でプラン作成を開始。
---

# Exec Plan

`.agent/PLANS.md` の仕様に準拠した ExecPlan を作成・管理するワークフロー。
コーディング規約・ドキュメントマップは `AGENTS.md` を参照。

## コマンド

### /create-plan <タスク概要>
1. `AGENTS.md` のドキュメントマップから関連ドキュメント・コードを探索
2. プラン案の骨子を提示（重点: Purpose / Context / Plan of Work / Concrete Steps / Validation）
   複雑タスクはマイルストーン分割（目標→作業→成果→検証の物語構造、PoCを先行）
3. エンジニアと設計方針を確認し、非交渉要件（自己完結・初心者実行可能・動作する成果物・用語定義）を検討
4. `.agent/PLANS.md` テンプレートに従い `.agent/plans/YYYY-MM-DD-task-name.md` を作成
   全12セクション記載、Progress以外は散文、CMS用語を定義、Validationは観察可能な動作で定義
   空セクションは見出しのみ残す（プレースホルダ説明は書かない）
5. `/validate-plan` を自動実行

探索の重点: `assets/docs/architecture.md`（処理フロー）、`assets/docs/events-and-plugins.md`（フック）、`assets/docs/core-issues.md`（既知の課題）、対象ファイルの既存パターン

移行タスクでは追加で: 旧API使用箇所のGrep棚卸し、旧→新の置換パターン表、モジュール単位の分割

### 補助ツール（CLI導入済みの場合）
`php evo config:show [key]` / `db:tables [--pattern]` / `db:describe <table>` / `db:count <table> [--where]`

### /validate-plan [path]
`references/quality-checklist.md` に基づき品質チェック: 必須12セクション、非交渉要件・アンチパターンを検出し改善提案

### /update-plan [path]
各マイルストーン完了時・中断時にこまめに呼び出す:
1. Progress をタイムスタンプ付きで更新（完了チェック、新規項目追加）
2. Surprises & Discoveries に追記（観察+根拠）
3. Decision Log に日付・著者・根拠・代替案を記録
4. 完了マイルストーンの Progress 詳細を1行の要約に圧縮（トークン節約）
5. コア側の課題（UI結合・設計上の制約・技術的負債等）を発見した場合は `assets/docs/core-issues.md` に追記（発見日・発見元・ファイル・課題・改善案・関連ロードマップ）

## 意思決定の閾値

**自律判断可能**: コードベース探索、関連ドキュメント読み込み、プランのフォーマット整形

**要相談**: 設計方針の選定、影響範囲の判断、実装の優先順位、代替案のトレードオフ
