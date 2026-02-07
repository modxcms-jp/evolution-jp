# Execution Plans (ExecPlans)

ExecPlan の仕様書。複雑なタスクはこの仕様に従い `.agent/plans/YYYY-MM-DD-task-name.md` に作成する。
参考: [Codex Execution Plans](https://developers.openai.com/cookbook/articles/codex_exec_plans/)

## NON-NEGOTIABLE REQUIREMENTS

1. **自己完結**: 予備知識なしで実装完了できる情報量を含むこと
2. **生きたドキュメント**: 進捗・発見・意思決定に応じて継続的に更新すること
3. **初心者実行可能**: 専門用語には平易な説明を添えること
4. **動作する成果物**: 観察可能な動作（ブラウザ確認、CLI実行等）を示すこと
5. **用語定義**: 専門用語は平易に定義するか使用しないこと

## テンプレート

```markdown
# ExecPlan: [タスク名]

## Purpose / Big Picture
[達成したいこと。ユーザーにとっての価値を1–2文で。]

## Progress
- [ ] (YYYY-MM-DD) ステップの説明（タイムスタンプ付きチェックリスト）

## Surprises & Discoveries
（実装中に遭遇した予期しない挙動や知見。根拠を添えること）

## Decision Log
（設計判断を日付付きで記録。何を・なぜ選んだか、代替案は何か）

## Outcomes & Retrospective
（達成内容のまとめ、学んだ教訓）

## Context and Orientation
[対象コードの場所、既存の仕組み、前提条件。パスはリポジトリルート相対。]

## Plan of Work
[実装方針の概要。なぜこのアプローチを選んだか。]

## Concrete Steps
[具体的な実装手順。コード例やコマンドを含む。]

## Validation and Acceptance
[完了条件。具体的なコマンドとその期待出力。]

## Idempotence and Recovery
[中断時の復帰手順。何度実行しても安全であること。]

## Artifacts and Notes
[関連ファイル、URL、メモ。]

## Interfaces and Dependencies
[外部依存、他モジュールとのインターフェース。]
```

## ルール

- Progress 以外ではチェックボックスを多用せず散文で記述
- 検証手順には具体的なコマンドと期待出力を含める
- ネストしたコードブロックは使わずインデントで表現
- 次のマイルストーンへ自律的に進む（「次は？」と確認しない）
- 完了した ExecPlan は削除せずナレッジとして残す

## 成功基準

ステートレスなエージェントまたは初心者が、ExecPlan を通読して動作する成果物を生み出せること。

## 関連

- `AGENTS.md` — 開発ルール
- `assets/docs/roadmap.md` — ロードマップ
