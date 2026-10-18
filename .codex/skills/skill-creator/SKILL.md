---
name: skill-creator
description: "Guide for creating effective skills. This skill should be used when users want to create a new skill (or update an existing skill) that extends Claude's capabilities with specialized knowledge, workflows, or tool integrations. Use when: (1) creating a new SKILL.md, (2) initializing a skill directory, (3) packaging a .skill file, (4) improving or iterating on an existing skill, (5) understanding skill best practices and design patterns."
---

# スキル作成支援スキル

このファイルは Codex 実行用の入口とメタデータを管理する。
手順本文の正本は `.claude/skills/skill-creator/SKILL.md` に置く。必ず正本を読み、そのすべてに従う。ここには手順を写さない。スクリプトと参照資料も同じディレクトリのものを使う。

## Codex での差分

- 新しいスキルを作るときは、本文を `.claude/skills/<name>/` に置き、Codex 用の入口を `.codex/skills/<name>/` に追加する。入口の書き方は `.codex/skills/README.md` の「入口の形」に従う。
