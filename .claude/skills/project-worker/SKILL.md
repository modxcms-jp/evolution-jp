---
name: project-worker
description: Evolution CMS JP Editionの開発タスクを実行するためのワークフロー、コマンド(/work, /start-session等)、およびコーディング規約ガイド。開発作業を開始する際に使用します。
---

# Project Worker

Evolution CMS JP Edition の開発ワークフロー。
コーディング規約・技術スタック・ドキュメントマップは `AGENTS.md` を参照。

## コマンド

### /start-session
1. `AGENTS.md`（開発ルール）と `assets/docs/roadmap.md`（ロードマップ）を読み込む
2. `assets/docs/roadmap.md` の未完了タスクと現在のブランチ状態を確認
3. 取り組むタスクを特定し、ユーザーに確認

### /sync-status
`assets/docs/roadmap.md` のタスク状態を更新（SSOT原則）:
- 着手時: `cc:WIP`
- 完了時: `cc:DONE`
- 問題時: `cc:BLOCKED` + 理由

### /work
1. `AGENTS.md` のドキュメントマップから関連ファイルを特定
2. 複雑なタスクはステップごとの計画を提示
3. `AGENTS.md` の規約を厳守して実装
4. 規約チェック: No jQuery / No Frames / Helpers First / Secure SQL

### /validate
1. 構文チェック: `find . -path "./vendor" -prune -o -name "*.php" -exec php -l {} +`
2. エラー時は最大3回まで自律修正・再チェック
3. ブラウザ確認が必要な項目をリストアップ

### /handoff
作業完了報告を以下の形式で出力:
- 実施タスク / 変更内容 / 検証結果 / 次のアクション

## 意思決定の閾値

**自律判断可能**: 規約準拠、リファクタリング、構文エラー修正、ヘルパー関数への置き換え

**要相談**: DBスキーマ変更、互換性を損なう変更、`assets/docs/roadmap.md` の優先順位変更、大きな設計変更
