---
name: issue-resolver
description: 不具合報告（GitHub Issue、フォーラム投稿、社内報告）を起点に、調査・再現・修正・検証・記録までを一貫実行するスキル。症状の切り分け、原因仮説の整理、最小再現、修正実装、ExecPlan作成、PR下書き、ナレッジ追記が必要なときに使う。
---

# Issue Resolver

このファイルは Codex 実行用の入口とメタデータを管理する。
手順本文の正本は `.claude/skills/issue-resolver/SKILL.md` に置く。

不具合対応を「再現可能な事実」ベースで前進させる。
実装規約はプロジェクトの `AGENTS.md` を最優先とし、必要に応じて `project-worker` の規約へ委譲する。

## 実行ルール

詳細な実行ルールは `.claude/skills/issue-resolver/SKILL.md` の `## 実行ルール` を参照。

## evo CLI

詳細は `.claude/skills/issue-resolver/SKILL.md` の `## evo CLI（実用最小）` を参照。

## Workflow

### analyze-issue <URL|テキスト>

詳細な手順は `.claude/skills/issue-resolver/SKILL.md` の `### analyze-issue` を参照。

### reproduce

詳細な手順は `.claude/skills/issue-resolver/SKILL.md` の `### reproduce` を参照。

### create-branch

詳細な手順は `.claude/skills/issue-resolver/SKILL.md` の `### create-branch` を参照。

### draft-plan (必要時)

詳細な手順は `.claude/skills/issue-resolver/SKILL.md` の `### draft-plan` を参照。

### implement-fix

詳細な手順は `.claude/skills/issue-resolver/SKILL.md` の `### implement-fix` を参照。

### archive

詳細な手順は `.claude/skills/issue-resolver/SKILL.md` の `### archive` を参照。

### pull-request

詳細な手順は `.claude/skills/issue-resolver/SKILL.md` の `### pull-request` を参照。
